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

## Contrato de catálogos y datos

- Sembrar directamente `09 = ORDEN DE ENTREGA`. Los códigos retirados `31`, `71` y `72` no pertenecen al catálogo inicial.
- Mantener `app_modules.dispatches` con la descripción `Orden de entrega` y el permiso técnico `guia` en el catálogo inicial.
- Actualizar semillas y migraciones base, sin renombrar registros de tenants existentes ni ejecutar cambios sobre bases reales.
- No restaurar `2026_09_04_000003_rename_dispatch_module_to_delivery_orders.php`: las altas centrales de módulos y niveles ya contienen las denominaciones actuales y las semillas tenant también. No hace falta un renombrado retrospectivo.

## Compatibilidad técnica

- Preservar nombres internos vigentes como `dispatch` y `dispatches`, sus tablas, claves foráneas, rutas, permisos y campos de API. No restaurar el módulo retirado `dispatch_carrier` ni sus formularios para satisfacer pruebas históricas.
- Preservar el valor técnico del módulo `guia` y el alias de búsqueda `guia` por compatibilidad.
- La orden de entrega es local: no firma XML, no solicita ticket/CDR, no se envía a SUNAT y no expone `send_sunat`. El cierre sólo ofrece PDF, correo y WhatsApp.
- El esquema `dispatches` conserva `hash` y `qr_url` para seguimiento comercial, y elimina `sunat_error_response`, `has_xml`, `has_cdr`, `ticket` y `reception_date`.
- No restaurar `app/CoreFacturalo/WS`, endpoints de consulta CDR ni el selector `auto_send_dispatchs_to_sunat`.
- No confundir las órdenes de entrega con guías documentarias, archivos adjuntos de compra u otros conceptos ajenos al módulo de despachos.

## Verificación obligatoria

1. Buscar variantes visibles de la denominación anterior fuera de las excepciones técnicas.
2. Probar con `DeliveryOrderNamingContractTest` que el catálogo inicial contiene `09 = ORDEN DE ENTREGA`, excluye 31/71/72 y conserva los identificadores técnicos actuales de módulo y permiso.
3. Verificar menú, listado, creación, mensajes, correo, reportes y PDF.
4. Ejecutar las pruebas de contrato relacionadas. Aplicar `frontend-build`; no compilar por iniciativa propia.

## Permisos centrales de instalación nueva

- La migración inicial de niveles `2024_12_16_141507_update_modules_levels_table.php` no inserta ni reasigna `dispatch_carrier`. Se retira `2026_09_04_000004_disable_carrier_dispatches.php`, que únicamente borraba ese permiso después de crearlo.
- Conservar `dispatches`, `dispatchers`, `drivers` y `transports` y sus identificadores originales. No renumerar permisos para llenar el hueco del registro retirado.
- `FiscalEmissionSchemaTest` ejecuta las migraciones centrales en una base temporal y comprueba que existe `dispatches` y no se crean los permisos retirados del transportista.
