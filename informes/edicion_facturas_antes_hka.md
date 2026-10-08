# Edición de facturas antes del registro HKA

Implementación del 7 de octubre de 2026, según la regla autorizada por el usuario.

## Política

`DocumentEditPolicy` permite editar facturas vigentes sin control remoto en `not_requested`, `prepared`, `rejected` y `uncertain` únicamente cuando una consulta acreditó ausencia (`retry_allowed`). Bloquea `pending`, incertidumbre sin conciliar, confirmación, cancelación y anulación. Los indicadores públicos `can_edit`, `is_editable` y `edit_block_reason` usan la misma política y respetan la sucursal del usuario. El antiguo indicador persistido no impide editar facturas pendientes de registro.

## Persistencia

`Facturalo::update` bloquea empresa, documento y emisión en ese orden. Conserva identidad, moneda, emisor, usuario original, referencias comerciales, cuotas y todos los cobros existentes. No reinserta pagos ni aplica nuevamente retenciones o fondos. Recalcula líneas, IVA, saldo y el PDF, y rechaza importes inferiores a lo aplicado. Con retenciones conserva el cliente agente y exige que el nuevo IVA respalde base e importes retenidos. Las direcciones seleccionadas deben pertenecer al cliente; el comprador se vuelve a obtener del catálogo autorizado incluso si no cambia de cliente.

Una operación preparada, rechazada o conciliada como ausente se invalida dentro de la misma transacción: nuevo UUID, sin payload y estado `not_requested`. `response.edit_history` conserva UUID anterior, estado, código, diagnóstico saneado, hash del payload, actor y fecha. Los intentos de envío conservan ese historial. Los errores anteriores a la transmisión de una factura aún no preparada se limpian después de editar. Guardar la edición no envía HKA.

Las respuestas finales se vinculan al intento reclamado. La preparación, reclamación y fallos de autenticación se vinculan también al UUID para impedir que intentos antiguos afecten una operación invalidada. El HTTP continúa fuera de los bloqueos.

## Interfaz

El listado, detalle y formulario muestran el motivo del bloqueo. Serie, moneda y condición de pago quedan protegidas. Pagos, retenciones y fondos existentes se muestran en lectura. La edición no crea otro registro de caja al regresar al diálogo compartido. El contrato de guardado devuelve la política y el resultado fiscal actualizado.

## Evidencia

- Suite completa `FiscalEmissionSchemaTest` con `PRO9_FISCAL_MYSQL_TESTS=1`: 4 pruebas, 1899 aserciones, MySQL temporal.
- Verificación final específica de emisión/edición: 1 prueba, 157 aserciones; incluye carrera real de procesos, respuestas tardías, fallo tardío de autenticación y preparación con UUID invalidado.
- Pruebas afectadas de política, autorización, cálculos, transformación de pagos, payload, transporte, respuestas y PDF aprobadas.
- 12 pruebas JavaScript afectadas aprobadas; análisis de scripts Vue y compilación de plantillas sin errores. No se ejecutaron comandos de build ni se editaron bundles.
- Cobertura de preparación invalidada, rechazo corregido, ausencia conciliada, pendientes/confirmados bloqueados, rollback, totales inferiores, retenciones/fondos conservados, concurrencia y respuestas tardías.
- PHP lint y `git diff --check` sin errores.

En `bbc.localhost` se guardó la edición de la factura «Sin serie» nº 5, ID 17, con la dirección proporcionada por el usuario: «Carupano». Conservó total Bs. 100, saldo cero, un único pago, identidad, moneda, registros de caja y existencias. El PDF regenerado contiene la dirección. Su estado continuó `not_requested`; no se transmitió la factura durante esta aceptación.

La compilación corresponde al usuario según `frontend-build`. La actualización externa de los assets permitió verificar el formulario en el navegador.
