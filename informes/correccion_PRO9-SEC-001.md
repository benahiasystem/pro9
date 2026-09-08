# Corrección de PRO9-SEC-001

Fecha: 2026-09-08.

## Resultado y alcance

Corregida en el código de la rama `codex/fix-pro9-sec-001` la selección de asociaciones MultiUser ajenas en web y API. La rama parte de `feat-categorias-tables`, commit `5b99d2088544812181f08f7042ca0e3067391a48`. Los cambios están en el árbol de trabajo, sin commit ni despliegue realizado por esta tarea.

El servidor resuelve la asociación dentro del conjunto permitido al **par empresa/usuario autenticado**, no por el ID enviado sin restricciones. Se conserva la ida desde la cuenta origen, el regreso desde una cuenta espejo y el cambio entre destinos asociados al mismo origen. Una cuenta espejo acredita su pertenencia mediante su enlace central y ambos identificadores de destino.

## Cambios

- `modules/MultiUser/Services/MultiUserAccessService.php`: autorización común, pertenencia al par empresa/usuario, dirección permitida y cuenta solicitante activa.
- `modules/MultiUser/Http/Requests/Tenant/ChangeClientRequest.php` y su variante `Api`: autenticación y validación de identificador/dirección; admite los textos `true`/`false` del formulario y booleanos/0/1 de API. La API valida además el dominio.
- Controladores tenant web/API de MultiUser: autorizan antes de escribir la solicitud de acceso o cambiar de conexión y consultar credenciales. La API restaura el contexto origen mediante `finally`, incluso al fallar.
- `modules/MultiUser/Traits/Tenant/MultiUserTrait.php`: el listado de empresas de una cuenta espejo también verifica que su enlace le pertenezca.
- `modules/MultiUser/Helpers/Tenant/AutoLoginHelper.php`: antes del login verifica que la asociación conserve sus extremos, que el dominio corresponda al destino y que el usuario destino esté activo. La sincronización de permisos existente permanece sin cambios.

No se modificaron rutas, frontend, contratos de respuesta exitosos, esquema, datos ni dependencias. No requiere compilar assets ni ejecutar migraciones.

## Verificación ejecutada

```bash
docker exec -w /var/www/pro9 pro9-php php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/MultiUserAccessSecurityTest.php
```

Resultado: **18 pruebas, 75 aserciones; todas correctas**.

Se usan modelos, consultas, FormRequests, controladores y formateador de respuesta API reales sobre cuatro conexiones SQLite `:memory:`: una central y tres tenants, deliberadamente con IDs de usuarios coincidentes. Se simulan el cambio de entorno Hyn, la caché y la autenticación. No se carga el Kernel ni `.env`, ni se consultan bases de datos reales.

Casos comprobados:

- Asociación de otro usuario rechazada en web, con y sin AJAX, sin escritura de caché ni cambio de conexión.
- Coincidencia de ID local en otro tenant insuficiente para autorizar.
- Ida, regreso y tránsito entre destinos hermanos conservados en web y API.
- Listado de empresas legítimas conservado; enlace espejo falsificado ignorado.
- Dirección inversa no autorizada, asociación desconocida, espejo falsificado o enlace revocado rechazados.
- API sin emisión de token para asociaciones ajenas, dominio incorrecto o usuario destino inactivo.
- Respuesta API legítima conserva token, empresa, dirección y establecimiento del destino; restaura el origen.
- Respuesta sin token y excepción por usuario inexistente también restauran el origen.
- Canje web válido invoca login con el usuario esperado y consume la entrada; asociación modificada/eliminada o destino inactivo no autentican.
- Solicitante inactivo, visitante y entradas malformadas rechazados.

También se verifican sintaxis PHP, `git diff --check` y coincidencia del commit base con `feat-categorias-tables`.

## Límites y riesgo pendiente

Las pruebas son de integración aislada de componentes, no una prueba de navegador ni de sesiones/caché real entre dominios. No verifican concurrencia o expiración real; la sincronización de permisos sigue su retorno sin administrador en los fixtures. No se ejecutó la suite global porque podría arrancar servicios o conexiones reales.

**PRO9-SEC-002 sigue pendiente:** el canje web conserva la entrada compartida `auto_login_{fqdn}` y no exige un ticket secreto por solicitante. Esta corrección impide crear accesos usando una asociación ajena, pero no elimina la posibilidad independiente de interceptar un canje legítimo pendiente. No debe interpretarse como que todo MultiUser quedó seguro.

Las solicitudes de auto-login creadas antes de aplicar este cambio carecen de la instantánea de asociación y serán rechazadas durante su corta vigencia (10 segundos); basta volver a seleccionar la empresa. No se eliminaron sesiones ni datos.

Antes de publicar, conviene verificar en staging el selector entre dominios con sesiones y caché del despliegue, y abordar PRO9-SEC-002 antes de considerar cerrado el riesgo global de autenticación automática.
