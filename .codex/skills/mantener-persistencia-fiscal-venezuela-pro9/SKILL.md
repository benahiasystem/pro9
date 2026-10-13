---
name: mantener-persistencia-fiscal-venezuela-pro9
description: Mantener el guardado transaccional de facturas y notas de Pro9, snapshots fiscales, pagos USD/VES, IGTF, retenciones recibidas y fondos de garantía. Usar al modificar su persistencia, saldos, reversión, contratos de cobro, PDFs o reportes financieros; la emisión y el transporte HKA se tratan en habilidades separadas.
---

# Persistencia fiscal y cobros de Pro9

## Alcance y fuentes

El contrato vigente conserva la operación comercial y prepara datos para HKA. Las facturas digitales DEMO y las notas exclusivas IGTF 08/98 elegibles pueden enviarse después del commit comercial mediante la habilidad de emisión; preparar una solicitud no acredita aceptación fiscal ni homologación. Órdenes de entrega y comprobantes emitidos de retención quedan fuera de este flujo.

- Consultar [contratos y puntos de intervención](references/contratos.md) al cambiar tablas, endpoints o consumidores.
- Consultar el [informe de implementación](../../../informes/persistencia_fiscal_venezuela_hka.md) para el esquema detallado y la evidencia histórica. Los resultados registrados no sustituyen las pruebas del cambio actual.
- Para DDL y semillas, aplicar [reconstruir-migraciones-tenant](../reconstruir-migraciones-tenant/SKILL.md): modificar el consolidado para instalaciones nuevas, sin conversiones ni backfills de bases existentes.
- Para payloads y operación fiscal congelada, aplicar [emitir-facturas-notas-hka](../emitir-facturas-notas-hka/SKILL.md).
- Para correo, descargas y generación del ticket con QR, aplicar [distribuir-documentos-hka](../distribuir-documentos-hka/SKILL.md).

## Guardado y snapshots

- Mantener `Facturalo::save`/`update` y `FiscalDocumentPersistence` como guardado común de los canales comerciales. Cabecera, líneas, relaciones, pagos y efectos comerciales participan de la transacción tenant; un error revierte la operación completa.
- Recalcular líneas, bases, impuestos y equivalencias en servidor. Obtener el emisor desde empresa/sucursal del tenant; el navegador no decide la identidad fiscal ni asigna un supuesto control HKA.
- Conservar `documents.issuer`, cliente/sucursal, artículo/unidad, `document_items.iva_rate`, equivalencias de pago y datos fiscales usados. Reimpresiones y preparación posterior leen esos snapshots; no reconstruirlos desde catálogos actuales ni modificar facturas anteriores al editar empresa o cliente.
- Distinguir número persistido, referencia visible, clave de almacenamiento y nombre público de descarga. Aplicar [numeración fiscal](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md) a la presentación y consultar [contratos y componentes](references/contratos.md) al modificar sus consumidores. El formato visible no calcula correlativos, no forma el payload HKA ni decide la ruta o autorización de un archivo.
- La emisión conserva estado, control y `document_emissions.consulta_url`. Después del commit y de confirmar, guardar A4 HKA en el disco privado tenant mediante `HkaPdfStore`; A5 se deriva bajo demanda. Validar en memoria y escribir de forma atómica, separado por operación/formato. Reutilizar y recuperar copias sin alterar venta, pagos, inventario, confirmación, payload ni correo; jamás generar A4/A5 locales digitales. La descarga 80MM y la vista previa del formulario anterior a emitir continúan en memoria, sin guardar el ticket con QR. Seguir [distribución HKA](../distribuir-documentos-hka/SKILL.md).
- Conservar tipos internos `01/07/08`, series/correlativos, anticipos, descuentos, cargos, cuotas e inventario. `igv` sigue siendo el nombre interno de IVA. `total_exportation` e `invoices.operation_type_id` distinguen exportaciones web digitales DEMO con INCOTERM 0201/0202/0203, líneas EXP y comprador ND. Conservar el INCOTERM/tipo externo/país en snapshots; sumar exportación separadamente de exento y en document_currency_totals.exportation. La edición conserva la categoría interna/exportación y los cobros; actualizar INCOTERM invalida el UUID y exige Enviar HKA explícito. No admitir notas referidas a exportaciones. Ver [contrato de exportación](../../../informes/facturas_exportacion_hka.md).
- La edición de facturas antes del registro HKA usa `DocumentEditPolicy`, según la regla autorizada el 7 de octubre de 2026: permitir cobros existentes y operaciones preparadas/rechazadas sin registro remoto, conservar todos los cobros y bloquear pendientes, incertidumbre sin conciliar y confirmaciones. Consultar [la política y evidencia de edición](../../../informes/edicion_facturas_antes_hka.md). Mantener separadas las restricciones de reversión de cobros de `assertMutable`.
- Mantener tipos y precisiones existentes: importes/bases/porcentajes nuevos usan `decimal(12,2)` y tasas `decimal(18,8)`, conforme a los equivalentes actuales. Conservar ocho decimales de tasa sin redondeo mediante `ExchangeRateMath` y el helper JS `exchange-rate-math`; los importes finales conservan su precisión monetaria.

