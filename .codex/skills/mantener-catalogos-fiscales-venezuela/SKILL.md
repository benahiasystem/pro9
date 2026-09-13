---
name: mantener-catalogos-fiscales-venezuela
description: Mantener el inventario histórico y el estado inicial de los catálogos venezolanos de Pro9. Usar al tocar bancos, documentos, identidad, IVA, descuentos/cargos, leyendas, notas, operaciones, pagos, traslados, gastos o tablas fiscales peruanas retiradas.
---

# Mantener catálogos iniciales venezolanos

Antes de cambiar cualquier categoría cubierta por esta skill, leer el inventario normativo [references/catalogos-iniciales.md](references/catalogos-iniciales.md). Ese archivo conserva el estado final y el registro histórico retirado; actualizarlo junto con el código y las pruebas en el mismo cambio.

## Contrato

- El catálogo de ambientes fiscales es `fiscal_environments`, exclusivamente `demo` y `production`. Aplicar [mantener-modalidad-emision-fiscal-pro9](../mantener-modalidad-emision-fiscal-pro9/SKILL.md): el esquema nace sin SOAP/PFX y sin tipos de auditoría retirados, por lo que no se crean migraciones incrementales de conversión o limpieza. Esta excepción prevalece sobre las instrucciones generales para instalaciones existentes.

- Tratar los IDs conservados como contratos estables: no reasignar códigos. Los códigos venezolanos nuevos de traslado son `20` y `21`.
- `cat_identity_document_types` contiene exactamente `0`, `1`, `6`, `7`, `E`, `C`, `G` y `R`, en ese orden y todos con `active = 1`. Mantener sincronizados la fuente central, el seeder y los consumidores sin crear un backfill para tenants existentes.
- Tratar `expense_reasons` como un catálogo inicial nuevo sin históricos: sus IDs contractuales son `1` a `30` y no representan una reclasificación de gastos preexistentes.
- El estado inicial venezolano es una depuración total de los catálogos enumerados en el inventario. No reintroducir filas retiradas como inactivas.
- No conservar Boletas, series BB/BC/BD ni resúmenes fiscales de Boletas. Aplicar `mantener-facturas-notas-venta-sin-boleta` a creación, consultas, reportes y PDF.
- Usar los tipos del inventario vigente: Factura de venta, notas de crédito/débito, Orden de entrega, Retención, Nota de venta y notas de almacén. No reintroducir códigos retirados sólo para resolver datos antiguos.
- Usar únicamente `10 = Gravado` y `20 = Exento`. No conservar otros IDs para históricos; aplicar `migrar-iva-venezuela` para tasa, cálculos y nombres internos `igv`.
- Los scopes de tipos documentales excluyen IDs retirados: compras ofrece 01/NE76 y el selector general sólo los códigos vigentes de su flujo. El resolutor de reportes admite 01/07/08/80 y no asigna `Document` a un ID desconocido. Probarlo con `CurrentDocumentResolutionTest`.
- Mantener activos los descuentos por ítem `00` y `01` con descripciones IVA. No reproducir estados históricos intermedios que los retiraban.
- Retirar del esquema inicial las nueve tablas declaradas eliminadas en el inventario y adaptar sus consumidores; no basta con dejarlas vacías o inactivas.
- Retirar relaciones y modelos exclusivos de catálogos eliminados. No implementar consultas opcionales que comprueben si una tabla antigua existe.
- Ocultar los paneles de atributos UBL adicionales mediante una capacidad central de Venezuela; no comentar bloques grandes de Vue.

## Datos

1. Actualizar `database/seeders/data/tenant_initial_data.php` para tenants nuevos.
2. Mantener la migración de `expense_reasons` limitada a crear su estructura; todas sus filas iniciales deben existir únicamente en `database/seeders/data/tenant_initial_data.php`. No crear una migración incremental para este catálogo mientras no existan tenants históricos.
3. Excepción acordada para detracciones: retirar `cat_payment_method_types` y sus consumidores, campos y datos desde el consolidado, sin migración incremental ni conservación histórica; validar en una base temporal. No eliminar `payment_method_types`. La reconstrucción de tenants existentes requiere destinos explícitos.
4. El proyecto sólo admite instalación nueva para esta adaptación. Actualizar directamente creaciones y semillas, sin conversión incremental.
5. Los `up()` y `down()` crean y eliminan estructura; el seeder carga el estado final una sola vez por identidad.
6. Comprobar en bases temporales que todos los datos iniciales resuelvan sus claves foráneas. No mantener columnas consumidoras de catálogos retirados por compatibilidad histórica.
7. Invalidar cachés o adaptar proveedores de opciones cuando los catálogos no se consulten directamente.

## Presentación

- Usar `%IVA` y `Tipo %IVA` en los consumidores equivalentes, conservando nombres técnicos heredados.
- Actualizar literales visibles activos de Factura de venta y Guía de despacho. Mantener textos técnicos en XML, WSDL, endpoints, validadores externos y formatos peruanos cuando describan contratos reales.
- No editar `public/build`; si cambia Vue o JavaScript, aplicar `frontend-build` e informar que el usuario debe compilar.

## Marcadores y pruebas

- Delimitar cada hunk con `########## INICIO CAMBIO CATÁLOGOS DE NOMBRES` y `######### FIN CAMBIO CATÁLOGOS DE NOMBRES`, usando comentarios válidos.
- Ejecutar `scripts/apply_catalog_contract.php` sólo cuando se necesite reaplicar mecánicamente el inventario al consolidado; revisar siempre su diff.
- Ejecutar `scripts/validate_catalog_contract.php` para comprobar migración limpia, `TenancyDatabaseSeeder`, ausencia de tablas retiradas y conteos venezolanos en una base temporal descartable. La comprobación de `pse_providers` es estructural; no cargar modelos del módulo `PseService`, porque el módulo completo está retirado.
- Probar el inventario completo, las tablas ausentes, los bancos, los 30 motivos de gasto, métodos de pago, los ocho tipos de identidad activos, códigos de traslado, afectaciones `10/20` y paneles UBL.
- Verificar en una base tenant nueva que la migración deje `expense_reasons` vacía antes del seeding y que `TenancyDatabaseSeeder` cargue exactamente los 30 motivos contractuales.
- Ejecutar las pruebas de contrato de catálogos, IVA, ventas sin Boleta, datos tenant y SUNAT/SENIAT, además de `git diff --check` y lint PHP.
