---
name: mantener-persistencia-fiscal-venezuela-pro9
description: Mantener el guardado transaccional de facturas y notas de Pro9, snapshots fiscales, pagos USD/VES, IGTF, retenciones recibidas y fondos de garantía. Usar al modificar su persistencia, saldos, reversión, contratos de cobro, PDFs o reportes financieros; la emisión y el transporte HKA se tratan en habilidades separadas.
---

# Persistencia fiscal y cobros de Pro9

## Alcance y fuentes

El contrato vigente conserva la operación comercial y prepara datos para HKA. Preparar una solicitud no acredita emisión, homologación ni aceptación fiscal. Órdenes de entrega y comprobantes emitidos de retención quedan fuera de este flujo.

- Consultar [contratos y puntos de intervención](references/contratos.md) al cambiar tablas, endpoints o consumidores.
- Consultar el [informe de implementación](../../../informes/persistencia_fiscal_venezuela_hka.md) para el esquema detallado y la evidencia histórica. Los resultados registrados no sustituyen las pruebas del cambio actual.
- Para DDL y semillas, aplicar [reconstruir-migraciones-tenant](../reconstruir-migraciones-tenant/SKILL.md): modificar el consolidado para instalaciones nuevas, sin conversiones ni backfills de bases existentes.
- Para payloads y operación fiscal congelada, aplicar [emitir-facturas-notas-hka](../emitir-facturas-notas-hka/SKILL.md).

## Guardado y snapshots

- Mantener `Facturalo::save`/`update` y `FiscalDocumentPersistence` como guardado común de los canales comerciales. Cabecera, líneas, relaciones, pagos y efectos comerciales participan de la transacción tenant; un error revierte la operación completa.
- Recalcular líneas, bases, impuestos y equivalencias en servidor. Obtener el emisor desde empresa/sucursal del tenant; el navegador no decide la identidad fiscal ni asigna un supuesto control HKA.
- Conservar `documents.issuer`, cliente/sucursal, artículo/unidad, `document_items.iva_rate`, equivalencias de pago y datos fiscales usados. Reimpresiones y preparación posterior leen esos snapshots; no reconstruirlos desde catálogos actuales ni modificar facturas anteriores al editar empresa o cliente.
- Conservar tipos internos `01/07/08`, series/correlativos, anticipos, descuentos, cargos, cuotas e inventario. `igv` sigue siendo el nombre interno de IVA. `total_exportation` e `invoices.operation_type_id` permanecen, sin activar exportaciones por la existencia de un catálogo.
- No editar directamente un documento con pagos/retenciones contabilizados o una emisión preparada. Mantener la validación de mutabilidad del servicio.
- Mantener tipos y precisiones existentes: importes/bases/porcentajes nuevos usan `decimal(12,2)` y tasas `decimal(13,3)`, conforme a los equivalentes actuales. No ampliar `exchange_rate_sale` ni sustituir la estrategia numérica vigente dentro de este contrato.

## Pagos, IGTF y saldos

- `payment` es el principal neto aplicado en la moneda documental. `original_amount` es el principal recibido en la moneda del pago, sin vuelto ni IGTF; `tax_amount` es el IGTF recibido en esa moneda. La tasa expresa VES por USD.
- Conservar UUID `operation_key`, fuente/fecha de tasa y snapshot del medio de pago. La API conserva su comportamiento al omitir moneda propia; una moneda propia exige UUID y una moneda distinta exige importe original y tasa explícitos. Rechazar tasas no positivas después del redondeo vigente.
- Mantener idempotencia con restricciones únicas y bloqueo transaccional. Repetir la misma operación devuelve el mismo pago; reutilizar su clave con datos distintos o para un pago revertido produce un error.
- Configurar IGTF por tenant, deshabilitado inicialmente y sin tasa automática del catálogo HKA. Su escritura requiere administrador y auditoría. `subject` exige habilitación/tasa positiva y, en la implementación actual, pago USD; `exempt` exige motivo; `not_applicable` no cobra impuesto. No inferir el perfil tributario del cliente ni clasificar automáticamente por moneda.
- Calcular IGTF sobre el principal neto sujeto, sin vuelto, retenciones ni el propio impuesto. El inicial forma parte de los impuestos separados de la factura; el posterior genera una nota local `08`, motivo `IGTF`, transacción HKA `98`, vinculada al pago y factura. La nota no contiene productos, IVA ni movimientos de inventario y necesita una serie de débito autorizada.
- El recibo derivado `receipt_parent_id` liquida el cargo fiscal sin duplicar ingreso de caja. En saldos, caja, bancos, dashboard, PDFs y reportes distinguir principal aplicado, recepción efectiva e impuesto. Los recibos derivados no representan otra recepción de efectivo.
- Revertir conserva pago, actor, fecha y motivo, excluye pagos revertidos del saldo y revierte sus recibos derivados. El cargo IGTF inicial permanece trazable; una nota IGTF posterior aún no preparada se anula localmente. Si ya está preparada, rechazar la reversión completa hasta implementar su ajuste fiscal. No borrar silenciosamente el cargo ni revertir aisladamente su recibo derivado.

## Retenciones recibidas y fondos

- Registrar comprobantes reales IVA/ISLR múltiples. Validar identidad/snapshot del agente, número/fecha, base, porcentaje, sustraendo, moneda/tasa y fórmula del importe. El agente corresponde al cliente; ISLR exige concepto vigente. La base IVA corresponde al impuesto de la factura, no al total de venta.
- Conservar unicidad por documento, tipo, agente y número; bloquear la factura al aplicar para impedir duplicaciones/excesos concurrentes. Limitar la aplicación al saldo y, para IVA, al impuesto disponible.
- Un adjunto pertenece al almacenamiento privado del tenant y a esa factura. No aceptar rutas ajenas, recorridos `..` ni archivos inexistentes.
- Los comprobantes reducen el saldo mediante `applied_amount`, sin alterar total de venta ni generar efectivo. Los fondos de garantía usan su relación propia y efecto comercial sobre saldo; no reutilizar el JSON fiscal de retenciones retirado.
- Mantener autorización de cobro, sucursal y tenant en web/API, y permiso de reversión. No guardar JWT, credenciales ni claves sensibles anidadas en snapshots, datos fiscales o emisiones.

## Verificación según el cambio

- Cálculos/contratos API: `FiscalLineCalculationTest`, `FiscalApiPaymentTransformTest`, `HkaPayloadBuilderTest` y `DocumentFiscalAuthorizationTest` según los consumidores afectados.
- Persistencia, esquema o concurrencia: `FiscalEmissionSchemaTest` con `PRO9_FISCAL_MYSQL_TESTS=1` en MySQL temporal. Incluye instalación/seeding/FKs, rollback/repetición, pagos, IGTF, retenciones y concurrencia; no ejecutar reconstrucciones sobre tenants reales.
- Presentación: revisar PDFs con `CurrentPdfRenderingTest` y `CurrentPdfTemplateContractTest`, y fuentes con `tests/js/fiscal-payment-ui.test.cjs`. Aplicar [frontend-build](../frontend-build/SKILL.md) al tocar Vue/JavaScript y antes de compilar.
- Ejecutar lint PHP y `git diff --check` para los archivos afectados. Informar qué se verificó y las limitaciones; las pruebas locales no acreditan emisión real en HKA.
