# Consumidores exactos y auditoría de Pro9

17 de octubre de 2026. **Implementación parcial; el plan global sigue abierto.**

Esta entrega amplía consumidores y controles reales. No acredita el cierre de todos los módulos ni la clasificación de todos sus redondeos. Las aplicaciones compiladas Vendeya, Mozo y LiveApp mantienen su cierre externo separado; sus servicios presentes en Pro9 siguen dentro del alcance.

## Cambios implementados

| Recorrido | Comportamiento y evidencia |
| --- | --- |
| Entrada JSON | `PreserveJsonDecimals` conserva los tokens decimales y exponentes como texto antes de los normalizadores HTTP. Mantiene los bytes originales para firmas y continúa retirando snapshots de resultados enviados por clientes. No recupera precisión perdida dentro de una app externa. `ExactJsonBoundaryTest`. |
| Lectura y guardado | `Decimal::readRequired` rechaza operandos ausentes y snapshots desconocidos. `HasExactValues` valida proyección, rango y fracción después de `creating`/`updating`, antes del SQL, incluyendo cálculos realizados en `saving`. `ExactLateCalculationTest`, `ExactInputValidationTest`. |
| Conjunto de reportes | `ReportDataset` conserva una copia temporal privada, permite varias lecturas, filtra y compara fracciones antes de paginar. Ordenación externa estable en lotes de 500 y hasta 32 cabeceras de mezcla por pasada. `ExactReportDatasetTest`. |
| Ventas consolidadas: partidas | El total se suma desde cada partida guardada y su snapshot; se elimina el producto incorrecto del precio de la primera partida por toda la cantidad. Conserva la unidad, distingue documento/ID, resta NC y excluye notas de venta convertidas. Convierte cada operación USD usando su tasa histórica y expone el desglose original. Una moneda o tasa necesaria ausente produce un error. **Los ajustes que existen sólo en la cabecera siguen pendientes**, como se detalla abajo. `ExactSalesConsolidatedTest`, `ExactConsolidatedConsumersTest`. |
| Consumidores del consolidado | API, listado, resumen de la página, agrupación por producto/unidad, PDF, tickets de 58/80 mm, Excel y generación en bandeja comparten los DTO exactos. Las vistas dejaron de calcular importes a partir del catálogo. El total del listado se identifica como total de la página. |
| Presentación de reportes | `ReportProjection` reparte los céntimos de representación por mayores residuos firmados, con desempate estable y sin modificar las fracciones. La suma visible coincide con el total representado. `ExactReportProjectionTest`. |
| Cuentas por pagar | Compras, gastos y préstamos opcionales se leen en lotes, con pagos precargados y exclusión de reversiones cuando existe su campo. El filtro de saldo positivo precede a la paginación; los resúmenes cubren el conjunto filtrado. Se distinguen total exacto, liquidable, aplicado, saldo y ajuste. Un tercio liquidado en 0,33 no crea una deuda ficticia de 1/300. Listado, PDF, tres exportaciones y el consumidor de cuentas por pagar del libro contable se adaptaron. **El resto del libro contable y la equivalencia consolidada VES de este reporte siguen pendientes.** `ExactPayablesTest`. |
| Selección de productos | Precios seleccionados por almacén y precio de compra base/con IVA conservan su fracción. Se evita adjuntar un snapshot de stock global a una cantidad de almacén. La primera operación conserva la fracción del precio seleccionado si el usuario no lo cambió. La edición explícita tiene prioridad. **Variaciones y otros productores de precios/stock siguen pendientes.** `ExactProductPricesTest` y regresiones JavaScript. |
| Lotes e importación de stock | La comparación de cantidades de lotes ya no limita a cuatro posiciones. Stock XLSX se lee desde el lexema OOXML, rechaza fórmulas y calcula el movimiento racional bajo bloqueo. Se probó en MySQL la diferencia periódica y repetir el archivo sin generar otro movimiento. `ExactStockWorkbookTest`, `FiscalEmissionSchemaTest`. |
| Recetas: vista previa | Costo efectivo, merma, cantidad, costo de producción y precio sugerido conservan fracciones en PHP/JavaScript. Controles editables envían cadenas y validan precisión. **Consumo, preparación y producción completos siguen pendientes.** `ExactSupplyCostTest`, `exact-recipe.test.cjs`. |
| Excel de estos consumidores | Importes como texto, hoja de numerador/denominador e identidad compuesta del registro. Se rechaza una fracción que exceda el límite de caracteres de Excel antes de que la biblioteca la trunque. `ExactExtensionBoundariesTest`, `ExactConsolidatedConsumersTest`. |

Los cambios conservan las reglas de negocio y el esquema inicial previamente ampliado. Las pruebas de MySQL usan bases efímeras. No se convirtieron bases instaladas, no se recalcularon documentos históricos y no se hicieron emisiones HKA.

