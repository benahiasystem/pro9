# PostgreSQL 17.11: instalación y recuperación

Rama: `codex/postgresql-supabase-17-11`. Sólo instalaciones nuevas. Plataforma local Intel i7: `linux/amd64`. Imagen Supabase `17.11.0.019` con digest fijado en Compose. La cadena ARM64 suministrada corresponde al servidor remoto; el servidor local debe indicar 17.11, x86_64 y GCC 15.2.0. No se necesita emulación ARM.

## Instalación nueva

1. Conservar los archivos de configuración fuera de Git. Copiar `.env.example`; generar `APP_KEY` y `TENANCY_KEY` propios. Configurar `API_BCV_PATH` y el token BCV conforme al README.
2. Definir contraseñas distintas y aleatorias para `SUPABASE_POSTGRES_PASSWORD`, `DB_MIGRATOR_PASSWORD`, `DB_PASSWORD` y `DB_PROVISIONER_PASSWORD`. Conservar `DB_CONNECTION=system`, `DB_HOST=postgres`, puerto 5432, propietario `pro9_owner`, operación `pro9_runtime` y aprovisionador `pro9_provisioner`.
3. Ejecutar los comandos de instalación del README. `pro9:install-postgres` exige central vacía y ejecuta migraciones, semillas y secuencias. `--resume` comprueba que las migraciones pertenecen a Pro9 y repite semillas por identidad sin cambiar la contraseña de un administrador existente.
4. Iniciar PHP, Nginx, Redis y BCV. Crear empresas desde panel/API; el adaptador Hyn crea base/rol, migra, siembra e inicializa negocio antes de publicar el alta. Un fallo revierte metadata central y elimina únicamente recursos acreditados por el intento.
5. La aplicación y las conexiones usan `America/Caracas`, UTF-8 y `READ COMMITTED`. Los reportes exactos abren su propia transacción `REPEATABLE READ`.

El runtime no tiene `pdo_mysql` ni dependencia de mysqldump. `system` usa el rol de operación, `central_migrator` el propietario y `provisioner` el rol limitado de aprovisionamiento. El runtime central no crea tablas, roles ni bases. Los propietarios tenant no acceden a central ni a otras empresas. No modificar `vendor`.

Los volúmenes `postgres_data` (`/var/lib/postgresql/data`) y `postgres_config` (`/etc/postgresql-custom`) se conservan al recrear el contenedor. No usar `down -v` en una instalación que se quiera conservar. El script de inicialización sólo se ejecuta con datos vacíos. La exposición local opcional del puerto está en `docker-compose.postgresql.local.yml`, ligada a `127.0.0.1`.

## Pruebas aisladas

```bash
php tests/Infrastructure/postgres_compose_probe.php
php scripts/test_postgresql.php
php scripts/test_postgresql.php --skip-build --suite=install
php scripts/test_postgresql.php --skip-build --suite=concurrency
php scripts/test_postgresql.php --skip-build --suite=http
php scripts/test_postgresql.php --skip-build --suite=performance --without-pos-performance
```

`--without-pos-performance` ejecuta los presupuestos de HTTP y reportes con la referencia archivada y registra sólo el caso POS como aplazado. Puede emplearse en la batería completa o en `--suite=performance`.

El ejecutor crea proyecto Compose, servidor, contraseñas, almacenamiento y bases propios; verifica versión 170011 y un marcador de ejecución. No utiliza conexiones de `.env`. El servidor queda en una red interna sin puerto publicado. Los fixtures repetidos pueden clonarse desde una plantilla privada de esa ejecución; se conserva una prueba obligatoria de copia nativa `pg_dump`/`pg_restore`. El error de una prueba no autoriza acceder a bases reales.

La ejecución completa requiere también Python 3 y Chrome/Chromium disponible en `PATH`. El control de navegador no utiliza bases: levanta únicamente un HTTP local para assets permitidos y un perfil de navegador temporal, con productos sintéticos. Se verifica el bundle generado y los scripts clásicos del carrito, los botones reales de cantidad, el redondeo de fracciones, los límites de stock y los totales separados VES/USD. No se reutiliza el perfil del usuario ni se publican puertos de la base de pruebas.

