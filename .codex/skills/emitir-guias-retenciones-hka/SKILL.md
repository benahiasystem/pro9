---
name: emitir-guias-retenciones-hka
description: Documentar o preparar la emisión HKA de guías/órdenes de entrega y comprobantes de retención IVA, ISLR y ARC. Usar al trabajar con tipos HKA 04–07, relaciones factura-guía, transporte, sujeto retenido o nodos ARC.
---

# Guías y comprobantes HKA

Leer [referencia HKA, secciones 1, 4.5–4.6, 5 y 10–12](../../../informes/imprenta_digital_hka_api.md), [órdenes de entrega Pro9](../mantener-ordenes-entrega-pro9/SKILL.md) y [numeración fiscal](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md).

El IVA recibido de un cliente sobre una factura usa `AplicarRetencion`, no emisión `05`. Su previsión, saldo, soporte, outbox y reversión están implementados bajo [retenciones IVA recibidas](../mantener-retenciones-iva-recibidas-pro9/SKILL.md). Mantener esa separación al modificar órdenes de entrega/conversiones: el comprobante pertenece a la factura resultante. Este chat no habilitó emitir retenciones a proveedores.

- La orden de entrega Pro9 `09` se traduce a HKA `04` sólo en el flujo HKA. Conservar rutas, tablas, nombre comercial, numeración y validaciones internas de dispatch. HKA `05` y `06` son retenciones cuya emisión interna sigue sin equivalencia fiscal aprobada. El catálogo Pro9 registra `ARCV` para el tipo HKA `07`, sin habilitar todavía su emisión; no recodificar la nota de crédito interna `07`.
- Para `04`, usar el esquema normal `POST /api/Emision` y revisar `guiaDespacho`, conductor, vehículo, transportista y `facturaGuia[]`; `/api/Documentos/Relacionar` administra el vínculo externo factura–guía (`01`/`04`). Para `05`/`06`, revisar `sujetoRetenido`, `totalesRetencion` y `detallesRetencion[]` del mismo endpoint. Para `07`, usar **otro esquema y ruta**, `POST /api/EmisionARC`, con `beneficiario`, totales y detalles ARC; no enviarlo al esquema normal que admite sólo `01`–`06`.
- El Swagger añade constancia DNF tipo `99` por `POST /api/EmisionDNF/constanciaRecepcion`; no tiene tipo Pro9 aprobado ni pertenece al alcance de los siete documentos del PDF. No asumir que los campos no marcados obligatorios son prescindibles ni fabricar payloads completos para `04`–`07`: el PDF no los incluye. Contrastar Swagger y respuestas DEMO antes de habilitar emisión.
- Mantener la autorización y aislamiento de [seguridad Pro9](../mantener-seguridad-pro9/SKILL.md); no afirmar soporte fiscal productivo a partir de formularios o simuladores existentes.

## Implementación 04 verificada en DEMO (2026-10-13)

Leer [informe y matriz](../../../informes/ordenes_entrega_hka.md). `HkaDispatchPayloadBuilder`, `HkaDispatchEmission`, `HkaDispatchPdf` y `HkaDispatchRelations` trabajan con `dispatch_emissions`, separados de `document_emissions`. Conservar Pro9 `09`, HKA `04`, `esGuiaDespacho=1`, motivos venezolanos, snapshots de transporte y equivalencias HKA de unidades.

DEMO exige `precioUnitario` incluso en guías sin valorización: enviar cero y omitir el nodo `totales`. En guías valorizadas VES, congelar cálculos canónicos de IVA y enviar precio neto, importe, código/tasa/IVA y total por línea; también omitir `totales`. No utilizar el precio oculto del selector si `enabled_price_items_dispatch` está desactivado. Reparación exige `descripcionServicio`. Los payloads de ambas variantes tienen aceptación real; el PDF no acredita que DEMO imprima todos los campos de transporte enviados.

Relaciones: consultar primero, conservar el estado por separado y confirmar después del POST. La ausencia comprobada es HTTP 200 con código 404, mensaje `Relación no encontrada` y única validación `Relación no encontrada en nuestra base de datos.`; otros errores no habilitan mutación a ciegas. Probar relaciones en ambos sentidos y `facturaGuia[]` con facturas confirmadas. Tipos 05–07, producción, anulación y correo HKA de guías siguen pendientes.
