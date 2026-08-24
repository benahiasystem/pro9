---
name: quitar-seleccion-billetes-pos
description: Ocultar los accesos rápidos de denominaciones en el pago efectivo del POS de Pro9, conservando el monto manual, vuelto y los demás métodos de pago.
---

# Quitar la selección de billetes del POS

## Objetivo

Mantener el POS sin botones rápidos de denominaciones al cobrar en efectivo. La regla es global para el flujo POS principal y no cambia cálculos, persistencia ni métodos de pago.

## Contrato

1. Localizar primero la vista activa que renderiza el cierre de pago del POS. No modificar respaldos o variantes sin ruta o registro activo.
2. Al elegir efectivo, no renderizar accesos rápidos equivalentes a `setAmountCash(10|20|50|100)` ni componentes de denominaciones que produzcan el mismo resultado.
3. Conservar el campo manual del monto recibido, su actualización de pagos, el cálculo de faltante/vuelto, pago exacto, validación de insuficiencia, pago múltiple y métodos no efectivos.
4. No eliminar `setAmountCash` ni helpers similares sólo porque dejen de aparecer en la plantilla: su retiro requiere una auditoría independiente de consumidores.
5. Delimitar la supresión con comentarios Vue válidos y los textos exactos `########## INICIO CAMBIO QUITAR SELECCIÓN DE BILLETES` y `######### FIN CAMBIO QUITAR SELECCIÓN DE BILLETES`.
6. Agregar una prueba que cubra la ausencia de denominaciones en la vista activa y la presencia de la entrada manual y el cálculo de vuelto.
7. Si se toca Vue, seguir `frontend-build`; no editar `public/build/` manualmente.

## Verificación

- Buscar las llamadas de denominaciones en fuentes no compiladas y clasificar cada resultado como activo, variante independiente o respaldo.
- Validar la sintaxis de Vue y ejecutar la prueba del contrato POS.
- En una sesión manual del POS: elegir efectivo, introducir un importe exacto, uno mayor y uno insuficiente; comprobar vuelto, validación y pago múltiple.
