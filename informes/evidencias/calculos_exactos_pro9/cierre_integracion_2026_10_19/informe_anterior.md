# Consumidores exactos y auditoría de Pro9

19 de octubre de 2026. **Implementación parcial: el cierre global del plan sigue abierto.**

La continuación reduce los pendientes de **37.144 a 36.588**. Se completaron correcciones de cobros y lecturas concretas, se reforzó la validación exacta y se ejecutó toda la suite PHP con los casos MySQL habilitados. No se consideran terminados todos los productores, consumidores ni módulos.

## Inventario reconciliado

| Estado | Antes de esta continuación | Actual |
| --- | ---: | ---: |
| Candidatos | 46.553 | 46.534 |
| Clasificados con evidencia vigente | 9.409 | 9.946 |
| Pendientes | 37.144 | **36.588** |
| Aprobaciones huérfanas | 0 | 0 |

| Fuente | Candidatos | Clasificados | Pendientes |
| --- | ---: | ---: | ---: |
| PHP/Blade | 23.349 | 5.965 | 17.384 |
| JavaScript/Vue | 23.185 | 3.981 | 19.204 |

Los 556 pendientes menos combinan sustitución de expresiones, clasificación técnica y revisiones individuales. **No equivalen a 556 fallos monetarios corregidos.** Se analizaron 4.676 archivos únicos. Ambos auditores `--check` terminan con código 1 exclusivamente por pendientes; no hay errores de análisis, evidencias registradas inválidas/obsoletas, aprobaciones huérfanas ni fuentes omitidas según la política vigente. La cobertura sintáctica no acredita cierre interprocedimental o SQL dinámico.

La referencia inicial conserva `cab49e553230e48d3cf962261e5f6c961f6ffb13`, el estado sin confirmar, diferencias comprimidas y hashes de las fuentes existentes. Se preservaron los cambios anteriores; no se creó un commit ni se reconstruyeron datos históricos.

## Cambios de esta continuación

| Recorrido | Resultado y límites |
| --- | --- |
| Cobro inicial de Nota de venta | `TenderAllocation` conserva solicitado, recibido, neto, vuelto y ajuste. Rechaza flotantes y precisión/rango inválidos. Devuelve el exceso desde efectivo 01/10 en orden de entrada, sin importes negativos ni reducir transferencias para simular efectivo. PHP y JavaScript comparten 13 casos. |
| Lote y eventos | `Settlement::defer` bloquea el propietario antes de insertar, calcula el cierre una sola vez y libera el ámbito al fallar. El listener antiguo deja al responsable exacto el cierre de esquemas nuevos. La validación de medios se consulta una sola vez por lote. Se probaron rollback después de una inserción y la siguiente operación correcta. |
| Saldos de Nota de venta | `SaleNoteBalance` calcula desde pagos guardados y total liquidable. El GET de saldo usa corte consistente y no escribe. Listado, diálogo, API de pagos y comprobación de crédito conservan fracciones; no deciden pago completo desde un texto que se representa como cero. La eliminación vuelve a comprobar el saldo en transacción. |
| Lecturas y presentación | El listado formatea desde el snapshot, evitando doble redondeo al cruzar el umbral de medio céntimo y pérdida por `Number`. El recurso de cobros precarga destinos y archivos; aprovecha la relación de banco precargada. Los ajustes inferiores a doce posiciones se muestran como n/d. La selección de partidas para convertir notas incluye ahora su snapshot; los consumidores agrupadores de esas conversiones siguen pendientes. |
| IGTF cobrado | El recibo derivado aplica céntimos y conserva `converted_principal`/`payment_adjustment`. Evita el saldo negativo diminuto de una nota cuyo total exacto era 1,5000000015 y cobrable 1,50. La recepción física continúa en el padre. La pantalla identifica el recibo derivado como IGTF del cobro y conserva el ajuste visible. |
| Validaciones y cierre | `HkaIgtfNote` compara las identidades monetarias con racionales y elimina tolerancias de medio céntimo. El cierre de pagos recupera `payment` del snapshot después de ajustarlo; ya no compara su proyección contra un saldo ligeramente menor. Tras anular una nota, el estado de crédito usa el saldo exacto. |
| Órdenes centrales | Las órdenes nuevas congelan moneda, UUID, importe exacto, cobrable y ajuste en el snapshot. `CentralPaymentQuote::read` rechaza cotizaciones incoherentes. Se exige la indisponibilidad VES en seis POST directos Culqi/Izipay antes de DB/credenciales/red, aunque el cliente envíe USD. La notificación `payment-orders/payment-view/pays` también respeta esa restricción y deja de marcar pagada una orden por el texto del navegador: sólo devuelve el estado guardado, sin SQL de escritura. Los adaptadores en otros contextos, respuestas vinculadas, idempotencia y reversión siguen pendientes. |
| Infraestructura de aceptación | `test_exact_isolated_mysql.php` crea y elimina un servidor MySQL 5.7.33 temporal con imagen local. Tres fixtures antiguos ahora respetan `DB_PORT`, permitiendo ejecutar sus casos en ese servidor. No cambia ajustes de la instancia de la aplicación. |

