<img src="icon_pro5.png" width="120">

# **Facturador PRO 9**

## Términos y condiciones del uso de este repositorio

1.- Este repositorio es de código abierto pero de acceso privado, se permite la distribución y/o modificaciones si se hace referencio a la casa matriz del desarrollo de este es software es [https://facturaloperu.com](https://facturaloperu.com)

2.- Esta sección de términos y condiciones no puede ser removida al compartir o distribuir el repositorio de alguna forma, de hacerlo, [https://facturaloperu.com](https://facturaloperu.com) se reserva el derecho de remover el acceso y limitar el uso a quien lo distribuya de esa forma o a quien se atribuya el desarrollo del mismo.

3.- Si desea distribuir el código fuente como propio, debe tener al menos un 30% de modificaciones en todo el código, y previamente debe validarse dicho % por [https://facturaloperu.com](https://facturaloperu.com)

4.- El uso del software a nivel funcional es marca blanca, sin embargo a nivel de distribución del código fuente, debe contener esta sección de términos y condiciones.

5.- [https://facturaloperu.com](https://facturaloperu.com) no se hace responsable por los daños o perjuicios del uso del código de este software cuando no ha sido distribuido directamente por [https://facturaloperu.com](https://facturaloperu.com)

## Manuales de Instalación

<!-- ######## INICIO API BCV EN DOCKER ######## -->
### API BCV exclusiva para Pro9

El Compose construye `api-bcv` desde el proyecto hermano `../api-bcv`. La API usa
un token fijo de 64 caracteres hexadecimales minúsculos: `API_TOKEN` en su `.env`
y el mismo valor en `BCV_API_TOKEN` del `.env` de Pro9. No existe inicio de sesión
por usuario/clave. Los secretos se mantienen fuera de Git y del navegador.

Pro9 configura `BCV_API_URL=http://api-bcv:3000`, `BCV_API_TIMEOUT=15` y
`BCV_API_CONNECT_TIMEOUT=5`. PHP envía `Authorization: Bearer <token>` desde el backend.
Para rotar el token, actualizar ambos archivos `.env`, recrear API BCV y limpiar la
configuración de Pro9. No imprimir el Compose expandido ni los archivos de secretos.

Sólo PHP y API BCV comparten la red `bcv`; PHP conserva también la red predeterminada.
La API no publica puertos en el host ni tiene proxy público. Mantiene salida HTTPS
al banco con verificación TLS. El administrador de Docker conserva control del despliegue.
`GET /health` comprueba el proceso; `GET /` exige token y devuelve `{ euro, dolar }`.

Desde la carpeta de Pro9:

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

API BCV y Pro9 devuelven las tasas como cadenas, por ejemplo `"873.86700000"`.
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
ceros finales; los decimales perdidos no se recuperan. Probar primero en MySQL temporal.
Los cambios de fuentes frontend requieren la compilación que ejecuta el usuario.
<!-- ######## FIN TASAS OCHO DECIMALES ######## -->
