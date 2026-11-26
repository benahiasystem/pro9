# SAB — Sistema Administrativo Benahia

SAB es un sistema administrativo multitenant de Benahia para la gestión comercial,
inventario, compras, ventas, caja y documentos fiscales. Está construido con
Laravel 9, Vue 2 y Vite y contiene módulos para distintas operaciones comerciales.
La configuración fiscal y los catálogos del proyecto están orientados a Venezuela.

La aplicación se identifica como SAB; los informes y evidencias históricas conservan
el nombre con el que se produjeron para mantener su trazabilidad.

## Términos y condiciones del uso de este repositorio

1.- Este repositorio es de código abierto pero de acceso privado, se permite la distribución y/o modificaciones si se hace referencio a la casa matriz del desarrollo de este es software es [https://facturaloperu.com](https://facturaloperu.com)

2.- Esta sección de términos y condiciones no puede ser removida al compartir o distribuir el repositorio de alguna forma, de hacerlo, [https://facturaloperu.com](https://facturaloperu.com) se reserva el derecho de remover el acceso y limitar el uso a quien lo distribuya de esa forma o a quien se atribuya el desarrollo del mismo.

3.- Si desea distribuir el código fuente como propio, debe tener al menos un 30% de modificaciones en todo el código, y previamente debe validarse dicho % por [https://facturaloperu.com](https://facturaloperu.com)

4.- El uso del software a nivel funcional es marca blanca, sin embargo a nivel de distribución del código fuente, debe contener esta sección de términos y condiciones.

5.- [https://facturaloperu.com](https://facturaloperu.com) no se hace responsable por los daños o perjuicios del uso del código de este software cuando no ha sido distribuido directamente por [https://facturaloperu.com](https://facturaloperu.com)

## Manuales de Instalación

### Migración de una instalación Docker existente

El proyecto Compose y sus contenedores ahora usan el prefijo `sab`; las rutas de
trabajo también cambiaron a `/var/www/sab`. El cambio del nombre de proyecto hace
que Compose busque volúmenes nuevos. No iniciar la nueva pila antes de migrar los
datos, ni ejecutar `docker compose down -v`.

1. En el directorio y configuración de la instalación anterior, identifique los
   nombres reales de los volúmenes MySQL y Redis con `docker inspect` sobre los
   contenedores antiguos y sus montajes. El nombre puede variar por `-p`,
   `COMPOSE_PROJECT_NAME` u otros overrides; no suponga que el prefijo es fijo.
2. Detenga la pila anterior con su configuración original, sin eliminar volúmenes.
   Guarde y compruebe un respaldo restaurable de MySQL y Redis. Conserve los
   volúmenes originales y el respaldo para una reversión.
3. Confirme el nombre efectivo del proyecto Compose y de sus volúmenes: por
   defecto son `sab`, `sab_mysql_data` y `sab_redis_data`. Si los destinos ya
   existen, deténgase y determine su procedencia; no los sobrescriba. Para los
   nombres predeterminados, cree los volúmenes vacíos con las etiquetas de Compose:

   ```bash
   docker volume create --label com.docker.compose.project=sab --label com.docker.compose.volume=mysql_data sab_mysql_data
   docker volume create --label com.docker.compose.project=sab --label com.docker.compose.volume=redis_data sab_redis_data
   ```

   Si usa otro nombre de proyecto, sustituya tanto los nombres de volumen como
   la etiqueta `com.docker.compose.project` por los valores efectivos.
4. Con los servicios detenidos, copie cada volumen por separado. Sustituya los
   marcadores por los nombres reales, sin incluir caracteres `<>`:

   ```bash
   docker run --rm --network none -v <origen>:/origen:ro -v <destino>:/destino alpine:3.20 \
   sh -ec 'set -o pipefail; test -z "$(ls -A /destino)"; cd /origen; tar cf - . | (cd /destino && tar xpf -)'
   docker run --rm --network none -v <origen>:/origen:ro -v <destino>:/destino:ro alpine:3.20 \
     sh -ec 'diff -r /origen /destino'
   ```

   El primer comando rechaza destinos no vacíos; repítalo para MySQL y Redis.
   Compare también permisos y propiedad antes de arrancar:

   ```bash
   docker run --rm --network none -v <origen>:/origen:ro -v <destino>:/destino:ro alpine:3.20 \
     sh -ec '(cd /origen && find . -exec stat -c "%n %u %g %a" {} + | sort) >/tmp/origen; (cd /destino && find . -exec stat -c "%n %u %g %a" {} + | sort) >/tmp/destino; diff -u /tmp/origen /tmp/destino'
   ```

   Una copia de archivos no reemplaza la prueba de restauración del respaldo.
5. Configure `APP_NAME=SAB` en el `.env` existente. Actualice automatizaciones y variables `PRO9_*` propias de pruebas a `SAB_*`,
   así como referencias a contenedores e imágenes. Arranque la nueva pila, ejecute
   `docker compose exec -T php php artisan config:clear` y compruebe integridad,
   acceso a tenants y recuperación de operaciones Hotel pendientes. Mantenga la
   instalación sin escrituras durante la validación inicial. Si falla en esa
   etapa, detenga la nueva pila y vuelva a la configuración y volúmenes anteriores.
   Si SAB ya recibió escrituras, antes de revertir es necesario respaldarlas y
   migrarlas o restaurarlas a la instalación anterior: volver directamente a los
   volúmenes antiguos las perdería. Conserve el respaldo y los originales hasta
   validar la migración.

<!-- ######## INICIO API BCV EN DOCKER ######## -->
### API BCV exclusiva para SAB

El Compose construye `api-bcv` desde el proyecto hermano `../api-bcv`. La API usa
un token fijo de 64 caracteres hexadecimales minúsculos: `API_TOKEN` en su `.env`
y el mismo valor en `BCV_API_TOKEN` del `.env` de SAB. No existe inicio de sesión
por usuario/clave. Los secretos se mantienen fuera de Git y del navegador.

SAB configura `BCV_API_URL=http://api-bcv:3000`, `BCV_API_TIMEOUT=15` y
`BCV_API_CONNECT_TIMEOUT=5`. PHP envía `Authorization: Bearer <token>` desde el backend.
Para rotar el token, actualizar ambos archivos `.env`, recrear API BCV y limpiar la
configuración de SAB. No imprimir el Compose expandido ni los archivos de secretos.

Sólo PHP y API BCV comparten la red `bcv`; PHP conserva también la red predeterminada.
La API no publica puertos en el host ni tiene proxy público. Mantiene salida HTTPS
al banco con verificación TLS. El administrador de Docker conserva control del despliegue.
`GET /health` comprueba el proceso; `GET /` exige token y devuelve `{ euro, dolar }`.

Desde la carpeta de SAB:

```bash
docker compose config --quiet
docker compose up -d --build --no-deps api-bcv php
docker compose exec -T php php artisan config:clear
# Nginx debe volver a resolver PHP si cambió su IP al recrearlo:
docker compose exec -T nginx nginx -s reload
docker compose ps api-bcv php
node docker/test-api-bcv.cjs
```

La prueba consulta desde PHP, sin mostrar el token; comprueba salud, rechazos `401`,
retirada de `/login` y tasas reales. No modifica tablas de tenants.

`GET /services/exchange/{date}` guarda la primera tasa de hoy en `exchange_rates`
del tenant solicitante. Consultas posteriores y el botón **Obtener** reutilizan esa fila.
`purchase`, `sale` y sus originales contienen la misma tasa BCV VES por USD a ocho
decimales. `date_original` es la fecha de consulta en Caracas: la API no proporciona
fecha oficial de vigencia. El euro no se almacena en esas columnas.

Las fechas anteriores requieren una fila local; las futuras se rechazan (`422`).
Fallos de API o guardado no inventan una tasa (`503`). No hay tareas programadas,
actualizaciones masivas ni cambios en snapshots de documentos.
<!-- ######## FIN API BCV EN DOCKER ######## -->

### Producción con Supabase gestionado

Consulta la [guía de producción](docker/production/README.md) para configurar el proyecto Supabase, Storage privado y Cloudflare Tunnel.

### Instalación PostgreSQL 17.11

SAB usa PostgreSQL 17.11 de Supabase (`17.11.0.019`, digest fijado en Compose),
con una base central y una base por empresa. El despliegue local utiliza
`linux/amd64`, correspondiente al Intel i7. La cadena ARM64 de Supabase es una
referencia del servicio remoto; no requiere emulación ARM en este equipo.

Seguir el [manual de instalación y recuperación](informes/postgresql_17/operacion.md).
Copiar `.env.example` y definir las claves independientes de inicialización,
propietario, operación y aprovisionamiento. API BCV conserva su configuración
indicada arriba. Preparar una instalación nueva:

```bash
docker compose config --quiet
docker compose build php
docker compose up -d --wait postgres redis
docker compose run --rm --no-deps php composer install --no-interaction
docker compose run --rm --no-deps -e SAB_ARTISAN_USER= php php artisan storage:link --relative
docker compose run --rm --no-deps php php artisan sab:install-postgres
docker compose up -d php nginx redis api-bcv
```

En el servicio PHP, Artisan se ejecuta como `www-data`, igual que PHP-FPM,
incluso si `docker compose exec` inicia el comando como root. Así las vistas
compiladas y los archivos privados de clientes quedan accesibles para ambos.
La excepción anterior permite crear el enlace público de instalación como root;
no desactive `SAB_ARTISAN_USER` para comandos que escriban datos de clientes.

La base queda en la red interna. Para un cliente local, añadir
`-f docker-compose.postgresql.local.yml` al Compose; publica únicamente
`127.0.0.1:${SUPABASE_POSTGRES_PORT:-54322}`. Usar credenciales de operación
y la base central configurada; el usuario de inicialización queda reservado.
Mantener tanto `postgres_data` como `postgres_config` para conservar datos,
configuración y claves al recrear contenedores.

La verificación automatizada crea su propio proyecto, servidor y credenciales:

```bash
php scripts/test_postgresql.php
```

No lee conexiones de `.env` ni utiliza bases del sistema en operación. El ejecutor
retira sus recursos temporales y guarda resultados en
`informes/evidencias/postgresql_17_implementacion/resultados/`.

[Windows ](https://manual.pro8.uio.la/devs/despliegue/plataformas/windows "Clic")
<br>
[Docker - Linux](https://git.buho.la/-/snippets/79 "Clic")
<br>
[Linux - SSL](https://manual.pro8.uio.la/devs/despliegue/seguridad/instalar-ssl "Clic")
<br>
[Valet - Linux](https://manual.pro8.uio.la/devs/despliegue/plataformas/valet-linux "Clic")
<br>
[Linux - gestión externa de SSL](https://manual.pro8.uio.la/devs/despliegue/seguridad/gestion-externa-ssl "Clic")

### Scripts de instalación con Docker

Linux - Ubuntu 20 - 24 - Docker - SSL opcional<br>
[Guia](https://git.buho.la/-/snippets/12/ "Clic")<br>
[Script](https://git.buho.la/-/snippets/12/raw/main/newSSL.sh "Clic")<br>

### Manuales de actualización

-   Docker - Comandos manuales

[Con Docker](https://git.buho.la/-/snippets/79/raw/main/update_all.sh "Clic")
<br>

### Manuales de actualización de SSL gratuito

-   Docker

[SSL](https://manual.pro8.uio.la/devs/despliegue/seguridad/instalar-ssl "Clic") <br>

[Script](https://git.buho.la/-/snippets/13 "Clic") <br>

[SSL AUTOMATICO](https://manual.pro8.uio.la/devs/despliegue/seguridad/ssl-cloudflare "Clic") <br>

### Manuales de Usuario

[Manual de usuario](https://docs.google.com/document/d/1i7yKGy3rIvv9TrnwRWZifTuZMMnZ8dbWpqcjPZ3ClmE/edit "Clic")<br>
[Manual de Tareas Programadas](https://manual.pro8.uio.la/devs/Manual-de-Usuario/Tareas-Programadas "Clic")<br>
[Manual de Cambio de Entorno (Usuario secundario)](https://manual.pro8.uio.la/devs/Manual-de-Usuario/Cambio-de-Entorno "Clic")<br>
[Configuración esencial para tu cuenta de facturación](https://manual.pro8.uio.la/devs/guias-adicionales/Configuracion-esencial-para-tu-cuenta-de-facturacion "Clic")

## API

[Descargar colección para Postman](https://drive.google.com/file/d/1JQctRCIZdC7K30JiizruKkPUaBSs_xml/view?usp=sharing "Clic")<br>
[Documentación - Ver json con respuestas](https://manual.pro8.uio.la/devs/api/introduccion "Clic")<br>

## Pruebas online

### Panel de administración

[URL](https://facturalo.pro "Clic")
<br>
Usuario: admin@gmail.com<br>
Contraseña: 123456

### Panel de cliente

[URL](https://empresa.pro8.uio.la/login "Clic")
<br>
Usuario: empresa@gmail.com<br>
Contraseña: empresa@gmail.com

## Manuales adicionales

### Conexión

Conexión remota al servidor: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/conexion/conexion-remota "Clic")<br>
Guía acceso SSH - Putty: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/conexion/conexion-ssh-putty "Clic")<br>
Conexión servidor Winscp: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/conexion/conexion-winscp "Clic")<br>
Montar proyecto en /home: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/conexion/montar-proyecto-en-home "Clic")<br>

### Manipulación de archivos dentro del servidor

Documentación del archivo .ENV: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/archivo-env "Clic")<br>
Incrementar recursos - servidor: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/recursos.servidor "Clic")<br>
Incrementar recursos - aplicación: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/incrementar-recursos "Clic")<br>
Configuracion de correo electrónico emisor: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/correo-electronico-emisor "Clic")<br>
Configuracion de correo emisor por cliente: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Manipular-datos/CORREO-ELECTR%C3%93NICO-EMISOR-cliente "Clic")<br>
Manual - Cambio de dominio: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Manipular-datos/cambio-de-dominio "Clic")<br>
Linux - Eliminar temporales: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/eliminar-temporales "Clic")<br>
Linux - Eliminar archivos por extensión: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/eliminar-archivos-por-extension "Clic")<br>
Configuración servidor alterno SUNAT: [Guía](https://manual.pro8.uio.la/devs/devops/manuales-adicionales/Manipular-datos/configuracion-servidor-alterno-sunat "Clic")<br>
Habilitar debug: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Manipular-datos/habilitar-debug "Clic")<br>
Configuración de API RUC/DNI (APIPERU): [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Manipular-datos/configuracion-api "Clic")<br>
Configuración de tareas programadas (crontab-LAMP): [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Manipular-datos/config-tareas-programadas "Clic")<br>

### Base de datos

Guía acceso a base de datos: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Acceso-DB/Guia-acceso-a-base-de-datos "Clic")<br>
Cambiar Contraseña root: [enlace](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Acceso-DB/Cambiar-Contrase%C3%B1a-root "clic")<br>

### Docker

Iniciar servicios docker: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Doker/Iniciar-servicios "Clic")<br>
Guía generar backup: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Doker/Generar-backup "Clic")<br>
Restauración de Mysql|Docker: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Doker/Restauracion-MySQL "Clic")<br>

### Servidores

Guía incrementar espacio disco: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Servidores/Guia-incrementar-espacio-disco "Clic")<br>
Limpiar inodes: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Servidores/Limpiar-Inodes "Clic")<br>
Migración servidor: [Guía](https://manual.pro8.uio.la/devs/devops/migracion-server-docker "Clic")<br>
Habilitar puertos en Google Cloud: [Manual](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Servidores/Habilitar-Puertos "clic")<br>

### Funcionalidades

ICBPER en POS: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Funcionalidades/ICBPER-en-POS "Clic")<br>

### Laragon

Acceso red local - laragon: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Laragon/Acceso-Laragol "Clic")<br>

### Errores comunes

Procedimiento para solucionar error 1033 SUNAT: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/Errores-comunes/error-sunat "Clic")<br>

### No clasificados

Recreación de documentos: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/No-clasificados/Recreacion-documentos "Clic")<br>
Manual de cambios privados: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/No-clasificados/Crear-repositorio-independiente "Clic")<br>
Validador documentos: [Guía](https://manual.pro8.uio.la/devs/devops/Manuales-adicionales/No-clasificados/Validar-Documento "Clic")<br>

## Soporte

-   Use Issues como sistema de tickets para añadir sus sugerencias, problemas o inquietudes
-   Inconvenientes con facturación serán atendidos con prioridad
-   Una vez obtiene acceso al repositorio tendrá un año de vigencia, pasado el año debe renovar su contrato
-   Toda instalación es gestionada mediante los canales de Slack
-   Nuevas instalaciones o actualizaciones deben ser programadas y gestionadas, para ser ejecutada el mismo día debe haber un problema previo

## FacturaloPeru

[facturaloperu.com](http://facturaloperu.com "Clic")<br>
soporte@facturaloperu.com<br>
wsapp: 930 973 902<br>

<!-- ######## INICIO TASAS OCHO DECIMALES ######## -->
### Precisión exacta de tasas

API BCV y SAB devuelven las tasas como cadenas, por ejemplo `"873.86700000"`.
Las columnas de tasas de 19 tablas usan `DECIMAL(18,8)`; los importes conservan su
precisión monetaria. `ExchangeRateMath` (PHP) y `exchange-rate-math` (JS) operan con
racionales exactos antes del redondeo del importe final. No convertir tasas a float
ni aplicar `toFixed`, `round` o formatos de importes sobre la propia tasa.

Para ampliar tenants existentes, sin borrar filas ni reconstruir históricos:

```bash
docker compose exec -T php php artisan exchange-rates:upgrade-precision --all-tenants --dry-run
docker compose exec -T php php artisan exchange-rates:upgrade-precision --all-tenants
```

El comando es idempotente y preserva nulabilidad y defaults. Las tasas antiguas reciben
ceros finales; los decimales perdidos no se recuperan. Probar primero en PostgreSQL 17 temporal.
Los cambios de fuentes frontend requieren la compilación que ejecuta el usuario.
<!-- ######## FIN TASAS OCHO DECIMALES ######## -->


### PostgreSQL 17.11: dashboard compilado y verificado

El build 08 del worktree autorizado aprobó junto con 111 pruebas PHP/553 aserciones, 857 JavaScript, 35 comprobaciones del dashboard Vue/Apex/Chart.js montado y 17 del catálogo/checkout compilado. Se verificó una misma fuente fija con PostgreSQL temporal y limpieza. Las auditorías estrictas siguen abiertas: 11.938 PHP y 13.451 JavaScript pendientes, sin errores de registro/análisis/cobertura. El cambio completo aún no se declara terminado. Véanse [evidencias](informes/evidencias/postgresql_17_implementacion/resultados/dashboard_frontend_resumen_20261112.json) y [trabajo pendiente](informes/postgresql_17/pendientes_verificacion.md).


La batería completa `pro9-pgtest-e8e32958907e` aprobó 1.952 pruebas funcionales/81.256 aserciones, 857 JavaScript, ambos navegadores, SQL, sintaxis y Git, con fuente fija y limpieza de PostgreSQL temporal. El resultado global continúa en 1 por el presupuesto del POS de 1.000 documentos y las dos auditorías estrictas abiertas. [Última evidencia completa](informes/evidencias/postgresql_17_implementacion/resultados/validacion_completa_resumen_20261112_02.json).

El build 09 del worktree también está compilado y verificado: 313 pruebas PHP/3.376 aserciones, 864 JavaScript y 22 + 35 comprobaciones de navegador. Corrige el transporte de claves grandes y los precios de recomendaciones, incluida su ocultación. Los controles estrictos mantienen 11.696 PHP/13.412 JavaScript pendientes, sin errores de registro/análisis/cobertura; continúa pendiente la batería completa sobre la fuente final. [Evidencia del bloque](informes/evidencias/postgresql_17_implementacion/resultados/transporte_bigint_resumen_20261112.json).

La validación posterior de IDs y límites del servidor aprobó 56 casos/231 aserciones en PostgreSQL temporal. Los controles estrictos actuales mantienen 11.676 PHP/13.412 JavaScript pendientes, sin errores de registro/análisis/cobertura. El build 09 continúa vigente; la implementación sigue abierta. [Evidencia](informes/evidencias/postgresql_17_implementacion/resultados/ids_recomendados_resumen_20261112.json).

La repetición completa posterior aprobó rendimiento, pero se interrumpió por memoria durante un XLSX. La liberación de referencias de los fixtures ya aprobó cinco casos/1.994 aserciones con los mismos 768 MiB; falta repetir la suite completa. Las auditorías actuales mantienen 11.660 PHP/13.412 JavaScript pendientes, sin errores de registro/análisis/cobertura. El build 09 continúa vigente y la implementación permanece abierta. [Diagnóstico y corrección](informes/evidencias/postgresql_17_implementacion/resultados/liberacion_fixture_resumen_20261112.json).

El ejecutor completo separa ahora los grupos funcionales en procesos PHP y contrasta todos los casos/datasets con el inventario descubierto. Mantiene 768 MiB y los presupuestos originales. Aprobaron 60 casos/380 aserciones dirigidos; falta aprobar el modo completo nuevo. Las auditorías mantienen 11.431 PHP/13.412 JavaScript pendientes, sin errores de registro/análisis/cobertura. [Evidencia de la corrección](informes/evidencias/postgresql_17_implementacion/resultados/fases_funcionales_resumen_20261112.json).


La última batería completa restante ejecutó **2.135 casos/82.212 aserciones**, con inventario exacto: 1.812 generales aprobaron y el bloque fiscal detectó un error de edición de ajustes. Aprobaron JavaScript (912), SQL, sintaxis y navegador (22 + 35), con fuente fija y limpieza temporal. La regresión se corrigió después y aprobó 36 casos/188 aserciones, conservando las aserciones anteriores. Falta una nueva aceptación completa. El POS de rendimiento siguió aplazado y no se ejecutó. [Batería 08](informes/evidencias/postgresql_17_implementacion/resultados/validacion_completa_resumen_20261112_08.json).

Los anticipos conservan ahora sus fracciones desde el listado y la selección hasta la factura, el consumo y la restitución. Se evita el saldo inicial inflado y los snapshots inválidos devuelven validación. Aprobaron **90 casos/1.668 aserciones**, **895 JavaScript**, **22 + 35 comprobaciones de navegador**, SQL y sintaxis PHP 8.2; el build 10 fue compilado en el worktree autorizado. La fuente permaneció fija y PostgreSQL fue temporal, con limpieza comprobada. [Corrección y evidencias](informes/evidencias/postgresql_17_implementacion/resultados/anticipos_seleccion_resumen_20261112.json).

La implementación sigue abierta: las auditorías estrictas mantienen **11.224 PHP/11.410 JavaScript pendientes**, sin errores de registro, análisis ni cobertura. Se corrigieron también tres normalizadores que descartaban originales de descuentos/cargos: aprobaron 37 casos/183 aserciones, incluidas las siete reproducciones sin cambiar sus entradas. La batería completa ya incluye las correcciones de anticipos y ajustes. Falta cerrar la revisión global de precisión. El rendimiento del POS permanece aplazado por el usuario.

[Conservación de descuentos y cargos](informes/evidencias/postgresql_17_implementacion/resultados/ajustes_cabecera_resumen_20261112.json).

Se revisaron individualmente 252 expresiones de color y clases CSS con pruebas del consumidor real; no cambian las reglas globales ni se aprueban cálculos financieros por archivo. Se descartó una aprobación automática de sumas racionales porque no demostraba que sus operandos conservaran los originales. [Revisión y decisión](informes/evidencias/postgresql_17_implementacion/resultados/revisiones_visuales_tecnicas_resumen_20261112.json).

El guardado de servicios técnicos conserva ahora los originales de descuentos y cargos manuales: aprobaron 36 casos/1.158 aserciones y el suplemento de originales históricos (12/62, con diez casos solapados). Se revisaron 180 expresiones de fecha en 129 plantillas contra modelos y columnas PostgreSQL temporales. Estas revisiones son individuales. La pérdida de precisión al cargar/restar anticipos en tres formularios se corrigió y verificó después. [Servicio](informes/evidencias/postgresql_17_implementacion/resultados/servicio_ajustes_resumen_20261112.json), [fechas](informes/evidencias/postgresql_17_implementacion/resultados/fechas_pdf_resumen_20261112.json).

Los cinco fallos de carga y resta de anticipos ya pasan con las mismas entradas. La validación posterior aprobó **912 JavaScript, 40 casos PostgreSQL/226 aserciones, build 11, SQL, sintaxis y 57 comprobaciones del navegador**, con fuente fija y limpieza temporal. Los 103 archivos de pruebas JavaScript anteriores son idénticos. Las auditorías siguen abiertas y queda repetir la aceptación completa de estos cambios; el POS de rendimiento permanece aplazado. [Corrección y pruebas](informes/evidencias/postgresql_17_implementacion/resultados/anticipos_cabecera_formulario_resumen_20261112.json).

La corrección de edición conserva una fracción válida si no cambió su decimal y usa el nuevo importe validado al editarlo. Las reproducciones de links de pago se corrigieron después; la revisión global continúa y 135 revisiones técnicas siguen preparadas sin aplicar. [Corrección](informes/evidencias/postgresql_17_implementacion/resultados/servicio_ajustes_edicion_resumen_20261112.json), [links de pago](informes/evidencias/postgresql_17_implementacion/resultados/diagnostico_links_pago_importes_20261112.json).

El bloque de links de pago aprobó **89 pruebas PHP/206 aserciones, 933 JavaScript, 15 comprobaciones del bundle compilado, build 12, SQL y sintaxis PHP 8.2** en recursos temporales. Las auditorías estrictas mantienen **11.194 PHP/11.409 JavaScript pendientes**, sin errores de registro/análisis/cobertura. Por instrucción del usuario, se detiene el trabajo antes de completar la revisión de PDF y lanzar la batería final, reservadas para **GPT-6.1 Terra en alto**. La implementación sigue abierta y el rendimiento del POS continúa aplazado. [Punto de continuación](informes/postgresql_17/pendientes_verificacion.md), [evidencia del bloque](informes/evidencias/postgresql_17_implementacion/resultados/links_pago_resumen_20261112.json).

La batería completa posterior a las últimas correcciones de facturación aprobó **2.281 pruebas funcionales/82.755 aserciones**, SQL, sintaxis, JavaScript y ambos navegadores, con PostgreSQL temporal y limpieza correcta. El resultado global sigue en error por **11.145 candidatos PHP y 11.409 JavaScript** de las auditorías estrictas de todo el sistema; el usuario limitó los cambios de exactitud a facturación. El rendimiento del POS continúa aplazado. [Estado y evidencia](informes/postgresql_17/pendientes_verificacion.md).
