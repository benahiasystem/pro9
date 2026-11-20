#!/bin/sh
# ########## INICIO CAMBIO POSTGRESQL 17
set -eu
umask 0002
mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
if [ "$(id -u)" = 0 ]; then
    chown www-data:www-data storage storage/framework storage/framework/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
fi
chmod g+s storage storage/framework storage/framework/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
chmod o+x storage storage/app
exec docker-php-entrypoint "$@"
# ######### FIN CAMBIO POSTGRESQL 17
