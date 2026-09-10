# Contrato funcional

## Tipos permitidos

| Contexto | Permitidos | Prohibido para crear |
| --- | --- | --- |
| Venta general, POS, pedidos, Hotel, ecommerce y restaurante | `01`, `80` | `03` |
| Conversión de Nota de venta, cotización y guía | `01` | `03` |
| Servicio técnico | `01`, `nv` | `03` |
| Procesamiento de Facturas y notas fiscales | `01`, `07`, `08` | Tipos de otros módulos y `03` |
| Consulta, PDF y auditoría | Tipos vigentes de cada módulo | Sin ramas exclusivas de Boletas |

## Puntos de control

- `app/Services/SalesDocumentTypePolicy.php`: política compartida y rechazo de nuevas Boletas/series.
- `app/CoreFacturalo/Facturalo.php`: última barrera común antes de guardar un documento fiscal.
- `app/Services/SeriesCodeGenerator.php`: catálogo actual de series, sin resolutores para Boletas ni prefijos antiguos.
- `database/seeders/TenantMigrationDataSeeder.php`: sembrar directamente el catálogo vigente sin conversiones de tenants existentes.
- Controladores y vistas de POS, pedidos, Hotel, tienda, restaurante, servicio técnico, ecommerce, WhatsApp, guías, cotizaciones y conversión de Nota de venta: listas explícitas según la tabla anterior.

## Instalación nueva

Retirar las ramas exclusivas de Boletas (`03`) y las series `BB`, `BC` y `BD` de modelos, consultas, reportes, herramientas de búsqueda, PDF y resolutores. No eliminar otros códigos `03` por coincidencia: motivos de notas, estados y otros catálogos tienen significados distintos. Conservar auditoría futura y funciones actuales compartidas. No ejecutar cambios sobre bases reales.

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
5. Revisar que las referencias restantes a Boleta sólo documenten su retirada o prueben su rechazo; no conservar caminos ejecutables exclusivos de documentos retirados.