```bash
python3 scripts/verify_storefront_bundle.py --output informes/evidencias/postgresql_17_implementacion/resultados/navegador_carrito.json
```

Cada build genera `public/build/frontend-sources.json` con las huellas de sus módulos, `vite.config.js` y dependencias declaradas. El navegador falla si cambió alguna fuente desde la compilación. El ejecutor PostgreSQL incluye además assets, manifiesto y scripts públicos en la huella invariable de la ejecución. Cuando cambien fuentes empaquetadas, compilar en este worktree conforme a la autorización y al procedimiento de frontend; no editar archivos generados manualmente. Los layouts públicos que cargan los scripts clásicos incluyen la misma entrada decimal, también en las vistas compartidas de Restaurante.

Para ejecuciones largas, guardar stdout, informes y estado dentro del directorio de evidencias del worktree, con un supervisor independiente de la sesión de terminal. Un XML o JSON vacío representa una ejecución incompleta; conservarlo y repetir el bloque en otra instancia temporal después de comprobar que terminó la anterior y que sus recursos privados se eliminaron.

## Respaldos

`php artisan bk:bd --help` muestra las modalidades conservadas. Los respaldos usan `pg_dump` 17 en formato personalizado, ZIP privado, manifiesto PostgreSQL 17.11 y SHA-256 de cada archivo. Incluyen archivos tenant y uploads compartidos. El paquete no incluye contraseñas.

Un respaldo individual en línea es consistente por base. Para un corte integral: poner la aplicación en mantenimiento (`artisan down`), detener workers, scheduler y todos los productores externos de escrituras; esperar que terminen transacciones; ejecutar la modalidad integral con `--quiesced`. El comando exige mantenimiento y rechaza sesiones activas. Conservar el ZIP y la configuración secreta (`APP_KEY`, `TENANCY_KEY`, credenciales e imagen fijada) en almacenamiento privado. Después del corte, reanudar productores y `artisan up`.

## Restaurar una empresa

```bash
php artisan pro9:restore-tenant nombre_actual /ruta/respaldo.zip --source=nombre_en_manifiesto
```

Primero valida manifiesto/checksums y restaura en base/rol independiente. Devuelve el nombre de staging y la carpeta privada con archivos tenant/uploads. Comprueba datos iniciales y secuencias; conserva las restricciones y los propietarios del destino. El aprovisionador puede validar staging por su membresía sin revelar una contraseña.

Para activarla, mantener aplicación, workers y scheduler detenidos y repetir con `--activate`. Se rechazan conexiones abiertas al destino. Se conserva la base y carpeta anteriores con sufijo `_previous_…`; los uploads sobrescritos se guardan para recuperación. Si falla el movimiento de archivos se revierten tanto renombrados de bases como archivos. Los enlaces simbólicos no se sobrescriben. Revisar staging y archivos antes de retirar explícitamente los recursos anteriores.

## Recuperación integral en instalación independiente

1. Crear un proyecto Compose nuevo, con volúmenes vacíos y una base central nueva. Conservar las claves originales `APP_KEY` y `TENANCY_KEY`; no ejecutar aún el instalador de esquema ni iniciar aplicación/workers.
2. Ejecutar `php artisan pro9:restore-central /ruta/respaldo.zip --source=central_del_manifiesto`. El propietario central debe apuntar a una base independiente vacía. El comando comprueba que el dump contiene las tablas centrales, restaura y reajusta secuencias. Las concesiones predeterminadas del bootstrap dan acceso al nuevo runtime.
3. Mantener mantenimiento y recuperar cada empresa con `pro9:restore-tenant … --source=… --activate`. En el clúster nuevo permite activar cuando no existe base/rol de destino; si existe un rol aislado exige revisión. No recrear empresas desde el panel durante esta recuperación.
4. Revisar central, dos empresas como mínimo, permisos, relaciones, fracciones/snapshots, secuencias, adjuntos, logos y uploads. El paquete integral incluye uploads compartidos; recuperarlos durante el mismo corte. Repetir el ensayo con las pruebas temporales antes de utilizar el procedimiento operativo.
5. Iniciar HTTP y productores únicamente después de validar todas las empresas y archivos.

