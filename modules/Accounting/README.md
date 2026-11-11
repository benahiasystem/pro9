# Accounting para PRO9

Este módulo contiene la API y el dominio contable de Contabilidad Benahia. Se
ejecuta dentro de PRO9: Laravel 9, PHP 8.2, MySQL 5.7 y conexión Hyn `tenant`.
No incorpora otro servidor Laravel, otra tabla de usuarios ni otro login.

La PWA TypeScript se desarrolla y compila en AWS, en
`/home/ubuntu/contabilidad/application/web`. Su compilado se instala en
`public/contabilidad`, con historial de navegación servido por este módulo y
service worker limitado a `/contabilidad/`. Los archivos compilados no se
versionan en este repositorio.

## Acceso y datos

El acceso usa el `POST /api/login` existente y su Bearer con `auth:api`. Las
rutas del módulo están en `/api/accounting/v1`. Los permisos pertenecen a cada
libro; las tablas `accounting_*` incluyen organización, empresa y relaciones
compuestas. El segundo factor autoriza operaciones críticas por un plazo
limitado y no sustituye la identidad de PRO9.

`Pro9SourceReader` consulta los documentos del mismo tenant. Reutiliza los
valores exactos de PRO9, conserva versiones de origen y detecta recepciones
repetidas. Recibir un documento no lo contabiliza. Las empresas ficticias no
se vinculan a datos reales por defecto.

El único enlace en el manejador global de excepciones delega las respuestas de
este prefijo al módulo. Las respuestas de las demás rutas conservan su manejo
existente. Las respuestas contables no se almacenan en caché ni muestran SQL.

## Instalación en un tenant

Con la rama del módulo y las dependencias actuales de PRO9 disponibles:

```sh
php artisan accounting:install --hostname=DOMINIO_PRO9 --owner-user=ID_ADMIN
```

El comando exige un tenant existente y un administrador activo de PRO9; ejecuta
solo las migraciones de este módulo sobre la conexión `tenant` indicada. No
registra sus migraciones como migraciones globales del sistema. `--demo` exige
un entorno local o de pruebas y `ACCOUNTING_DEMO_MODE=true`.

## Trabajo local

La preparación autoriza un dispositivo por empresa, usuario y período durante
un máximo de 72 horas. El navegador cifra la copia y los borradores con
AES-GCM; deriva la clave de una frase local mediante PBKDF2. El Bearer y la
verificación adicional permanecen en memoria. La sincronización vuelve a
comprobar permisos, autorización del dispositivo y versión del borrador.

Sin conexión se permiten consultas preparadas y borradores. La aprobación,
contabilización, reverso y cierre requieren PRO9. Un conflicto conserva el
borrador local. La copia preparada tiene límites explícitos de período,
comprobantes y tamaño; no representa una réplica completa del servidor.

## Verificación aislada

`phpunit.xml` exige una base MySQL y un host de pruebas específicos antes de
crear o limpiar tablas. No ejecutar esta suite contra una instalación activa.
`http_smoke.py` usa las credenciales ficticias del directorio privado
`.accounting-isolated` y comprueba el contenedor y la marca del servidor de
pruebas antes de escribir.

El worktree autorizado es `codex/contabilidad-api-v1`, basado en
`861228462e208d2060c9148b87fd7d7d3f354da5`. Las bases reales se copian para
probar en esta PC y no se transfieren a AWS. El directorio privado, los volcados,
las credenciales y los compilados están excluidos de Git.

La implementación sigue en desarrollo. Los comprobantes, catálogos, informes
básicos, segundo factor y trabajo local tienen verificaciones iniciales; los
flujos fiscales completos, cierres, conciliaciones, importación y exportaciones
definitivas aún deben completar sus criterios de aceptación.
