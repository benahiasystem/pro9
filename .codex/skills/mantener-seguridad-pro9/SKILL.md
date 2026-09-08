---
name: mantener-seguridad-pro9
description: Mantener los controles de seguridad de Pro9 al crear o modificar autorización tenant, MultiUser, administración ecommerce, pedidos y pagos, archivos públicos, impresión y webhooks. Usar también para remediar o revisar los hallazgos de la auditoría de seguridad de Pro9.
---

# Seguridad de Pro9 en cambios futuros

## Estado y uso

La auditoría base está en [informes/auditoria_seguridad_pro9.md](../../../informes/auditoria_seguridad_pro9.md), con fecha 2026-09-08 y hallazgos PRO9-SEC-001 a PRO9-SEC-013. Lee las secciones vinculadas al flujo que vas a cambiar. **Son vulnerabilidades detectadas, no correcciones implementadas.** Comprueba el estado actual antes de dar cualquiera por resuelta.

Esta skill establece requisitos para los cambios autorizados en Pro9. No autoriza corregir componentes ajenos, migrar datos, rotar claves ni desactivar integraciones por iniciativa propia. Si una revisión detecta un problema fuera del cambio solicitado, documéntalo. Para cambios de negocio ordinarios, aplica los invariantes relevantes sin convertir toda tarea en una auditoría integral.

## Resolver identidad y autorización en servidor — 001, 003, 004, 008, 012, 013

- Distingue visitante, cliente del guard ecommerce, usuario tenant y administrador del guard admin. Tener ecommerce habilitado en la empresa no autentica ni autoriza al visitante.
- Resuelve el tenant antes de autenticar/consultar modelos tenant. Un ID válido solo identifica un registro; no prueba pertenencia ni permiso.
- Revisa el grupo de rutas, middleware de controlador, FormRequest y políticas de la acción. Las rutas de modules se registran desde sus proveedores; comprobar únicamente routes/web.php deja superficie sin revisar.
- Autoriza cada consulta/mutación sensible en backend. No uses X-Requested-With, request->ajax(), visibilidad de menú o permisos del primer administrador como condición para saltar autorización.
- Separa el cambio de perfil propio de administrar usuarios/roles. No admitas type, permisos, modules/levels o ID de otra cuenta mediante un formulario de perfil.
- No devuelvas api_token, password, hashes o claves privadas en recursos generales ni en respuestas de permiso denegado. UserResource y la respuesta inicial de regenerateToken son puntos históricos que requieren atención.
- Las operaciones administrativas del ecommerce deben exigir identidad administrativa tenant, incluso si comparten prefijo con la tienda pública. Usa campos validados y allowlist propia de cada operación; el fillable de un modelo no reemplaza autorización por campo.
- Pedidos/notas/archivos se resuelven desde el conjunto permitido al solicitante. Para guests legítimos, usa una capacidad específica y verificable, no un ID secuencial ni el guard de empleados.
- Mantén CSRF en mutaciones de sesión. Ningún GET debe vaciar tablas, cambiar permisos, procesar pagos o ejecutar mantenimiento. Padrón demo/charges_data son antecedentes concretos.
- Conserva los usos deliberadamente públicos de catálogos y enlaces compartidos, con datos mínimos y autorización por capacidad cuando corresponda; no cierres toda la tienda por exigir auth indiscriminadamente.

## Tránsito MultiUser y aislamiento — 001, 002

Puntos históricos: MultiUserController::changeClient, AutoLoginHelper::getMultiUser/saveLoginRequest/startProcess, rutas multi-users y auto-login.

- La asociación central debe pertenecer al par usuario/tenant solicitante y permitir la dirección elegida. El filtro del listado no protege un POST independiente.
- Antes del canje verifica estado de cuentas, vínculo vigente y destino autorizado.
- Usa un ticket criptográficamente aleatorio, corto en vigencia, de un solo uso y ligado a identidad, origen y destino; exige que la petición lo presente.
- Consume el ticket de forma atómica. No uses una única entrada auto_login_{fqdn} como credencial compartida entre visitantes; has/get/forget separados no garantizan exclusión.
- Evita reutilización de contexto en jobs/procesos persistentes: establecer tenant explícito al trabajar y restaurar/limpiar al salir según el ciclo de vida del framework. No reutilices IDs tenant en modelos centrales sin relación validada.

## Pedidos, precios y pagos — 008, 009, 010

