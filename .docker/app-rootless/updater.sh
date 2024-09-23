#!/bin/sh -e

# We don't need those here (HTTP_HOST would cause false SELF_URL_PATH check failures)
unset HTTP_PORT
unset HTTP_HOST

unset ADMIN_USER_PASS
unset AUTO_CREATE_USER_PASS

# wait for the app container to delete .app_is_ready and perform rsync, etc.
sleep 30

if ! id app; then
	addgroup -g $OWNER_GID app
	adduser -D -h /var/www/html -G app -u $OWNER_UID app
fi

while ! pg_isready -h $TTRSS_DB_HOST -U $TTRSS_DB_USER; do
	echo waiting until $TTRSS_DB_HOST is ready...
	sleep 3
done

sed -i.bak "s/^\(memory_limit\) = \(.*\)/\1 = ${PHP_WORKER_MEMORY_LIMIT}/" \
	/etc/php83/php.ini

DST_DIR=/var/www/html/tt-rss

while [ ! -s $DST_DIR/config.php -a -e $DST_DIR/.app_is_ready ]; do
	echo waiting for app container...
	sleep 3
done

sudo -E -u app /usr/bin/php83 /var/www/html/tt-rss/update_daemon2.php "$@"
