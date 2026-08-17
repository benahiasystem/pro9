---
name: migrar-venezuela-telefonia
description: Adaptar prefijos telefónicos, enlaces tel y envíos de WhatsApp a Venezuela. Usar al modificar +51, +58, wa.me, customer_telephone, wsPhone, QR API, ecommerce, restaurante o normalización telefónica en Pro9.
---

# Migrar telefonía venezolana

## Flujo

1. Buscar `+51`, `wa.me/51`, payloads con `51` y teléfonos codificados en fuentes activas.
2. No modificar medidas de impresión, nombres como `ticket_51` ni números no telefónicos.
3. Centralizar la normalización PHP en `Localization::normalizePhone`; usar lógica equivalente en JavaScript donde no se pueda invocar el helper.
4. Mostrar `+58` y normalizar antes de construir enlaces o payloads.
5. Eliminar caracteres no numéricos y retirar un único prefijo histórico 58 o 51 antes de anteponer 58; evitar `5858...`.
6. Enlaces `tel:` y `wa.me` deben usar el teléfono configurado, no abonados de ejemplo codificados.
7. Enviar `+58...` desde PHP cuando el consumidor acepte formato internacional; enviar `58...` sin `+` en URLs y payloads QR/WhatsApp.
8. Codificar el texto de WhatsApp con `encodeURIComponent`.
9. Aplicar el contrato en documentos, guías, cotizaciones, notas de venta, pedidos, POS, ecommerce, restaurante, QR API, QrChatBuho y pantallas del sistema.
10. Delimitar cada modificación de código con comentarios válidos que contengan `########### INICIO` y `########### FIN`.

## Validación

- Probar números locales, +58 existente, +51 histórico, caracteres de formato, vacío y doble prefijo.
- Auditar que no queden prefijos peruanos en fuentes activas.
- Verificar enlaces `tel:`, `wa.me`, texto codificado y payloads QR/WhatsApp.
- Excluir coincidencias no telefónicas como dimensiones, nombres de plantillas, respaldos o códigos internos.