## Pagos, IGTF y saldos

### Edición antes del registro HKA

Las reglas de registro, incertidumbre y bloqueo HKA de esta sección corresponden a facturas en modalidad `digital` («Medios digitales»). No extenderlas como integración HKA a Máquina fiscal ni Forma libre; conservar sus reglas comerciales y de control propias.

- Aplicar `DocumentEditPolicy` en servidor, listado, detalle y formulario, incluidos accesos directos. Publicar `can_edit`, `is_editable` y `edit_block_reason` coherentes. Permitir `not_requested`, `prepared` y rechazo definitivo sin registro/control remoto; bloquear `pending`, `confirmed`, canceladas/anuladas e incertidumbre sin conciliación. La ausencia remota acreditada permite recuperar la edición mediante `retry_allowed`.
- Conservar ID, serie (también vacía), número, moneda, emisor y referencias comerciales. Mantener pagos, recibos, movimientos de caja, retenciones y fondos existentes. Mostrar los cobros como lectura; el guardado de edición no inserta pagos ni acepta su modificación. Los cobros se gestionan mediante sus acciones específicas.
- Actualizar productos, impuestos, saldo y PDF en una transacción. Aplicar únicamente la diferencia de inventario y rechazar un total inferior al importe ya aplicado. No reaplicar retenciones ni fondos. Con retenciones existentes, conservar al cliente agente y verificar que el nuevo IVA respalde las retenciones.
- Actualizar el snapshot del comprador desde el cliente y la dirección autorizados, incluso si no cambia el cliente. No inventar una dirección para completar la emisión.
- Bloquear empresa → documento → emisión, como el envío HKA. Invalidar la operación editable preparada/rechazada o conciliada como ausente: nuevo UUID, payload descartado y estado `not_requested`. Conservar en `response.edit_history` UUID/estado/diagnóstico saneado anteriores, hash del payload, actor y fecha. Las respuestas tardías de otra operación no pueden modificar la nueva.
- Guardar una edición no transmite automáticamente. Mantener el envío posterior explícito mediante «Enviar HKA». Consultar [la política y evidencia de edición](../../../informes/edicion_facturas_antes_hka.md).

