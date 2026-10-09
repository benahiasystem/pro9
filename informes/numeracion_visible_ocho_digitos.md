# Numeración visible con ocho dígitos

Los documentos internos de Pro9 presentan sus números con un mínimo de ocho dígitos: `25` → `00000025`, `FF01-25` → `FF01-00000025`. Se conserva la serie, el prefijo y cualquier número mayor de ocho dígitos. Vacíos y provisionales no se rellenan.

La presentación se centraliza en FiscalIdentity y en el helper frontend document-number. Se aplica a facturas/notas, entregas, notas de venta, cotizaciones, pedidos, contratos, órdenes de compra, internos de inventario, servicios técnicos, liquidaciones, ingresos y enlaces de pago; incluye encabezados PDF, referencias, pantallas, correos y reportes.

No hay migraciones, renumeración ni regeneración masiva. Los correlativos y sus bloqueos, nombres de archivo, controles y payloads/consultas HKA conservan sus valores. Los PDF originales HKA y PDF locales ya guardados no se reescriben. Las compras y comprobantes externos mantienen los números recibidos.

## Verificación

Se ejecutan pruebas de presentación PHP/JS, identidad, referencias con anticipos por sucursal, numeración, renderizado PDF y descargas HKA. La compilación frontend corresponde al usuario conforme a frontend-build. Se registra por separado la comprobación visual y sus límites.

Resultados: 121 pruebas PHP / 1242 aserciones y 27 pruebas JavaScript aprobadas. Incluyen correlativos, anticipos con referencias rellenadas, límites del formato, documentos externos, renderizado, descargas y conservación explícita de los números del payload HKA. Sintaxis PHP y diff sin errores.

Verificación visual en el tenant bbc: listado y diálogo de la factura existente 25 muestran `00000025`; la factura con serie muestra `FF01-00000013`. El ticket 80MM descargado muestra `N° de documento: 00000025`, conserva control y QR y no presenta recortes. No se emitieron documentos ni se enviaron correos. Evidencias: `/tmp/pro9-numeracion-ocho-digitos-listado.png`, `/tmp/pro9-numeracion-ocho-digitos.png` y `/tmp/pro9-ticket-ocho-digitos.png`. El watcher del usuario produjo los assets y se verificó una recarga con el bundle correspondiente al manifiesto; no se ejecutó compilación desde el agente. La vista previa se cubrió mediante las pruebas automáticas; no se generó una venta para verificarla.
