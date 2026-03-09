#!/bin/sh -e
#
# this script initializes the working copy on a persistent volume and starts PHP FPM
#

DST_DIR=$APP_INSTALL_BASE_DIR/tt-rss

# Remove the previous file indicating the app is ready.
[ -e $DST_DIR ] && rm -f $DST_DIR/.app_is_ready

# helper to run git commands as the 'app' user while preserving proxy environment variables
git_as_app() {
    sudo -u app \
      HTTP_PROXY="${HTTP_PROXY:-}" http_proxy="${http_proxy:-}" \
      HTTPS_PROXY="${HTTPS_PROXY:-}" https_proxy="${https_proxy:-}" \
      NO_PROXY="${NO_PROXY:-}" no_proxy="${no_proxy:-}" \
      ALL_PROXY="${ALL_PROXY:-}" all_proxy="${all_proxy:-}" \
      git "$@"
}

# TODO this should do a reasonable amount of attempts and terminate with an error
while ! pg_isready -h $TTRSS_DB_HOST -U $TTRSS_DB_USER -p $TTRSS_DB_PORT; do
	echo waiting until $TTRSS_DB_HOST is ready...
	sleep 3
done

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

export PGPASSWORD=$TTRSS_DB_PASS

[ ! -e $APP_INSTALL_BASE_DIR/index.php ] && cp ${SCRIPT_ROOT}/index.php $APP_INSTALL_BASE_DIR

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

chown -R $OWNER_UID:$OWNER_GID $DST_DIR \
	/var/log/php${PHP_SUFFIX}

if [ -z "$TTRSS_NO_STARTUP_PLUGIN_UPDATES" ]; then
	echo updating all local plugins...

	find $DST_DIR/plugins.local -mindepth 1 -maxdepth 1 -type d | while read PLUGIN; do
		if [ -d $PLUGIN/.git ]; then
			echo updating $PLUGIN...

			cd $PLUGIN

			# Unless disallowed, migrate plugins in 'plugins.local' that were pulling from repos on tt-rss.org to their GitHub equivalent.
			if [ -z "$SKIP_LEGACY_ORIGIN_REPLACE" ]; then
				ORIGIN_URL=$(git_as_app config --get remote.origin.url)

				case "$ORIGIN_URL" in
					https://git.tt-rss.org/fox/ttrss-*.git)
						NEW_ORIGIN_URL="https://github.com/tt-rss/tt-rss-plugin-${ORIGIN_URL#'https://git.tt-rss.org/fox/ttrss-'}"
						;;
					https://gitlab.tt-rss.org/tt-rss/plugins/ttrss-*.git)
						NEW_ORIGIN_URL="https://github.com/tt-rss/tt-rss-plugin-${ORIGIN_URL#'https://gitlab.tt-rss.org/tt-rss/plugins/ttrss-'}"
						;;
					https://dev.tt-rss.org/tt-rss/ttrss-*.git)
						NEW_ORIGIN_URL="https://github.com/tt-rss/tt-rss-plugin-${ORIGIN_URL#'https://dev.tt-rss.org/tt-rss/ttrss-'}"
						;;
					https://dev.tt-rss.org/tt-rss/plugins/ttrss-*.git)
						NEW_ORIGIN_URL="https://github.com/tt-rss/tt-rss-plugin-${ORIGIN_URL#'https://dev.tt-rss.org/tt-rss/plugins/ttrss-'}"
						;;
					*)
						NEW_ORIGIN_URL=""
						;;
				esac

				if [ -n "$NEW_ORIGIN_URL" ]; then
					case $(git_as_app branch --show-current) in
						master)
							echo "Migrating origin remote from ${ORIGIN_URL} to ${NEW_ORIGIN_URL} (and switching the branch from 'master' to 'main')"
							git_as_app remote set-url origin "$NEW_ORIGIN_URL"
							git_as_app branch -m master main
							git_as_app fetch origin
							git_as_app branch --set-upstream-to origin/main main
							git_as_app remote set-head origin --auto
							;;
						main)
							echo "Migrating origin remote from ${ORIGIN_URL} to ${NEW_ORIGIN_URL}"
							git_as_app remote set-url origin "$NEW_ORIGIN_URL"
							git_as_app fetch origin
							git_as_app branch --set-upstream-to origin/main main
							git_as_app remote set-head origin --auto
							;;
						*)
							echo "Skipping migration of origin remote from ${ORIGIN_URL} to ${NEW_ORIGIN_URL} (local branch is not 'master' or 'main')"
							;;
					esac
				fi
			fi

			git_as_app config core.filemode false && \
			git_as_app config pull.rebase false && \
			git_as_app pull origin main || git_as_app pull origin master || echo warning: attempt to update plugin $PLUGIN failed.
		fi
	done
else
	echo skipping local plugin updates, disabled.
fi

PSQL="psql -q -h $TTRSS_DB_HOST -p $TTRSS_DB_PORT -U $TTRSS_DB_USER $TTRSS_DB_NAME"

$PSQL -c "create extension if not exists pg_trgm"

# this was previously generated
rm -f $DST_DIR/config.php.bak

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

sudo -Eu app php${PHP_SUFFIX} $DST_DIR/update.php --update-schema=force-yes

if [ ! -z "$ADMIN_USER_PASS" ]; then
	sudo -Eu app php${PHP_SUFFIX} $DST_DIR/update.php --user-set-password "admin:$ADMIN_USER_PASS"
else
	if sudo -Eu app php${PHP_SUFFIX} $DST_DIR/update.php --user-check-password "admin:password"; then
		RANDOM_PASS=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 16 ; echo '')

		echo "*****************************************************************************"
		echo "* Setting initial built-in admin user password to '$RANDOM_PASS'        *"
		echo "* If you want to set it manually, use ADMIN_USER_PASS environment variable. *"
		echo "*****************************************************************************"

		sudo -Eu app php${PHP_SUFFIX} $DST_DIR/update.php --user-set-password "admin:$RANDOM_PASS"
	fi
fi

if [ ! -z "$ADMIN_USER_ACCESS_LEVEL" ]; then
	sudo -Eu app php${PHP_SUFFIX} $DST_DIR/update.php --user-set-access-level "admin:$ADMIN_USER_ACCESS_LEVEL"
fi

if [ ! -z "$AUTO_CREATE_USER" ]; then
	sudo -Eu app /bin/sh -c "php${PHP_SUFFIX} $DST_DIR/update.php --user-exists $AUTO_CREATE_USER ||
		php${PHP_SUFFIX} $DST_DIR/update.php --force-yes --user-add \"$AUTO_CREATE_USER:$AUTO_CREATE_USER_PASS:$AUTO_CREATE_USER_ACCESS_LEVEL\""

	if [ ! -z "$AUTO_CREATE_USER_ENABLE_API" ]; then
		# TODO: remove || true later
		sudo -Eu app /bin/sh -c "php${PHP_SUFFIX} $DST_DIR/update.php --user-enable-api \"$AUTO_CREATE_USER:$AUTO_CREATE_USER_ENABLE_API\"" || true
	fi

fi

rm -f /tmp/error.log && mkfifo /tmp/error.log && chown app:app /tmp/error.log

(tail -q -f /tmp/error.log >> /proc/1/fd/2) &

unset ADMIN_USER_PASS
unset AUTO_CREATE_USER_PASS

find ${SCRIPT_ROOT}/sql/post-init.d/ -type f -name '*.sql' | while read F; do
	echo applying SQL patch file: $F
	$PSQL -f $F
done

touch $DST_DIR/.app_is_ready

exec /usr/sbin/php-fpm${PHP_SUFFIX} --nodaemonize --force-stderr