- `payment` es el principal neto aplicado en la moneda documental. `original_amount` es el principal recibido en la moneda del pago, sin vuelto ni IGTF; `tax_amount` es el IGTF recibido en esa moneda. La tasa expresa VES por USD.
- Conservar UUID `operation_key`, fuente/fecha de tasa y snapshot del medio de pago. La API conserva su comportamiento al omitir moneda propia; una moneda propia exige UUID y una moneda distinta exige importe original y tasa explícitos. Rechazar tasas no positivas o con más de ocho posiciones que requieran redondeo.
- Mantener idempotencia con restricciones únicas y bloqueo transaccional. Repetir la misma operación devuelve el pago con su `fiscal_context` original aunque cambien perfiles/configuración; reutilizar su clave con otros datos, intermediación/fundamento o para un pago revertido produce error.
- Configurar IGTF por tenant, deshabilitado inicialmente y sin tasa automática del catálogo HKA, con administración/auditoría. `SpecialTaxpayerPolicy` evalúa emisor SPE vigente, USD efectivamente recibido e intermediación explícita `none`; `subject` exige además habilitación/tasa positiva. `exempt` requiere motivo, fundamento y administrador; `not_applicable` no cobra impuesto. No deducirlo de moneda documental o SPE del comprador. Seguir [perfiles SPE](../mantener-sujetos-pasivos-especiales-pro9/SKILL.md).
- Calcular IGTF sobre el principal neto sujeto, sin vuelto, retenciones ni el propio impuesto. El inicial forma parte de los impuestos separados de la factura; el posterior genera una nota local `08`, motivo `IGTF`, transacción HKA `98`, vinculada al pago y factura. La nota no contiene productos, IVA ni movimientos de inventario y necesita una serie de débito autorizada.
- El recibo derivado `receipt_parent_id` liquida el cargo fiscal sin duplicar ingreso de caja. En saldos, caja, bancos, dashboard, PDFs y reportes distinguir principal aplicado, recepción efectiva e impuesto. Los recibos derivados no representan otra recepción de efectivo.
- Revertir conserva pago, actor, fecha y motivo, excluye pagos revertidos del saldo y revierte sus recibos derivados. El cargo IGTF inicial permanece trazable; una nota IGTF posterior aún no preparada se anula localmente. Si ya está preparada, rechazar la reversión completa hasta implementar su ajuste fiscal. No borrar silenciosamente el cargo ni revertir aisladamente su recibo derivado.

## Retenciones recibidas y fondos

- Para IVA, aplicar [retenciones IVA recibidas](../mantener-retenciones-iva-recibidas-pro9/SKILL.md) y su [informe vigente](../../../informes/retenciones_iva_recibidas_hka.md). `FiscalDocumentPersistence::retention` delega a `IvaRetentionService`; un comprobante IVA activo por factura, con historial/reemplazos. La previsión tiene campo dedicado, sin efecto en saldo; un comprobante anterior al control/confirmación aplica cero hasta cotejar su referencia. Una validación diferida fallida debe deshacer cambios locales antes de conservar `invalid`.
- Registrar soportes reales y congelar perfiles/propuesta/contexto/actor. IVA exige RIF vigente del cliente, número/control real, AAAAMM + ocho dígitos, mes concordante, 75/100 según causal/evidencia y sustraendo cero. ISLR usa [su ciclo recibido y outbox propios](../mantener-retenciones-islr-recibidas-pro9/SKILL.md), concepto existente, fundamento/evidencia independientes y exclusión del concepto salarial 001; no inferir tasas de SPE ni de etiquetas históricas.
- Respaldar unicidad/idempotencia con índices y bloqueo empresa → factura → comprobante; UUID de registro/reversión IVA/ISLR reutilizado para otros datos/acción produce 409. Limitar aplicación al saldo y al IVA fiscal VES conservado según porcentaje. Separar `base` IVA sujeto de `taxable_base`; con alícuotas mixtas, no inferir una base parcial sin desglose. Base ISLR no superior al valor neto facturado.
- Un adjunto pertenece al almacenamiento privado del tenant y a esa factura. No aceptar rutas ajenas, recorridos `..` ni archivos inexistentes.
- Carga durante creación: token privado por tenant/usuario, vencimiento y consumo único bajo bloqueo. Copiar conserva el temporal ante rollback; limpieza bajo el mismo bloqueo conserva soportes vinculados.
- Sólo aplicaciones vigentes reducen saldo mediante `applied_amount`, sin alterar venta/IVA ni generar efectivo/IGTF. Actualizar estado de crédito sin crear pagos globales o caja. La reversión IVA conserva importe aplicado hasta acreditar ausencia remota cuando hubo transmisión/presencia posible; libera una sola vez con actor/motivo/UUID. Fondos de garantía mantienen relación comercial propia.
- Sincronización IVA mediante outbox separado, después del commit y de confirmar factura digital DEMO. Fallo HKA conserva aplicación local válida; rollback no llama al proveedor. El cobro neto mantiene el bloqueo 203/1016 verificado, sin fabricar pagos. Originales HKA/payload de factura permanecen intactos.
- Mantener autorización de cobro, sucursal y tenant en web/API, y permiso de reversión. No guardar JWT, credenciales ni claves sensibles anidadas en snapshots, datos fiscales o emisiones.

