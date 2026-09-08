# Auditoría de seguridad de Pro9

## Resumen ejecutivo

**Sí se identificaron vulnerabilidades en el código de Pro9.** El riesgo principal es la autorización: existen operaciones públicas sensibles, controles de permisos que dependen de una cabecera manipulable y un cambio de empresa que no vincula la asociación multiusuario con el solicitante.

Se documentan **13 hallazgos de aplicación: 1 crítico, 10 altos y 2 medios**. “Confirmado” significa que el defecto y su flujo están demostrados en el código; las pruebas indicadas reproducen determinados componentes con datos simulados. **No significa que se haya explotado una instalación real ni que haya evidencia de un incidente.** La explotación depende de que las rutas y funciones correspondientes estén disponibles en el despliegue.

| ID | Severidad | Hallazgo |
|---|---|---|
| PRO9-SEC-001 | Crítica | Cambio de tenant mediante asociación multiusuario ajena |
| PRO9-SEC-002 | Alta | Inicio automático consumible por cualquier visitante durante su ventana de validez |
| PRO9-SEC-003 | Alta | Omisión AJAX de permisos, escalamiento de rol y exposición de tokens |
| PRO9-SEC-004 | Alta | Administración de ecommerce y configuración sensible sin autenticación |
| PRO9-SEC-005 | Alta | Endpoint público que devuelve la clave privada de QZ Tray |
| PRO9-SEC-006 | Media | Carga pública de SVG activo y sobrescritura de imagen compartida |
| PRO9-SEC-007 | Alta | XSS almacenado en contenido de la tienda |
| PRO9-SEC-008 | Alta | Modificación de pedidos ajenos y aplicación repetida de cupones |
| PRO9-SEC-009 | Alta | Confirmación Izipay no vinculada al pedido que se marca pagado |
| PRO9-SEC-010 | Alta | Total de compra y descuento aceptados desde el navegador |
| PRO9-SEC-011 | Alta | SSRF mediante destinos de webhooks sin restricción de red |
| PRO9-SEC-012 | Media | Lectura pública de notas de venta mediante ID secuencial |
| PRO9-SEC-013 | Alta | Ruta GET pública que vacía el padrón del tenant |

Dependencias, contabilizadas aparte:

- Composer informó **57 entradas en 15 paquetes; 56 avisos únicos** al agrupar el GHSA duplicado de Laravel. Distribución de avisos únicos del proveedor: 2 críticos, 17 altos, 29 medios y 8 bajos.
- NPM informó **153 paquetes afectados**, incluyendo propagación por dependencias: 5 críticos, 32 altos, 97 moderados y 19 bajos. La respuesta contiene 160 identificadores de aviso `source` distintos. Estas cifras **no representan 153 o 160 vulnerabilidades explotables de Pro9**.
- Se confirmaron las versiones afectadas en los lockfiles. La posibilidad de explotar cada aviso depende de la función usada y del entorno; no se atribuye RCE a Pro9 por la mera presencia de PhpSpreadsheet.

No se aplicaron correcciones a la aplicación. Se creó una skill preventiva basada en los hallazgos, que orienta cambios futuros y **no sustituye las correcciones ni las pruebas de seguridad**.

## Alcance y contexto

- Fecha: 8 de septiembre de 2026, zona America/Caracas.
- Repositorio: `/home/benahia/benahia/pro/Pro9`.
- Commit: `3e2130e13a6b25462297eb464d9a23abb70e40b6`.
- Rama: `feat-categorias-tables`.
- Árbol con numerosas modificaciones y archivos nuevos previos a esta auditoría. Se revisó el contenido presente, incluidos esos cambios; no se atribuye su autoría ni se afirma que los defectos nacieran en ese commit.
- Inventario versionado: 4.790 archivos PHP/Vue/JS en app, modules, routes, resources, config y database; 99 archivos de rutas. Se efectuaron búsquedas transversales y revisión dirigida de flujos de riesgo, **no lectura exhaustiva línea a línea de los 4.790 archivos**.
- Laravel `v9.52.21`, Hyn tenancy `5.9.1`, Vue `2.7.16`, Vite `4.5.14`.
- PHP del host: 7.4.33. PHP del contenedor existente `pro9-php`: 8.2.5; Composer 2.10.2. El contenedor monta este repositorio en `/var/www/pro9`.
- Las versiones instaladas de Laravel, Dompdf, Guzzle, PhpSpreadsheet y HttpFoundation coinciden con las versiones verificadas del lockfile.
- `modules_statuses.json` declara habilitados MultiUser, Ecommerce, Webhook y Padron, entre otros. No se inspeccionó la caché efectiva de rutas de producción.

Herramientas: Git, ripgrep, lecturas numeradas, Node para inventario/lockfiles, Composer audit, NPM audit, PHP con las librerías instaladas y Mockery. Consultas a avisos públicos de fabricantes/GitHub para contrastar dependencias. Las auditorías de dependencias consultaron metadatos de paquetes; no se enviaron archivos de negocio, bases de datos o secretos.

Las pruebas PHP cargaron `vendor/autoload.php`, un contenedor de dependencias en memoria y dobles de modelos/servicios. No arrancaron Laravel contra la base real. No se enviaron pagos, correos, webhooks ni solicitudes de explotación HTTP.

## Modelo de amenazas y superficie de ataque

Actores considerados: visitante sin cuenta, cliente de ecommerce, vendedor tenant, administrador tenant y usuario con acceso legítimo a varias empresas. Recursos protegidos: cuentas, tokens, claves privadas, documentos, configuración comercial, pedidos, pagos, bases por tenant y red del servidor.

El host identifica al tenant mediante Hyn. Ese aislamiento de conexión no reemplaza comprobar que el usuario pueda actuar sobre una asociación central, un pedido o una función administrativa.

| Superficie | Controles observados | Riesgo comprobado o límite |
|---|---|---|
| Web tenant | Sesión, CSRF, bloqueo de tenant, redirect.module | Omisión AJAX y permisos ausentes en acciones |
| API tenant | Throttle del grupo api; auth:api en subgrupo | Algunas rutas sensibles quedan fuera del subgrupo autenticado |
| Ecommerce | check.permission revisa módulos del primer administrador | No autentica al visitante ni autoriza operaciones administrativas |
| MultiUser | Asociación en base central; caché con destino | Falta pertenencia al solicitante y secreto del canje |
| Archivos/PDF | Validación MIME en varios flujos | SVG activo, destinos públicos compartidos y rutas temporales controladas |
| Webhooks salientes | Firma, timeout, sin redirects | URL sin política para direcciones internas |
| Administración central | auth:admin y reseller.system.admin | Revisión dirigida, no certificación completa de cada acción |

## Hallazgos confirmados

### [PRO9-SEC-001] Cambio de tenant usando una asociación multiusuario ajena

> Actualización 2026-09-08: corrección implementada en el árbol de trabajo de `codex/fix-pro9-sec-001`, con 18 pruebas aisladas y 75 aserciones correctas. Véase [detalle de corrección y límites](correccion_PRO9-SEC-001.md). La evidencia siguiente describe el estado auditado originalmente; PRO9-SEC-002 continúa pendiente. No se ha desplegado esta corrección.

**Severidad: Crítica. Confianza: Alta en el defecto de autorización; ejecución entre tenants no probada en vivo. CWE-639 / CWE-862.**

