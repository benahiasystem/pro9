---
name: mantener-ordenes-entrega-pro9
description: Mantener en Pro9 el módulo y la presentación de Órdenes de entrega, antes denominado Guía de Remisión, preservando rutas, tablas, códigos y contratos internos de dispatch. Usar al tocar despachos, transportistas, reportes, PDF, correos, catálogos o conversiones a comprobantes.
---

# Mantener Órdenes de entrega en Pro9

## Contrato de presentación

- Mostrar `Orden de entrega` en singular y `Órdenes de entrega` en plural.
- Aplicar la denominación a menús, títulos, botones, formularios, mensajes, correos, reportes, exportaciones y PDF.
- El módulo vigente utiliza el código `09`; no ofrecer documentos retirados del transportista.
- No presentar al usuario las denominaciones históricas `Guía de Remisión`, `G.R. Remitente` o `G.R. Transportista`.
- Aplicar [numeración fiscal](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md) a encabezados y referencias: mínimo ocho dígitos, serie con sus guiones cuando exista y campo vacío cuando falte. Usar los helpers compartidos en listado, PDF, correo y reportes; no presentar `Sin serie`, marcadores técnicos ni IDs internos. Los nombres públicos de descarga y adjuntos siguen [distribución documental](../distribuir-documentos-hka/SKILL.md#nombres-visibles-de-archivos), conservando los prefijos propios de la orden.

## Contrato de catálogos y datos

- Sembrar directamente `09 = ORDEN DE ENTREGA`. Los códigos retirados `31`, `71` y `72` no pertenecen al catálogo inicial.
- Mantener `app_modules.dispatches` con la descripción `Orden de entrega` y el permiso técnico `guia` en el catálogo inicial.
- Actualizar semillas y migraciones base, sin renombrar registros de tenants existentes ni ejecutar cambios sobre bases reales.
- No restaurar `2026_09_04_000003_rename_dispatch_module_to_delivery_orders.php`: las altas centrales de módulos y niveles ya contienen las denominaciones actuales y las semillas tenant también. No hace falta un renombrado retrospectivo.

## Compatibilidad técnica

- Preservar nombres internos vigentes como `dispatch` y `dispatches`, sus tablas, claves foráneas, rutas, permisos y campos de API. No restaurar el módulo retirado `dispatch_carrier` ni sus formularios para satisfacer pruebas históricas.
- Preservar el valor técnico del módulo `guia` y el alias de búsqueda `guia` por compatibilidad.
- La orden conserva el registro y PDF comercial local. En Medios digitales DEMO registra intención transaccional en `dispatch_emissions` y emite automáticamente HKA `04` después del commit, con consulta, PDF original A4 y relación con facturas. Producción, anulación remota y correo HKA de órdenes siguen fuera de alcance. No firma XML ni solicita ticket/CDR/SUNAT; no expone `send_sunat`.
- El esquema `dispatches` conserva `hash` y `qr_url` para seguimiento comercial, y elimina `sunat_error_response`, `has_xml`, `has_cdr`, `ticket` y `reception_date`.
- No restaurar `app/CoreFacturalo/WS`, endpoints de consulta CDR ni el selector `auto_send_dispatchs_to_sunat`.
- No confundir las órdenes de entrega con guías documentarias, archivos adjuntos de compra u otros conceptos ajenos al módulo de despachos.

## Persistencia, inventario y HKA DEMO

Leer [implementación y evidencia](../../../informes/ordenes_entrega_hka.md). Mantener `creation_key` UUID y hash comercial, resolver IDs/snapshots en servidor y usar el almacén de la sucursal de la orden. Repetir una clave no crea otra orden ni otro movimiento. La edición restaura líneas anteriores dentro de la misma transacción y preserva su configuración de descuento de stock. Seriales únicos, enteros y del almacén autorizado; lotes del producto.

`DeliveryOrderInvoiceInventory` conserva cantidades físicas ya trasladadas y lotes/seriales al convertir una o varias órdenes. Productos con trazabilidad requieren una línea por producto con su cantidad completa y unidad original; separar cantidades previamente trasladadas de cantidades nuevas. Bloquear edición fiscal y anulación local de órdenes HKA pendientes, inciertas o confirmadas. Conciliar antes de reenviar una solicitud incierta; conservar UUID, identidad y payload.

El generador `scripts/prepare_delivery_order_demo.php` se limita a BBC DEMO y tiene manifiesto, bloqueo de ejecución y claves estables. Su ampliación de esquema BBC fue expresamente autorizada para estas pruebas; no extenderla automáticamente a otros tenants. Concurrencia, rollback y cambios de configuración se prueban en bases temporales.

## Verificación obligatoria

1. Buscar variantes visibles de la denominación anterior fuera de las excepciones técnicas.
2. Probar con `DeliveryOrderNamingContractTest` que el catálogo inicial contiene `09 = ORDEN DE ENTREGA`, excluye 31/71/72 y conserva los identificadores técnicos actuales de módulo y permiso.
3. Verificar menú, listado, creación, mensajes, correo, reportes y PDF.
4. Ejecutar las pruebas de contrato relacionadas. Aplicar `frontend-build`; no compilar por iniciativa propia.
5. Si cambia presentación documental, ejecutar `DocumentNumberDisplayTest`, `DocumentFileNameTest` y `tests/js/empty-series-display.test.cjs`; revisar referencias de factura/orden, serie vacía y nombres de PDF y adjuntos, además de las pruebas de autorización afectadas.

## Permisos centrales de instalación nueva

- La migración inicial de niveles `2024_12_16_141507_update_modules_levels_table.php` no inserta ni reasigna `dispatch_carrier`. Se retira `2026_09_04_000004_disable_carrier_dispatches.php`, que únicamente borraba ese permiso después de crearlo.
- Conservar `dispatches`, `dispatchers`, `drivers` y `transports` y sus identificadores originales. No renumerar permisos para llenar el hueco del registro retirado.
- `FiscalEmissionSchemaTest` ejecuta las migraciones centrales en una base temporal y comprueba que existe `dispatches` y no se crean los permisos retirados del transportista.
