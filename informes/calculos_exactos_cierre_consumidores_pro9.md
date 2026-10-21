# Consumidores exactos y auditoría de Pro9

19 de octubre de 2026. **Implementación parcial: el cierre global sigue abierto.**

Esta integración prepara y agrupa partidas de notas en servidor, conserva sus fracciones y restaura moneda/tasa en los tres formularios de factura. Corrige la selección del primer precio al agrupar un producto y una pérdida de céntimos al leer descuentos JSON. Incluye pruebas y medición HTTP de esa preparación. Todavía no acredita el guardado de la conversión ni los demás bloques del plan.

## Estado actual

| Fuente | Candidatos | Clasificados | Pendientes |
| --- | ---: | ---: | ---: |
| PHP/Blade | 23.365 | 5.979 | 17.386 |
| JavaScript/Vue | 23.182 | 4.262 | 18.920 |
| **Total** | **46.547** | **10.241** | **36.306** |

La referencia de esta integración tenía 46.534 candidatos, 9.946 clasificados y 36.588 pendientes. Ahora hay trece candidatos adicionales y 295 clasificaciones adicionales: **282 pendientes menos**, sin equivaler a 282 errores monetarios corregidos. Se añadieron siete revisiones manuales y 288 pruebas AST individuales, incluidas 279 comparaciones JavaScript de presencia con literal `null`. La revalidación de la evidencia del contrato conservó el código y la prueba previos de `Decimal::represent`. El historial de las ocho revisiones manuales afectadas queda conservado.

Hay 557 revisiones manuales y 9.684 demostraciones AST vigentes. Las pruebas de presencia no aprueban aritmética anidada, comparaciones numéricas, igualdad con booleanos ni el identificador `undefined`; no se trasladan a PHP. No se autorizaron carpetas ni funciones completas.

Ambos `--check` terminan con código 1 sólo por pendientes; no hay errores de análisis, evidencias inválidas, aprobaciones huérfanas ni fuentes omitidas según la política vigente. Se analizaron 4.678 archivos únicos. Esta cobertura sintáctica no acredita llamadas indirectas o SQL dinámico.

## Cambios de esta integración

- `POST sale-notes/items` lee fuentes autorizadas y vigentes bajo corte consistente, con partidas en lotes de 500. Conserva `data` y añade `grouped_items`, `conversion_context` y trazabilidad por partida.
- La agrupación distingue precio racional, unidad/presentación y condiciones fiscales/comerciales. Separa las partidas con lotes/seriales; el stock consultado no determina un grupo de precio.
- Cantidades e importes, incluidos descuentos fijos, se acumulan desde n/d. Las cachés son locales y acotadas; sus identidades usan JSON, condiciones u operandos completos, nunca representaciones limitadas.
- Los JSON persistidos se leen lexicalmente antes de operar. Dos descuentos numéricos de `1234567890123.12`, sin `amount_exact`, sumaban `2469135780246.2` en la primera implementación del lector. La regresión HTTP exige ahora `2469135780246.24`. No se reconstruye precisión perdida previamente por MySQL u otros productores.
- Se conserva la forma de objetos/listas, incluidos objetos vacíos. Un snapshot de versión inválida o un operando ausente se rechaza incluso tras un acierto previo de caché.
- El modal deja de sumar cantidades con `parseFloat` y de aplicar el primer precio a todas las partidas del mismo producto. Los tres formularios conservan filas, fracciones y metadatos del servidor; restauran moneda y tasa histórica antes de importarlas.
- Selecciones incompatibles de cliente, sucursal, moneda o tasa se rechazan explícitamente. El modal muestra errores y no sustituye una tasa ausente por la actual.
- Si falla el almacenamiento de la selección en el navegador, se limpian las claves parciales y no se abre la factura. El contexto se publica después de partidas/notas; las selecciones nuevas eliminan borradores y lotes heredados. Esto no es una transacción entre pestañas ni una aceptación visual del navegador compilado.

La preparación **no escribe ni recalcula documentos guardados**. Se preservan los cambios anteriores de cobros/vuelto, saldos, IGTF y controles centrales; su evidencia histórica está en el [informe anterior](/home/benahia/benahia/pro/Pro9/informes/evidencias/calculos_exactos_pro9/cierre_integracion_2026_10_19/informe_anterior.md).

## Rendimiento HTTP del consumidor preparado

Se conservaron commit, diferencias y fuentes iniciales, incluidas las no confirmadas. La referencia es `cab49e553230e48d3cf962261e5f6c961f6ffb13` más ese árbol inicial. El controlador anterior se carga desde su copia congelada y las dependencias PHP comunes permanecen iguales. Dos servidores HTTP alternan kernel/rutas reales, sesión cifrada, CSRF y MySQL 5.7 temporal, con tres calentamientos y 30 muestras por variante/carga.

La última repetición registró:

| Partidas | Referencia p95 ms | Actual p95 ms | Límite por comparación ms | Consultas actuales máximas |
| --- | ---: | ---: | ---: | ---: |
| 1 | 224.15 | 262.98 | 324.15 | 16 |
| 100 | 348.32 | 361.41 | 448.32 | 16 |
| 1000 | 597.03 | 592.25 | 716.43 | 18 |

También cumplió los límites de la primera línea base de esta integración. Con 1.000 partidas, la memoria máxima actual fue 23.072.768 bytes. Los JSON conservan muestras, p50/p95, memoria y consultas. Las consultas cuentan el kernel tras bootstrap; la latencia incluye bootstrap y transporte.

