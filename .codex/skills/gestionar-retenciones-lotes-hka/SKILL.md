---
name: gestionar-retenciones-lotes-hka
description: Preparar aplicación y consulta de retenciones recibidas, lotes SFTP y facturación por lote en HKA. Usar para AplicarRetencion, AplicarRetencionISLR, SubirLote, EstadoLote, ResultadoLote o FacturacionLote, sin crear tipos fiscales internos por inferencia.
---

# Retenciones recibidas y lotes HKA

Leer [referencia HKA, secciones 3, 4.6, 6 y 10–12](../../../informes/imprenta_digital_hka_api.md), [numeración fiscal Pro9](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md) y [seguridad](../mantener-seguridad-pro9/SKILL.md).

- `POST/GET/DELETE /api/AplicarRetencion` y `/api/AplicarRetencionISLR` actualizan, consultan y eliminan datos de retenciones **recibidas** sobre documentos `01`–`03` HKA. No confundirlas con la emisión de comprobantes `05`–`07`. Swagger exige `tipoDocumento`, número y control también en POST; el ejemplo PDF de IVA omite el tipo. Traducir códigos sólo en el borde y conservar secuencias/validaciones Pro9.
- Swagger define cuerpo JSON para `GET` y `DELETE` de retenciones; comprobar que la ruta real y el cliente lo admiten, especialmente GET con cuerpo. Evitar eliminación local o efectos fiscales hasta conciliar la respuesta externa.
- Swagger añade `SubirLote` (ZIP Base64), `EstadoLote` y `ResultadoLote` (CSV Base64), más `FacturacionLote/Enviar` y `FacturacionLote/Estado` para un **lote JSON distinto**. Validar nombre, formato, paginación/estado e idempotencia antes de implementar carga masiva. Aislar credenciales, archivos y resultados por tenant.