## Verificación según el cambio

- Cálculos/contratos API: `FiscalLineCalculationTest`, `FiscalApiPaymentTransformTest`, `HkaPayloadBuilderTest` y `DocumentFiscalAuthorizationTest` según los consumidores afectados.
- Persistencia, esquema o concurrencia: `FiscalEmissionSchemaTest` con `PRO9_FISCAL_MYSQL_TESTS=1` en MySQL temporal. Incluye instalación/seeding/FKs, rollback/repetición, pagos, IGTF, retenciones y concurrencia; no ejecutar reconstrucciones sobre tenants reales.
- Edición: comprobar la política y el request de actualización, conservación de cobros/caja/fondos, restricciones de retenciones, renovación del snapshot y UUID, rollback y concurrencia edición/envío. Cubrir también formulario y visibilidad de acciones Vue.
- Presentación: revisar PDFs con `CurrentPdfRenderingTest` y `CurrentPdfTemplateContractTest`, y fuentes con `tests/js/fiscal-payment-ui.test.cjs`. Aplicar [frontend-build](../frontend-build/SKILL.md) al tocar Vue/JavaScript y antes de compilar.
- Ejecutar lint PHP y `git diff --check` para los archivos afectados. Informar qué se verificó y las limitaciones; las pruebas locales no acreditan emisión real en HKA.

## Precisión de tasas BCV — instrucción de 7 de octubre de 2026

- Pro9 conserva tasas como cadenas decimales de ocho posiciones en `DECIMAL(18,8)`, incluidos ceros finales. La API BCV devuelve cadenas y mantiene token fijo.
- Calcular conversiones con `Brick\Math\BigRational` en PHP y `ExactAmount`/`BigInt` en JavaScript. No redondear la tasa; redondear sólo el importe final a su precisión vigente.
- Excepción autorizada para tenants existentes: `exchange-rates:upgrade-precision --all-tenants --dry-run` y luego sin `--dry-run`, tras pruebas en MySQL temporal. El comando amplía únicamente las columnas inventariadas en `config/exchange_rate_precision.php`; no reconstruye bases ni recupera decimales históricos.

## Persistencia de ventas por terceros

Aplicar [contrato y evidencia](../../../informes/facturas_cuenta_terceros_hka.md). Resolver third_party_id bajo bloqueo dentro del tenant antes de efectos comerciales; conservar la referencia restrictiva y el snapshot en document_fiscal_data, con transacción 99. Exigir contado, saldo cero y pagos reales completos antes del commit. Una edición mantiene la categoría, cobros y numeración, renueva snapshot/UUID y requiere envío explícito. Desmarcar clientes no modifica históricos. Aplicar el contrato de notas domésticas del 13/10/2026 y bloquear el IGTF posterior de factura que genere una nota; conservar IGTF inicial, recibos derivados e inventario actuales. No permitir el flujo desde API, POS, Garage ni notas de venta.

## Persistencia de regímenes especiales

Aplicar [contrato y evidencia](../../../informes/facturas_regimenes_especiales_hka.md). Reutilizar document_fiscal_data.special_tax_regime_id y catalog_snapshot sin nuevas columnas ni conversiones de tenants existentes. Resolver IDs/descripciones activos bajo bloqueo en la transacción comercial y rechazar snapshots del navegador antes de efectos comerciales. HkaDomesticInvoicePolicy exige cobros reales completos y saldo cero antes del commit, para régimen propio o combinado con tercero. Conservar IVA y dinero local. Añadir/cambiar/quitar régimen en edición conserva cobros, moneda, categoría y numeración y renueva snapshot/UUID incluso en not_requested; quitar el régimen tampoco permite dejar saldo. Aplicar el contrato de notas domésticas del 13/10/2026 y bloquear el IGTF posterior de factura que origine una nota. Guardar la edición exige envío explícito.

## Versiones SPE y crédito

Aplicar [perfiles SPE](../mantener-sujetos-pasivos-especiales-pro9/SKILL.md) al modificar perfiles, clasificación o retenciones. Resolver perfiles con `FiscalProfileService::at` por fecha y congelarlos en emisor/comprador, `document_payments.fiscal_context` y `document_received_retentions.fiscal_context`; cambiar la versión no reescribe históricos. Empresa/persona no aceptan escritura fiscal genérica.

