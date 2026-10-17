---
name: mantener-retenciones-iva-recibidas-pro9
description: Mantener previsión, comprobantes IVA recibidos de clientes, saldos, soportes privados, AplicarRetencion HKA y reversión en la facturación Web, POS, Garage/Vende Ya y API de Pro9. Usar al modificar IvaRetentionService, HkaIvaRetention, sus endpoints, componentes o recuperación; la emisión a proveedores 05 y las retenciones ISLR tienen contratos separados.
---

# Retenciones IVA recibidas en Pro9

## Precisión exacta — contrato del 16/10/2026

Aplicar [cálculos exactos](../mantener-calculos-exactos-pro9/SKILL.md). Para instalaciones nuevas esta instrucción sustituye las precisiones de dos/cuatro/seis decimales y redondeos intermedios descritos anteriormente: operar con fracciones exactas, conservar snapshots y representar hasta 12 posiciones; tasas con ocho exactas. Dos decimales sólo en liquidación/presentación que lo requiera. Precio unitario PDF configurable de 2 a 12, con dos por defecto. No convertir tenants ni recalcular históricos. Consultar el informe de cálculos para el estado real de adopción por consumidor.


## Alcance y fuentes

Contrato implementado el 12 de octubre de 2026 para instalaciones nuevas. Mantener `Facturalo`/`FiscalDocumentPersistence` como guardado común y `IvaRetentionService` como ciclo del comprobante recibido. La retención pertenece a la factura fiscal interna `01`, también `01` en `AplicarRetencion` HKA. Una conversión conserva su relación comercial y vincula el comprobante a la factura resultante; no traslada comprobantes de otra factura.

Leer [datos, entradas y recuperación](references/contratos.md) al cambiar esquema, API, componentes o sincronización. El [informe de implementación](../../../informes/retenciones_iva_recibidas_hka.md) mantiene ejemplos API, matriz por módulo y evidencia DEMO saneada. Los resultados históricos no sustituyen las pruebas del cambio actual.

Para ISLR recibido usar [su contrato independiente](../mantener-retenciones-islr-recibidas-pro9/SKILL.md). Compartir ciclo `ReceivedRetentionService` y reclamo `HkaReceivedRetention`, manteniendo reglas/adaptadores/outboxes separados. Un IVA y un ISLR pueden coexistir; sugerencia neta suma ambas previsiones.

Emisión a proveedores HKA `05`, ARC, producción y conversión de tenants existentes tienen alcance separado. Aplicar [perfiles SPE](../mantener-sujetos-pasivos-especiales-pro9/SKILL.md) para elegibilidad, [persistencia fiscal](../mantener-persistencia-fiscal-venezuela-pro9/SKILL.md) para cobros, [esquema inicial](../reconstruir-migraciones-tenant/SKILL.md) para DDL y [frontend-build](../frontend-build/SKILL.md) para assets.

Al cambiar HTTP/autenticación leer [conexión HKA](../conectar-api-hka/SKILL.md); para autorización y soportes privados, [seguridad Pro9](../mantener-seguridad-pro9/SKILL.md). Aplicar sus controles relevantes al flujo autorizado, sin ampliar la tarea a otras integraciones o hallazgos.

## Contrato comercial y monetario

