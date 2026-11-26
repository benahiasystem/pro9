#!/bin/sh
# ########## INICIO CAMBIO POSTGRESQL 17
set -eu
umask 0002
mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
mkdir -p storage/app/tenancy/tenants
mkdir -p storage/app/public/uploads/firms
if [ "$(id -u)" = 0 ]; then
    chown www-data:www-data storage storage/framework storage/framework/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
    # Artisan ejecutado como root puede dejar vistas que PHP-FPM no puede recompilar.
    find storage/framework/views -type f -exec chown www-data:www-data {} +
    chown www-data:www-data storage/app/tenancy storage/app/tenancy/tenants
    chown www-data:www-data storage/app/public storage/app/public/uploads storage/app/public/uploads/firms
fi
chmod 0700 storage/app/tenancy storage/app/tenancy/tenants
chmod g+s storage storage/framework storage/framework/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app bootstrap/cache
chmod o+x storage storage/app
exec docker-php-entrypoint "$@"
# ######### FIN CAMBIO POSTGRESQL 17