En un checkout con las dependencias instaladas, `php tests/Infrastructure/postgres_compose_probe.php --recovery` ejecuta el procedimiento con dos proyectos Compose temporales. El sondeo compara datos, esquemas, archivos, permisos y secuencias. También comprueba que la API autentica a cada empresa restaurada y rechaza las credenciales de la otra. El ejecutor registra los identificadores de ambos clústeres y elimina sus recursos al terminar.

## Altas interrumpidas

Consultar `tenant_provisioning_runs` en central y `php artisan tenant:recover-provisioning --help`. La recuperación tiene simulación y aplicación explícita por UUID. Si la metadata central se publicó y los datos iniciales están completos, finaliza la publicación; si no, compensa recursos propios. Nunca elimina una base/rol preexistente sin acreditar marcador y propietario. Un alta pendiente devuelve 503 con `Retry-After`.

## Límites de la evidencia

Consultar los resultados de esta implementación y la matriz de trazabilidad. No confundir los sondeos históricos de noviembre 9 sobre la imagen oficial con esta imagen Supabase. Una comprobación pendiente o una auditoría global con pendientes previos debe conservar su resultado de fallo; no acredita cierre del despliegue.

El módulo histórico Padrón conserva su consulta de catálogo; sus operaciones de carga/demo pasan a POST con CSRF, autenticación y administrador. El lector de archivos es transaccional y no usa `LOAD DATA`. Esta compatibilidad técnica no implementa un proveedor fiscal SENIAT.

El contrato completo de columnas compara las 4.380 definiciones con la referencia temporal de MySQL: tipos PostgreSQL revisados, nulabilidad, valores predeterminados e identidades. Comprueba también las 554 FK diferibles inicialmente inmediatas. Se conserva la regla implícita de primera columna TIMESTAMP que MySQL aplicaba a `received_retention_uploads.expires_at`, además de la auditoría fiscal. Los valores temporales automáticos usan el instante de cada sentencia, incluso dentro de una transacción prolongada.

El sondeo de infraestructura inicia también PHP-FPM, Nginx y Redis propios; consulta el inicio de sesión por la red Docker sin publicar puertos de prueba. Los informes del ejecutor incluyen hashes de las fuentes y se invalidan si cambia el código durante la ejecución. Las revisiones de exactitud nuevas son por expresión: tipos decimales contrastados con el contrato y pruebas AST individuales; las aprobaciones de expresiones MySQL eliminadas se archivan, sin transferirlas a operaciones diferentes.

## Cupo de ventas y límite transaccional

`App\Services\Plans\SalesLimit` conserva la suma documental racional y el cupo completo del plan. La emisión registra el guard dentro de su transacción READ COMMITTED y conserva el bloqueo de `configurations.id=1` hasta confirmar o revertir. `ValidatesBeforeCommit`, incorporado al controlador PostgreSQL propio, ejecuta la validación sobre las cabeceras finales antes del commit exterior, conserva los guards de savepoints exitosos y descarta los que fueron revertidos. El reintento completo vuelve a registrar sólo las reglas de su intento. Esta extensión se mantiene fuera de vendor.

El listado y los reportes usan una lectura consistente independiente. La creación raíz de notas comprueba los guards antes del PDF y los conserva para el commit final; una creación anidada espera al commit exterior para generar su PDF. El control de cupo suma facturas/débitos, resta créditos e incluye notas vigentes cuando el plan lo habilita; no parte de etiquetas monetarias ya redondeadas.

### PDF de suscripciones con compensación

El controlador habitual conserva la salida anidada tras el commit exterior. SubscriptionSaleNoteController declara que su lote restaura archivos mediante restoreFiles: puede producir el PDF antes del commit, después de comprobar los guards de cupo, para conservar el rollback de todo el plan cuando falla el almacenamiento u otra nota. PaymentsSuscriptionController restaura los archivos ante la excepción, también la de validación definitiva. El punto de extensión es protegido y no procede de parámetros de una petición. La regresión actual incluye archivo anterior, archivo parcial, rechazo por cupo posterior y cancelación de callbacks del intento fallido.