- La previsión se guarda en `document_fiscal_data.iva_retention_prevision`, separada de pagos/aplicaciones y recalculada en servidor al cambiar cliente, fecha, artículos o impuestos. No reduce saldo: total 116, IVA 16, previsión 12 y cobro 104 conserva saldo 12 hasta validar el comprobante.
- La interfaz gestiona las retenciones recibidas exclusivamente desde el listado `/documents`, mediante su acción Retención. No montar previsiones ni carga de comprobantes durante la creación, pago o confirmación en Facturas, POS/Garage, Hotel, pedidos, servicio técnico o suscripciones. Los contratos API/servicios conservan sus capacidades; esta retirada es de interfaz y no concede autoridad fiscal al comprador público.
- La creación usa `helpers/document-creation.js`: excluir `iva_retention_prevision`, `islr_retention_prevision` y `received_retentions` de solicitudes y borradores POS/Garage restaurados. Cobrar según `total_payable` o `total`, sin deducir previsiones antiguas. Conservar artículos, abonos, monedas y tasas; al agregar un medio sugerir sólo el restante con aritmética exacta. No borrar retenciones persistidas ni recalcular históricos.
- Resolver cliente/perfiles por fecha mediante `FiscalProfileService`/`SpecialTaxpayerPolicy`: facultad vigente, operación/exclusión, RIF, 75/100 y evidencia. Exigir `AAAAMM########`, fecha/mes coherentes, sustraendo cero, fórmula, soporte y número/control fiscal real. El navegador no decide elegibilidad ni aplicación.
- Separar `base` (IVA sujeto), `taxable_base` (base imponible) y `amount` (retención). Comparar límites con el IVA fiscal VES conservado de la factura. `applied_amount` liquida saldo en moneda documental con la tasa del comprobante; no es efectivo ni base IGTF.
- Convertir importes HKA directamente de moneda del comprobante a moneda fiscal del payload confirmado. Evitar conversión intermedia por saldo documental o tasa actual: VES→VES conserva importe aun con factura USD y tasas distintas. Conservar los importes racionales y las tasas de ocho posiciones; aplicar dos decimales únicamente al importe del protocolo HKA, registrando esa representación final.
- Con alícuotas mixtas, un comprobante sobre todo el IVA conserva la base imponible completa. Rechazar IVA parcial sin desglose de partidas; no inventar su base mediante proporción entre alícuotas distintas. Reutilizar `voucherTaxableBase` para el cálculo admitido.
- Sólo aplicaciones vigentes reducen cuentas por cobrar. Actualizar condición del crédito sin insertar pagos globales/movimientos de caja; conservar total de venta e IVA causado. PDFs locales muestran previsión y aplicaciones vigentes. La aplicación posterior no modifica payload ni originales PDF HKA.

## Registro, idempotencia y soporte

- Separar estado local de HKA: `pending_validation`, `applied`, `invalid`, `reversed`. Antes de control/confirmación el comprobante aplica cero. Al confirmar, cotejar la referencia aportada; una discrepancia queda visible, sin sustituirla silenciosamente.
- Un IVA activo (`pending_validation` o `applied`) por factura, respaldado por bloqueo e índice generado único. Conservar soporte/historial inválido o revertido; reemplazar con nuevo UUID y permitir el mismo número después de liberar el anterior.
- Exigir UUID `operation_key` en registro/reversión y la clave HKA vigente para reintentar. Misma clave/datos devuelve resultado; otros datos o acción producen HTTP 409, validación ordinaria 422. Congelar solicitud/hash y excluir reutilización entre registro y reversión.
- Sesión/CSRF en Web y autenticación tenant en API. Autorizar documento/sucursal antes de operar; registro/carga requieren `create_payment`, reversión `delete_payment`, o administrador. Reintentar eliminación usa permiso de reversión; consulta/descarga conserva alcance de sucursal.
- Soporte privado PDF/JPG/PNG hasta 5 MB. El contrato API de carga temporal conserva tokens de `received_retention_uploads` ligados a tenant/usuario, vencimiento y consumo único bajo bloqueo; la interfaz del listado carga soporte vinculado a una factura guardada. Copiar preserva el origen ante rollback exterior; la limpieza toma ese mismo bloqueo y conserva soportes vinculados.
- Publicar `iva_retention` saneado en creación, detalle, listado y pagos: previsión, importes, saldo, estados, diagnóstico y acciones. Ocultar solicitud/hash, payload/contexto de conexión, intento interno, credenciales y respuestas crudas HKA. Los botones no sustituyen autorización.

## Sincronización y reversión

- Guardar factura, pagos, previsión y datos recibidos en la transacción comercial; emitir tras el commit exterior. Con control real y confirmación digital, validar/aplicar localmente y crear operación durable. HTTP fuera de bloqueos/transacciones: rollback no llama a HKA; fallo remoto conserva venta y aplicación local válida.
- La activación diferida usa savepoint: si falla una validación posterior a los primeros cambios locales, deshacerlos antes de guardar `invalid`, aplicación cero y diagnóstico. No dejar saldo aplicado a un comprobante inválido.
- Usar `HkaIvaRetention`/`HkaIvaRetentionPayload`; bloquear empresa → factura → comprobante → operación, reclamar UUID de intento y cotejarlo al finalizar. Mantener identidad/payload/configuración congelados; otra conexión o respuesta anterior no autoriza sobrescritura.
- POST aplica y GET/DELETE envían cuerpo JSON de identidad en `/api/AplicarRetencion`. Consultar antes de cada mutación y conciliar después. Coincidencia exacta puede adoptarse sin POST; otro comprobante/identidad detiene el envío como conflicto; JSON incompleto queda incierto.
- Acreditar ausencia sólo con resultado exitoso explícitamente vacío o el sobre 203 exacto de la referencia. Un 203 genérico o timeout no prueba ausencia. Consulta manual verifica un conflicto corregido; ausencia tras conflicto de aplicación mantiene el bloqueo de reenvío.
- Mantener `iva-retentions:recover` en scheduler tenant, espera progresiva y gracia de 90 segundos para intento pendiente/incierto anterior. Rechazos definitivos requieren corrección. No trasladar los tiempos/estados de recuperación de factura a retención.
- Revertir exige motivo, actor y UUID. Con transmisión/presencia remota posible, conservar efecto local hasta acreditar ausencia. Una retención nunca transmitida ni observada remotamente puede revertirse localmente bajo bloqueo. Completar reversión cancela intentos anteriores, libera el IVA activo, devuelve saldo una vez y conserva caja/historial.

