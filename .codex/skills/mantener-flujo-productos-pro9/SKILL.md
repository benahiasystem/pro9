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
4. Si se reconstruyen migraciones, incluir la columna y el índice en `create_items_table` y conservar una reparación idempotente, reversible y posterior a todas las migraciones existentes para tenants ya instalados. La migración histórica que precede a la creación de `items` debe poder omitir su trabajo sin fallar.
5. Tratar `ItemController::store` como la ruta común de creación y preservar su transacción, relaciones de almacén, invalidación de caché y respuesta de éxito.
6. Auditar todos los consumidores descritos en el contrato. Los buscadores compartidos deben devolver productos y servicios; los filtros de Vende Ya y restaurante deben validarse con datos que cumplan sus reglas de visibilidad.
7. Encerrar cambios manuales de migraciones con `########### INICIO/FIN CONTRATO FLUJO DE PRODUCTOS`.
8. Si se modifica Vue o JavaScript empaquetado, seguir `frontend-build` y no editar `public/build/` manualmente.
9. Ejecutar las pruebas unitarias del contrato, validar sintaxis, migración limpia, reparación de tenant existente, reversión y una prueba transaccional real en cada tenant disponible.

## Reglas de aceptación

- Un tenant nuevo y uno existente tienen columna, índice y clave foránea de `parent_item_id`.
- Se pueden crear y volver a consultar un producto y un servicio sin dejar datos de prueba.
- Catálogo, compras, ventas, POS, Vende Ya, restaurante, cotizaciones y notas de venta ejecutan sus consultas sin errores.
- Un resultado vacío en un canal se explica por sus filtros y se prueba también con un producto visible preparado dentro de una transacción.
- Las pruebas evitan que el orden de migraciones o un cambio del modelo vuelva a separar código y esquema.
