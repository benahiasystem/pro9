---
name: mantener-flujo-productos-pro9
description: Mantener y verificar en Pro9 la creación, listado y selección de productos, servicios y variaciones. Usar al tocar items, parent_item_id, ItemController, SearchItemController, almacenes, compras, ventas, POS, Vende Ya, restaurante, cotizaciones o notas de venta.
---

# Mantener el flujo de productos de Pro9

## Objetivo

Preservar un único contrato entre el esquema tenant, el modelo `Item` y todos los módulos que crean o buscan productos y servicios. Evitar arreglos locales que hagan funcionar un módulo mientras dejan rotos los demás.

## Procedimiento

1. Leer [references/contrato.md](references/contrato.md) antes de modificar el esquema, las variaciones, la creación o un buscador de productos.
2. Reproducir el flujo tanto con un producto como con un servicio (`unit_type_id=ZZ`). Distinguir un resultado vacío por filtros de un error de consulta o esquema.
3. Mantener `items.parent_item_id` como entero unsigned nullable, indexado y con clave foránea autorreferenciada a `items.id`. Los productos raíz usan `NULL`; sus variaciones apuntan al padre.
4. Incluir la columna y el índice en `create_items_table`, y la FK en `000999_add_tenant_foreign_keys`. Las tablas de variables y valores de variación nacen directamente en el consolidado. No mantener migraciones de reparación de tenants anteriores.
5. Tratar `ItemController::store` como la ruta común de creación y preservar su transacción, relaciones de almacén, invalidación de caché y respuesta de éxito.
6. Auditar todos los consumidores descritos en el contrato. Los buscadores compartidos deben devolver productos y servicios; los filtros de Vende Ya y restaurante deben validarse con datos que cumplan sus reglas de visibilidad.
7. Encerrar cambios manuales de migraciones con `########### INICIO/FIN CONTRATO FLUJO DE PRODUCTOS`.
8. Si se modifica Vue o JavaScript empaquetado, seguir `frontend-build` y no editar `public/build/` manualmente.
9. Ejecutar las pruebas unitarias, validar sintaxis y probar creación, seeding y reversión en bases temporales. Las pruebas de productos deben utilizar exclusivamente una instalación temporal.

## Reglas de aceptación

- Un tenant nuevo tiene columna, índice y clave foránea de `parent_item_id` desde el esquema inicial.
- Se pueden crear y volver a consultar un producto y un servicio sin dejar datos de prueba.
- Catálogo, compras, ventas, POS, Vende Ya, restaurante, cotizaciones y notas de venta ejecutan sus consultas sin errores.
- Un resultado vacío en un canal se explica por sus filtros y se prueba también con un producto visible preparado dentro de una transacción.
- Las pruebas evitan que el orden de migraciones o un cambio del modelo vuelva a separar código y esquema.
- Las líneas de productos resuelven exactamente Gravado (10) o Exento (20) en el catálogo vigente. `resolveSelectableAffectationType` no convierte IDs retirados; Facturas, cotizaciones y Notas de venta exigen una selección válida antes de añadir el producto.
- En `documents/invoice_generate.vue`, conservar el `v-for` que presenta `form.items` dentro del `tbody`. Al retirar columnas fiscales, verificar que agregar un producto muestre descripción, unidad, cantidad, precio, descuento y total antes de generar el comprobante.
- Ejecutar `node --test tests/js/fiscal-row.test.cjs` para comprobar el cálculo real de las líneas, incluyendo descuento sobre base, tasa configurable y conversión VES/USD. No sustituir estos cálculos por pruebas que sólo busquen texto en el código.
- Conservar `price1`/`price2`/`price3` de presentaciones y `FillItemPriceLabelsCommand`: no son exclusivamente históricos. Los selectores actuales `pos/partials/item_unit_types.vue`, `item_unit_types_table.vue`, `documents/partials/item.vue` y el editor de productos los leen o editan. El precio de la presentación prevalece sobre el precio general cuando se rellenan listas; no borrar ese comportamiento por un comentario antiguo que lo llame legacy.
- `FiscalEmissionSchemaTest::assertProductAndVariationPersistence` crea establecimiento y almacén temporales, guarda producto/servicio/variación con los modelos actuales, vincula una talla y modifica stock mediante `ItemWarehouse::addStock`. Comprueba relaciones, conteo de variaciones, IVA/precio y ausencia de filas después del rollback. Esta prueba acredita persistencia y relaciones; no sustituye pruebas HTTP de los controladores ni de movimientos completos de inventario.
