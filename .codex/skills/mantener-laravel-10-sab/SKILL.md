---
name: mantener-laravel-10-sab
description: Mantener la compatibilidad de SAB con Laravel 10, PHP 8.2, módulos y el ejecutor de pruebas PostgreSQL al cambiar dependencias o arranque.
---

# Laravel 10 en SAB

La migración fijó Laravel 10.50.4, Laravel Modules 10.0.6 y PHP 8.2, con PHPUnit 9.6 y Collision 6 para el reportero PostgreSQL. Consulta `composer.json` y `composer.lock` para las versiones vigentes; Laravel 10 está fuera de soporte de seguridad.

- Antes de actualizar dependencias, revisa compatibilidad con Tenancy 2, módulos, el reportero JUnit y la instalación con y sin dependencias de desarrollo. La referencia de Culqi procede de su etiqueta oficial: conserva una fuente reproducible.
- Al desplegar un artefacto de otra versión, prepara `bootstrap/cache` para ese artefacto y regenera el descubrimiento de paquetes y la configuración; una caché anterior puede referirse a proveedores ausentes.
- Verifica las rutas, el arranque, las conexiones PostgreSQL y el aislamiento de trabajos según el área afectada. No interpretes la batería dirigida histórica como una prueba completa. El informe `informes/migracion_laravel_10.md` recoge sus fallos heredados y la limitación de `view:cache`.
- Consulta `informes/resumen_migracion_laravel_10_tenancy_2.md` para la relación con la migración multitenant y sus evidencias.
