#!/bin/sh
# ########## INICIO CAMBIO POSTGRESQL 17
set -eu
umask 0002
mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
mkdir -p storage/app/tenancy/tenants
if [ "$(id -u)" = 0 ]; then
    chown www-data:www-data storage storage/framework storage/framework/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
    chown www-data:www-data storage/app/tenancy storage/app/tenancy/tenants
fi
chmod 0700 storage/app/tenancy storage/app/tenancy/tenants
chmod g+s storage storage/framework storage/framework/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
chmod o+x storage storage/app
exec docker-php-entrypoint "$@"
# ######### FIN CAMBIO POSTGRESQL 17