## Límite DEMO confirmado

HKA rechazó cobro neto VES/USD, tanto Contado como Crédito con abono, con `203 / 1016`: exige formas de pago por el total. Mantener `config/iva_retention.php` con `demo_net_collection_verified=false` y `HkaIvaRetentionCapability` en preparación/transmisión/vista. Guardado comercial permitido; esa combinación publica diagnóstico y `can_send=false` sin transmitir. No inventar pagos para satisfacer al proveedor.

POST/GET/DELETE y conciliación pasaron en facturas de control completamente cobradas: acredita transporte, no aceptación del circuito neto, habilitación SPE del emisor ni producción. Cambiar la capacidad requiere aceptación real del mismo circuito; activarla en dobles de prueba no la acredita. El [borrador para HKA](../../../informes/solicitud_hka_retenciones_iva_demo.md) conserva el caso sin autorizar su envío.

## Verificación según el cambio

- Servicio/esquema: `test_iva_*` de `FiscalEmissionSchemaTest` mediante `IvaRetentionPersistenceCases`, con `PRO9_FISCAL_MYSQL_TESTS=1` en bases MySQL temporales propias. Cubrir previsión sin saldo, 75/100/perfiles, soporte/fecha, referencia/exceso, UUID, rollback y activación diferida.
- Concurrencia/recuperación: registro frente a registro/cobro, reversión frente a cobro, dos reversiones del mismo UUID, timeout/adopción, rechazo, conflicto y reversión tras ausencia. Comprobar saldo/crédito una vez y caja/payload intactos.
- Moneda/HTTP: `HkaIvaRetentionPayloadTest`, `IvaRetentionHttpContractTest`, `HkaTransportTest`, `HkaPayloadBuilderTest`; base/IVA, alícuotas mixtas, tasas distintas, GET/DELETE JSON y 409/422.
- UI/PDF: `tests/js/iva-retention-ui.test.cjs`, `received-retention-list.test.cjs`, `pos-hka-email.test.cjs`, `CurrentPdfRenderingTest` y `CurrentPdfTemplateContractTest`; reapertura, borradores, respuestas obsoletas, errores y entradas compartidas. Elegir regresiones de pagos/SPE afectadas.
- Assets según `frontend-build`, compilación del usuario. Después verificar manifiesto/bundle y cada entrada visual en instalación nueva; métodos/plantillas no acreditan esa aceptación, pendiente en la evidencia del chat.
- Registrar evidencia saneada y límites sin JWT, credenciales o RIF reales en fixtures. Las pruebas nuevas siguen el alcance autorizado de la tarea; la evidencia histórica no concede permiso para emitir ni borrar registros remotos.

## Ajustes por notas de crédito (13/10/2026)

`NoteAdjustment` coordina solicitud de reversión y reemplazo desde una NC sin duplicar el servicio/outbox. En interfaz, `allowRetentionManagement` es falso por defecto y sólo el listado habilita ajustes/reemplazos IVA/ISLR; devoluciones de dinero, IGTF y fondos conservan su flujo propio. La base/IVA admisibles usan `FiscalNote::remaining`, conservando el IVA original. Un resultado incierto conserva aplicación y bloquea el reemplazo. Sólo ausencia acreditada libera registro del comprobante corregido real con soporte, UUID y referencia original. `withNoteReplacement` admite dentro del bloqueo el exceso de efectivo reembolsable, con límite tributario/neto; no usar este ámbito fuera de la coordinación autorizada ni inventar pagos. El reembolso reserva la parte no monetaria mientras se concilia. Consultar [contrato y pruebas](../../../informes/notas_credito_debito_hka.md).