Evidencia: `modules/MultiUser/Routes/web.php:11`, `modules/MultiUser/Http/Controllers/Tenant/MultiUserController.php:22`, `modules/MultiUser/Helpers/Tenant/AutoLoginHelper.php:115`, `modules/MultiUser/Helpers/Tenant/AutoLoginHelper.php:178`, `modules/MultiUser/Models/System/ModelSystem.php:10`.

Flujo: POST `multi-users` acepta `multi_user_id` e `is_destination`; busca la asociación central por ID, obtiene el cliente de destino y crea una solicitud de acceso. No compara el usuario/tenant actual con los extremos de la asociación. El listado sí filtra por cliente y usuario en `modules/MultiUser/Traits/Tenant/MultiUserTrait.php:93`, pero el POST no reutiliza ese control. El canje termina en `Auth::loginUsingId` (`AutoLoginHelper.php:83`).

Escenario: un usuario tenant activo presenta el ID de una asociación ajena válida. Si el backend de caché y el flujo de MultiUser funcionan, puede obtener sesión de la cuenta vinculada en otra empresa. Requiere una asociación existente y completar el canje; no se afirma acceso a tenants sin asociaciones.

Impacto: ruptura de la frontera entre empresas y acciones con privilegios del usuario vinculado. Validación: seguimiento completo ruta → asociación central → caché → login; prueba separada de canje con dobles en memoria. No se consultaron asociaciones reales.

Corrección: resolver la asociación dentro del conjunto autorizado para el usuario y tenant actual, verificar dirección de tránsito, estado de las dos cuentas y permisos de destino. Nunca tomar el ID central como autorización.

Regresión: usuario A/tenant A no puede usar asociación de B ni invertir un enlace no autorizado; un vínculo legítimo sigue funcionando. Verificar que un rechazo no crea solicitudes de acceso.

### [PRO9-SEC-002] Canje de acceso automático sin secreto ligado al solicitante

**Severidad: Alta. Confianza: Alta. CWE-287 / CWE-362. CVSS 3.1 estimado: 8.1, AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:N.**

Evidencia: `modules/MultiUser/Routes/web.php:23`; `modules/MultiUser/Http/Middleware/Tenant/AutoLogin.php:20`; `modules/MultiUser/Helpers/Tenant/AutoLoginHelper.php:17`, `:27`, `:104`, `:115`, `:133`, `:146`, `:157`.

Flujo: la solicitud se guarda bajo `auto_login_{fqdn}`, con CACHE_TIME=10. La URL de canje solo contiene el dominio y la ruta anterior. `startProcess()` consume la entrada por dominio y autentica sin token aportado por el visitante. La comparación FQDN solo verifica destino. `has/get/forget` son operaciones separadas.

Escenario: mientras existe un acceso legítimo pendiente, otro visitante llega primero al endpoint del destino y recibe esa sesión. Requiere acertar la breve ventana; es independiente del IDOR anterior.

Validación: el método real `startProcess()`, con caché, usuario y Auth simulados, invocó `loginUsingId(9)` sin aportar un secreto de petición. No se reprodujo una carrera sobre caché real.

Corrección: ticket aleatorio de un solo uso, breve, ligado a origen, destino e identidad autorizada; consumo atómico y validación completa antes del login. La vinculación de sesión/origen debe permitir el tránsito legítimo entre dominios.

Regresión: visitante sin ticket, ticket incorrecto/caducado, replay, canje simultáneo y ticket de otro destino deben fallar. Probar dos inicios legítimos simultáneos sin sobrescritura.

### [PRO9-SEC-003] Omisión AJAX de permisos y modificación de roles

**Severidad: Alta. Confianza: Alta. CWE-862 / CWE-269 / CWE-200.**

Evidencia: `app/Http/Middleware/RedirectModule.php:41`, `app/Http/Middleware/RedirectModuleLevel.php:45`, `routes/web.php:38`, `routes/web.php:252`, `app/Http/Requests/Tenant/UserRequest.php:9`, `app/Http/Controllers/Tenant/UserController.php:113`, `:139`, `:143`, `:186`.

Una petición con `X-Requested-With: XMLHttpRequest` evita la comprobación de módulos/niveles. POST `users` no exige rol administrador y su FormRequest autoriza siempre. El controlador acepta ID objetivo, rol y permisos desde el cliente. Un vendedor activo con CSRF válido puede modificar su rol o el de otro usuario si satisface los requisitos de datos del endpoint.

Hay una fuga relacionada: `UserController.php:96` inicializa la respuesta con el token del usuario objetivo **antes** de comprobar si el solicitante es administrador. `UserResource.php:33` también incluye `api_token`, y la ruta de consulta por usuario comparte el control débil.

Validación: el middleware real devolvió el marcador REACHED para una petición AJAX de un usuario ficticio sin módulo de usuarios. Se revisaron ruta, FormRequest y escritura de rol; no se modificaron cuentas reales. `auth`, bloqueo y CSRF permanecen como requisitos; no se afirma acceso anónimo a estas rutas.

Corrección: políticas/gates de servidor para cada operación y objeto; aplicar a AJAX, web y API por igual. Separar edición de perfil de administración de roles. No devolver tokens de otros usuarios en errores ni recursos generales.

Regresión: vendedor con/sin cabecera AJAX obtiene 403 al crear administradores, editar cuentas ajenas y leer/regenerar tokens; administrador autorizado puede realizar su operación dentro de su tenant.

### [PRO9-SEC-004] Configuración administrativa de ecommerce accesible sin autenticación

**Severidad: Alta. Confianza: Alta. CWE-306 / CWE-915 / CWE-200.**

Evidencia: `modules/Ecommerce/Routes/web.php:13`, `:80`, `:93`; `modules/Ecommerce/Http/Middleware/CheckPermission.php:18`; `modules/Ecommerce/Http/Controllers/ConfigurationController.php:27`, `:237`, `:250`; `app/Models/Tenant/ConfigurationEcommerce.php:9`; `app/Http/Resources/Tenant/ConfigurationEcommerceResource.php:23`.

Solo la vista administrativa añade `auth`; POST `ecommerce/configuration_paypal`, otros POST de configuración y GET `ecommerce/record` no lo hacen. `check.permission` comprueba que la empresa tenga ecommerce, no que el visitante sea administrador. Los middlewares de bloqueo no exigen identidad.

El POST usa `fill($request->all())`, permitiendo modificar campos fillable ajenos a su propósito, incluido contenido de páginas. GET record serializa `token_private_culqui` si ese campo tiene valor. No se comprobó si contiene una credencial vigente.

Requisitos: ecommerce habilitado y tenant operativo. Para POST hace falta una sesión CSRF válida que un visitante puede obtener al navegar la tienda; CSRF no equivale a autenticación.

Validación: registro aislado de rutas y ejecución del controlador real sobre modelo simulado: modificó `about_us` desde `store_configuration_paypal` sin identidad.

Corrección: separar explícitamente rutas públicas de administración, autenticar y autorizar todas las mutaciones administrativas; usar allowlist por operación. Retirar secretos de los recursos públicos.

Regresión: visitante y cliente ecommerce no pueden guardar configuración ni obtener secretos; los datos públicos necesarios siguen disponibles mediante recursos específicos.

### [PRO9-SEC-005] Clave privada de QZ Tray devuelta por API pública

**Severidad: Alta. Confianza: Alta. CWE-200 / CWE-306. CVSS 3.1 estimado: 7.5, AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N.**

