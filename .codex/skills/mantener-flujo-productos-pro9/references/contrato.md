# Contrato del flujo de productos

## Esquema y modelo

- Tabla: `items`.
- Variaciones: `parent_item_id int unsigned NULL`, índice `items_parent_item_id_index` y FK `items_parent_item_id_foreign` hacia `items.id` con borrado restringido.
- Modelo: `parent()` pertenece a `Item`; `variations()` contiene muchos `Item` por `parent_item_id`.
- Producto: unidad distinta de `ZZ`. Servicio: `Item::SERVICE_UNIT_TYPE`, actualmente `ZZ`.

No elimines `withCount('variations')` ni el agrupamiento por `parent_item_id` para ocultar una columna ausente: repara el esquema.

## Mapa de consumidores

| Flujo | Punto principal | Contrato mínimo |
|---|---|---|
| Catálogo y creación | `Tenant/ItemController` | listar variaciones; creación transaccional de producto y servicio |
| Compras | `SearchItemController::getItemToPurchase` | combinar productos y servicios |
| Ventas | `SearchItemController::getItemsToDocuments` | combinar productos y servicios |
| POS | `Tenant/PosController` | agrupar padres y cargar conteo/stock de variaciones |
| Vende Ya | `Tenant/Api/SellnowController::items` | código interno, almacén y activo |
| Restaurante | `RestaurantController::items` | `apply_restaurant`, código interno y almacén o insumo |
| Cotizaciones | `SearchItemController::getItemsToQuotation` | productos y servicios; respetar `only_service` |
| Notas de venta | `SearchItemController::getItemsToSaleNote` | productos y servicios con datos de almacén |

## Matriz de verificación

1. Crear un producto visible en tienda y restaurante y un servicio, dentro de una transacción que siempre termine en rollback.
2. Buscar sus identificadores desde catálogo, compras, documentos, cotizaciones y notas de venta.
3. Buscar el producto desde POS, Vende Ya y restaurante usando el usuario y almacén del establecimiento.
4. Ejecutar sólo en una instalación temporal con un producto que satisfaga los filtros. No operar sobre tenants reales.
5. Verificar en `information_schema` tipo nullable, índice y FK.
6. En una base temporal, ejecutar el consolidado y el seeding, comprobar todas las claves foráneas, hacer rollback y repetir la instalación. No exigir migraciones de reparación retiradas.
7. Ejecutar `ProductFlowSchemaContractTest`, `ProductModuleFlowContractTest` y la suite completa.

## Afectaciones de productos

Resolver 10/20 exactamente, sin convertir códigos desconocidos a Gravado o Exento. El formulario debe exigir una selección vigente antes de añadir la línea. Comprobar el cálculo real con `node --test tests/js/fiscal-row.test.cjs`, sin generar bundles.
