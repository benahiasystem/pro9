---
name: mantener-numeracion-fiscal-venezuela-pro9
description: Mantener numeración y emisión fiscal venezolana de Pro9 por establecimiento y canal, controles de imprenta, forma libre, máquina fiscal, contingencia e idempotencia. Usar al modificar secuencias, perfiles fiscales, emisión de facturas y notas, órdenes de entrega o integración de proveedores.
---

# Numeración y emisión fiscal venezolana

## Contrato del producto

- Instalaciones nuevas: editar el esquema consolidado; nunca convertir ni alterar bases reales para verificarlo.
- Serie, número de documento, número de control y registro de máquina son identificadores independientes. No renombrar `series` como control ni exigir `FF01` como formato fiscal.
- El ambiente demo/producción pertenece al tenant y queda bloqueado tras operaciones. Los perfiles de emisión pertenecen al establecimiento y canal; la modalidad única anterior se sustituye por esta política.
- Cubrir 01/07/08 y órdenes de entrega 09. Las notas de venta e inventario conservan numeración comercial. FE y retenciones no se habilitan como nuevas capacidades fiscales en esta entrega.
- Resolver modalidad y capacidades en servidor según el origen real. Los grupos dedicados seleccionan un punto de emisión; una cookie no concede autorización fiscal.
- Contrato de la API autenticada: `operation_key` (alias `clave_operacion`) identifica la operación y debe conservarse con su contenido en reintentos. La cuenta `integrator` usa el canal digital; las cuentas operativas admin/seller usan presencial. Es una decisión de canal del producto, no una regla legal deducida del transporte HTTP. No aceptar canal/perfil/numeración impuestos por el payload ni seleccionar la primera sucursal para una petición sin actor autorizado.
- Para clientes creados desde documentos web/API, resolver parroquia → municipio → estado con `PersonLocation` en la conexión del tenant. No derivar padres con `substr`; conservar ceros iniciales, permitir ubicación omitida y rechazar jerarquías inexistentes, ambiguas, inactivas o asociadas a otro país.
- El prefijo de clave `ecommerce-order-` está reservado al flujo interno, sin distinción de mayúsculas. El contexto público web/API no puede ocupar esas claves; el ID de pedido autorizado se pasa como argumento de servidor, nunca como campo del payload.
- Los pedidos automáticos usan el canal digital y una clave estable derivada del pedido. Su usuario emisor debe configurarse expresamente en el perfil digital de la sucursal (administrador o integrador activo); no atribuir facturas a la primera cuenta disponible. Si la sucursal no está indicada y existen varias posibles, rechazar la selección ambigua.
- Forma libre consume controles preimpresos de imprenta autorizada. Digital obtiene el control de la imprenta. Máquina fiscal devuelve su propia identificación. Los simuladores sólo funcionan en demo.
- No presentar selección de modalidad como conexión comprobada, ni registro comercial como emisión fiscal confirmada.

## Invariantes