- El servidor calcula total, impuestos, descuentos, envío y moneda desde catálogo y reglas autorizadas. Recalcular precios de líneas sin recalcular total sigue dejando control al navegador.
- No uses precio_culqi, total_discount ni otro total del cliente como importe autorizado. El formulario puede enviarlos para comparación, nunca para decidir el cobro.
- Valida existencia, disponibilidad y cantidades de todas las líneas; no devuelvas sin validar una línea cuyo producto no exista.
- Verifica propietario o capacidad guest antes de aplicar cupones o cambiar referencias documentales.
- Aplicación de descuento y registro de uso deben ser atómicos e idempotentes por pedido/cupón. Restringe repeticiones/concurrencia en la base cuando corresponda.
- PAID por sí solo no vincula un pago a una orden: coteja identificador de transacción, pedido, comercio, importe y moneda contra información del proveedor y la asociación del servidor.
- No permitas que external_id del request reemplace orderId confirmado por la pasarela. Vincula cada transacción una sola vez y haz la generación del documento idempotente.
- Respeta las restricciones vigentes de pasarelas/monedas de Pro9; una corrección de seguridad no debe habilitar una pasarela que la operación local tiene deshabilitada.
- Prueba pagos con dobles o sandbox configurado, nunca cobrando para demostrar una falla.

## Claves, uploads, HTML y documentos — 005, 006, 007, 012

- Mantén las claves privadas QZ en servidor. El certificado público es compartible; el material privado no debe entregarse al navegador. Un servicio de firma necesita autorización y alcance de mensaje, no firma arbitraria.
- Las cargas administrativas requieren permiso. Aísla almacenamiento por tenant y genera nombres en backend: public/defaults/default_image.* compartido permite sobrescritura entre empresas.
- Decodifica/recodifica imágenes raster y valida extensión/MIME/tamaño. image/* admite SVG; no lo trates como prueba de ausencia de scripts.
- Para SVG o HTML enriquecido requerido por negocio, usa sanitización especializada con allowlist y entrega segura. Un SVG en img y una navegación directa tienen comportamientos distintos; prueba el contexto real.
- Escapa textos simples en Blade/Vue. Para about_us, términos, privacidad y contenido rico, sanitiza antes de confiar en {!! !!} o v-html. El editor visual no es el límite de confianza.
- No leas temp_path suministrado por cliente como URL o ruta libre. Usa referencias de uploads emitidas por el servidor, ligadas a tenant/usuario, con caducidad y directorio permitido. Validar MIME después de file_get_contents no revierte una SSRF ya realizada.
- Para impresión/descarga pública usa un contrato de acceso deliberado, preferentemente capacidad firmada/expirable; mantén autorización y separación por tenant. No uses el ID numérico como secreto.
- La auditoría rechazó una imagen .php bajo la regla probada: no afirmar RCE por upload sin demostrar validación, persistencia y ejecución efectivas.

## Webhooks y red — 011

- Autoriza administrar suscripciones y consultar entregas.
- Valida el destino completo en el momento de conexión: esquema, puerto, resolución DNS y direcciones IPv4/IPv6. Una regla url no bloquea SSRF.
- Bloquea destinos internos/link-local/loopback salvo allowlist explícita de integración privada administrada fuera del control del usuario tenant. Complementa con restricciones de salida.
- Evita bypass por redirects o cambio DNS; conserva timeout y límite de respuesta. Actualmente SendWebhookJob desactiva redirects y firma el cuerpo: preserva ambos controles.
- No expongas secretos en logs de entregas. Conserva el tenant explícito del job antes de resolver IDs de suscripción/entrega.
- Para webhooks entrantes, verifica autenticidad antes de procesar, evita replay y duplicados; las verificaciones hash_equals existentes no deben desaparecer al refactorizar.

## Dependencias y evidencia

- Evalúa versiones resueltas del lockfile e instaladas, no solo restricciones del manifiesto. Consulta avisos actuales al remediar.
- Distingue paquete afectado de explotación alcanzable. Los avisos de Vite dev server/Windows no prueban compromiso de PHP; avisos de PhpSpreadsheet IOFactory requieren comprobar control de entrada y reader.
- No uses audit fix forzado ni actualizaciones masivas como sustituto de evaluar compatibilidad Laravel/Hyn/Vue, importación, PDF y pagos.
- No pegues valores secretos en informes, pruebas o prompts. Usa marcadores y documenta únicamente ubicación/tipo cuando sean necesarios.

## Verificación de los cambios

Escoge pruebas de comportamiento proporcionales al flujo modificado. Casos base para autorización: visitante, cliente ecommerce, vendedor y administrador; dos tenants con IDs locales coincidentes; petición normal y AJAX. Para tickets/pagos/cupones agrega caducidad, replay y concurrencia cuando el cambio afecte esos contratos.

Un rechazo debe ocurrir **antes** de guardar, firmar, autenticar o abrir una conexión de red. Comprueba que la operación legítima sigue funcionando. No declares corregido un problema solo por esconder un botón, añadir una palabra al middleware o pasar un test que busca texto en archivos.

Antes de pruebas Laravel con DB, verifica aislamiento de conexiones central/tenant, queues, mail y red: tests/CreatesApplication.php arranca la aplicación. Se pueden validar componentes con Container/Router y dobles en memoria, dejando explícito que no sustituyen una prueba HTTP integral.

Al finalizar una remediación informa el hallazgo abordado, rutas y controles cambiados, pruebas ejecutadas y limitaciones. Marca un hallazgo como resuelto en el informe solo después de verificar su flujo completo; conserva los que sigan pendientes.

