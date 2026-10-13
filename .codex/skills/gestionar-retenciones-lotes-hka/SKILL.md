---
name: gestionar-retenciones-lotes-hka
description: Mantener la separación entre IVA e ISLR recibidos implementados y contratos HKA de lotes SFTP/facturación por lote pendientes. Usar para AplicarRetencion, AplicarRetencionISLR, SubirLote, EstadoLote, ResultadoLote o FacturacionLote, sin inferir nuevos tipos fiscales.
---

# Retenciones recibidas y lotes HKA

Leer [referencia HKA, secciones 3, 4.6, 6 y 10–12](../../../informes/imprenta_digital_hka_api.md), [numeración fiscal Pro9](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md) y [seguridad](../mantener-seguridad-pro9/SKILL.md).

Para IVA recibido en facturación usar [mantener-retenciones-iva-recibidas-pro9](../mantener-retenciones-iva-recibidas-pro9/SKILL.md): servicio común, un comprobante activo, previsión sin saldo, aplicación local válida y outbox DEMO. El [informe y evidencia](../../../informes/retenciones_iva_recibidas_hka.md) acredita POST/GET/DELETE en controles completamente cobrados; el neto Contado/Crédito VES/USD permanece bloqueado por 203/1016. Para ISLR recibido usar [mantener-retenciones-islr-recibidas-pro9](../mantener-retenciones-islr-recibidas-pro9/SKILL.md): outbox/adaptador separados, ausencia específica 204 y campo opcional codigoOperacion omitido. Lotes continúan fuera de alcance.

- `POST/GET/DELETE /api/AplicarRetencion` y `/api/AplicarRetencionISLR` actualizan, consultan y eliminan datos de retenciones **recibidas** sobre documentos `01`–`03` HKA. No confundirlas con la emisión de comprobantes `05`–`07`. Swagger exige `tipoDocumento`, número y control también en POST; el ejemplo PDF de IVA omite el tipo. Traducir códigos sólo en el borde y conservar secuencias/validaciones Pro9.
- Swagger define cuerpo JSON para GET/DELETE de retenciones. IVA lo verificó en DEMO y usa `HkaTransport::queryIvaRetention/removeIvaRetention`, con lectura antes de mutar y conciliación posterior. Una aplicación local válida no espera aceptación HKA; su reversión con presencia remota posible sí conserva el efecto hasta acreditar ausencia. Aplicar los sobres de ausencia/conflicto y bloqueos del contrato IVA, ISLR conserva su sobre 204 verificado en DEMO; no reutilizar ausencia IVA 203.
- Swagger añade `SubirLote` (ZIP Base64), `EstadoLote` y `ResultadoLote` (CSV Base64), más `FacturacionLote/Enviar` y `FacturacionLote/Estado` para un **lote JSON distinto**. Validar nombre, formato, paginación/estado e idempotencia antes de implementar carga masiva. Aislar credenciales, archivos y resultados por tenant.
