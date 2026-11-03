# Optimización de la batería fiscal MySQL

`FiscalEmissionSchemaTest` continúa usando bases nuevas con nombres aleatorios `pro9_fiscal_test_` y una base independiente por prueba. No utiliza ni copia datos de tenants reales. La instalación, siembra, integridad, reversión y repetición del esquema se siguen comprobando por la ruta original de migraciones.

Para las pruebas que llaman al fixture comercial, el primer uso de cada proceso ejecuta las migraciones y semillas normales y captura en memoria las sentencias `SHOW CREATE TABLE` y las filas iniciales. Los usos posteriores restauran esa referencia en otra base vacía: preservan claves, índices, columnas generadas y datos, sin envolver las operaciones de la prueba en una transacción artificial. Cada proceso mantiene su propia referencia; los trabajadores fiscales siguen compartiendo únicamente la base temporal de la prueba que los lanzó. `PRO9_FISCAL_SNAPSHOT=0` desactiva la referencia para comparar con la ruta original.

La prueba `test_fiscal_fixture_snapshot_preserves_schema_seed_data_and_database_isolation` compara el DDL y los datos exactos de todas las tablas restauradas, incluidas las fechas, y comprueba aislamiento y una clave foránea. Esta comparación exacta pasó con cinco aserciones. Los nombres creados se registran para limpieza normal y errores PHP que ejecuten el apagado; una terminación forzada del sistema o `SIGKILL` puede impedir esa limpieza y requiere inspección manual de la base temporal propia.

La captura y restauración verifican tanto el nombre configurado como la base efectivamente seleccionada por MySQL; rechazan conexiones que no apunten a la base temporal esperada. Las pruebas unitarias cubren captura y restauración frente a un nombre real o una conexión que cambió de base.

En una medición local, la primera preparación tardó 98,332 s y la siguiente restauración 11,348 s, una reducción aproximada del 88 % en la preparación. El escenario de facturas por terceros tardó 101,988 s en una ejecución individual sin referencia previa y 28,456 s al restaurarla después de otra prueba; son mediciones de ejecuciones distintas, no un benchmark controlado de toda la batería. Ambos escenarios funcionales de la ejecución optimizada pasaron con 586 aserciones. La comparación de bases pasó con cinco aserciones.

Esos mismos dos escenarios pasaron en orden inverso con 586 aserciones; la restauración tardó 22,662 s bajo carga concurrente. La prueba de pagos concurrentes y retenciones pasó en la batería general restaurando el fixture, con commits y trabajadores reales sobre la base temporal del caso.

Dos procesos independientes ejecutaron simultáneamente las pruebas de terceros y regímenes especiales: pasaron con 105 y 481 aserciones. Bajo esa carga, sus tiempos fueron 206 y 269 segundos, respectivamente, mientras también corría la batería general; esto ilustra por qué no se supone que más paralelismo siempre sea más rápido. El inventario actual contiene los 155 casos previos y dos comprobaciones nuevas, repartidos 79 y 78 con dos trabajadores. La limpieza tras una salida de proceso con error se verificó en MySQL temporal con tres aserciones.

## Ejecución

El comando existente de PHPUnit permanece disponible. Para ver el avance, porcentaje, nombre, duración y errores de cada prueba sin esperar al informe final:

```bash
DB_HOST=127.0.0.1 PRO9_FISCAL_TIMING=1 bash scripts/run_fiscal_tests.sh
```

Se puede elegir un grupo mediante los filtros estándar de PHPUnit, por ejemplo:

```bash
DB_HOST=127.0.0.1 bash scripts/run_fiscal_tests.sh --filter 'test_iva_|test_islr_'
DB_HOST=127.0.0.1 PRO9_FISCAL_SNAPSHOT=0 PRO9_FISCAL_TIMING=1 bash scripts/run_fiscal_tests.sh --filter 'test_special_tax_regimes|test_third_party_invoices'
```

Para repartir toda la batería entre procesos aislados se puede ejecutar `DB_HOST=127.0.0.1 php scripts/run_fiscal_parallel.php 2`; se admiten de uno a cuatro trabajadores. Cada porcentaje corresponde a su proceso. Con `2 --list` se imprime el reparto sin ejecutar pruebas y con `2 --filter 'test_iva_|test_islr_'` se limita a esos nombres. La paralelización es opcional porque más procesos pueden empeorar el tiempo cuando MySQL o el disco están saturados. Una interrupción forzada puede dejar bases temporales propias, como en cualquier ejecución detenida sin limpieza.

