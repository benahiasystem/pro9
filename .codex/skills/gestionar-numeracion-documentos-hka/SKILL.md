---
name: gestionar-numeracion-documentos-hka
description: Preparar asignación y consultas de controles, estado/listado de documentos y anulación en la imprenta digital HKA. Usar para AsignarNumeraciones, ConsultaNumeraciones, ConsultaReservaciones, ListadoAsignaciones, UltimoDocumento, EstadoDocumento o Anular.
---

# Numeración y ciclo de vida HKA

Leer [referencia HKA, secciones 1, 3, 6 y 10–12](../../../informes/imprenta_digital_hka_api.md) y el [contrato de numeración fiscal Pro9](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md).

- Pro9 conserva número, serie, secuencia, reserva y reglas de unicidad. El control HKA es una identidad externa a conciliar, no un sustituto de la numeración interna. Traducir `01→01`, `07→02`, `08→03`, `09→04` por tipo de documento exclusivamente al llamar HKA y leer su respuesta. No cambiar `FiscalProfileService::TYPES` ni validaciones existentes.
- Al asignar, relacionar rango/serie/tipo HKA con la reserva local sin crear otra operación comercial. Si una emisión o anulación queda incierta, consultar el estado de la misma identidad antes de reintentar. Mantener trazabilidad de controles asignados, anulados y en contingencia.
- Swagger también publica `ConsultaNumeraciones`, `ConsultaReservaciones`, `ListadoAsignaciones` y `UltimoDocumento`; sus correlativos y rangos son observaciones del proveedor, nunca fuente para avanzar o reemplazar la secuencia Pro9. La respuesta de asignación contiene `rangosAsignados[]` y `detallesReserva[]`; validar identidad antes de confirmar.
- Confirmar con HKA la secuencia exacta entre asignación y emisión, serie vacía, semántica de `201` duplicado y paginación. El Swagger sólo documenta HTTP 200: revisar `codigo` y `validaciones`. Aplicar permisos y aislamiento de [seguridad Pro9](../mantener-seguridad-pro9/SKILL.md). Para contingencia externa usar `Contingencia/*` sin sustituir la cadena de contingencia interna.
