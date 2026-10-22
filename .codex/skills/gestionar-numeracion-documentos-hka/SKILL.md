---
name: gestionar-numeracion-documentos-hka
description: Mantener contratos HKA de numeraciones y controles, la consulta EstadoDocumento y la anulación de NC/ND ordinarias digital DEMO; las asignaciones y la anulación de facturas permanecen fuera.
---

# Contratos de numeración HKA

## Estado de Pro9

El módulo de asignación anticipada y sus perfiles/secuencias/reservas fue retirado del código y del esquema inicial. La configuración vigente usa series y correlativos originales: leer [numeración Pro9](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md). La emisión y conciliación por `EstadoDocumento` de facturas en «Medios digitales» DEMO están implementadas mediante [emitir-facturas-notas-hka](../emitir-facturas-notas-hka/SKILL.md). Eso no implementa asignaciones ni importación histórica; la anulación remota de NC/ND ordinarias digital DEMO usa el contrato descrito abajo; no recrear sus endpoints locales por ejecutar esta skill.

## Referencias del proveedor

Leer [referencia HKA](../../../informes/imprenta_digital_hka_api.md) y contrastar el [Swagger HKA](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json) antes de implementar nuevas operaciones. Los contratos del proveedor no determinan el esquema local.

- Número documental: correlativo local por sucursal, tipo y serie; la serie local puede estar vacía. Número de control: identificador de imprenta independiente; conservarlo como texto, con prefijo y ceros. Un documento 16 puede tener control `00-00000021`.
- Traducción Pro9 → HKA: Factura `01→01`, crédito `07→02`, débito `08→03`, entrega `09→04`. No modificar códigos internos ni catálogos por los del proveedor.
- Todas las operaciones autenticadas usan JWT y host HTTPS del ambiente de empresa, según [conexión HKA](../conectar-api-hka/SKILL.md). Nunca devolver credenciales/JWT ni mensajes crudos del proveedor.
- `POST /api/AsignarNumeraciones` utiliza `detalleAsignacion`; sus detalles de reserva no sustituyen una verificación de tipo, serie, rango y cobertura. Es una mutación remota; no llamar al guardar una serie.
- `POST /api/ListadoAsignaciones` permite comprobar documento/control, tipo y serie, recorriendo todas las páginas necesarias. Exigir cobertura completa, datos coherentes y ausencia de documentos/controles duplicados. No deducir continuidad de controles no consecutivos ni importar el correlativo del proveedor.
- `POST /api/ConsultaNumeraciones` recibe filtros opcionales `serie`, `tipoDocumento` y `prefix`; sin filtros enviar un objeto `{}`, no `[]`. Sus rangos maestros son información del proveedor, no solicitudes de asignación ni secuencias documentales locales. Preservar prefijo `00`, códigos desconocidos y estado sin interpretación inventada.
- `POST /api/ConsultaReservaciones` publica `serie`, `numeroDocumentoFin`, `numeroControlFin`, `fechaReservacion`, `rangoMaestro` y paginación. No fabricar extremos iniciales ni declarar un control libre con una lectura parcial. No confundir ausencia válida con fallo de consulta.
- Variantes observadas anteriormente: ConsultaNumeraciones `201` sin filas; ListadoAsignaciones HTTP 200/código `201`, sin validaciones ni filas, página 1 y cantidad de página cero; ConsultaReservaciones HTTP exitoso/código `200`, sin validaciones, `reservas=[]`, `totalPaginas=0`, `numeroPagina=1`, `totalReservasPagina=0`, puede omitir `cantidadReservas`. Verificar el contrato actual antes de reutilizarlas; cualquier conteo presente debe ser coherente. No trasladar códigos entre operaciones.
- Timeout o respuesta incompleta de una mutación no acredita rechazo: resolver el resultado antes de reenviar, sin reintentos automáticos que dupliquen operaciones. Las consultas son de lectura; no confirman emisión por sí solas.
- Swagger no publica liberación de controles reservados. El borrado local no los libera; `Anular` trata documentos, no liberación de reservaciones.

