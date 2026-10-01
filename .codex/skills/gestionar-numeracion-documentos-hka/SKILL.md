---
name: gestionar-numeracion-documentos-hka
description: Consultar contratos HKA de numeraciones, controles, documentos y anulación para una integración posterior. Usar al trabajar con AsignarNumeraciones, ConsultaNumeraciones, ConsultaReservaciones, ListadoAsignaciones, UltimoDocumento, EstadoDocumento o Anular.
---

# Contratos de numeración HKA

## Estado de Pro9

El módulo de asignación anticipada y sus perfiles/secuencias/reservas fue retirado del código y del esquema inicial. La configuración vigente usa series y correlativos originales: leer [numeración Pro9](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md). La autenticación de empresa se conserva. No recrear endpoints locales de asignación ni importar/conciliar historia por ejecutar esta skill. La emisión automática HKA requiere una tarea posterior explícita.

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

## Pruebas futuras

Usar HTTP simulado y bases temporales para cualquier nueva integración. La consulta o asignación real necesita autorización correspondiente y credenciales del ambiente. No probar enviando documentos ni alterando configuraciones de tenants reales.
