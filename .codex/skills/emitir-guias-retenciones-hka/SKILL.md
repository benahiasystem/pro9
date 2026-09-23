---
name: emitir-guias-retenciones-hka
description: Documentar o preparar la emisión HKA de guías/órdenes de entrega y comprobantes de retención IVA, ISLR y ARC. Usar al trabajar con tipos HKA 04–07, relaciones factura-guía, transporte, sujeto retenido o nodos ARC.
---

# Guías y comprobantes HKA

Leer [referencia HKA, secciones 1, 4.5–4.6, 5 y 10–12](../../../informes/imprenta_digital_hka_api.md), [órdenes de entrega Pro9](../mantener-ordenes-entrega-pro9/SKILL.md) y [numeración fiscal](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md).

- La orden de entrega Pro9 `09` se traduce a HKA `04` sólo en el flujo HKA. Conservar rutas, tablas, nombre comercial, numeración y validaciones internas de dispatch. HKA `05`, `06` y `07` son retenciones, sin equivalencia fiscal interna aprobada: no inventar tipos Pro9 ni recodificar la nota de crédito `07`.
- Para `04`, usar el esquema normal `POST /api/Emision` y revisar `guiaDespacho`, conductor, vehículo, transportista y `facturaGuia[]`; `/api/Documentos/Relacionar` administra el vínculo externo factura–guía (`01`/`04`). Para `05`/`06`, revisar `sujetoRetenido`, `totalesRetencion` y `detallesRetencion[]` del mismo endpoint. Para `07`, usar **otro esquema y ruta**, `POST /api/EmisionARC`, con `beneficiario`, totales y detalles ARC; no enviarlo al esquema normal que admite sólo `01`–`06`.
- El Swagger añade constancia DNF tipo `99` por `POST /api/EmisionDNF/constanciaRecepcion`; no tiene tipo Pro9 aprobado ni pertenece al alcance de los siete documentos del PDF. No asumir que los campos no marcados obligatorios son prescindibles ni fabricar payloads completos para `04`–`07`: el PDF no los incluye. Contrastar Swagger y respuestas DEMO antes de habilitar emisión.
- Mantener la autorización y aislamiento de [seguridad Pro9](../mantener-seguridad-pro9/SKILL.md); no afirmar soporte fiscal productivo a partir de formularios o simuladores existentes.