Incidencia previa separada de precisión: el antiguo helper etiquetaba los gastos como `sale_note` cuando no había filtro de fechas y como `expense` cuando sí lo había. El DTO identifica ahora su origen como `expense` en ambos casos; esta corrección de identidad no cambia el importe.

## Auditoría individual

Se amplió la búsqueda a aritmética y comparaciones, casts, SQL, escrituras masivas, incrementos/decrementos, controles y expresiones de plantillas. Vue utiliza su AST de plantilla, incluyendo filtros, ramas, clases, eventos, slots y modificadores; no depende de una extracción parcial mediante expresiones regulares. El inventario contiene también operaciones técnicas y falsos positivos explícitos, no sólo redondeos monetarios.

| Fuente | Candidatos | Clasificados | Pendientes |
| --- | ---: | ---: | ---: |
| PHP y Blade | 23.418 | 5.719 | 17.699 |
| JavaScript y Vue | 23.169 | 3.306 | 19.863 |
| Total | **46.587** | **9.025** | **37.562** |

El aumento respecto a los 39.912 candidatos anteriores procede de la cobertura ampliada. **37.562 pendientes no equivalen a 37.562 errores monetarios.**

Las 9.025 clasificaciones incluyen 8.786 pruebas AST individuales de guardias de tipo o igualdad con texto no numérico y 239 revisiones manuales. Las pruebas AST no aprueban comparaciones numéricas, aritmética, casts ni operaciones anidadas por estar dentro de una guardia aprobada. No hay aprobaciones generales por carpeta.

Cada candidato del [inventario JSONL](calculos_exactos_extension_inventario.jsonl) conserva expresión, línea, símbolo, operación, ID, categoría, explicación y evidencia de su clasificación. El [registro](../config/exact_rounding_registry.json) enlaza las aprobaciones mediante ID estable y hashes SHA-256 de los archivos de evidencia. Una expresión cambiada, evidencia modificada, ancla inexistente o aprobación huérfana no conserva su autorización. Las aprobaciones retiradas se archivan como antecedentes, sin transferirlas a expresiones nuevas.

Ambos comandos `--check` terminaron con **código 1 por candidatos pendientes**. Hubo cero errores de análisis, cero aprobaciones huérfanas y cero archivos omitidos respecto a la política de fuentes. Esto acredita cobertura sintáctica de 4.662 archivos únicos, no demuestra por sí solo la trazabilidad interprocedimental o todo SQL dinámico.

La [matriz por módulo y superficie](calculos_exactos_cierre_consumidores_matriz.json) enumera fuentes, rutas, controladores, modelos, comandos, jobs, eventos, importaciones, exportaciones y frontend por ubicación. Es un índice para continuar la revisión; un archivo escaneado o un grupo sin candidatos no acredita un recorrido completo.

## Pendientes concretos para completar el plan

1. **Cabeceras en ventas consolidadas:** distribuir o exponer exactamente descuentos, cargos y anticipos que sólo estén en la cabecera; reconciliar el detalle con el documento. Las partidas ya incorporan sus propios descuentos. No aplicar una proporción indiscriminada: un descuento que sólo afecta base gravada no debe reducir una partida exenta. Faltan casos mixtos de afectación, NC parcial y documentos convertidos con estos ajustes.
2. **Reportes y saldos restantes:** cuentas por cobrar, Dashboard, indicadores, caja, bancos, comisiones y el resto del libro contable. El resumen de cuentas por pagar conserva VES/USD separados; falta su consolidación VES con tasa histórica. Completar filtros monetarios, ordenación antes de paginar y todas las salidas de los demás reportes.
3. **Operaciones comerciales y operativas:** productores y consumidores restantes de compras, presentaciones, variaciones, inventario, Kardex, valoración, costos, producción, pedidos, cotizaciones, conversiones, ecommerce, Marketplace, WhatsApp, Offline, restaurante, hotel, suscripciones y servicio técnico. La vista previa de recetas no cierra el consumo y la preparación. Revisar de nuevo Web/POS/Garage al completar esos productores.
4. **Cobros y proveedores:** completar todos los lectores/productores de pagos, cuotas, vuelto, IGTF, retenciones, garantías, devoluciones y reversión. Terminar pasarelas y checkout central, verificación autorizada de importe/moneda/orden, importe enviado/liquidado/ajuste, idempotencia y concurrencia. No se enviaron cobros de prueba a proveedores reales.
5. **Entradas y persistencia restantes:** modelos fuera de las bases comunes, pivotes, escrituras SQL directas, mutadores, campos enteros de negocio y controles limitados. `Decimal::of` conserva compatibilidad con entradas `float` heredadas: la validación final y el middleware no convierten por sí solos todos sus productores en exactos. Los consumidores antiguos de `rateMultiply`/`rateDivide` siguen pendientes.
6. **Intercambio y presentación:** ampliar la lectura lexical a todas las importaciones XLSX y las exportaciones de texto/fracciones a los demás Excel/CSV. Revisar los PDF locales restantes y gráficos. Mantener las reglas del precio unitario PDF 2–12, opción desactivada/default 2, otros importes a dos y originales HKA sin reescritura.
7. **Clasificación completa:** resolver los 37.562 candidatos restantes, corrigiendo cualquier pérdida intermedia o aportando evidencia individual de su frontera autorizada. Completar la revisión semántica de rutas dinámicas y consumidores indirectos; no sustituirla por coincidencias de nombres.
8. **Aceptación global y rendimiento:** medir HTTP/facturación/POS con 1/100/1.000 partidas y caja/reportes con 1.000/10.000 operaciones sobre entornos equivalentes, además de los recorridos y casos de aceptación restantes. No se ha cumplido todavía esta matriz global.

