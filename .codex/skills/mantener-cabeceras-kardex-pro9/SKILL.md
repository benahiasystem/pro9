---
name: mantener-cabeceras-kardex-pro9
description: Mantener la nomenclatura de los encabezados del Kardex de inventario de Pro9, alineada con el uso administrativo venezolano, en pantalla y exportaciones.
---

# Encabezados del Kardex de Pro9

Usar esta convención al crear o modificar la vista del Kardex, sus reportes PDF o sus exportaciones Excel. Cambiar únicamente los textos de los encabezados; no alterar los valores, nombres internos, filtros, cálculos ni tipos de movimiento.

## Encabezados obligatorios

| Texto anterior | Texto vigente |
| --- | --- |
| `Consulta kardex` | `Consulta de Kardex` |
| `Fecha y hora transacción` | `Fecha y hora del movimiento` |
| `Tipo transacción` | `Tipo de movimiento` |
| `Número` | `N.º de documento` |

## Aplicación

- Mostrar `Consulta de Kardex` en el título o breadcrumb del módulo.
- Usar los tres encabezados de columnas exactamente como están escritos, incluyendo la abreviatura `N.º` y sus puntos.
- Mantener la misma nomenclatura en la tabla Vue, el reporte PDF y la exportación Excel.
- No reemplazar `Nota de venta`, `Pedido`, `Entrada`, `Salida` o `Saldo` como parte de este cambio; son campos distintos y requieren una decisión funcional independiente.
- No cambiar etiquetas por nombres internos como `type_transaction`, `number` o `date_time`.

## Verificación

Después de un cambio, buscar las etiquetas antiguas en las fuentes activas del Kardex y confirmar que los cuatro textos vigentes estén presentes en la vista y en las salidas descargables. No editar `public/build/` manualmente; la compilación de assets queda a cargo del usuario.
