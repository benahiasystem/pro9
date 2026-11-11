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

Los roles son explícitos por empresa. Administrar usuarios y configuración no
concede aprobación ni contabilización. Para asumir también esas funciones hay
que asignar el rol de contador. Los administradores y contadores verifican el
segundo factor antes de consultar datos; las operaciones críticas exigen una
verificación reciente. La revisión separada considera a quien preparó la última
versión, aunque otra persona haya creado el primer borrador.

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
La opción `--accountant` asigna expresamente la responsabilidad de contador al
propietario. Sin esa opción el propietario conserva las funciones administrativas.

## Documentos y configuración fiscal

El módulo permite registrar borradores manuales, recibir versiones de documentos
de PRO9 y validarlos con perfiles y reglas aprobadas por fecha. Conserva la
identidad del tercero, los importes originales, la tasa histórica y sus soportes
en una copia de la validación. No instala alícuotas tributarias predeterminadas.

Las facturas validadas generan propuestas con cuentas elegidas por el contador
y una explicación por línea. Al enviarlas a revisión se comprueba que la partida
por cobrar o pagar coincida con el total, tercero, moneda y tasa validados.
Revalidar retira las aprobaciones pendientes; regenerar una propuesta exige
motivo y conserva la evidencia anterior en auditoría. La contabilización vuelve
a comprobar el estado y la versión del documento de PRO9.

Los registros fiscales disponibles son consultas preliminares separadas por
perfil e incluyen los documentos validados del intervalo. Los formatos fiscales
definitivos, aplicaciones de notas y retenciones aún deben completar
su implementación y conciliaciones; recibir o validar no equivale a contabilizar.

## Apertura y auxiliares

La apertura concilia los saldos por cuenta con el detalle de clientes y
proveedores. Conserva el importe original, su valor en VES y el antecedente
documental. Prepararla no mueve saldos: se somete al mismo circuito de revisión,
aprobación y contabilización de los comprobantes. Una factura anterior al corte
puede identificar una deuda inicial; no vuelve a reconocer su ingreso o gasto.
Los saldos iniciales de resultados se presentan por separado del resultado del
intervalo consultado.

Los cobros y pagos parciales distribuyen el valor contable pendiente y separan
la diferencia de cambio. La última aplicación toma el remanente contable exacto.
Cada propuesta conserva la versión del saldo que revisó: si otra operación lo
modifica, debe regenerarse y aprobarse de nuevo. El reverso íntegro restaura los
saldos aplicados y conserva su historia. Las cuentas de clientes y proveedores
requieren estos flujos o una factura; un asiento manual sin detalle no puede
descuadrar el auxiliar.

Esta primera liquidación exige soporte bancario, tasas y revisión tributaria
documentada. Todavía no integra retenciones, IGTF, notas, anticipos ni devoluciones
en sus aplicaciones. Esos casos siguen pendientes del flujo específico; no se
compensan automáticamente contra el banco ni se presume una exención.

## Trabajo local

La preparación autoriza un dispositivo por empresa y usuario durante un máximo
de 72 horas. Incluye un intervalo configurable de hasta 90 días por defecto,
con comprobantes, documentos, catálogos, perfiles, reglas, tasas e informes
preparados para fechas explícitas. Las consultas fuera del alcance requieren
PRO9 y no sustituyen el intervalo solicitado por otro informe en caché. El navegador cifra la copia y los borradores con
AES-GCM; deriva la clave de una frase local mediante PBKDF2. El Bearer y la
verificación adicional permanecen en memoria. La sincronización vuelve a
comprobar permisos, autorización del dispositivo y versión del borrador.

Sin conexión se permiten consultas preparadas y borradores. La aprobación,
contabilización, reverso y cierre requieren PRO9. Un conflicto conserva el
borrador local. La copia preparada tiene límites explícitos de fechas,
comprobantes y tamaño; no representa una réplica completa del servidor.

La recuperación de un respaldo local exige sesión activa en PRO9 y coincidencia
de empresa y usuario. Renueva la copia de consulta con datos y permisos del
servidor, verifica los datos cifrados y conserva las versiones diferentes de
los borradores existentes. Reimportar la misma alternativa no la duplica. Este
respaldo corresponde al trabajo del navegador; la recuperación integral de base
de datos y adjuntos del servidor sigue pendiente.

Las operaciones de cifrado y las respuestas de autorización pendientes conservan
la identidad de la sesión que las inició. Un cierre o cambio de sesión impide
que una preparación, recuperación o desbloqueo anterior publique su clave local
en la sesión siguiente.

## Verificación aislada

`phpunit.xml` exige una base MySQL y un host de pruebas específicos antes de
crear o limpiar tablas. No ejecutar esta suite contra una instalación activa.
`http_smoke.py` usa las credenciales ficticias del directorio privado
`.accounting-isolated` y comprueba el contenedor y la marca del servidor de
pruebas antes de escribir.
`documents_http_smoke.py` verifica el flujo de factura con el enrutamiento y
autenticación reales de PRO9. `documents_browser_smoke.cjs` lo recorre desde la
PWA compilada en AWS, incluyendo configuración fiscal y adaptación a móvil.
`restore_browser_smoke.cjs` verifica exportación cifrada, conservación de
alternativas, rechazo de propietario distinto e integridad, y recuperación en
un navegador limpio seguida de un reinicio sin conexión.
`auxiliary_http_smoke.py` y `auxiliary_browser_smoke.cjs` verifican apertura,
antecedentes y cobro parcial con diferencia de cambio y revisión independiente.
Las suites que consumen las identidades ficticias serializan su acceso mediante
el archivo de bloqueo del directorio privado.

El worktree autorizado es `codex/contabilidad-api-v1`, basado en
`861228462e208d2060c9148b87fd7d7d3f354da5`. Las bases reales se copian para
probar en esta PC y no se transfieren a AWS. El directorio privado, los volcados,
las credenciales y los compilados están excluidos de Git.

La implementación sigue en desarrollo. Los comprobantes, catálogos, informes
básicos, apertura, liquidaciones parciales, segundo factor y trabajo local tienen
verificaciones iniciales; los
flujos fiscales completos, cierres, conciliaciones, importación y exportaciones
definitivas aún deben completar sus criterios de aceptación.
