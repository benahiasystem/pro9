---
name: mantener-catalogos-fiscales-venezuela
description: Mantener el inventario histórico y el estado inicial de los catálogos venezolanos de Pro9. Usar al tocar bancos, documentos, identidad, IVA, descuentos/cargos, leyendas, notas, operaciones, pagos, traslados, gastos o tablas fiscales peruanas retiradas.
---

# Mantener catálogos iniciales venezolanos

Antes de cambiar cualquier categoría cubierta por esta skill, leer el inventario normativo [references/catalogos-iniciales.md](references/catalogos-iniciales.md). Ese archivo conserva el estado final y el registro histórico retirado; actualizarlo junto con el código y las pruebas en el mismo cambio.

## Contrato

- El catálogo de ambientes fiscales es `fiscal_environments`, exclusivamente `demo` y `production`. Aplicar [mantener-modalidad-emision-fiscal-pro9](../mantener-modalidad-emision-fiscal-pro9/SKILL.md): el esquema nace sin SOAP/PFX y sin tipos de auditoría retirados, por lo que no se crean migraciones incrementales de conversión o limpieza. Esta excepción prevalece sobre las instrucciones generales para instalaciones existentes.

- Tratar los IDs conservados como contratos estables: no reasignar códigos. Los códigos venezolanos nuevos de traslado son `20` y `21`.
- Tratar `expense_reasons` como un catálogo inicial nuevo sin históricos: sus IDs contractuales son `1` a `30` y no representan una reclasificación de gastos preexistentes.
- El estado inicial venezolano es una depuración total de los catálogos enumerados en el inventario. No reintroducir filas retiradas como inactivas.
- Mantener `03` y sus series para consulta, PDF y auditoría, pero no ofrecer nuevas Boletas; aplicar también `mantener-facturas-notas-venta-sin-boleta` cuando intervenga un flujo de emisión.
- Mostrar `01 = FACTURA DE VENTA`, `07 = NOTA DE CRÉDITO`, `08 = NOTA DE DÉBITO`, `09 = GUÍA DE DESPACHO REMITENTE`, `20 = COMPROBANTE DE RETENCIÓN`, `31 = GUÍA DE DESPACHO TRANSPORTISTA` y `40 = COMPROBANTE DE PERCEPCIÓN`.
- Ofrecer únicamente `10 = Gravado` y `20 = Exento` en nuevas selecciones de afectación. Conservar los demás IDs para históricos y aplicar `migrar-iva-venezuela` para tasa, cálculos y nombres internos `igv`.
- Mantener activos los descuentos por ítem `00` y `01` con descripciones IVA. No reproducir estados históricos intermedios que los retiraban.
- Retirar del esquema inicial las nueve tablas declaradas eliminadas en el inventario y adaptar sus consumidores; no basta con dejarlas vacías o inactivas.
- No incluir relaciones hacia catálogos retirados en el `$with` global de Eloquent: conservar la relación para datos históricos y cargarla sólo cuando la tabla exista. Incluso una clave foránea nula provoca una consulta `where 0 = 1` durante la precarga.
- Ocultar los paneles de atributos UBL adicionales mediante una capacidad central de Venezuela; no comentar bloques grandes de Vue.

## Datos

1. Actualizar `database/seeders/data/tenant_initial_data.php` para tenants nuevos.
2. Mantener la migración de `expense_reasons` limitada a crear su estructura; todas sus filas iniciales deben existir únicamente en `database/seeders/data/tenant_initial_data.php`. No crear una migración incremental para este catálogo mientras no existan tenants históricos.
3. Excepción acordada para detracciones: retirar `cat_payment_method_types` y sus consumidores, campos y datos desde el consolidado, sin migración incremental ni conservación histórica; validar en una base temporal. No eliminar `payment_method_types`. La reconstrucción de tenants existentes requiere destinos explícitos.
4. Para las demás instalaciones existentes, crear una migración tenant incremental e idempotente que aplique el mismo estado sin asumir que todas las tablas aún existen.
5. Hacer `up()` idempotente por ID y limitar `down()` a valores reconocibles introducidos por la migración.
6. Antes de eliminar tablas en un tenant histórico, medir referencias y retirar primero sus claves foráneas. Preservar columnas históricas consumidoras cuando borrarlas no haya sido solicitado.
7. Invalidar cachés o adaptar proveedores de opciones cuando los catálogos no se consulten directamente.

## Presentación

- Usar `%IVA` y `Tipo %IVA` en los consumidores equivalentes, conservando nombres técnicos heredados.
- Actualizar literales visibles activos de Factura de venta y Guía de despacho. Mantener textos técnicos en XML, WSDL, endpoints, validadores externos y formatos peruanos cuando describan contratos reales.
- No editar `public/build`; si cambia Vue o JavaScript, aplicar `frontend-build` e informar que el usuario debe compilar.

## Marcadores y pruebas

- Delimitar cada hunk con `########## INICIO CAMBIO CATÁLOGOS DE NOMBRES` y `######### FIN CAMBIO CATÁLOGOS DE NOMBRES`, usando comentarios válidos.
- Ejecutar `scripts/apply_catalog_contract.php` sólo cuando se necesite reaplicar mecánicamente el inventario al consolidado; revisar siempre su diff.
- Ejecutar `scripts/validate_catalog_contract.php` para comprobar migración limpia, `TenancyDatabaseSeeder`, ausencia de tablas retiradas y conteos venezolanos en una base temporal descartable.
- Probar el inventario completo, las tablas ausentes, los bancos, los 30 motivos de gasto, métodos de pago, códigos de traslado, afectaciones `10/20` y paneles UBL.
- Verificar en una base tenant nueva que la migración deje `expense_reasons` vacía antes del seeding y que `TenancyDatabaseSeeder` cargue exactamente los 30 motivos contractuales.
- Ejecutar las pruebas de contrato de catálogos, IVA, ventas sin Boleta, datos tenant y SUNAT/SENIAT, además de `git diff --check` y lint PHP.
