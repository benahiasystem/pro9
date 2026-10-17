# Extensión de los cálculos exactos de Pro9

17 de octubre de 2026. **Entrega parcial: el plan global continúa abierto.**

**Actualización posterior:** la [entrega de consumidores y auditoría](calculos_exactos_cierre_consumidores_pro9.md) incorpora los cambios y resultados más recientes. El inventario actual tiene 46.587 candidatos, 9.025 clasificados y 37.562 pendientes. Se migraron las partidas y salidas del consolidado y cuentas por pagar, con los límites descritos allí. Los cuadros y pendientes que siguen documentan la ejecución anterior; no sustituyen el estado actual.

## Implementado

| Recorrido | Cambio y límite |
| --- | --- |
| Motor y modelos | Lectura de snapshots con caché por objeto que se invalida al cambiar el snapshot; suma racional sin crecimiento artificial de denominadores iguales; rasgos compartidos para modelos tenant, `Person` y el nuevo `ModelSystem`. Una edición escalar invalida inmediatamente la fracción anterior de ese campo. |
| Esquema tenant | 48 antiguos DOUBLE de negocio pasan a DECIMAL(30,12) en hotel, contabilidad, suscripciones y servicio técnico. Inventario: 115 tablas y 539 campos. Las dimensiones técnicas de etiquetas no se convierten en importes. |
| Esquema central | Planes, clientes, pagos, órdenes, facturación masiva y bandeja: seis tablas, once campos y snapshots. `plans.sales_limit` usa DECIMAL(32,12) para conservar veinte enteros. Se valida también el rango de la fracción, aunque su proyección parezca caber. |
| Reversión del esquema central | El ciclo completo reveló y corrigió nombres equivocados de tabla/criterio, orden de borrado de claves foráneas y métodos `down()` que intentaban borrar campos de componentes ya retirados. No se recrean esos componentes. Se comprueban instalación completa, reversión hasta quedar sin tablas y reinstalación. |
| Cuentas y órdenes centrales | Precios de planes y excepciones de cliente se copian como fracciones a las órdenes. Totales por cliente/estado desde snapshots, en lotes de 500 y sin consulta por orden. Cuentas de cliente y tenant conservan fracciones en sus respuestas. La edición del listado deja de sobrescribir importes a dos decimales al leer o perder foco. Las pasarelas y otros consumidores centrales siguen pendientes. |
| Puntos | Acumulación, canje y reversión exactos. Bloqueo de clientes en orden estable; contribución por documento; repetición sin duplicar puntos; canje desde catálogo, con costo congelado por partida; rechazo de gasto de puntos futuros. La opción de enteros sólo cambia presentación. Los controles de configuración y producto transportan texto decimal. |
| Cuotas | Factura, nota de venta y compra comparten el guardado del reparto: importe a céntimos, participación original exacta, peso y ajuste. Hay prueba de guardado, recuperación y conservación de la suma. Otros productores y lectores siguen pendientes. |
| Adaptación de pagos comerciales | Si el pago solicitado procede de una fracción periódica, se recupera esa fracción antes de adaptarlo a céntimos. Se conservan el solicitado exacto, el liquidado y la diferencia; la prueba de 1/3 conserva un ajuste de −1/300. |
| Importación de productos | `XlsxDecimals` recupera el texto numérico OOXML antes de validar e importar; se conservan las posiciones del libro y el rechazo de fórmulas. Precios, stock y mínimo usan el contrato 30,12. Se elimina el cast de precio a float y la suma entera del stock de lote. El reporte de errores conserva los mismos textos numéricos. |
| Facturación masiva | El cálculo utiliza el motor de partidas, conserva el precio original con/sin IVA y mantiene sus entradas para el recálculo en la API. El guardado central recalcula desde precio/cantidad en vez de confiar en totales del navegador. Las rutas y los nombres de campos se mantienen. |
| Excel de facturación masiva | Importes en celdas de texto hasta doce posiciones, sin ceros sobrantes; segunda hoja con fracciones exactas de cantidad, precio, base, IVA y total. No convierte nombres del usuario que empiezan por `=` en fórmulas. No acredita las demás exportaciones. |
| Inventario y capacidad | Movimientos manuales usan la cantidad exacta; reversión de lotes y presentaciones conserva fracciones; la comprobación de lote deja de comparar cantidades truncadas a enteros. Capacidad de recetas/combos divide stock y cantidades exactas, incluidos snapshots de pivotes, antes de proyectar unidades completas. El resto de restaurante/producción sigue pendiente. |

Sólo se modificó el esquema inicial: no se convirtió ninguna base instalada ni se recalcularon documentos históricos. Las pruebas MySQL crean y eliminan exclusivamente bases temporales. No se enviaron documentos ni solicitudes al proveedor HKA.

## Auditoría y excepciones

El [registro individual](../config/exact_rounding_registry.json) vincula cada expresión revisada con categoría, justificación y archivos de evidencia. Cubre fronteras concretas del motor, almacenamiento, reparto, PDF, liquidación, BigInt y unidades completas. No contiene permisos generales por carpeta o por nombre de función.