El ejecutor paralelo se probó con dos procesos y dos casos de migraciones centrales: ambos pasaron, con 16 y 76 aserciones. La primera ejecución completa agotó las conexiones de MySQL después de 74 casos; su informe incluyó 83 errores, la mayoría en cascada por `Too many connections`, además de los fallos de fixtures previos documentados abajo. Los errores causados por el límite no verificaron los casos afectados. Se añadió la desconexión explícita de todas las conexiones de cada caso y se retiró un cierre duplicado de bases al terminar el proceso. Una prueba de resistencia de 80 casos pasó con 1.280 aserciones; en 50 muestras, `Threads_connected` fue 1 antes, 5 al inicio, 7 como máximo y 1 al finalizar. No se cambiaron límites globales ni se cerraron conexiones ajenas.

Los 82 casos afectados se repitieron en dos procesos de 41: ambos grupos terminaron; uno pasó con 995 aserciones y una prueba de rendimiento omitida por bandera, y el otro tuvo dos fallos de fixtures ya cargados antes de su corrección. Ambos casos fallidos pasaron posteriormente corregidos con y sin restauración; la prueba omitida se ejecutó después con `PRO9_PAYMENT_BENCHMARK=1` y pasó con 16 aserciones. Por tanto los casos se verificaron en ejecuciones complementarias, no en una sola ejecución general sin fallos. No existe una medición completa anterior sin optimización porque aquella ejecución fue cancelada; los tiempos de preparación y del caso individual son la comparación disponible.

La prueba de rendimiento de reportes completó los conjuntos de 1.000 y 10.000 cobros en 120,3 segundos incluyendo la preparación inicial de la base. Los reportes clasificados registraron p95 de 315 ms y 4.581 ms, respectivamente; esta medición corresponde al reporte PHP/MySQL y no mide HTTP ni PDF.

La prueba de exportación `test_export_invoices_persist_freeze_edit_and_emit_after_commit` falló tanto en la batería optimizada como con `PRO9_FISCAL_SNAPSHOT=0`: el dato de prueba solicitaba recibir USD usando el método inicial `01`, configurado en VES. La validación monetaria devolvía `currency_type_id` antes de que el caso alcanzara la comprobación fiscal esperada bajo `payment`. Se corrigió únicamente el dato del caso al método inicial `10`, configurado en USD; se mantienen la aserción y el código de producción. El caso corregido pasó sin optimización con 148 aserciones y con restauración después de la prueba de terceros en una ejecución de dos pruebas y 253 aserciones. La batería general que estaba en marcha aún contiene el fallo anterior porque cargó el archivo antes de la corrección.

La prueba `test_note_debit_is_created_on_credit_without_cash_effects_and_collects_later` también falló con y sin restauración al cobrar USD mediante el método bancario inicial `04`, configurado en VES. Para el caso aislado se configura ese mismo método en USD, en concordancia con la cuenta y el cobro de prueba. Al corregir la moneda, ambas rutas expusieron otro error previo: el caso intentaba asociar el cobro fuera de la transacción requerida por `ReceiptDestination`. El caso ahora registra y asocia el pago dentro de una misma transacción, como exige ese contrato; no se alteran las aserciones ni la validación de producción. El caso corregido pasó sin restauración con 53 aserciones y con restauración en una ejecución de dos pruebas con 76 aserciones.

Las pruebas concurrentes `test_method_activation_rechecks_classification_after_concurrent_edit` y `test_sale_note_batch_uses_current_locked_method_and_blocks_reclassification` fallaron con y sin restauración: sus solicitudes de edición no incluían `hka_code`, obligatorio en Medios digitales. Ambos casos ahora envían el código compatible `99`; se conservan sus bloqueos, concurrencia y aserciones y no se altera la validación de producción. Las dos repeticiones corregidas pasaron tanto con restauración como sin ella, con 24 aserciones en cada ruta.

`DB_HOST` debe apuntar al servidor de pruebas autorizado del entorno. La cuenta requiere crear y borrar bases temporales. El filtro debe coincidir con los nombres concretos de PHPUnit; usar `--list-tests` para consultarlos. Ninguno de los comandos se debe dirigir a bases de tenants reales.
