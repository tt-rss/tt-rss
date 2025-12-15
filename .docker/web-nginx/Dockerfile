FROM nginx:1.29.4-alpine

ARG APP_UPSTREAM=app
ARG APP_FASTCGI_PASS=\$backend
ARG APP_WEB_ROOT=/var/www/html
ARG APP_BASE=/tt-rss
ARG RESOLVER=127.0.0.11

# By default, nginx will send the php requests to "app" server, but this server
# name can be overridden at runtime by passing an APP_UPSTREAM env var
ENV APP_UPSTREAM=${APP_UPSTREAM}
ENV APP_FASTCGI_PASS="${APP_FASTCGI_PASS}"

# Web root (defaults to /var/www/html)
ENV APP_WEB_ROOT=${APP_WEB_ROOT}

# Base location for tt-rss (defaults to /tt-rss)
ENV APP_BASE=${APP_BASE}

# NOTE: In order to make tt-rss appear on website root without /tt-rss/ set the following in .env:
# APP_WEB_ROOT=/var/www/html/tt-rss
# APP_BASE=

# Resolver for nginx (kube-dns.kube-system.svc.cluster.local for k8s)
ENV RESOLVER=${RESOLVER}

# It's necessary to set the following NGINX_ENVSUBST_OUTPUT_DIR env var to tell
# nginx to replace the env vars of /etc/nginx/templates/nginx.conf.template
# and put the result in /etc/nginx/nginx.conf (instead of /etc/nginx/conf.d/nginx.conf)
# See https://github.com/docker-library/docs/tree/master/nginx#using-environment-variables-in-nginx-configuration-new-in-119
ENV NGINX_ENVSUBST_OUTPUT_DIR=/etc/nginx

COPY .docker/web-nginx/nginx.conf /etc/nginx/templates/nginx.conf.template

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD ["sh", "-c", "curl --fail --silent --show-error \"http://localhost${APP_BASE}/index.php\" || exit 1"]