Permanecen disponibles los avances anteriores: participaciones exactas de cabecera en el consolidado, escrituras acotadas y transaccionales, lectura congelada o derivación histórica sólo en memoria, cuentas por cobrar/pagar con equivalentes VES y desglose original, `ReadSnapshot`, almacenamiento temporal de reportes y widgets de deudores/stock. Compartir esas bibliotecas no acredita sus productores o consumidores aún pendientes.

## Clasificación individual

Hay **550 revisiones manuales vigentes** y **9.396 pruebas AST individuales**: 7.465 igualdades con texto no numérico, 1.356 guardias de tipo, 71 uniones PHP con literal de arreglo, 23 formatos de fecha resueltos y 481 igualdades JavaScript con texto cuya conversión numérica es NaN.

Esta continuación documenta 68 revisiones manuales, incluidas revalidaciones justificadas de expresiones conservadas; aporta 53 incorporaciones manuales netas. Se archivaron cuatro aprobaciones de expresiones sustituidas, sin trasladarlas a otros IDs. Las evidencias ampliadas se revalidaron comprobando expresión y fuente idénticas y conservando su explicación; no se renovaron permisos por carpeta.

La nueva prueba de igualdad JavaScript excluye decimales, hexadecimal, Infinity y blancos. También se incorporó y probó la capacidad de demostrar comparaciones de contadores PHP nativos resueltos con enteros literales; nombres desconocidos y operandos monetarios quedan pendientes. Ninguna prueba aprueba cálculos anidados. Las evidencias inválidas/obsoletas producen ahora diagnóstico explícito y mantienen el candidato pendiente.

## Pruebas y evidencia

| Verificación | Resultado |
| --- | --- |
| Suite PHP completa con MySQL habilitado | **939 pruebas, 22.823 aserciones, cero fallos, errores y omisiones** |
| Casos MySQL dentro de esa suite | 53 fiscales, siete de numeración concurrente, uno BCV, uno de precisión de tasas y uno de esta continuación: **63 casos**, antes omitidos sin habilitación |
| Regresiones finales repetidas | Tres escenarios de persistencia/IGTF/concurrencia y el cierre ampliado de posición veinte: **4 pruebas, 153 aserciones, sin fallos** |
| Notificación central y moneda sobre las fuentes finales | **6 pruebas, 39 aserciones**, incluida la negativa de cuatro textos de éxito sin escritura; **8 pruebas de moneda, 49 aserciones**. Los dos casos nuevos centrales son posteriores a la suite integral de 939. |
| JavaScript | **220 pruebas**, cero fallos/omisiones; incluye ejecución de helpers y compilación en memoria de componentes, sin build de assets |
| Sintaxis PHP | 35 archivos modificados/nuevos en esta continuación, sin errores |
| Habilidad y diferencias | Habilidad validada; `git diff --check` limpio |

La suite integral comenzó antes de los últimos ajustes; los escenarios afectados se repitieron después sobre las fuentes finales. El caso de cierre conserva exactamente un saldo `1 - 1/10²⁰`, aunque su representación de doce posiciones sea 1, y termina en saldo cero con ajuste trazable.

Se conserva la primera aceptación fiscal de 53 casos y sus tres fallos. Se corrigieron dos expectativas de precisión: equivalencia de exportación almacenada y total exacto de nota IGTF. La simulación de carrera de stock actualizaba sólo DECIMAL y dejaba la fracción anterior; ahora utiliza el escritor de modelo que conserva ambos valores. La aceptación detectó además el recibo derivado IGTF aplicado por encima del cobrable, corregido y probado sin ocultarlo mediante tolerancias. Todos los intercambios HKA de las pruebas usan dobles; no hubo emisión real ni cargos a proveedores.

## Rendimiento y condiciones de entrega

