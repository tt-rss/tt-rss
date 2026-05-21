#!/bin/sh -e
#
# this scripts waits for startup.sh to finish (implying a shared volume) and runs multiprocess daemon when working copy is available
#

# We don't need those here (HTTP_HOST would cause false SELF_URL_PATH check failures)
unset HTTP_PORT
unset HTTP_HOST

unset ADMIN_USER_PASS
unset AUTO_CREATE_USER_PASS

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

# wait for the app container to delete .app_is_ready and perform rsync, etc.
sleep 30

if ! id app; then
	addgroup -g $OWNER_GID app
	adduser -D -h $APP_INSTALL_BASE_DIR -G app -u $OWNER_UID app
fi

update-ca-certificates || true

# TODO this should do a reasonable amount of attempts and terminate with an error
while ! pg_isready -h $TTRSS_DB_HOST -U $TTRSS_DB_USER -p $TTRSS_DB_PORT; do
	echo waiting until $TTRSS_DB_HOST is ready...
	sleep 3
done

sed -i.bak "s/^\(memory_limit\) = \(.*\)/\1 = ${PHP_WORKER_MEMORY_LIMIT}/" \
	/etc/php${PHP_SUFFIX}/php.ini

DST_DIR=$APP_INSTALL_BASE_DIR/tt-rss

while [ ! -s $DST_DIR/config.php ] && [ -e $DST_DIR/.app_is_ready ]; do
	echo waiting for app container...
	sleep 3
done

# this is some next level bullshit
# - https://stackoverflow.com/questions/65622914/why-would-i-get-a-php-pdoexception-complaining-that-it-cant-make-a-postgres-con
# - fatal error: could not open certificate file "/root/.postgresql/postgresql.crt": Permission denied
chown -R app:app /root # /.postgresql

exec sudo -E -u app "${TTRSS_PHP_EXECUTABLE}" $APP_INSTALL_BASE_DIR/tt-rss/update_daemon2.php "$@"