## Conflictos de numeración de NC

Aplicar [contrato y evidencia del conflicto](../../../informes/notas_credito_debito_hka.md#conflicto-de-numeración-en-nc--21102026). Para NC ordinarias internas `07` / HKA `02`, digitales DEMO, la ausencia acreditada por `transaccionId` requiere consultar también por tipo, serie y número originales antes de permitir otro envío. `HkaCreditNumbering` confirma conflicto sólo con tipo y número coincidentes, control válido y transacción remota presente y diferente; cotejar la serie si HKA la devuelve. Que la otra operación esté Anulada no libera el número.

Conservar la evidencia ligada al UUID e intento actuales en el JSON de emisión. Un conflicto verificado permanece ante consultas fallidas: mantener `uncertain` y bloquear el reenvío en servidor y cliente. La recuperación confirmada de la operación original desactiva el conflicto; encontrar la operación propia invalida su evidencia anterior de ausencia. No adoptar el control ajeno ni cambiar correlativos, UUID, importes o inventario para resolver el diagnóstico.

Sólo la ausencia acreditada en ambas consultas permite el reintento existente después de30 segundos. Timeout, respuesta incompleta o identidad contradictoria conservan incertidumbre y bloqueo, sin afirmar duplicidad. Distinguir autorización de reenvío y ausencia de la operación actual: esta última puede permitir anulación exclusivamente local aun con conflicto, respetando las dependencias comerciales y sin llamar a Anular sobre la operación ajena.

Verificar `HkaCreditNumberingTest`, los casos MySQL `test_note_numbering_*` de `FiscalEmissionSchemaTest` y `tests/js/hka-emission-ui.test.cjs`; los comprobantes y controles concretos de evidencia permanecen en el informe.

## Pruebas de nuevas operaciones

Usar HTTP simulado y bases temporales para cualquier nueva integración. La consulta o asignación real necesita autorización correspondiente y credenciales del ambiente. No probar enviando documentos ni alterando configuraciones de tenants reales.

## Anulación de notas ordinarias verificada en DEMO

Leer [contrato y evidencia](../../../informes/notas_credito_debito_hka.md#anulación). `NoteAnnulment` integra Anular para NC02/ND03 ordinarias, con identidad congelada, administrador y motivo; facturas y ND98 siguen fuera. Usa JSON de emisión, sin nuevas tablas. Bloquear cobros, reembolsos y ajustes activos; no revertirlos automáticamente ni utilizar rutas antiguas para cambiar estado.

Cuando existan devoluciones de dinero activas, conservar el diagnóstico de `NoteAnnulment`: **«Para anular esta nota de crédito, primero regularice las devoluciones de dinero asociadas».** Es un impedimento para anular; no presentar una devolución registrada como un error que deba corregirse fuera de esa acción. Para su ubicación en detalle/listado aplicar [acciones de comprobantes](../distribuir-documentos-hka/SKILL.md#acciones-del-listado-de-comprobantes).

HKA devuelve200 al aceptar Anular. Ante resultado desconocido conservar efectos y conciliar sin repetir la mutación. ListadoDocumentos201 vacío no prueba ausencia: DEMO no lista algunas notas anuladas. EstadoDocumento200/Anulada con UUID, tipo, número y control exactos verifica el resultado. No convertir estados desconocidos o203 genérico en autorización para otro intento.

La reversión comercial puede quedar pendiente después de confirmar fiscalmente. Conservar estado13 y recursos reservados; Completar reversión comercial no llama a Anular. Comprobar stock/lotes/seriales antes de transmitir y al aplicar; conservar auditoría, PDF y numeración. Toda prueba real requiere el alcance autorizado y datos sintéticos aislados; la evidencia no autoriza anular notas de clientes.