Evidencia: `routes/api.php:144`, fuera del grupo auth:api que termina en `:135`; `app/Http/Controllers/Tenant/CertificateQzTrayController.php:92`; `modules/Store/Helpers/StorageHelper.php:48`.

GET `api/certificates-qztray/private` lee y retorna el archivo privado configurado en Company. Requiere que exista un certificado privado legible; si no existe devuelve null. No se leyó ninguna clave real.

Impacto: pérdida de confidencialidad de la clave y posible firma de mensajes como el titular en clientes que confíen en ese certificado. No se presupone acceso automático a estaciones ni ejecución remota.

Validación: ruta registrada con únicamente grupo api; controlador y helper devolvieron un marcador sintético desde Storage simulado. Se comprobó que Flysystem normaliza las barras invertidas del helper, por lo que no se descartó erróneamente el flujo por ejecutarse en Linux.

Corrección: mantener la clave en servidor, firmar allí únicamente solicitudes autorizadas y acotadas. La interfaz puede recibir el certificado público, no el material privado. Si se confirma exposición histórica, rotar/revocar el material correspondiente.

Regresión: ningún endpoint anónimo ni recurso frontend devuelve la clave, y el servicio de firma rechaza solicitudes ajenas/no permitidas.

### [PRO9-SEC-006] Carga pública de SVG activo y almacenamiento compartido

**Severidad: Media. Confianza: Alta en carga y sobrescritura; ejecución de script condicionada al modo de servir/abrir el SVG. CWE-434 / CWE-862 / CWE-79.**

Evidencia: `routes/api.php:22`; `app/Http/Controllers/Tenant/ConfigurationImageController.php:13`, `:32`, `:41`, `:46`; `config/filesystems.php:46`, `:54`.

POST `api/configurations/default-image` tiene solo middleware api. Acepta MIME `image/*`, conserva la extensión original y escribe `public/defaults/default_image.{extension}` en almacenamiento local compartido. También actualiza configuraciones del tenant sin autenticar.

Un visitante puede sustituir la imagen y cargar SVG con script que se conserva como contenido activo. La ruta de almacenamiento no contiene identidad del tenant, por lo que empresas que usan el mismo archivo pueden verse afectadas por una sobrescritura. La ejecución de SVG requiere navegación directa/incrustación activa y headers que lo permitan; una etiqueta img por sí sola no demuestra ejecución.

Validación: un SVG mínimo con script inerte pasó exactamente la regla del controlador. Una imagen GIF con nombre `audit.php` **fue rechazada** por Laravel; no se reporta RCE por esa vía. Los dos fixtures temporales se eliminaron.

Corrección: autorización administrativa, imágenes raster decodificadas y recodificadas, nombres generados por servidor y almacenamiento aislado por tenant. Si SVG es requisito, sanitización especializada y entrega controlada.

Regresión: visitante rechazado, SVG activo rechazado/sanitizado, tenant A no modifica la imagen de B; límite de tamaño vigente.

### [PRO9-SEC-007] XSS almacenado en páginas de ecommerce

**Severidad: Alta. Confianza: Alta en flujo de datos; sin ejecución en navegador. CWE-79.**

Evidencia: `modules/Ecommerce/Http/Controllers/ConfigurationController.php:54`, `:237`; `app/Models/Tenant/ConfigurationEcommerce.php:31`; `modules/Ecommerce/Http/Controllers/EcommerceController.php:3141`; `modules/Ecommerce/Resources/views/pages_fields/about_us.blade.php:14`.

El texto persistido de `about_us` llega directamente a salida Blade sin escape mediante `{!! $about_us !!}`. No hay sanitizador en la escritura/lectura revisada. Las páginas terms_conditions y privacy_policy usan el mismo patrón. El hallazgo 004 permite introducir contenido desde una sesión visitante con CSRF; aun corrigiendo autorización, se requiere una política de HTML permitido para los autores de contenido.

Impacto: ejecución de JavaScript en el origen de la tienda al visitar contenido manipulado, con acciones/datos accesibles a la sesión de la víctima. No se asume que pueda leer cookies HttpOnly.

Validación: cadena campo fillable → persistencia → controlador público → Blade sin escape; la prueba de asignación usó solo un marcador inerte. No se publicó contenido malicioso.

Corrección: escapar texto simple y sanitizar HTML enriquecido con allowlist mantenida; eliminar scripts, handlers y URL peligrosas. Revisar contenido almacenado durante la remediación, sin confiar únicamente en el editor visual.

Regresión: marcadores con etiquetas/atributos activos quedan neutralizados y el formato permitido se conserva. Probar salida real, no solo búsqueda de patrones.

### [PRO9-SEC-008] Pedidos modificables por ID y cupones sin idempotencia por pedido

**Severidad: Alta. Confianza: Alta. CWE-639 / CWE-862 / CWE-841.**

Evidencia: `modules/Ecommerce/Routes/web.php:56`, `:63`; `modules/Ecommerce/Http/Controllers/EcommerceController.php:1010`, `:1108`, `:1133`; `app/Models/Tenant/Order.php:47`.

`transactionFinally` busca Order por `orderId` y cambia referencias del comprobante sin comprobar propietario. `applyCoupon` busca por `order_id`, consulta si el cupón es utilizable, pero no verifica propiedad del pedido; descuenta del total actual, guarda y registra uso sin comprobación de cupón ya aplicado al pedido ni transacción que haga atómica esa secuencia. Devuelve el objeto order.

Escenario: visitante con CSRF y un ID de pedido altera referencias; con un cupón aplicable puede modificar importes ajenos. La repetición puede volver a descontar cuando los límites del cupón lo permiten; no se afirma que todo cupón admita repetición.

Validación: método real `transactionFinally` actualizó un pedido simulado sin identidad. Análisis estático de la secuencia de cupones. No se ejecutaron carreras ni se alteraron pedidos reales.

Corrección: acceso basado en propietario autenticado o capacidad guest firmada y acotada; derivar referencia documental en servidor. Aplicación de cupón transaccional/idempotente, restricciones de unicidad y cálculo sobre base autorizada.

Regresión: pedido de otro cliente rechazado; repetir la misma aplicación mantiene el mismo resultado; peticiones simultáneas no duplican descuento/uso.

### [PRO9-SEC-009] Pago Izipay válido puede asociarse a un pedido diferente

**Severidad: Alta. Confianza: Alta en ausencia de vinculación; integración real no ejercitada. CWE-345 / CWE-841.**

Evidencia: `modules/Ecommerce/Routes/web.php:54`; `modules/Ecommerce/Http/Controllers/EcommerceController.php:1528`, `:1539`, `:1540`, `:1552`; `modules/Payment/Http/Controllers/PaymentGatewayController.php:281`.

El backend consulta una transacción por `uuid` y obtiene PAID. Después, el controlador ecommerce prioriza `external_id` proporcionado por el cliente sobre el orderId devuelto por la pasarela. Busca ese otro pedido y puede marcarlo pagado/emitir documento sin comparar vinculación, importe o moneda.

Requisitos: Izipay configurado y habilitado, una transacción PAID consultable con las credenciales del comercio y conocer el external_id del pedido objetivo. La creación de pagos en bolívares tiene un bloqueo explícito; eso limita la operación, pero no es una comprobación de correspondencia de transacción y pedido.