**La aceptación de rendimiento HTTP sigue pendiente.** Se conservó la referencia de fuentes al iniciar esta continuación, pero no se capturó una línea base HTTP antes de estas modificaciones ni una medición HTTP posterior. No se presenta el tiempo de las suites como p50/p95 ni se declara cumplido el presupuesto de este tramo.

La medición backend/MySQL anterior de cuentas por pagar permanece en su evidencia histórica; no sustituye HTTP, POS, caja ni los nuevos consumidores. Antes de cerrar los bloques restantes se exige el presupuesto acordado, calentamiento, 30 muestras, memoria y consultas con datos/entorno equivalentes.

No se modificaron migraciones ni bases instaladas ni se recalcularon históricos. El contrato PDF se mantiene: precio unitario local configurable 2–12, desactivado y dos por defecto; demás importes a dos. Los originales HKA conservan sus bytes. **La compilación y aceptación visual del frontend siguen a cargo del usuario**; no se ejecutó build/watch ni se editaron bundles.

## Pendientes de cierre global

1. Completar Web/POS/Garage/API y conversiones, incluidos agrupadores que aún usan precios/cantidades limitados. Incorporar sus pruebas HTTP completas, notas parciales, documentos convertidos y salidas.
2. Completar idempotencia/reversión auditada de los demás cobros, cuotas, retenciones, garantías, devoluciones, gastos y préstamos; caja, bancos y dinero efectivo con su tasa propia, sin duplicar derivados.
3. Migrar los demás KPI/metas/gráficos del Dashboard, indicadores, comisiones y libro contable. El cambio de estos cobros no acredita sus agregados o costos.
4. Completar productos, presentaciones, compras, inventario/Kardex/valoración, producción, pedidos, cotizaciones, ecommerce, Marketplace, WhatsApp, Offline, restaurante, hotel, suscripciones, servicio técnico y todos los recorridos de la matriz.
5. Vincular las cotizaciones centrales con los adaptadores y verificar respuesta autorizada, importe, moneda, orden, enviado/liquidado, idempotencia y reversión; mantener las restricciones actuales.
6. Completar importaciones lexicales, Excel/CSV, PDF/tickets, listados y descargas restantes. Retirar compatibilidad flotante sólo después de migrar los productores correspondientes.
7. Resolver individualmente los **36.588 candidatos**, revisar llamadas indirectas/SQL dinámico y obtener ambos `--check` satisfactorios sin exclusiones injustificadas.
8. Completar la aceptación HTTP y el rendimiento de facturación/POS 1/100/1.000 partidas y caja/reportes 1.000/10.000 operaciones. Mantener separado el cierre externo de Vendeya, Mozo y LiveApp; sus endpoints Pro9 siguen dentro del alcance.

La revisión de administración central identifica consumidores concretos pendientes: `PaymentOrderPublicController::culqiCharge` todavía toma importe y moneda del request y decide éxito por `outcome`; `izipayPayment` envía importe/moneda/orden recibidos; `izipayTransaction` confirma `PAID` sin cotejar esos tres datos. El cambio de la notificación antigua no acredita esas confirmaciones independientes. `PaymentGatewayController`, los formularios `CheckoutGuest`/`CheckoutAdmin`/`payment-view` y la renovación de cotización cuando `updateTable` edita el importe necesitan migración conjunta. No se habilitó ninguna pasarela ni se ensayaron cargos reales.

## Archivos de entrega

- [Matriz de consumidores](/home/benahia/benahia/pro/Pro9/informes/calculos_exactos_cierre_consumidores_matriz.json), inventario JSON/JSONL y registro individual actualizados.
- [Resultados de esta continuación](/home/benahia/benahia/pro/Pro9/informes/evidencias/calculos_exactos_pro9/cierre_continuacion_2026_10_19/resultados.json), con logs, XML de la suite, revisiones individuales, aprobaciones retiradas, sintaxis y referencia inicial en la misma carpeta.
- [Habilidad](/home/benahia/benahia/pro/Pro9/.codex/skills/mantener-calculos-exactos-pro9/SKILL.md) y [contrato](/home/benahia/benahia/pro/Pro9/.codex/skills/mantener-calculos-exactos-pro9/references/contrato.md).

Comandos: `php scripts/test_exact_isolated_mysql.php --testsuite Unit`, `node --test tests/js/*.test.cjs` y ambos `audit_exact_calculations --check`. Los auditores deben continuar fallando mientras existan pendientes. La implementación global y su aceptación permanecen abiertas.
