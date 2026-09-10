---
name: migrar-venezuela-telefonia
description: Adaptar prefijos telefónicos, enlaces tel y envíos de WhatsApp a Venezuela. Usar al modificar +51, +58, wa.me, customer_telephone, wsPhone, QR API, ecommerce, restaurante o normalización telefónica en Pro9.
---

# Migrar telefonía venezolana

## Flujo

1. Buscar `+51`, `wa.me/51`, payloads con `51` y teléfonos codificados en fuentes activas.
2. No modificar medidas de impresión, nombres como `ticket_51` ni números no telefónicos.
3. Centralizar PHP en `Localization::normalizePhone` y JavaScript en `resources/js/helpers/phone.js::whatsappNumber`. Mantener ambos contratos alineados.
4. Mostrar `+58` y normalizar antes de construir enlaces o payloads.
5. Conservar un prefijo 58 existente. Para un número local, quitar caracteres de formato y ceros iniciales antes de anteponer 58. Rechazar números internacionales explícitos con otro prefijo (por ejemplo +51), vacíos y valores sin dígitos útiles. No convertir un prefijo extranjero a Venezuela.
6. Enlaces `tel:` y `wa.me` deben usar el teléfono configurado, no abonados de ejemplo codificados.
7. Enviar `+58...` desde PHP cuando el consumidor acepte formato internacional; enviar `58...` sin `+` en URLs y payloads QR/WhatsApp.
8. Codificar el texto de WhatsApp con `encodeURIComponent`.
9. Aplicar el contrato en documentos, guías, cotizaciones, notas de venta, pedidos, POS, ecommerce, restaurante, QR API, QrChatBuho y pantallas del sistema.
10. Delimitar cada modificación de código con comentarios válidos que contengan `########### INICIO` y `########### FIN`.

## Validación

- Probar números locales, +58 existente, rechazo de +51/+1 explícitos, caracteres de formato, vacío y ceros. Verificar que un 58 existente no se duplique.
- Auditar que no queden prefijos peruanos en fuentes activas.
- Verificar enlaces `tel:`, `wa.me`, texto codificado y payloads QR/WhatsApp.
- Excluir coincidencias no telefónicas como dimensiones, nombres de plantillas, respaldos o códigos internos.

## Instalación nueva

- Los enlaces de documentos, Notas de venta, cotizaciones, órdenes, POS, pagos y el componente de envío usan `whatsappNumber`, con resultado completo `58...`. Mostrar un error antes de abrir una ventana si devuelve null.
- QrApi y QrChatBuho validan el teléfono antes de descargar el PDF o iniciar un POST. Sus payloads usan el mismo helper; no concatenar otro 58. La validación no debe dejar activo `loading_submit`.
- Ejecutar `node --test tests/js/phone.test.cjs` y `VenezuelaPhoneLocalizationTest`/`VenezuelaLocalizationTest`. Validar las plantillas y scripts Vue sin generar assets, conforme a `frontend-build`.
