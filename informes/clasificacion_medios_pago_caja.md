# Clasificación de cobros y caja por moneda

## Alcance vigente: instalaciones nuevas

Por instrucción del usuario, esta implementación se incorpora exclusivamente al esquema inicial y a las semillas de instalaciones nuevas. Las instalaciones actuales serán sustituidas por instalaciones nuevas; este cambio no incluye su eliminación ni su recreación automática.

La clasificación y el cálculo VES/USD están disponibles desde el alta, sin comando de actualización, activación diferida, tabla de configuración de versiones de instalación, copias del catálogo anterior ni lectores históricos añadidos por este cambio. No se preparan conversiones, backfills ni escrituras sobre tenants existentes.

## Clasificación

`payment_method_types.collection_kind` admite `cash`, `account`, `credit` y `unclassified`. El servidor deriva `is_cash` e `is_credit`; no acepta estos indicadores como clasificación independiente. `cash_currency_type_id` corresponde exclusivamente al efectivo y admite VES/USD. Todos los métodos registran una moneda; inicialmente `10` y `13` usan USD y los demás VES. El IGTF opcional del método es informativo y no modifica el cálculo fiscal. Todos los métodos pueden cambiar naturaleza y moneda para operaciones futuras; los movimientos registrados conservan sus snapshots.

- Efectivo: 01/VES y 10/USD.
- Bancario/electrónico: 02, 03, 04, 11, 12 y 13.
- Crédito: 05 y 09; no representa recepción de dinero.
- Los IDs 06, 07 y 14–26 no se siembran y pueden usarse para métodos personalizados.

Se conservan IDs, `hka_code`, disponibilidad inicial y `has_card`. Los métodos nuevos reciben HKA 99 desde el servidor. Los métodos sin clasificar no pueden activarse. Configuración y disponibilidad exigen administrador. Los métodos iniciales no pueden eliminarse.

## Movimiento financiero

`Movement` valida el destino y conserva en `global_payments.report_context` catálogo/ID/descripción/naturaleza del medio, moneda recibida, tasa, fecha, dirección, destino y referencia comercial. `report_context.version = 2` identifica el formato del snapshot, no una activación de empresa. `cash_session_id` identifica el turno y es independiente del destino bancario. Las cuentas deben estar activas, pertenecer al tenant y coincidir con la moneda recibida. El efectivo necesita caja abierta; los cobros bancarios sin turno abierto permanecen en finanzas sin atribuirse a una caja cerrada.

Facturas utilizan `original_amount + tax_amount`, excluyendo recibos derivados IGTF. El principal aplicado documental no sustituye al recibido. Los demás productores leen sus fracciones conservadas; el neto guardado de notas de venta ya excluye el vuelto. El exceso se devuelve únicamente desde el efectivo recibido; un exceso exclusivamente bancario se rechaza.

Los gastos conservan `expense_method_types`, separado de los IDs de medios de pago. Hotel, préstamos, ingresos, compras, ventas, cotizaciones, contratos, servicios y devoluciones utilizan la política común al registrar sus movimientos. Los traslados conservan la dirección de sus dos registros originales; la caja de ese flujo continúa siendo VES: este cambio no incorpora un intercambio de billetes USD entre cajas.

Los cobros contabilizados no se editan en importe, medio, moneda o fecha. Su eliminación comercial conserva en el movimiento una marca de reversión y sus fracciones para auditoría; el lector excluye ese movimiento. Los lectores detectan referencias financieras rotas y no inventan importes cero.

Una factura puede reutilizar un cobro de una nota de venta mediante `source_sale_note_payment_id`; también se reconoce la referencia comercial `id` + `sale_note_id`. El servidor verifica nota relacionada, cliente, sucursal, moneda, importe, fecha y uso único bajo bloqueo. Conserva `receipt_origin` y el medio original, sin crear otro movimiento ni otro IGTF. Un cobro reutilizado no se revierte desde la nota mientras su aplicación a la factura siga activa.

## Caja y reportes

`cash_currency_balances` conserva apertura, entradas, salidas, cierre y fracciones por caja/VES/USD. Una apertura positiva crea un registro `cash_transactions` por moneda; los lectores lo distinguen de los ingresos. No se vuelve a sumar la apertura como venta. Las aperturas se serializan mediante bloqueo del usuario; se admite una caja abierta por usuario. El registro de movimientos y el cierre comparten bloqueo de caja.

`CashLedger` es la proyección común para cierres, pantalla, API y reportes. El cierre guarda los saldos y su snapshot; los lectores consultan movimientos vigentes y mantienen el cierre persistido como evidencia, sin reescribirlo al consultar. Los campos escalares expuestos son proyecciones VES; `income` conserva el neto VES. `balances_by_currency` distingue ingresos, egresos y saldos VES/USD.

Los formatos A4, ticket 58/80 mm y Excel, los reportes simple, general con pagos, ingresos/egresos, asociados, ingreso y operaciones diarias comparten el cálculo. Los reportes de productos conservan su detalle y añaden el bloque financiero común. Las rutas de restaurante y móvil delegan en la misma implementación. El reporte diario consolida cajas con la misma clasificación. Los reportes financieros agrupan por cuenta y moneda; Caja y bancos respeta la moneda seleccionada. El listado de pagos muestra totales de página calculados en servidor. El trabajo de exportación genera PDF y XLSX.

El XLSX utiliza celdas de texto y una hoja **Fracciones exactas** con numerador y denominador. La presentación de dos decimales no se reutiliza para operar. La consulta de datos se materializa bajo `ReadSnapshot`; las partidas se cargan en lotes de 250.

## Esquema inicial

El consolidado contiene 348 tablas de aplicación y 349 migraciones; las semillas contienen 81 catálogos y 948 filas. Las creadoras de `payment_method_types`, `cash_transactions` y `global_payments` incorporan directamente sus campos nuevos. `000343_create_cash_currency_balances_table.php` crea los saldos por moneda; sus FKs y las de los movimientos se agregan y retiran en la migración final. `cash` no contiene un selector de lógica histórica por instalación.

Las interfaces se entregan en fuentes; la compilación sigue el procedimiento del proyecto y la ejecuta el usuario.

## Verificación

Las pruebas de clasificación, destinos, precisión, vuelto y conversiones utilizan la clasificación obligatoria desde el inicio. Las pruebas MySQL crean y eliminan únicamente bases temporales desde el consolidado, verifican seeding, FKs, rollback, repetición, concurrencia y cuadre Bs.120/USD13. Incluyen la ausencia de infraestructura de actualización o activación para este cambio.

Las auditorías de cálculo revisan las expresiones modificadas y sus excepciones por separado de los pendientes globales del repositorio. La evidencia y los resultados vigentes se registran en [verificacion.json](evidencias/clasificacion_medios_pago/verificacion.json). Las mediciones previas y la revisión visual inicial de A4/tickets se conservan como referencia de rendimiento y formato; no acreditan ejecución sobre tenants reales.
