# Ships its own copy of public/ (build it with `npm run build` before
# `docker compose build` so public/build/* is present) - nginx never talks
# to the app container's filesystem, only to php-fpm over fastcgi on :9000.
FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