Impacto: pedido distinto considerado pagado con una transacción ajena/reutilizada. No se enviaron peticiones a Izipay ni se aseguró que esté activo en la instalación.

Corrección: localizar el pedido desde la asociación interna con la transacción, cotejar importe, moneda, comercio y estado confirmado; unicidad de identificador de pago e idempotencia. Rechazar external_id contradictorio.

Regresión: pago de pedido A no liquida B; importe/moneda incorrectos y replay rechazados; reconsulta legítima no duplica documentos.

### [PRO9-SEC-010] Totales y descuentos del navegador controlan el pedido

**Severidad: Alta. Confianza: Alta. CWE-602 / CWE-20 / CWE-840.**

Evidencia: `modules/Ecommerce/Routes/web.php:57`; `modules/Ecommerce/Http/Controllers/EcommerceController.php:1232`, `:1238`, `:1255`, `:1260`, `:3443`; `app/Models/Tenant/Order.php:18`, `:47`.

`paymentCash` usa precios de catálogo para datos de líneas mediante `applyAuthoritativeCampaignPrices`, pero persiste `total = $request->precio_culqi`. Para ciertos cupones acepta además `total_discount` del cliente. La función de precios no recalcula ese total; el hook creating de Order genera código, no valida importes.

Escenario: un comprador envía un carrito válido con un total/descuento incoherente y registra una orden con un monto distinto del catálogo. Requiere datos de cliente y condiciones normales del checkout válidos.

Impacto confirmado: integridad del importe del pedido. La pérdida monetaria/entrega depende de los pasos posteriores; no se afirma que se haya cobrado de menos en una pasarela.

Validación: seguimiento de entradas, validación de cliente, recálculo parcial, asignación y modelo; no se creó ninguna orden real.

Corrección: calcular en servidor subtotal, descuentos, impuestos, envío, moneda y total desde datos autorizados; rechazar cantidades e IDs inválidos. Usar el mismo cálculo para pedido, cobro y comprobante.

Regresión: manipular precio_culqi o total_discount no reduce el importe autorizado; líneas inexistentes y cantidades inválidas fallan; resultados coherentes con/sin cupón.

### [PRO9-SEC-011] Destinos de webhooks permiten solicitudes a redes internas

**Severidad: Alta. Confianza: Alta en flujo; alcance de red no comprobado. CWE-918.**

Evidencia: `modules/Webhook/Routes/web.php:9`; `modules/Webhook/Http/Requests/WebhookSubscriptionRequest.php:16`, `:30`; `modules/Webhook/Http/Controllers/WebhookSubscriptionController.php:83`; `modules/Webhook/Jobs/SendWebhookJob.php:61`, `:68`; `modules/Webhook/Http/Resources/WebhookDeliveryResource.php:27`.

Un usuario tenant autenticado puede guardar URL validada solo como URL. El worker realiza POST a esa dirección sin excluir loopback, redes privadas/link-local o destinos resultantes de DNS. La respuesta se guarda y es visible en el historial. Los redirects están desactivados: control positivo que no bloquea un destino interno directo.

Requisitos: suscripción activa, evento/entrega y worker operativo, destino alcanzable desde el servidor. No se presume acceso a metadata cloud concreta, protocolos arbitrarios ni endpoints que solo aceptan GET.

Impacto: exploración/acceso a servicios internos que acepten esas peticiones, exposición de respuestas o datos del webhook enviados a destinos no autorizados.

Validación: trazado configuración → almacenamiento → job → respuesta serializada. No se contactó ninguna dirección interna o externa de prueba.

Corrección: autorización para gestionar integraciones y política explícita de destinos. Restringir esquema, puerto y direcciones IPv4/IPv6; controlar DNS en el momento de conectar y egress. Si hay integraciones privadas legítimas, allowlist administrada fuera del control del usuario tenant.

Regresión: localhost, redes privadas/link-local y cambios DNS se bloquean antes de abrir conexión; destino autorizado sigue operando; redirects permanecen controlados.

### [PRO9-SEC-012] Nota de venta accesible por ID secuencial sin autorización

**Severidad: Media. Confianza: Alta. CWE-639 / CWE-200.**

Evidencia: `routes/web.php:32`, anterior al grupo auth de `:38`; `app/Http/Controllers/Tenant/SaleNoteController.php:1336`.

GET `sale-notes/ticket/{id}/{format?}` resuelve `SaleNote::find($id)` y genera la representación HTML de impresión. No verifica identidad, propietario o token. El ID interno es enumerable.

Impacto: lectura de transacciones comerciales y datos del cliente que incluya la plantilla. Alcance limitado al tenant resuelto por el dominio; no se extrapola lectura entre bases.

Validación: ruta → lookup por ID → createPdf con salida html. No se descargaron tickets reales. Las rutas que usan external_id aleatorio no se consideraron equivalentes sin analizar su contrato de compartición.

Corrección: exigir permiso o enlace firmado/capacidad acotada y expirable para impresión pública. Un ID secuencial nunca debe funcionar como secreto.

Regresión: visitante no puede imprimir por ID; usuario autorizado sí; enlaces firmados de otro tenant o caducados fallan.

### [PRO9-SEC-013] GET público de Padrón ejecuta TRUNCATE

**Severidad: Alta. Confianza: Alta. CWE-306 / CWE-862 / CWE-352.**

Evidencia: `modules/Padron/Routes/web.php:14`, `:19`; `modules/Padron/Providers/RouteServiceProvider.php:48`; `modules/Padron/Http/Controllers/PadronController.php:158`, `:162`, `:186`; `database/migrations/tenant/2026_08_17_000129_create_padrones_table.php:38`.

GET `padron/demo` se registra con grupo web, sin autenticación; vacía `padrones` en conexión tenant antes de intentar importar un archivo local. Si la carga posterior falla, el vaciado ya ocurrió. La tabla tiene migración vigente. La ruta charges_data también realiza operaciones de descarga/importación y vaciado sin auth.

Requisitos: ruta registrada en un host con tenant resuelto y permisos de base para truncar. No se presupone que funcione en el dominio central sin conexión tenant.

Validación: se registró la ruta con middleware web y se ejecutó `demo()` con DB/PDO simulados, verificando la llamada a truncate. **No se vació ninguna tabla real.**

Corrección: retirar las rutas demo de la superficie web o llevar mantenimiento a comandos autorizados; cualquier acción administrativa HTTP debe exigir autorización, método de mutación y CSRF. Evaluar recuperación/carga atómica según motor, sin confiar en rollback de TRUNCATE.

Regresión: GET/visitante no puede mutar padrón; permisos insuficientes no llegan a DB; fallos de importación no destruyen el conjunto previo.

## Dependencias vulnerables confirmadas

La confirmación se refiere a **versiones instaladas/resueltas incluidas en rangos de avisos**, no a una explotación de todas las funciones. No se ejecutó actualización automática ni audit fix.

Composer terminó con código 1 por los avisos. NPM produjo un informe JSON con vulnerabilidades; una repetición canalizada a un resumidor terminó con código 0 del resumidor, **no de aprobación de seguridad**. La primera captura NPM excedió el límite de salida; se repitió con salida resumida, timeout de 15 segundos por fetch y sin reintentos.

Fuentes contrastadas especialmente:

- Laravel 9.52.21 entra en los rangos de validación de archivos y de email. El primero exige la forma de validación wildcard descrita en el aviso; no implica que cualquier upload de Pro9 sea evadible. [Validación de archivos](https://github.com/laravel/framework/security/advisories/GHSA-78fx-h6xr-vch4), [regla email](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq).
- PhpSpreadsheet 1.30.2 está afectado por el aviso de IOFactory con nombre de archivo controlado. El importador de ítems revisado crea explícitamente un reader Xlsx; no se demostró en esa ruta el prerrequisito de RCE del aviso. [Aviso del mantenedor](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-q4q6-r8wh-5cgh).
- Axios 0.27.2 entra en el rango de URLs absolutas y posible fuga de credenciales. El uso en navegador no se debe presentar automáticamente como SSRF desde PHP. [Aviso de Axios](https://github.com/axios/axios/security/advisories/GHSA-jr5f-v2jv-69x6).
- Vite 4.5.14 tiene avisos del servidor de desarrollo. Los que requieren Windows o acceso al dev server no prueban exposición del PHP de producción. [Aviso del servidor Vite](https://github.com/vitejs/vite/security/advisories/GHSA-4w7w-66w2-5vf9).

El inventario detallado de dependencias al final conserva versiones, avisos y clasificación del proveedor. Los rangos/recomendaciones de versión deben consultarse de nuevo al remediar: aplicar solo el primer parche de un aviso antiguo puede dejar avisos posteriores sin resolver.

## Pendientes de validación

1. **Rutas temporales controladas / SSRF adicional.** `ItemController.php:507` lee temp_path en `:520` antes de validarlo en `:524`; `UploadFileHelper.php:180` tiene una secuencia equivalente. El PHP inspeccionado tiene allow_url_fopen=1. Los validadores MIME/imagen posteriores limitan divulgación/persistencia, pero no deshacen una lectura remota ya iniciada. Validar con transporte simulado y formularios completos la alcanzabilidad de cada ruta; sustituir rutas proporcionadas por cliente por tickets de upload ligados a tenant/usuario. No se declara lectura arbitraria de todo el filesystem.
2. **Serialización de usuarios.** `app/Models/Tenant/User.php:232` solo oculta remember_token. No se identificó en esta revisión un endpoint público confirmado que devuelva el hash de contraseña completo. Auditar cada retorno de User y recursos; no extrapolar la enumeración de mozos, que selecciona campos explícitos.
3. **Deserialización.** `Person.php:703` usa unserialize sin allowed_classes. El flujo principal inspeccionado excluye optional_email de fill (`PersonController.php:172`) y serializa arrays mediante `Person.php:774`. No se probó inyección de objetos por esa vía. Revisar APIs/importaciones que pudieran persistir el campo crudo.
4. **Secretos históricos.** Se detectó un literal de contraseña en `resources/js/views/tenant/documents/invoicetensu.vue:306`, cuyo componente se importa desde `resources/js/tenant-components.js:45`. No se usó ni se comprobó vigencia. Hay material privado en `app/CoreFacturalo/WS/Signed/Resources/certificate.pem:30`, asociado a código de certificado demo; no se asume que sea una clave productiva. Clasificar ambos con el propietario, retirar credenciales reales y rotarlas si corresponde. Valores omitidos deliberadamente.
5. **Configuración de producción.** Defaults: APP_DEBUG=false; sesión HttpOnly=true, Secure=false, SameSite=null; force_https=false; proxies='*'. ForceHttps también confía directamente en X-Forwarded-Proto (`ForceHttps.php:40`). Verificar terminación TLS, filtrado de headers por proxy, cookies efectivas, dominio de sesión y acceso directo al backend antes de atribuir impacto productivo.
6. **ZIP/XML/PDF/DoS.** Hay procesamiento XML y extracción ZIP en flujos históricos. No se demostró una ruta activa con entidades externas habilitadas o un ZIP controlable que escriba fuera del destino. Las alertas de parsers exigen actualización y pruebas aisladas con límites, no ensayos de agotamiento sobre este servidor.
7. **SQL/command injection.** Las búsquedas dirigidas no demostraron SQLi ni shell injection con entradas alcanzables. Las consultas whereRaw revisadas usan bindings o expresiones internas; LOAD DATA de Padrón usa rutas fijas. Esto no certifica todas las consultas del repositorio.
8. **Cobertura restante.** Revisar en staging todos los permisos por acción de POS, caja, inventario, restaurante, MobileApp y administración central, recuperación de contraseña y límites de login; no se hizo una prueba dinámica de todas las combinaciones de rol/tenant. Revisar jobs consecutivos A/B y limpieza de contexto Hyn en procesos persistentes.
9. **Esquemas/cabeceras de enlaces públicos.** Downloads usa clase dinámica derivada de model (`DownloadController.php:27`) y puede regenerar/escribir documentos durante GET (`:45`). Revisar allowlists y el contrato de compartición sin afirmar una inclusión arbitraria de PHP no demostrada.

## Controles positivos observados

- CSRF web sin exclusiones en `app/Http/Middleware/VerifyCsrfToken.php:21`; no protege endpoints API públicos ni convierte visitantes en administradores.
- Grupo API con throttle 60/min y auth:api en numerosas rutas; tokens generados con Str::random y contraseñas con bcrypt en creación de usuarios.
- Modelo tenant usa UsesTenantConnection; el middleware de recuperación resuelve host mediante consulta parametrizada. No se encontró un parámetro directo de nombre de base que permita elegir arbitrariamente una conexión.
- Webhooks salientes desactivan redirects, tienen timeout y firman el cuerpo. El job conserva website_id y cambia tenant antes de buscar la entrega.
- Webhook WhatsApp rechaza token vacío/incorrecto con hash_equals (`IncomingWebhookProcessor.php:32`) e incorpora deduplicación por message_id (`:58`), aunque la resistencia a carreras no fue probada.
- Cotizaciones públicas autenticadas sí filtran por customer_id y origen ecommerce (`QuotationStorefrontController.php:69`).
- La prueba de extensión PHP en carga de imágenes fue rechazada. El helper general revisa extensión, MIME y decodificación; no se ignoraron esos controles al evaluar archivos.
- CORS soporta credenciales=false; un '*' de origen por sí solo no se clasificó como robo de sesión.

## Plan de remediación priorizado

### Inmediato: 0–48 horas

1. Corregir o deshabilitar temporalmente el tránsito MultiUser inseguro (001/002) hasta tener autorización y ticket de canje correcto.
2. Cerrar operaciones públicas de configuración, QZ, upload y Padrón (004/005/006/013); comprobar accesos previos y rotar claves que se confirmen expuestas.
3. Aplicar autorización real a usuarios/permisos/tokens (003).
4. Impedir confirmación de pagos no asociados al pedido (009) y controlar acceso a pedidos (008).
5. Inspeccionar contenido de tienda y neutralizar XSS persistente (007) mediante una remediación autorizada.

### Corto plazo: 7–30 días

- Unificar cálculo comercial de backend (010), cupones/idempotencia (008), política de webhooks/egress (011) y acceso a impresión (012).
- Planificar actualizaciones de dependencias con matriz de compatibilidad PHP/Laravel/Vue/Hyn y pruebas de importaciones, PDF, auth y pagos. Cuatro paquetes Composer marcados abandonados requieren evaluación: doctrine/annotations, fabpot/goutte, fruitcake/laravel-cors y laravelcollective/html; abandono no equivale a explotación.
- Implementar pruebas negativas de permisos con dos tenants y tres perfiles, más pruebas de replay y concurrencia donde corresponda.
- Reemplazar paths temporales del cliente por referencias verificables.

### Mediano plazo

- Revisar todos los endpoints con políticas explícitas y documentar cuáles son deliberadamente públicos.
- Añadir auditoría de dependencias/secrets y pruebas de seguridad al CI, con excepciones justificadas y caducidad.
- Verificar TLS/proxy/sesión efectivos, restricciones de ejecución en directorios de uploads y aislamiento de jobs/cachés/storage.
- Mantener la skill creada a partir de correcciones verificadas, no solo de intenciones.

## Cobertura, pruebas y limitaciones

| Comprobación | Resultado y alcance |
|---|---|
| Registro de rutas con Router real en memoria | API upload/QZ sin auth; administración ecommerce sin auth; MultiUser y Webhook con los grupos descritos |
| Middleware RedirectModule real con usuario simulado | AJAX llega a la acción sin permiso de módulo |
| Regla upload real con imagen GIF nombrada .php | Rechazada; sospecha de RCE no confirmada |
| Misma regla con SVG y script inerte | Aceptado; sin abrirlo en navegador |
| Normalización de ruta Flysystem | Barras invertidas QZ se normalizan |
| store_configuration_paypal con modelo simulado | Permite asignar about_us sin identidad |
| transactionFinally con Order simulado | Cambia referencias de pedido sin identidad |
| QZ controller/helper con Storage simulado | Devuelve contenido privado sintético |
| startProcess MultiUser con caché/Auth simulados | Autentica por entrada de caché del dominio sin secreto de petición |
| padron/demo con DB/PDO simulados | Alcanza truncate sin identidad; cero tablas reales afectadas |
| Composer audit | 57 entradas; 56 GHSA/CVE únicos en 15 paquetes |
| NPM audit | 153 paquetes afectados; resultados resumidos al final |

La primera prueba QZ necesitó corregir el doble de filesystem del harness, tras lo cual pasó; fue un problema del montaje de prueba, no de la aplicación. Los controladores probados aisladamente no validan por sí solos toda la cadena HTTP: su evidencia se combina con las rutas, middleware y FormRequests revisados. El constructor ecommerce se omitió únicamente en la prueba del método de pedido para evitar consultas reales; fue leído y no agrega autorización.

No se ejecutó la suite completa: `tests/CreatesApplication.php:15` arranca la aplicación y `phpunit.xml` no aísla explícitamente todas las conexiones central/tenant ni servicios externos. El test existente TenantConnectionBootstrapContractTest comprueba estructura/cadenas, no aislamiento ni autorización en operación. No se presenta ese test como prueba de seguridad.

No se analizaron datos de clientes ni logs productivos, no se probó escalamiento real, no se consultaron secretos del .env y no se comprobó si alguien explotó estas fallas. La búsqueda de secretos fue heurística, no revisión íntegra del historial Git. Los paquetes generados/vendor se consultaron solo para las versiones y comprobaciones concretas.

## Anexo: comandos ejecutados

Principales comandos y familias reproducibles:

- `git status --short`, `git rev-parse HEAD`, `git branch --show-current`, `git ls-files`.
- `rg --files`, `rg -n` para rutas, middleware, permisos, sinks SQL/procesos/archivos, HTML, XML/ZIP, jobs y configuración; lecturas mediante `nl -ba` y `sed`.
- `php -v`, `composer --version` en host: PHP 7.4 y Composer no disponible.
- `docker ps --format '{{.Names}} {{.Image}}'`; inspección limitada a mounts del contenedor.
- `docker exec pro9-php php -v`; `docker exec pro9-php composer --version`.
- `docker exec -w /var/www/pro9 pro9-php composer --no-plugins --no-scripts audit --locked --format=json`.
- `npm audit --package-lock-only --ignore-scripts --json`: primera ejecución detenida tras varios minutos sin salida; reintento con `--fetch-timeout=15000 --fetch-retries=0`. Captura inicial demasiado extensa, repetida con resumidor JSON.
- Programas Node de solo lectura para versiones, inventario y detección de candidatos de secretos, con valores redactados.
- `docker exec -i -w /var/www/pro9 pro9-php php` con programas de prueba por stdin: autoload, Container/Router, Validator, Mockery y marcadores ficticios. Únicos fixtures físicos: imágenes temporales creadas y eliminadas en la misma prueba.
- Consultas de avisos públicos citados; no se usaron credenciales del proyecto.

## Skill para futuros cambios

Archivo: `.codex/skills/mantener-seguridad-pro9/SKILL.md`. Define invariantes y criterios de pruebas derivados de estos hallazgos. Su presencia orienta al agente cuando la skill se selecciona; no implementa controles en Laravel ni garantiza aplicación automática a todos los cambios.

## Anexo: inventario de dependencias y avisos

### Composer: paquetes del lockfile con avisos

| Paquete | Versión resuelta | Entradas del auditor |
|---|---|---|
| dompdf/dompdf | v2.0.8 | 6 |
| guzzlehttp/guzzle | 7.10.0 | 9 |
| guzzlehttp/psr7 | 2.9.0 | 4 |
| laravel/framework | v9.52.21 | 4 |
| league/commonmark | 2.8.2 | 10 |
| phpoffice/phpspreadsheet | 1.30.2 | 9 |
| phpseclib/phpseclib | 3.0.50 | 3 |
| setasign/fpdi | v2.6.6 | 1 |
| symfony/dom-crawler | v6.4.34 | 1 |
| symfony/http-foundation | v6.4.35 | 1 |
| symfony/mailer | v6.4.34 | 1 |
| symfony/mime | v6.4.35 | 2 |
| symfony/polyfill-intl-idn | v1.33.0 | 1 |
| symfony/routing | v6.4.34 | 2 |
| symfony/yaml | v7.4.6 | 3 |

### Composer: avisos únicos reportados

Identificadores y severidades del proveedor, sin reproducir descripciones extensas. Los rangos completos se recuperan con el comando audit y cada enlace. El aviso Laravel GHSA-5vg9-5847-vvmq llegó por dos entradas PKSA y se cuenta una vez.

| Paquete | Aviso | Severidad del proveedor |
|---|---|---|
| dompdf/dompdf | [GHSA-j8qw-6jw8-r297 / CVE-2026-59943](https://github.com/advisories/GHSA-j8qw-6jw8-r297) | medium |
| dompdf/dompdf | [GHSA-f5gf-2cj8-52g2 / CVE-2026-59942](https://github.com/advisories/GHSA-f5gf-2cj8-52g2) | medium |
| dompdf/dompdf | [GHSA-8hg6-c449-896m / CVE-2026-59941](https://github.com/advisories/GHSA-8hg6-c449-896m) | medium |
| dompdf/dompdf | [GHSA-cx96-42px-69fm / CVE-2026-56722](https://github.com/advisories/GHSA-cx96-42px-69fm) | medium |
| dompdf/dompdf | [GHSA-7x2p-4jvh-6384 / CVE-2026-55555](https://github.com/advisories/GHSA-7x2p-4jvh-6384) | low |
| dompdf/dompdf | [GHSA-wvh6-f5jh-8gw4 / CVE-2026-55554](https://github.com/advisories/GHSA-wvh6-f5jh-8gw4) | low |
| guzzlehttp/guzzle | [GHSA-v5mv-p594-2x33 / CVE-2026-69246](https://github.com/advisories/GHSA-v5mv-p594-2x33) | high |
| guzzlehttp/guzzle | [GHSA-f7vp-7xgx-4w4r / CVE-2026-69245](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r) | medium |
| guzzlehttp/guzzle | [GHSA-h95v-h523-3mw8 / CVE-2026-67354](https://github.com/advisories/GHSA-h95v-h523-3mw8) | medium |
| guzzlehttp/guzzle | [GHSA-wm3w-8rrp-j577 / CVE-2026-67355](https://github.com/advisories/GHSA-wm3w-8rrp-j577) | medium |
| guzzlehttp/guzzle | [GHSA-f283-ghqc-fg79 / CVE-2026-67353](https://github.com/advisories/GHSA-f283-ghqc-fg79) | medium |
| guzzlehttp/guzzle | [GHSA-g446-98w2-8p5w / CVE-2026-59883](https://github.com/advisories/GHSA-g446-98w2-8p5w) | medium |
| guzzlehttp/guzzle | [GHSA-94pj-82f3-465w / CVE-2026-67339](https://github.com/advisories/GHSA-94pj-82f3-465w) | medium |
| guzzlehttp/guzzle | [GHSA-cwxw-98qj-8qjx / CVE-2026-55767](https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx) | medium |
| guzzlehttp/guzzle | [GHSA-wpwq-4j6v-78m3 / CVE-2026-55568](https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3) | medium |
| guzzlehttp/psr7 | [GHSA-c2w2-prh8-qm98 / CVE-2026-59882](https://github.com/advisories/GHSA-c2w2-prh8-qm98) | medium |
| guzzlehttp/psr7 | [GHSA-vm85-hxw5-5432 / CVE-2026-55766](https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432) | medium |
| guzzlehttp/psr7 | [GHSA-hq7v-mx3g-29hw / CVE-2026-49214](https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw) | medium |
| guzzlehttp/psr7 | [GHSA-34xg-wgjx-8xph / CVE-2026-48998](https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph) | medium |
| laravel/framework | [GHSA-crmm-hgp2-wgrp](https://github.com/advisories/GHSA-crmm-hgp2-wgrp) | medium |
| laravel/framework | [GHSA-5vg9-5847-vvmq](https://github.com/advisories/GHSA-5vg9-5847-vvmq) | high |
| laravel/framework | [GHSA-78fx-h6xr-vch4 / CVE-2025-27515](https://github.com/advisories/GHSA-78fx-h6xr-vch4) | medium |
| league/commonmark | [GHSA-8rr7-cvq3-gmfh](https://github.com/advisories/GHSA-8rr7-cvq3-gmfh) | high |
| league/commonmark | [GHSA-jjv6-8j6v-6j52](https://github.com/advisories/GHSA-jjv6-8j6v-6j52) | high |
| league/commonmark | [GHSA-f8fg-pg57-v4j8](https://github.com/advisories/GHSA-f8fg-pg57-v4j8) | high |
| league/commonmark | [GHSA-j8pm-gj4c-rq4x](https://github.com/advisories/GHSA-j8pm-gj4c-rq4x) | high |
| league/commonmark | [GHSA-mj63-m3rc-8ppr](https://github.com/advisories/GHSA-mj63-m3rc-8ppr) | medium |
| league/commonmark | [GHSA-mh25-x5hq-wrqp](https://github.com/advisories/GHSA-mh25-x5hq-wrqp) | high |
| league/commonmark | [GHSA-jfm3-95jq-q3rf](https://github.com/advisories/GHSA-jfm3-95jq-q3rf) | high |
| league/commonmark | [GHSA-g2gp-3wwq-f4ph](https://github.com/advisories/GHSA-g2gp-3wwq-f4ph) | high |
| league/commonmark | [GHSA-2q4p-g7hv-5rgv / CVE-2026-71488](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv) | high |
| league/commonmark | [GHSA-29pj-957v-52mc / CVE-2026-71478](https://github.com/advisories/GHSA-29pj-957v-52mc) | medium |
| phpoffice/phpspreadsheet | [GHSA-xh5m-36r6-47m3 / CVE-2026-59933](https://github.com/advisories/GHSA-xh5m-36r6-47m3) | high |
| phpoffice/phpspreadsheet | [GHSA-2mrg-gjxq-2gvr / CVE-2026-59932](https://github.com/advisories/GHSA-2mrg-gjxq-2gvr) | high |
| phpoffice/phpspreadsheet | [GHSA-6hq5-7373-42rg / CVE-2026-59931](https://github.com/advisories/GHSA-6hq5-7373-42rg) | high |
| phpoffice/phpspreadsheet | [GHSA-87m4-826x-3crx / CVE-2026-45034](https://github.com/advisories/GHSA-87m4-826x-3crx) | critical |
| phpoffice/phpspreadsheet | [GHSA-7c6m-4442-2x6m / CVE-2026-40902](https://github.com/advisories/GHSA-7c6m-4442-2x6m) | high |
| phpoffice/phpspreadsheet | [GHSA-84wq-86v6-x5j6 / CVE-2026-40863](https://github.com/advisories/GHSA-84wq-86v6-x5j6) | high |
| phpoffice/phpspreadsheet | [GHSA-q4q6-r8wh-5cgh / CVE-2026-34084](https://github.com/advisories/GHSA-q4q6-r8wh-5cgh) | critical |
| phpoffice/phpspreadsheet | [GHSA-hrmw-qprp-wgmc / CVE-2026-40296](https://github.com/advisories/GHSA-hrmw-qprp-wgmc) | medium |
| phpoffice/phpspreadsheet | [GHSA-6wpp-88cp-7q68 / CVE-2026-35453](https://github.com/advisories/GHSA-6wpp-88cp-7q68) | medium |
| phpseclib/phpseclib | [GHSA-m557-wrgg-6rp4 / CVE-2026-55599](https://github.com/advisories/GHSA-m557-wrgg-6rp4) | medium |
| phpseclib/phpseclib | [GHSA-3qpq-r242-jqj7 / CVE-2026-44167](https://github.com/advisories/GHSA-3qpq-r242-jqj7) | high |
| phpseclib/phpseclib | [GHSA-r854-jrxh-36qx / CVE-2026-40194](https://github.com/advisories/GHSA-r854-jrxh-36qx) | low |
| setasign/fpdi | [GHSA-2mgw-7q6p-8grg / CVE-2026-45802](https://github.com/advisories/GHSA-2mgw-7q6p-8grg) | medium |
| symfony/dom-crawler | [CVE-2026-45071 / CVE-2026-45071](https://symfony.com/cve-2026-45071) | low |
| symfony/http-foundation | [CVE-2026-48736 / CVE-2026-48736](https://symfony.com/cve-2026-48736) | medium |
| symfony/mailer | [CVE-2026-45068 / CVE-2026-45068](https://symfony.com/cve-2026-45068) | medium |
| symfony/mime | [CVE-2026-45070 / CVE-2026-45070](https://symfony.com/cve-2026-45070) | medium |
| symfony/mime | [CVE-2026-45067 / CVE-2026-45067](https://symfony.com/cve-2026-45067) | high |
| symfony/polyfill-intl-idn | [CVE-2026-46644 / CVE-2026-46644](https://symfony.com/cve-2026-46644) | low |
| symfony/routing | [CVE-2026-48784 / CVE-2026-48784](https://symfony.com/cve-2026-48784) | medium |
| symfony/routing | [CVE-2026-45065 / CVE-2026-45065](https://symfony.com/cve-2026-45065) | medium |
| symfony/yaml | [CVE-2026-45304 / CVE-2026-45304](https://symfony.com/cve-2026-45304) | low |
| symfony/yaml | [CVE-2026-45305 / CVE-2026-45305](https://symfony.com/cve-2026-45305) | low |
| symfony/yaml | [CVE-2026-45133 / CVE-2026-45133](https://symfony.com/cve-2026-45133) | low |

### NPM: paquetes afectados, incluida propagación transitiva

No sumar estos paquetes a los hallazgos de aplicación. La severidad es la que devuelve NPM para cada paquete, incluidas sus dependencias afectadas; no es una clasificación del uso concreto en Pro9.

- **critical (5):** `form-data`, `request`, `shell-quote`, `tar`, `websocket-driver`.
- **high (32):** `@babel/plugin-transform-modules-systemjs`, `@mapbox/node-pre-gyp`, `axios`, `brace-expansion`, `braces`, `browserslist`, `canvas`, `exec-sh`, `express`, `extract-zip`, `fast-uri`, `glob`, `immutable`, `js-yaml`, `lodash`, `lodash-es`, `merge`, `micromatch`, `minimatch`, `nanoid`, `ngrok`, `node-forge`, `path-to-regexp`, `picomatch`, `postcss`, `rollup`, `serialize-javascript`, `socket.io-parser`, `svgo`, `vite`, `watch`, `ws`.
- **moderate (97):** `@ckeditor/ckeditor5-adapter-ckfinder`, `@ckeditor/ckeditor5-alignment`, `@ckeditor/ckeditor5-autoformat`, `@ckeditor/ckeditor5-autosave`, `@ckeditor/ckeditor5-basic-styles`, `@ckeditor/ckeditor5-block-quote`, `@ckeditor/ckeditor5-bookmark`, `@ckeditor/ckeditor5-build-classic`, `@ckeditor/ckeditor5-ckbox`, `@ckeditor/ckeditor5-ckfinder`, `@ckeditor/ckeditor5-clipboard`, `@ckeditor/ckeditor5-cloud-services`, `@ckeditor/ckeditor5-code-block`, `@ckeditor/ckeditor5-core`, `@ckeditor/ckeditor5-easy-image`, `@ckeditor/ckeditor5-editor-balloon`, `@ckeditor/ckeditor5-editor-classic`, `@ckeditor/ckeditor5-editor-decoupled`, `@ckeditor/ckeditor5-editor-inline`, `@ckeditor/ckeditor5-editor-multi-root`, `@ckeditor/ckeditor5-emoji`, `@ckeditor/ckeditor5-engine`, `@ckeditor/ckeditor5-enter`, `@ckeditor/ckeditor5-essentials`, `@ckeditor/ckeditor5-find-and-replace`, `@ckeditor/ckeditor5-font`, `@ckeditor/ckeditor5-heading`, `@ckeditor/ckeditor5-highlight`, `@ckeditor/ckeditor5-horizontal-line`, `@ckeditor/ckeditor5-html-embed`, `@ckeditor/ckeditor5-html-support`, `@ckeditor/ckeditor5-image`, `@ckeditor/ckeditor5-indent`, `@ckeditor/ckeditor5-language`, `@ckeditor/ckeditor5-link`, `@ckeditor/ckeditor5-list`, `@ckeditor/ckeditor5-markdown-gfm`, `@ckeditor/ckeditor5-media-embed`, `@ckeditor/ckeditor5-mention`, `@ckeditor/ckeditor5-minimap`, `@ckeditor/ckeditor5-page-break`, `@ckeditor/ckeditor5-paragraph`, `@ckeditor/ckeditor5-paste-from-office`, `@ckeditor/ckeditor5-remove-format`, `@ckeditor/ckeditor5-restricted-editing`, `@ckeditor/ckeditor5-select-all`, `@ckeditor/ckeditor5-show-blocks`, `@ckeditor/ckeditor5-source-editing`, `@ckeditor/ckeditor5-special-characters`, `@ckeditor/ckeditor5-style`, `@ckeditor/ckeditor5-table`, `@ckeditor/ckeditor5-theme-lark`, `@ckeditor/ckeditor5-typing`, `@ckeditor/ckeditor5-ui`, `@ckeditor/ckeditor5-undo`, `@ckeditor/ckeditor5-upload`, `@ckeditor/ckeditor5-utils`, `@ckeditor/ckeditor5-watchdog`, `@ckeditor/ckeditor5-widget`, `@ckeditor/ckeditor5-word-count`, `@vue/cli-plugin-babel`, `@vue/cli-plugin-router`, `@vue/cli-plugin-vuex`, `@vue/cli-service`, `@vue/component-compiler-utils`, `@xmldom/xmldom`, `ajv`, `anymatch`, `bn.js`, `body-parser`, `chokidar`, `ckeditor5`, `copy-webpack-plugin`, `css-minimizer-webpack-plugin`, `decode-uri-component`, `engine.io-client`, `esbuild`, `follow-redirects`, `http-proxy-middleware`, `joi`, `launch-editor`, `mocha`, `qs`, `query-string`, `readdirp`, `sockjs`, `source-map-resolve`, `terser-webpack-plugin`, `tough-cookie`, `uuid`, `vue-keypress`, `vue-loader`, `vue-template-compiler`, `wd`, `webpack-dev-server`, `xml2js`, `yaml`.
- **low (19):** `@babel/core`, `@riophae/vue-treeselect`, `@vitejs/plugin-vue2`, `browserify-sign`, `create-ecdh`, `crypto-browserify`, `diff`, `element-ui`, `elliptic`, `laravel-vite-plugin`, `postcss-selector-parser`, `vue`, `vue-apexcharts`, `vue-ckeditor5`, `vue-content-loading`, `vue-data-tables`, `vue-wysiwyg`, `vuex`, `webpack`.

### NPM: muestra trazable de dependencias directas revisadas

| Paquete | Versión del lockfile | Aviso representativo | Contexto a verificar |
|---|---|---|---|
| axios | 0.27.2 | [GHSA-jr5f-v2jv-69x6](https://github.com/axios/axios/security/advisories/GHSA-jr5f-v2jv-69x6) | URL absoluta controlable y credenciales anexadas; distinguir navegador/Node |
| vite | 4.5.14 | [GHSA-4w7w-66w2-5vf9](https://github.com/vitejs/vite/security/advisories/GHSA-4w7w-66w2-5vf9) | Servidor de desarrollo y acceso a rutas de sourcemaps |
| xml2js | 0.4.23 | [GHSA-776f-qx25-q3cc](https://github.com/advisories/GHSA-776f-qx25-q3cc) | Parseo de XML no confiable; rango reportado menor que 0.5.0 |
| vue | 2.7.16 | [GHSA-5j4c-8p2g-v4jx](https://github.com/advisories/GHSA-5j4c-8p2g-v4jx) | Entrada no confiable al compilador/parseHTML |
| lodash | 4.17.21 | [GHSA-r5fr-rjxr-66jc](https://github.com/advisories/GHSA-r5fr-rjxr-66jc) | Uso de template/imports bajo control externo |

NPM también reportó otros avisos para estos paquetes. La muestra no sustituye volver a ejecutar la auditoría completa al actualizar.