## Pruebas y mediciones

Los registros completos están en [resultados.json](evidencias/calculos_exactos_pro9/cierre_consumidores_2026_10_17/resultados.json). Los subconjuntos se solapan y no deben sumarse.

| Verificación | Resultado |
| --- | --- |
| PHP Unit completa | 861 pruebas, 16.302 aserciones, cero fallos/errores; 60 omitidas |
| JavaScript | 200 pruebas, cero fallos ni omisiones |
| Pruebas negativas y guardias del auditor | 14 pruebas PHP, 27 aserciones; pruebas Vue/JavaScript incluidas en las 200 |
| MySQL: cálculos, ciclo comercial, rango central y rollback | 3 pruebas, 42 aserciones |
| MySQL: instalación tenant, semillas, rollback/repetición y stock XLSX | 2 pruebas, 1.608 aserciones |
| MySQL: repetición del ciclo comercial con lectura SQL UNION del consolidado | 1 prueba, 12 aserciones; se solapa con el ciclo anterior |
| Sintaxis PHP | 129 archivos modificados/nuevos, cero errores |
| Vue | 15 componentes comprobados en memoria, cero errores; sin generar assets |
| Habilidad de cálculos exactos | Validada |
| PDF sintético | Tickets 58/80 mm renderizados y revisados visualmente: Bs. 500, total Bs. 503 y USD 1 repartido 0,34/0,33/0,33 |

Las 60 omisiones de la suite general no se presentan como pruebas aprobadas. Se ejecutaron aparte los subconjuntos MySQL indicados; no se reejecutó toda la matriz de concurrencia con MySQL.

Se comparó el antiguo consumidor de cuentas por pagar extraído de Git `fd9e6d18bb2808ea97bf4909044ca6e20ff13966` con el nuevo, usando los modelos actuales, fixtures equivalentes, SQLite temporal, siete muestras y serialización JSON. El anterior devuelve todas las filas; el nuevo devuelve una página con resumen global.

| Operaciones | p50 anterior/actual | p95 anterior/actual | Consultas anterior/actual | Memoria pico anterior/actual |
| --- | ---: | ---: | ---: | ---: |
| 1.000 | 193,68 / 205,04 ms | 213,10 / 285,10 ms | 1.001 / 8 | 42,5 / 42,5 MiB |
| 10.000 | 2.215,71 / 2.135,86 ms | 2.297,05 / 2.222,66 ms | 10.001 / 44 | 68,5 / 64,5 MiB |

Ambos casos cumplen referencia + max(20 %, 100 ms). El caso de 1.000 operaciones aumenta su p95 en 72 ms, dentro del umbral. Esta medición local no acredita transporte HTTP, MySQL, POS ni otros módulos. La medición pura del motor se conserva por separado y tampoco acredita esos recorridos.

Reproducción de los controles principales:

```bash
DB_HOST=127.0.0.1 DB_PORT=1 vendor/bin/phpunit --testsuite Unit
node --test tests/js/*.test.cjs
php scripts/audit_exact_calculations.php --check
node scripts/audit_exact_calculations.cjs --check
php scripts/benchmark_exact_consumers.php --baseline-sha=fd9e6d18bb2808ea97bf4909044ca6e20ff13966
```

El puerto 1 impide acceso accidental a bases reales en la suite general; las pruebas de persistencia MySQL usan su configuración aislada con `PRO9_FISCAL_MYSQL_TESTS=1`. Los dos auditores deben seguir fallando mientras existan pendientes. `classify_exact_type_guards.php --write` sólo materializa/revalida sus pruebas AST limitadas; no sirve para aprobar los demás cálculos.

## Entrega y frontend

Se actualizaron la [habilidad](../.codex/skills/mantener-calculos-exactos-pro9/SKILL.md), el [contrato](../.codex/skills/mantener-calculos-exactos-pro9/references/contrato.md), el [inventario de campos](calculos_exactos_extension_inventario.json), la matriz y las evidencias. El informe anterior se conserva como antecedente.

La compilación y aceptación visual de las fuentes frontend siguen a cargo del usuario, conforme a `frontend-build`. No se ejecutó Vite/build/watch ni se editaron bundles. Se preservaron los assets modificados por el proceso externo activo y el trabajo previo del repositorio. Los PDF de prueba no son comprobantes emitidos.
