#!/bin/sh -e
#
# this script kickstarts a minimal working environment and runs update.php, could be used as an entrypoint for a cronjob
# which doesn't share a volume with FPM/updater
#

# We don't need those here (HTTP_HOST would cause false SELF_URL_PATH check failures)
unset HTTP_PORT
unset HTTP_HOST

# Allow setting environment variables with Docker secrets.
# The format is '<variable-name>__FILE'.
SUFFIX="__FILE"

# loop through all environment variables
for VAR in $(printenv | awk -F= '{print $1}'); do
	# shellcheck disable=SC2330 # https://github.com/koalaman/shellcheck/issues/2998
	if [[ $VAR == *"$SUFFIX" ]]; then
		ENV_FILE_NAME="$(printenv "${VAR}")"
		ENV_VAR="${VAR%$SUFFIX}"

		if printenv "$ENV_VAR" &>/dev/null; then
			echo "warning: Both $ENV_VAR and $VAR are set. $VAR will override $ENV_VAR."
		fi

		if [[ -r "$ENV_FILE_NAME" ]]; then
			VALUE="$(cat "$ENV_FILE_NAME")"
			export "$ENV_VAR"="$VALUE"
			echo "$ENV_VAR environment variable was set by secret file $ENV_FILE_NAME"
		else
			echo "warning: Secret file $ENV_FILE_NAME for $VAR is not readable or does not exist."
		fi
	fi
done

if ! id app >/dev/null 2>&1; then
	addgroup -g $OWNER_GID app
	adduser -D -h $APP_INSTALL_BASE_DIR -G app -u $OWNER_UID app
fi

update-ca-certificates || true

DST_DIR=$APP_INSTALL_BASE_DIR/tt-rss

if [ -z $SKIP_RSYNC_ON_STARTUP ]; then
	if [ ! -d $DST_DIR ]; then
		mkdir -p $DST_DIR
		chown $OWNER_UID:$OWNER_GID $DST_DIR

		sudo -u app rsync -a --no-owner \
			$SRC_DIR/ $DST_DIR/
	else
		chown -R $OWNER_UID:$OWNER_GID $DST_DIR

		sudo -u app rsync -a --no-owner --delete \
			--exclude /cache \
			--exclude /lock \
			--exclude /plugins/af_comics/filters.local \
			--exclude /plugins.local \
			--exclude /templates.local \
			--exclude /themes.local \
			$SRC_DIR/ $DST_DIR/

		sudo -u app rsync -a --no-owner --delete \
			$SRC_DIR/plugins.local/nginx_xaccel/ \
			$DST_DIR/plugins.local/nginx_xaccel
	fi
else
	echo "warning: working copy in $DST_DIR won't be updated, make sure you know what you're doing."
fi

for d in cache lock plugins.local themes.local templates.local cache/export cache/feeds cache/images cache/upload; do
	sudo -u app mkdir -p $DST_DIR/$d
done

# this is some next level bullshit
# - https://stackoverflow.com/questions/65622914/why-would-i-get-a-php-pdoexception-complaining-that-it-cant-make-a-postgres-con
# - fatal error: could not open certificate file "/root/.postgresql/postgresql.crt": Permission denied
chown -R app:app /root # /.postgresql

for d in cache lock; do
	chown -R app:app $DST_DIR/$d
	chmod -R u=rwX,g=rX,o=rX $DST_DIR/$d
done

sudo -u app cp ${SCRIPT_ROOT}/config.docker.php $DST_DIR/config.php
chmod 644 $DST_DIR/config.php

if [ ! -z "${TTRSS_XDEBUG_ENABLED}" ]; then
	if [ -z "${TTRSS_XDEBUG_HOST}" ]; then
		TTRSS_XDEBUG_HOST=$(ip ro sh 0/0 | cut -d " " -f 3)
		export TTRSS_XDEBUG_HOST
	fi
	echo enabling xdebug with the following parameters:
	env | grep TTRSS_XDEBUG
	cat > /etc/php${PHP_SUFFIX}/conf.d/50_xdebug.ini <<EOF
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.start_with_request = yes
xdebug.client_port = ${TTRSS_XDEBUG_PORT}
xdebug.client_host = ${TTRSS_XDEBUG_HOST}
EOF
fi

sed -i.bak "s/^\(memory_limit\) = \(.*\)/\1 = ${PHP_WORKER_MEMORY_LIMIT}/" \
	/etc/php${PHP_SUFFIX}/php.ini

sed -i.bak "s/^\(pm.max_children\) = \(.*\)/\1 = ${PHP_WORKER_MAX_CHILDREN}/" \
	/etc/php${PHP_SUFFIX}/php-fpm.d/www.conf

sudo -Eu app php${PHP_SUFFIX} $DST_DIR/update.php "$@"