- Asignación transaccional con bloqueo e índices únicos. Número de control único por emisor entre todos los documentos consumidores; secuencia documental independiente por tipo/serie. Sin reinicio anual automático.
- Idempotencia por operación lógica: un reintento no duplica documento, control, pago ni inventario. Una respuesta incierta exige consulta/conciliación antes de reenviar.
- Inventario de notas: los motivos vigentes de crédito 01 (anulación total) y 07 (devolución parcial) pueden restituir mercancía. Los motivos 04/09 y las notas de débito son ajustes monetarios y no generan movimientos físicos. Rechazar/anular revierte únicamente el efecto físico aplicable, una sola vez; actualizar otro campo o pasar de rechazado a anulado no repite la reversión.
- Las reservas de stock de pedidos se guardan en `orders.stock_reservation`, con cantidades y almacenes verificados contra el pedido persistido. Descontar/liberar y facturar comparten orden de bloqueo emisor → pedido → existencias. Al facturar, liberar la reserva previa y registrar el movimiento definitivo dentro de la misma transacción, conservando el almacén reservado. No reconstruir devoluciones a partir del estado actual del pack ni de cantidades enviadas por el navegador.
- La conversión automática de pedidos a Nota de venta también debe conciliar la reserva dentro de su transacción y recuperar la NV existente en reintentos. Liberar una reserva vuelve a leer el pedido bajo bloqueo; una copia antigua del objeto no autoriza devolver existencias otra vez.
- La conversión de pedidos guarda la factura y el vínculo del pedido en la transacción de reserva, con bloqueo del pedido y comprobación de que su compra persistida no cambió durante la preparación. El contexto de origen se pasa como argumento interno, nunca se toma de campos enviados por el consumidor de facturas.
- Los documentos conservan instantánea fiscal y registro de intentos. No reciclar números comprometidos, anulados o inutilizados. Reimprimir no asigna otro número.
- Los lotes no se superponen; no emitir con lotes agotados. No inventar autorizaciones, rangos ni QR de proveedores.
- Contingencia explícita y trazable: `FiscalContingencyService` vincula una reserva física mediante `parent_reservation_id` único, conserva el vínculo comercial y la instantánea original, y no escribe pagos ni inventario. Requiere administrador activo de la sucursal, causa, perfil compatible y validación del PDF antes de confirmar la reserva. La original pasa a `contingency`; `FiscalReservation::effective` resuelve el control físico en estado, impresión y referencias de notas. No sustituir emisiones confirmadas o inciertas: primero conciliar; admitir originales sin envío o resultados de rechazo/ausencia verificados. Reintentar conserva el mismo control y causa. El prefijo `contingency-` es interno y se rechaza en altas públicas.
- Configuración con Guardar/Cancelar, entero positivo sin coerción silenciosa a 1 y bloqueo después del uso. Código de sucursal interno, independiente del control.
- Credenciales cifradas y excluidas de logs/respuestas; permisos administrativos y aislamiento tenant en todos los endpoints.

## Fuentes y verificación

Leer [referencias normativas](references/normativa.md) al cambiar reglas legales. Consultar [el informe de implementación](../../../informes/numeracion_fiscal_venezuela.md) para el estado real: un requisito documentado no significa que ya esté implementado.

La política sustituye las restricciones anteriores de modalidad única y emisión exclusivamente local para el alcance indicado. No restaura SOAP/XML/CDR/SUNAT. Conservar IVA venezolano y eliminar la opción peruana `has_igv_31556` y sus consumidores.

Aplicar `reconstruir-migraciones-tenant` al esquema y `frontend-build` a fuentes Vue; no compilar sin petición explícita. Marcar bloques propios con `######## INICIO NUMERACIÓN FISCAL VENEZUELA ########` y su FIN correspondiente. Probar comportamiento, concurrencia, permisos, recuperación, PDF y canales, además de sintaxis. No declarar integración real basándose en simuladores. Validar este skill con `quick_validate.py`.

Para cambios en reservas o transacciones, ejecutar también `docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php vendor/bin/phpunit tests/Unit/FiscalMySqlConcurrencyTest.php`. Usa bases temporales y procesos independientes para ventas distintas, reintentos, conflictos de contenido y agotamiento del último control. Incluye también carreras entre reserva/liberación de stock del pedido y su conversión fiscal. Sus efectos comerciales son fixtures; no presentarlos como aceptación de pagos/inventario reales de Facturalo.

Para verificar el contrato HTTP de configuración, ejecutar `docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php vendor/bin/phpunit tests/Unit/FiscalEmissionSchemaTest.php --filter test_http_kernel`. El trabajador arranca los proveedores, rutas y kernel reales contra bases system/tenant temporales separadas; verifica configuración, factura DEMO digital y forma libre con pago, inventario, PDF, reintento y confirmación de impresión; fija hostname y sesiones web de prueba, y verifica tokens Bearer de usuarios API temporales. No presentar esa evidencia como prueba de login, resolución DNS/Hyn real, interfaz visual ni cobertura HTTP de todos los documentos/canales.
