---
name: mantener-facturas-notas-venta-sin-boleta
description: Mantener en Pro9 la política de nuevas ventas con Factura y Nota de venta, sin emitir nuevas Boletas. Usar al cambiar tipos de comprobante, series, POS, pedidos, Hotel, ecommerce, restaurante, WhatsApp, servicio técnico, conversiones, importaciones o configuración de documentos, preservando consulta, impresión y auditoría de Boletas históricas.
---

# Mantener Facturas y Notas de venta sin Boleta

## Objetivo

Aplicar un único contrato funcional en todos los canales de venta: permitir crear Facturas (`01`) y Notas de venta (`80` o el alias técnico `nv`), impedir nuevas Boletas (`03`) y conservar intacto el acceso a documentos históricos.

## Procedimiento

1. Leer [references/contrato.md](references/contrato.md) antes de modificar un flujo de emisión, conversión o series.
2. Identificar si el código crea documentos nuevos o solamente consulta documentos existentes.
3. En creación, validar el tipo en el servidor antes de persistir o ejecutar efectos laterales. Reutilizar `App\Services\SalesDocumentTypePolicy`; no confiar únicamente en filtros de interfaz.
4. Restringir cada selector y endpoint al subconjunto permitido por su flujo. Usar `01` y `80` en ventas; usar `01` y `nv` únicamente donde el servicio técnico mantenga ese alias.
5. Mantener la lectura, listado, PDF, envío, estado y auditoría de Boletas históricas (`03`). No borrar catálogos ni reinterpretar registros existentes.
6. Evitar nuevas series `BB`, `BC` y `BD`; mantener sus registros históricos. Los tenants nuevos reciben `FF`, `FC`, `FD` y `NV`.
7. Encerrar los cambios funcionales con los marcadores de la tarjeta correspondiente indicados en el contrato.
8. Si se cambia Vue o JavaScript empaquetado, seguir el skill `frontend-build`; no editar `public/build/` a mano.
9. Ejecutar las pruebas unitarias del contrato, análisis de sintaxis y búsquedas de regresión antes de entregar.
10. Para una Nota de venta creada desde Hotel (`source_module=HOTEL`), el resumen de productos de Hotel no muestra IVA como total separado; conserva Subtotal y Total. Esta es una regla de presentación exclusiva de Hotel y no autoriza cambiar los cálculos, los datos persistidos ni otros canales de venta.

## Reglas de aceptación

- Una petición manipulada con `document_type_id=03` falla también en el servidor.
- Ningún flujo nuevo propone `03`, `BB`, `BC` o `BD`.
- Facturas y Notas de venta siguen creándose desde todos los canales que las soportan.
- Las Boletas antiguas siguen visibles, imprimibles, consultables y auditables.
- Los textos activos no prometen emisión de Boletas.
- El resumen de una Nota de venta de Hotel no muestra una fila o importe separado de IVA.
- Las pruebas verifican la política central, las series nuevas y los puntos de integración críticos.
