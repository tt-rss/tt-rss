ARG PROXY_REGISTRY

FROM ${PROXY_REGISTRY}alpine:3.20
EXPOSE 9000/tcp

ARG ALPINE_MIRROR

ENV SCRIPT_ROOT=/opt/tt-rss
ENV SRC_DIR=/src/tt-rss/

# overriding those without rebuilding image won't do much
ENV OWNER_UID=1000
ENV OWNER_GID=1000

RUN [ ! -z ${ALPINE_MIRROR} ] && \
		sed -i.bak "s#dl-cdn.alpinelinux.org#${ALPINE_MIRROR}#" /etc/apk/repositories ; \
	apk add --no-cache dcron php83 php83-fpm php83-phar php83-sockets php83-pecl-apcu \
	php83-pdo php83-gd php83-pgsql php83-pdo_pgsql php83-xmlwriter php83-opcache \
	php83-mbstring php83-intl php83-xml php83-curl php83-simplexml \
	php83-session php83-tokenizer php83-dom php83-fileinfo php83-ctype \
	php83-json php83-iconv php83-pcntl php83-posix php83-zip php83-exif \
	php83-openssl git postgresql-client sudo php83-pecl-xdebug rsync tzdata && \
	sed -i 's/\(memory_limit =\) 128M/\1 256M/' /etc/php83/php.ini && \
	sed -i -e 's/^listen = 127.0.0.1:9000/listen = 9000/' \
		-e 's/;\(clear_env\) = .*/\1 = no/i' \
		-e 's/;\(pm.status_path = \/status\)/\1/i' \
		-e 's/;\(pm.status_listen\) = .*/\1 = 9001/i' \
		-e 's/^\(user\|group\) = .*/\1 = app/i' \
		-e 's/;\(php_admin_value\[error_log\]\) = .*/\1 = \/tmp\/error.log/' \
		-e 's/;\(php_admin_flag\[log_errors\]\) = .*/\1 = on/' \
			/etc/php83/php-fpm.d/www.conf && \
	mkdir -p /var/www ${SCRIPT_ROOT}/config.d && \
	addgroup -g $OWNER_GID app && \
	adduser -D -h /var/www/html -G app -u $OWNER_UID app && \
	update-ca-certificates && \
	chown -R $OWNER_UID /etc/php83 /var/log/php83

ARG CI_COMMIT_BRANCH
ENV CI_COMMIT_BRANCH=${CI_COMMIT_BRANCH}

ARG CI_COMMIT_SHORT_SHA
ENV CI_COMMIT_SHORT_SHA=${CI_COMMIT_SHORT_SHA}

ARG CI_COMMIT_TIMESTAMP
ENV CI_COMMIT_TIMESTAMP=${CI_COMMIT_TIMESTAMP}

ARG CI_COMMIT_SHA
ENV CI_COMMIT_SHA=${CI_COMMIT_SHA}

ADD .docker/app-rootless/startup.sh ${SCRIPT_ROOT}
ADD .docker/app-rootless/updater.sh ${SCRIPT_ROOT}

ADD .docker/app-rootless/index.php ${SCRIPT_ROOT}
ADD .docker/app-rootless/config.docker.php ${SCRIPT_ROOT}

COPY . ${SRC_DIR}

ARG ORIGIN_REPO_XACCEL=https://git.tt-rss.org/fox/ttrss-nginx-xaccel.git

RUN git clone --depth=1 ${ORIGIN_REPO_XACCEL} ${SRC_DIR}/plugins.local/nginx_xaccel

USER $OWNER_UID

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

ENV TTRSS_DB_TYPE="pgsql"
ENV TTRSS_DB_HOST="db"
ENV TTRSS_DB_PORT="5432"

ENV TTRSS_MYSQL_CHARSET="UTF8"
ENV TTRSS_PHP_EXECUTABLE="/usr/bin/php83"
ENV TTRSS_PLUGINS="auth_internal, note, nginx_xaccel"

CMD ${SCRIPT_ROOT}/startup.sh