**Hay variabilidad sin causa demostrada:** otra ejecución sobre el mismo PHP final falló a 1.000 partidas, con p95 actual 1.152,84 ms frente a 908,80 ms de referencia y límite 1.090,56 ms; también excedió la línea base inicial. Se conservan ese fallo, la repetición satisfactoria y los datos de carga disponibles. Una repetición satisfactoria no certifica estabilidad global ni borra el fallo anterior.

Se conservan además mediciones intermedias que excedieron presupuesto, el error de tratar texto adicional como JSON y la regresión numérica de descuentos. Las optimizaciones reutilizan decodificaciones, claves y racionales repetidos, sin limitar decimales ni añadir tolerancias monetarias.

**Alcance limitado:** selección de 1/100/1.000 partidas de precio 500 con IVA 16 %, más casos funcionales de distintos precios. No certifica facturación/POS, caja/reportes de 1.000/10.000 operaciones, mezclas diversas a escala ni instalación central separada. El fixture HTTP comparte un esquema temporal entre system/tenant. Esta referencia no acredita cambios anteriores ni cierra el bloque completo de ventas/conversiones.

## Aceptación

- JavaScript: **229 pruebas**, sin fallos ni omisiones. Ejecutan el modal, los tres importadores, rechazo de contexto inválido y fallos de cuota en las dos variantes de selección; compilan componentes en memoria.
- Agrupación y fronteras JSON PHP: **14 pruebas y 42 aserciones**, sin fallos. Incluyen precios subdoce, fracciones periódicas, descuentos y rechazo de snapshots/operandos inválidos.
- HTTP final: **una prueba y 3.306 aserciones**, sin fallos. Incluye autenticación, CSRF, comparación de rendimiento, contextos incompatibles, objetos vacíos y la suma de descuentos JSON numéricos.
- PHP integral con MySQL habilitado: **948 pruebas Unit y 22.893 aserciones**, sin fallos, errores ni omisiones. La ejecución conjunta de 949 casos falló únicamente en `Feature/ExampleTest`: buscaba la base de desarrollo `multifacturalo_dusk`. Se aisló su consulta de hostnames en SQLite en memoria, conservando el middleware, y se repitió Feature: **una prueba y 3 aserciones**, sin fallos. No se repitió la corrida completa después de cambiar únicamente ese fixture; producción y Unit permanecen idénticos. Se conservan el fallo y la repetición. Las ejecuciones interrumpidas anteriores y las 939 pruebas de otra continuación no se acreditan como esta aceptación.
- Habilidad validada. Los hashes de las 5.990 fuentes distinguen la ejecución integral y el estado final; sólo cambió después el fixture Feature descrito. Los informes/evidencias se actualizan fuera de ese conjunto. No se ejecutó build/watch ni se modificó `public/build`; compilación y aceptación visual corresponden al usuario.

## Pendientes para cerrar el plan

1. Resolver y bloquear de nuevo las fuentes al persistir la conversión (ver el [recorrido con referencias verificadas](/home/benahia/benahia/pro/Pro9/informes/evidencias/calculos_exactos_pro9/cierre_integracion_2026_10_19/nota_conversion_recorrido.json)); reconstruir grupos autorizados, reconciliar cabeceras, anticipos, impuestos y cobros, y cubrir parciales, concurrencia e idempotencia. Las dos representaciones nuevas de agrupación siguen pendientes en el auditor por ese recorrido abierto.
2. Migrar consumidores de borradores heredados `itemsForNotes`; verificar capacidad, recuperación y concurrencia del traslado entre pestañas con frontend compilado. La limpieza de un fallo de almacenamiento no acredita guardado, inventario ni movimiento de dinero.
3. Completar productores Web/POS/Garage/API y el reemplazo diferido de partidas pendiente en otros controladores. Continuar revisando escrituras de estados comerciales durante lecturas/PDF y aritmética residual.
4. Completar cobros, tesorería, productos/compras/inventario/costos, Dashboard/contabilidad, administración central/pasarelas y los 77 grupos de la matriz. Sigue pendiente el ciclo central de intento, bloqueo de edición monetaria/nuevo intento, conciliación, verificación de respuesta y reversión.
5. Completar reportes, PDF/tickets, importaciones/exportaciones, consumidores indirectos y los **36.306 candidatos**. No renovar hashes sin revisión ni aprobar proyecciones que todavía alimentan cálculos.
6. Ejecutar la matriz HTTP completa y verificar repetibilidad del rendimiento, con aceptación tenant/central, semillas, rollback y reinstalación pendientes de cierre integral. Vendeya, Mozo y LiveApp mantienen cierre externo separado.

## Entrega

La [matriz de consumidores](/home/benahia/benahia/pro/Pro9/informes/calculos_exactos_cierre_consumidores_matriz.json), inventarios JSON/JSONL, registro individual, habilidad y contrato reflejan este estado parcial. La [evidencia de esta integración](/home/benahia/benahia/pro/Pro9/informes/evidencias/calculos_exactos_pro9/cierre_integracion_2026_10_19/) contiene referencia y fuentes comprimidas, parche frente al árbol inicial, clasificaciones individuales nuevas, revisiones manuales históricas, pruebas, comparaciones HTTP y fallos conservados.

No se modificaron migraciones, bases instaladas ni documentos históricos. El fixture de la prueba raíz no certifica una instalación central MySQL. No hubo emisiones HKA ni cobros reales. Se mantiene el PDF local con precio unitario configurable 2–12, desactivado y dos por defecto; demás importes a dos y originales HKA intactos. El cierre final requiere cero pendientes, controles satisfactorios y aceptación integral; **todavía no se cumple**.
