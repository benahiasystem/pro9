# Contrato funcional

## Tipos permitidos

| Contexto | Permitidos | Prohibido para crear |
| --- | --- | --- |
| Venta general, POS, pedidos, Hotel, ecommerce y restaurante | `01`, `80` | `03` |
| Conversión de Nota de venta, cotización y guía | `01` | `03` |
| Servicio técnico | `01`, `nv` | `03` |
| Consulta, PDF, reenvío y auditoría histórica | `01`, `03`, `80` según exista | Ninguno: es lectura histórica |

## Puntos de control

- `app/Services/SalesDocumentTypePolicy.php`: política compartida y rechazo de nuevas Boletas/series.
- `app/CoreFacturalo/Facturalo.php`: última barrera común antes de guardar un documento fiscal.
- `app/Services/SeriesCodeGenerator.php`: catálogo histórico y conjunto reducido para nuevas series.
- `database/seeders/TenantMigrationDataSeeder.php`: no asignar el grupo de Boleta a tenants nuevos sin borrar tenants existentes.
- Controladores y vistas de POS, pedidos, Hotel, tienda, restaurante, servicio técnico, ecommerce, WhatsApp, guías, cotizaciones y conversión de Nota de venta: listas explícitas según la tabla anterior.

## Compatibilidad histórica

No eliminar `03` de modelos, relaciones, consultas, reportes, herramientas de búsqueda, PDF ni resolutores de series. El catálogo puede conservar `BB`, `BC` y `BD` para interpretar documentos existentes; solamente debe excluirlos al ofrecer o crear series nuevas.

## Marcadores heredados de las tarjetas

- `INICIO/FIN CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS`
- `INICIO/FIN CAMBIO QUITAR BOLETA`
- `INICIO/FIN CAMBIO QUITAR BOLETAS A CRÉDITO`
- `INICIO/FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA`
- `INICIO/FIN CAMBIO FACTURAS Y NOTAS DE VENTA HOTEL`
- `INICIO/FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA`

Conservar el número de almohadillas utilizado por el archivo de origen. La diferencia entre “FACTURAS” plural y “FACTURA” singular identifica contratos distintos y no debe normalizarse.

## Verificación mínima

1. Ejecutar `SalesDocumentTypePolicyTest` y `SalesWithoutReceiptSourceContractTest`.
2. Ejecutar la suite unitaria completa si el entorno dispone de sus dependencias.
3. Validar sintaxis PHP y plantillas Vue modificadas.
4. Buscar valores por defecto o selectores activos que todavía creen `03`.
5. Revisar que las referencias restantes a Boleta pertenezcan exclusivamente a históricos, catálogos compatibles o archivos de respaldo no ejecutados.