Los auditores recorren PHP, Blade compilado, JavaScript/TypeScript de recursos y módulos, expresiones Vue, controles numéricos, casts y SQL directo. Inventarían también comparaciones y operaciones técnicas: el recuento de candidatos **no es un recuento de errores monetarios**. No hacen un análisis completo del flujo de datos entre funciones o de SQL construido dinámicamente.

El [inventario actualizado](calculos_exactos_extension_inventario.json) y sus entradas JSONL conservan cada candidato, su ubicación, identificador, expresión y estado. Toda expresión no revisada queda `pending`. Una expresión que cambia pierde su aprobación anterior. Una excepción sin justificación o sin archivo de evidencia existente también queda pendiente.

```bash
php scripts/audit_exact_calculations.php --check
node scripts/audit_exact_calculations.cjs --check
```

**Ambos controles continúan fallando por pendientes.** No se ha clasificado ni justificado cada redondeo restante del sistema. No utilizar el resultado satisfactorio de las pruebas unitarias como cierre de esa auditoría.

## Lo que falta para cerrar el plan

1. Migrar y probar los recorridos completos de Dashboard, indicadores, comisiones, ecommerce, WhatsApp, restaurante, hotel, suscripciones, producción, costos y los módulos secundarios que mantienen aritmética binaria. Ampliar la revisión de campos enteros de negocio y de modelos que no hereden las bases comunes.
2. Completar las pasarelas y salidas externas, incluido el checkout central: adaptación final, importe enviado/liquidado y ajuste persistido, reversión, idempotencia y concurrencia. Los auxiliares antiguos `rateMultiply`/`rateDivide` con salida limitada requieren revisar sus consumidores.
3. Sustituir agregados y filtros monetarios sobre proyecciones en reportes, balances y listados antes de paginar. Por ejemplo, `ReportSaleConsolidatedController`, su colección y sus PDF/Excel aún tienen cálculos antiguos. Revisar moneda documental, notas y descuentos con sus consumidores antes de cambiar su semántica.
4. Extender la lectura lexical de XLSX a las demás importaciones y las celdas de texto/hoja de fracciones a todas las exportaciones pertinentes. La implementación de facturación masiva no acredita las demás exportaciones.
5. Revisar individualmente los restantes candidatos del inventario, corregir el cálculo o aportar evidencia de una frontera final autorizada. Revisar también código inalcanzable antes de justificarlo como tal.
6. Ejecutar la matriz de aceptación de esos recorridos y las medidas de latencia, memoria y consultas de facturación/POS/caja/reportes con cargas representativas. La compilación y aceptación visual del frontend corresponden al usuario.

## Rendimiento y evidencia

El script `scripts/benchmark_exact_calculations.php` mide sumas de tercios, lecturas de snapshot y cálculo puro de partidas. Los resultados y registros de pruebas se conservan en [la evidencia de esta extensión](evidencias/calculos_exactos_pro9/extension_2026_10_17/resultados.json).

La medición inicial y las mediciones posteriores se realizaron con PHP 8.2.5 en el contenedor. Las lecturas repetidas y sumas largas mejoran con el caché y la reducción de denominadores. Los tiempos de muestras cortas varían con la carga concurrente del equipo; la evidencia conserva los datos crudos. No se midió todavía la latencia completa de todas las pantallas y reportes, ni se compara esta prueba con una versión antigua de aritmética float. No se puede garantizar todavía el rendimiento global.

Los resultados definitivos de cada ejecución se detallan en `resultados.json`; los subconjuntos se solapan y no deben sumarse. Las pruebas omitidas permanecen identificadas. El agente no ejecutó comandos de compilación; preservó los assets regenerados por el proceso externo activo.

| Verificación de esta extensión | Resultado |
| --- | --- |
| Suite PHP Unit | 813 pruebas, 16.044 aserciones, cero fallos; 59 omitidas |
| JavaScript | 193 pruebas, cero fallos ni omisiones |
| MySQL: cálculos, cuotas, ciclo comercial, stock, rango central y rollback | 3 pruebas, 45 aserciones |
| MySQL: instalación central, reversión completa, reinstalación y rango exacto | 2 pruebas, 92 aserciones |
| MySQL: instalación tenant, semillas, reversión y repetición | 1 prueba, 1.601 aserciones |
| Sintaxis PHP | 84 archivos modificados/nuevos sin errores |
| Plantillas Vue modificadas | 8 analizadas y compiladas en memoria sin errores; no genera assets |
| Habilidades | Cálculos exactos e importación de ítems válidas |
| Auditoría ampliada | 39.912 candidatos, 123 clasificados, 39.789 pendientes; cero errores de análisis; `--check` falla por pendientes |

Los arreglos del rollback central se limitaron a las rutas de reversión: la tabla `job_batching_trays`, el criterio por `id` de `extra_services`, el borrado de niveles antes del módulo Digemid y las comprobaciones de campos/tablas retirados del bot central. La prueba confirma que el rollback termina sin tablas y que el esquema se instala de nuevo.