La nota de IGTF posterior conserva modalidad/ambiente de factura y fecha/tasa/perfiles de cobro. `HkaIgtfNote` valida el vínculo al recibo y padre; espera confirmación/control real y reanuda fuera del commit. La excepción no habilita notas de exportación, terceros o regímenes bloqueados por sus políticas.

En Web/POS/Garage, Crédito conserva saldo y abonos introducidos: quitar cobro sugerido, transmitir condición a Pagos, no proponer ni redistribuir el total al abrir el diálogo y recuperar importes reales del borrador al recargar. Comprobar `test_spe_*`, `SpecialTaxpayerPolicyTest`, `fiscal-payment-ui.test.cjs` y `pos-hka-email.test.cjs` según el cambio.

`ReceivedRetentionService` comparte el ciclo comercial IVA/ISLR. Guardar previsiones independientes, un activo por tipo, aplicar sólo después de control válido y conservar efecto ante reversión remota incierta. ISLR verifica base neta fiscal VES congelada; edición no puede dejar esa base sin respaldo.

## Notas ordinarias y devoluciones (13/10/2026)

Aplicar [contrato completo](../../../informes/notas_credito_debito_hka.md). La habilitación de notas domésticas para terceros/regímenes reemplaza sus restricciones históricas anteriores. `FiscalNote` resuelve originales y motivos dentro del guardado común, con bloqueo empresa antes de cualquier lectura consistente MySQL, luego factura; UUID/hash único y partida original restrictiva. Misma solicitud no repite crédito, stock ni cobros; otro contenido con la clave produce409. La factura con notas y las notas guardadas conservan sus originales.

Recalcular IVA, bases y valor a partir de la factura y NC anteriores, distribuyendo los centavos originales una sola vez. Reservar límites al guardar aunque HKA rechace o quede pendiente. Sólo NC01/07 reingresan cantidades, composición del pack/presentación, almacén, lotes y seriales originales; ajustes monetarios/ND no mueven stock. Capturar componentes al persistir la factura. Fallo comercial revierte todo y no transmite.

`Document::balance` y `FiscalNoteBalance` unifican factura menos NC/cobros/retenciones/fondo ajustado más reembolsos principales. Cada ND conserva saldo/cobros propios. La NC no tiene cobros ni saldo cobrable. `NoteRefund` requiere confirmación/control coherente, permiso y cobro real; salida independiente VES/USD, tasa explícita de ocho decimales, UUID y reversión auditada. No borrar cobros originales. Conservar la parte atribuible a retenciones/fondos fuera del efectivo reembolsable hasta su ajuste respaldado. Caja/banco y reportes cuentan salidas vigentes, excluyen reversión y evitan simular efectivo mediante el importe de la NC.

`NoteAdjustment` coordina IVA/ISLR, fondos e IGTF con diagnóstico propio. Retenciones conservan su efecto hasta ausencia remota verificada y luego admiten el comprobante corregido real. `withNoteReplacement` es un ámbito interno bajo bloqueo que admite el exceso real reembolsable, sin fabricar pagos; sus límites siguen usando IVA/neto restante. Fondos se reducen por movimientos auditados sin modificar el importe original. IGTF vincula impuesto/cobro originales, incluidos cargos de ND relacionadas; permanece bloqueado sin contrato fiscal acreditado y no habilita reembolso de ese componente.

Esquema inicial:345 tablas de aplicación/346 archivos tenant, sin conversión de instalaciones existentes. Ejecutar casos `test_note_*`, regresiones IVA/ISLR/emisión e instalación/rollback en MySQL temporal. Ver restricciones DEMO y evidencia en el informe.

El cobro posterior de una ND ordinaria puede generar el cargo IGTF98 con tasa/perfiles/recibo y clasificación heredados, sin revertir el cobro. Si combina tercero/régimen, `HkaNoteCapability::RELATED_IGTF_REASON` conserva el pendiente hasta contrato y aceptación específicos; no transmitir esa variante ni usar98 para disminuir IGTF en NC.
