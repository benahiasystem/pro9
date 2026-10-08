---
name: distribuir-documentos-hka
description: Mantener el envío y rastreo de correo fiscal HKA desde ventas web, listado, POS y Garage de Pro9; consultar contratos de descarga y agrupación todavía no integrados. Usar para HkaMail, los diálogos de correo, Correo/Enviar, Correo/Rastreo o futura descarga y distribución por orden.
---

# Distribución de documentos HKA

Leer [referencia HKA, secciones 3, 4.3, 6 y 10–12](../../../informes/imprenta_digital_hka_api.md), [operación fiscal local](../mantener-operacion-local-fiscal-pro9/SKILL.md) y [seguridad Pro9](../mantener-seguridad-pro9/SKILL.md).

- Resolver serie, tipo y número desde el payload fiscal congelado, sin reservas anticipadas ni catálogo mutable. Para `tipoDocumento` externo usar `01→01`, `07→02`, `08→03`, `09→04` según el significado; los códigos HKA no reemplazan los internos ni afectan numeración. Verificar autorización dentro del tenant y sucursal antes de enviar o consultar.
- `Correo/Enviar` envía el documento, `Correo/Rastreo` consulta cada correo y `Correo/EnviaOrden`/`RastreoOrden` hacen lo propio con una orden consolidada. Swagger define `rastreos[]` con `messageId`, `correo`, `status`, `fecha`; `orden` admite hasta 20 caracteres de un conjunto restringido. `DescargaArchivo` acepta `tipoArchivo` PDF/XML/JSON y responde `archivo` string: no inferir que es Base64 sin probarlo. Solicitar XML HKA no restaura el flujo XML fiscal peruano de Pro9.
- Separar estado de emisión, generación/descarga del PDF y entrega del correo; un éxito de una operación no demuestra éxito de las otras. Evitar duplicar envíos en reintentos inciertos y no divulgar datos del comprador a otro tenant.

## Correo implementado

- `HkaMail` aplica a facturas `01` digitales: exige documento vigente, emisión confirmada, control, DEMO y correspondencia de RIF/configuración con la identidad conservada. HKA envía su PDF fiscal. Producción queda bloqueada; no hacer fallback automático a SMTP. Los demás documentos conservan correo comercial.
- Reutilizar `POST documents/email`, con `id`, `customer_email`, UUID `request_id` y `resend` explícito. Validar destinatarios en servidor. Devolver sólo `email_delivery` saneado: proveedor, estado, mensaje, código, intento, rastreo y acciones permitidas; no JWT, payload ni respuesta completa del proveedor.
- `document_emissions.response.mail` conserva intentos y destinatarios, independiente del estado fiscal, sin nuevas tablas. Reclamar cada intento atómicamente con bloqueos empresa → documento → emisión antes del HTTP, ejecutado fuera de transacciones. Repetir UUID no transmite otra vez; no reutilizarlo para otros destinatarios.
- Éxito significa «Solicitud de correo aceptada por HKA». Rechazo definitivo permite un nuevo intento; timeout/respuesta incompleta queda «Por verificar», sin reenvío automático. Un pendiente interrumpido se recupera mediante consulta después del umbral de 50 segundos; no permitir otra solicitud mientras siga pendiente/incierta.
- `POST documents/{document}/query-hka-email` consulta `Correo/Rastreo` y filtra por destinatarios del intento. Conservar la referencia previa de `messageId` para no conciliar un intento nuevo con correo antiguo. Un rastreo vacío o no atribuible no acredita ausencia ni entrega. Mostrar «Entregado» sólo cuando el evento del proveedor permita acreditarlo.
- Consultas y actualizaciones fiscales deben conservar `response.mail`; correo y rastreo no alteran venta, pagos, inventario, control ni payload congelado.

## Diálogos y verificación

- Ventas web/generación/listado usan `documents/partials/options.vue`; POS y Venta rápida (`/pos/garage`) usan `pos/partials/options.vue`. Ambos comparten `resources/js/mixins/document-email.js`; no duplicar el envío entre entradas.
- Bloquear correo hasta confirmación y mostrar el motivo. Deshabilitar durante HTTP, conservar UUID ante pérdida de respuesta y liberar el botón también si falla su generación. Después de aceptación, otro envío exige «Enviar de nuevo». Fallos mantienen abierto el comprobante y explican que la venta sigue guardada.
- Admitir validaciones AJAX planas de Pro9 y respuestas envueltas en `errors`; mapear `recipients.*` al campo de correo y mostrar errores ajenos a ese campo. La configuración opcional y el foco antes de montar el formulario no deben impedir abrir/enviar el comprobante. El envío comercial automático se omite para facturas digitales: solicitar el correo fiscal desde el diálogo.
- Ejecutar `HkaMailTest`, `HkaTransportTest`, persistencia/concurrencia de `FiscalEmissionSchemaTest` en MySQL temporal y `tests/js/hka-mail-ui.test.cjs`/`pos-hka-email.test.cjs`. Probar ambas entradas, UUID/doble clic, bloqueo, aceptación/rechazo, fallos de red/autenticación, validaciones planas, recuperación, rastreo y conservación del estado fiscal.
- Aplicar [frontend-build](../frontend-build/SKILL.md). Verificar el clic en la ventana afectada con assets actualizados; aceptación desde otro diálogo o tests de métodos no reemplazan esa comprobación. Mantener resultados y limitaciones en [el informe HKA, sección 13](../../../informes/imprenta_digital_hka_api.md): la prueba real de correo de Garage nº 14 continúa pendiente de autorización, no declararla completada por la aceptación del correo nº 12 desde el listado. Los números y destinatarios de evidencia no constituyen autorización para nuevos envíos.
