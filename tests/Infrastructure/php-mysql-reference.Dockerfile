# Only the disposable, frozen Git reference uses this image; it is never an application service.
FROM pro9-postgres-php:17.11
RUN docker-php-ext-install pdo_mysql
