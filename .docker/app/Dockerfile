FROM alpine:3.23.3

EXPOSE 9000/tcp

ARG ALPINE_MIRROR

ENV SCRIPT_ROOT=/opt/tt-rss
ENV SRC_DIR=/src/tt-rss/

# Normally there's no need to change this, should point to a volume shared with nginx container
ENV APP_INSTALL_BASE_DIR=/var/www/html

# Used to centralize the PHP version suffix for packages and paths
ENV PHP_SUFFIX=85

RUN [ ! -z ${ALPINE_MIRROR} ] && \
	sed -i.bak "s#dl-cdn.alpinelinux.org#${ALPINE_MIRROR}#" /etc/apk/repositories ; \
	apk add --no-cache ca-certificates dcron git postgresql-client rsync sudo tzdata \
		php${PHP_SUFFIX} \
		$(for p in ctype curl dom exif fileinfo fpm gd iconv intl json mbstring \
			openssl pcntl pdo pdo_pgsql pecl-apcu pecl-xdebug phar posix session simplexml sockets sodium tokenizer xml xmlwriter zip; do \
			php_pkgs="$php_pkgs php${PHP_SUFFIX}-$p"; \
		done; \
		echo $php_pkgs) && \
	sed -i 's/\(memory_limit =\) 128M/\1 256M/' /etc/php${PHP_SUFFIX}/php.ini && \
	sed -i -e 's/^listen = 127.0.0.1:9000/listen = 9000/' \
		-e 's/;\(clear_env\) = .*/\1 = no/i' \
		-e 's/;\(pm.status_path = \/status\)/\1/i' \
		-e 's/;\(pm.status_listen\) = .*/\1 = 9001/i' \
		-e 's/^\(user\|group\) = .*/\1 = app/i' \
		-e 's/;\(php_admin_value\[error_log\]\) = .*/\1 = \/tmp\/error.log/' \
		-e 's/;\(php_admin_flag\[log_errors\]\) = .*/\1 = on/' \
			/etc/php${PHP_SUFFIX}/php-fpm.d/www.conf && \
	mkdir -p /var/www ${SCRIPT_ROOT}/config.d ${SCRIPT_ROOT}/sql/post-init.d

ARG CI_COMMIT_BRANCH
ENV CI_COMMIT_BRANCH=${CI_COMMIT_BRANCH}

ARG CI_COMMIT_SHORT_SHA
ENV CI_COMMIT_SHORT_SHA=${CI_COMMIT_SHORT_SHA}

ARG CI_COMMIT_TIMESTAMP
ENV CI_COMMIT_TIMESTAMP=${CI_COMMIT_TIMESTAMP}

ARG CI_COMMIT_SHA
ENV CI_COMMIT_SHA=${CI_COMMIT_SHA}

ADD .docker/app/startup.sh ${SCRIPT_ROOT}
ADD .docker/app/update.sh ${SCRIPT_ROOT}
ADD .docker/app/updater.sh ${SCRIPT_ROOT}
ADD .docker/app/dcron.sh ${SCRIPT_ROOT}
ADD .docker/app/backup.sh /etc/periodic/weekly/backup

RUN chmod 0755 ${SCRIPT_ROOT}/*.sh /etc/periodic/weekly/backup

ADD .docker/app/index.php ${SCRIPT_ROOT}
ADD .docker/app/config.docker.php ${SCRIPT_ROOT}

COPY . ${SRC_DIR}

ARG ORIGIN_REPO_XACCEL=https://github.com/tt-rss/tt-rss-plugin-nginx-xaccel.git

RUN git clone --depth=1 ${ORIGIN_REPO_XACCEL} ${SRC_DIR}/plugins.local/nginx_xaccel

ENV OWNER_UID=1000
ENV OWNER_GID=1000

ENV PHP_WORKER_MAX_CHILDREN=5
ENV PHP_WORKER_MEMORY_LIMIT=256M

# these are applied on every startup, if set
ENV ADMIN_USER_PASS=""
# see classes/UserHelper.php ACCESS_LEVEL_*
# setting this to -2 would effectively disable built-in admin user
# unless single user mode is enabled
ENV ADMIN_USER_ACCESS_LEVEL=""

# these are applied unless user already exists
ENV AUTO_CREATE_USER=""
ENV AUTO_CREATE_USER_PASS=""
ENV AUTO_CREATE_USER_ACCESS_LEVEL="0"
ENV AUTO_CREATE_USER_ENABLE_API=""

# TODO: remove prefix from container variables not used by tt-rss itself:
#
# - TTRSS_NO_STARTUP_PLUGIN_UPDATES -> NO_STARTUP_PLUGIN_UPDATES
# - TTRSS_XDEBUG_... -> XDEBUG_...

# don't try to update local plugins on startup
ENV TTRSS_NO_STARTUP_PLUGIN_UPDATES=""

# TTRSS_XDEBUG_HOST defaults to host IP if unset
ENV TTRSS_XDEBUG_ENABLED=""
ENV TTRSS_XDEBUG_HOST=""
ENV TTRSS_XDEBUG_PORT="9000"

ENV TTRSS_DB_HOST="db"
ENV TTRSS_DB_PORT="5432"

ENV TTRSS_PHP_EXECUTABLE="/usr/bin/php${PHP_SUFFIX}"
ENV TTRSS_PLUGINS="auth_internal, note, nginx_xaccel"

CMD ["/bin/sh", "-c", "${SCRIPT_ROOT}/startup.sh"]
