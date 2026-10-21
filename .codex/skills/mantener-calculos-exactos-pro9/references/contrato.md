# Contrato de precisión — actualizado el 19 de octubre de 2026

## Autoridad y representación

La instrucción del usuario sustituye las reglas anteriores de importes DECIMAL(12,2), cantidades de cuatro posiciones y precios de seis para instalaciones nuevas. Toda operación decimal de negocio debe conservar su valor exacto. La representación decimal final puede tener hasta 12 posiciones; una división periódica conserva adicionalmente su fracción simplificada.

`calculation_snapshot` tiene forma `{version: 1, inputs: {...}, values: {campo: {n: "numerador", d: "denominador"}}}`. Denominador positivo, enteros de texto y reducción por máximo común divisor. El snapshot y el registro se escriben bajo la misma transacción y bloqueo del flujo original. La edición renueva sólo los resultados afectados y no altera comprobantes ya congelados.

Las entradas decimales tienen máximo 12 posiciones y 18 enteros; las tasas mantienen su contrato DECIMAL(18,8). Las entradas fuera de precisión se rechazan; no se truncan. Los valores calculados conservan fracciones aunque excedan la representación decimal. HALF_UP se permite sólo al serializar una representación finita, renderizar o liquidar, nunca para alimentar otro cálculo.

El ámbito incluye todos los módulos tenant y la administración central. Se conserva cualquier rango entero declarado mayor que dieciocho: `plans.sales_limit`, anteriormente DECIMAL(22,2), usa DECIMAL(32,12) y admite veinte enteros. Tanto el resultado exacto como su proyección deben estar dentro de rango antes de escribir. Los antiguos campos de punto flotante de negocio adoptan el contrato de entrada de dieciocho enteros y doce decimales; esto no reconstruye cifras perdidas en registros existentes.

## Lectura y agregados

Recuperar los operandos del snapshot exacto antes de sumar, restar, multiplicar, dividir o comparar. Una entrada original sin fracción se interpreta exactamente desde su cadena decimal. Los SQL SUM de proyecciones y Collection::sum con aritmética binaria no sustituyen una agregación exacta cuando haya resultados periódicos. No recuperar supuestos decimales históricos.

## Liquidación y distribución

Separar `total` exacto, `total_payable` a céntimos y `settlement_adjustment = total_payable - total`. El saldo liquidable se calcula contra el importe cobrable. Guardar el ajuste y mostrarlo sin producir movimientos ficticios. El reparto por mayores residuos asigna primero unidades mínimas inferiores y reparte las restantes por residuo descendente, desempate por posición original; los negativos usan las magnitudes y conservan signo.

Las adaptaciones HKA operan sobre una copia; congelar los valores transmitidos, respetar precisión de precio/cantidad/importes del proveedor y conservar originales y estados. No extender este cambio a una autorización de emisión real.

## PDF

Reutilizar `change_decimal_quantity_unit_price_pdf` y `decimal_quantity_unit_price_pdf`. Desactivado: dos decimales; activado: entero entre 2 y 12. Semilla/DDL/formulario: desactivado y cantidad 2. Sólo precio unitario; otros importes PDF a dos posiciones. Cantidades conservan reglas de unidad. No modificar cálculos ni almacenamiento al cambiar la preferencia. Los PDF originales de HKA conservan sus bytes.

## Puntos, unidades discretas e intercambio Excel

Los puntos son valores racionales: acumular, canjear y revertir sin truncar. `round_points_of_sale` sólo controla la proyección visible. La contribución aceptada de cada documento, el cliente, la participación canjeada y los puntos de catálogo por partida se conservan en el snapshot. El canje usa el saldo disponible anterior a la venta. Una reversión elimina exactamente su contribución; si el cliente gastó puntos de otra venta ya anulada, conservar la deuda resultante, sin inventar puntos ni limitar el saldo a cero.

El número de platos o combos completos preparables puede proyectarse a unidades enteras inferiores al final de la consulta de capacidad. Dividir primero las cantidades exactas del stock y la receta; conservar el stock, la receta y sus fracciones intactos. Esta frontera discreta no autoriza truncar movimientos o cantidades fraccionarias.

Importar precios y cantidades XLSX desde el texto OOXML original antes de cualquier operación decimal. Excel ya puede haber limitado el dato antes de guardar el archivo; no reconstruir cifras que el archivo no contiene. Las exportaciones de importes usarán texto decimal sin ceros sobrantes, junto con hoja de fracciones exactas por registro y campo. No ejecutar fórmulas como datos importados ni exportar texto del usuario como fórmulas.

## Auditoría y rendimiento

El registro de excepciones identifica expresiones individuales mediante archivo, símbolo y hash estable. Categorías permitidas: almacenamiento, presentación, liquidación, protocolo externo, reparto, unidades discretas, validación, aritmética exacta, operación técnica o código inalcanzable. Cada excepción requiere justificación concreta y evidencia verificable. Una operación desconocida o modificada vuelve a quedar pendiente. No inferir exactitud a partir de un cast de cadena o la presencia de un snapshot.

Conservar filtros e índices SQL; los SUM de proyecciones no sustituyen las fracciones periódicas. Los agregados de registros exactos usarán lectura acotada y evitarán N+1. Medir cálculo, consultas, memoria y latencia de recorridos representativos por separado. No prometer rendimiento global a partir de una microprueba del motor.

## Fronteras y consumidores ampliados

- `PreserveJsonDecimals` conserva decimales y exponentes del JSON como texto antes de normalizar la entrada; los enteros que caben mantienen su tipo y los grandes usan texto. No modificar los bytes originales: siguen siendo la autoridad de cualquier firma de webhook. `RemoveClientCalculationSnapshots` continúa retirando resultados no autorizados, incluidos los anidados.
- `Decimal::readRequired` distingue un operando ausente de cero y rechaza snapshots desconocidos o corruptos. Seleccionar explícitamente los campos y snapshots necesarios en consultas y uniones.
- `Persistence::prepareValues` valida después de `creating`/`updating`, incluso si el cálculo se originó en `saving`. La validación inicial no acredita el resultado que finalmente llega al SQL.
- `ReportDataset` conserva filas exactas en archivos temporales privados durante una ejecución. Permite releer el mismo conjunto, filtrar antes de paginar y ordenar externamente con comparación racional. No serializar el objeto temporal como un histórico persistente ni recalcular sus filas desde catálogos.
- Ventas consolidadas tienen como importe principal el total congelado del documento, distinguen origen/ID y unidades, descuentan NC y excluyen notas de venta convertidas. El consolidado es VES con desglose original VES/USD y tasa de cada documento. Falta de moneda o tasa histórica es un error explícito.
- La proyección monetaria de reportes conserva originales y reparte residuos firmados sólo para presentar. Filas de crédito conservan su signo y la suma visible coincide con el total representado. No guardar esa proyección como operación comercial.
- Cuentas por pagar distinguen `total`, `total_payable`, `exact_balance`, `total_to_pay` y `settlement_adjustment`. Las tarjetas de totales cubren el conjunto filtrado completo; la página contiene sólo sus filas. Un tercio liquidado en 0,33 no genera un cobro pendiente ficticio de 1/300.
- El inventario de fuentes documenta dependencias, generados y clientes externos separados. Cada guardia técnica aprobada por AST sigue teniendo su ID, expresión, fuente y evidencia. Una comparación monetaria, un cast o un redondeo anidado no hereda la aprobación de la guardia que lo contiene.
- Presupuesto inicial de regresión: p95 ≤ referencia + max(20 % de referencia, 100 ms), con entorno/carga equivalentes. Una comparación de consumidores backend con SQLite no acredita HTTP, MySQL, POS ni otros módulos.

## Cabeceras, saldos y cortes de lectura

`HeaderAllocation` comparte la elegibilidad de descuentos con los productores fiscal y comercial. Un descuento gravado afecta únicamente base gravada; uno exento sólo base exenta. Las partidas con distribución global conservada se descuentan del remanente elegible de su base. Un anticipo representado por descuento 04/05 no se vuelve a restar como `total_prepayment`. El IVA del lector es la variación entre la cabecera y las partidas congeladas, sin recalcular impuestos históricos con catálogos actuales. Rechazar inconsistencias, ajustes sin base y partidas negativas; no inventar una diferencia para forzar el cierre.

Las fracciones `line_total`, `header_discount`, `header_charge`, `header_iva_adjustment`, `header_other_taxes`, `header_prepayment` y `report_total` se congelan dentro de `calculation_snapshot.values` de cada partida. El productor conserva la clave canónica de cabecera en `inputs.header_allocation_key` y escribe todos los snapshots en la misma transacción que la cabecera, en lotes de hasta 500. No se añaden columnas ni se modifican los importes originales de línea. Los lectores reutilizan las participaciones congeladas cuya clave coincide; para registros anteriores, construyen el desglose temporal desde los valores guardados, sin persistirlo.

Durante el reemplazo de todas las líneas de una nota de venta, `CommercialCalculation::defer` conserva el bloqueo y calcula la cabecera una sola vez al completar el conjunto. No validar ni congelar una cabecera intermedia que aún carezca de partidas; un fallo revierte todo y libera el ámbito diferido. Los cambios de líneas individuales mantienen su actualización inmediata.

`Receivables` conserva el saldo cobrable después de notas, pagos activos, retenciones, garantías efectivas y devoluciones activas. Los ajustes de cierre no son dinero recibido. `OutstandingSummary` utiliza las mismas filas para sumar total y saldo original VES/USD y sus equivalentes históricos VES. La mora se calcula exactamente en servidor; los días son unidades de calendario. El widget de deudores agrupa y ordena esos saldos por fracción antes de limitar clientes, sin umbral destinado a ocultar subcéntimos. El widget de stock agrega cantidades exactas por producto antes de comparar su límite funcional de diez unidades.

La comprobación `customer-expired-days`, utilizada al vender a crédito en Factura web y Nota de venta, consulta esos mismos saldos por cliente y origen antes de calcular sus días de vencimiento. Mantiene su alcance entre sucursales y excluye notas de venta convertidas; no filtra desde `SUM` o proyecciones. La visibilidad del ajuste de liquidación en pantalla también compara la fracción. Si la representación de doce posiciones es cero y la fracción no lo es, mostrar numerador/denominador como detalle del ajuste; no ocultarlo por su texto decimal.

`ReadSnapshot::run` fija REPEATABLE READ sólo para la siguiente transacción MySQL y conserva detalle y totales en el mismo conjunto. Un reporte no puede entrar silenciosamente dentro de una transacción MySQL ajena: iniciar el ámbito consistente antes. No cambiar el aislamiento de sesión ni mantenerlo abierto mientras se descarga un archivo; materializar primero `ReportDataset` y renderizar después.

Las pruebas AST de clasificación acreditan sólo su expresión: guardias de tipo/texto, unión de arreglos PHP con literal de arreglo, o formato desde un receptor de fecha resuelto con métodos concretos conocidos. Un método llamado `format` sobre una variable desconocida, macros de fecha desconocidas y aritmética dentro de un arreglo continúan pendientes. Las pruebas no se heredan por archivo ni por función. El registro comprueba que la propiedad AST de la aprobación siga coincidiendo; el materializador compara también los hashes de las fuentes que realmente analizó.

## Continuación: cobros y órdenes centrales

`TenderAllocation` interpreta las entradas iniciales de Notas de venta como cadenas decimales, admite enteros exactos y rechaza flotantes. Adapta cada recepción real a céntimos una sola vez y conserva `requested_payment`, `tendered_amount`, `payment`, `change` y `cash_adjustment`. Se cumplen `payment + change = tendered_amount` y `requested_payment + cash_adjustment = tendered_amount`. El vuelto se devuelve únicamente desde los métodos de efectivo 01/10, en orden de entrada y sin superar el efectivo de cada fila. Un exceso que sólo pueda cubrirse reduciendo una transferencia o tarjeta se rechaza antes de escribir.

`Settlement::defer` bloquea el propietario antes de insertar el lote, evita resumir cada conjunto parcial y valida el total una sola vez al terminar. Un error revierte las escrituras del ámbito transaccional y libera su estado diferido. Los GET de saldo usan `ReadSnapshot` y devuelven fracciones sin guardar un estado de pago derivado de una representación. El diálogo presenta el ajuste y el vuelto; su comparación de saldo usa la fracción.

Las órdenes centrales nuevas conservan en sus snapshots moneda y UUID de autorización, importe exacto, `provider_payable` y `provider_adjustment`. `CentralPaymentQuote::read` rechaza una referencia, moneda o adaptación incoherente. No reconstruye la moneda de órdenes históricas. La restricción de checkout nacional VES también bloquea los POST directos de creación/consulta Culqi e Izipay antes de acceder a credenciales o red, aunque el cliente envíe USD. Los adaptadores para otros contextos conservan pendientes su vinculación de respuesta, idempotencia y reversión; la existencia de esta restricción no convierte su código en inalcanzable.

Se permiten además pruebas AST individuales de igualdad JavaScript con texto cuya conversión numérica es NaN, excluyendo literales numéricos, hexadecimal, Infinity y blancos; y comparaciones de contadores nativos PHP resueltos (`count`, `sizeof`, `strlen`) con enteros literales. Las funciones de un namespace desconocido, operandos monetarios y cálculos anidados no reciben esa aprobación. Una aprobación inválida u obsoleta produce un diagnóstico explícito y mantiene pendiente su candidato.

En la nota del IGTF efectivamente cobrado, conservar la conversión exacta del impuesto recibido y separar el importe aplicado a céntimos del recibo derivado. Su snapshot guarda `converted_principal` y `payment_adjustment`; la recepción física se cuenta exclusivamente en el recibo padre. Las validaciones internas de identidad monetaria comparan fracciones, sin tolerancias de medio céntimo. Los ajustes no nulos inferiores a doce posiciones se muestran como n/d en el diálogo. Esto conserva la distinción existente entre `tax_exact` calculado y `tax_amount` recibido; no redefine la política fiscal.

Al cerrar un cobro, cualquier comparación posterior a recuperar o ajustar `calculation_snapshot.values.payment` usa esa fracción. Comparar de nuevo la proyección de doce posiciones puede rechazar falsamente un pago que coincide exactamente con el saldo. Los estados de crédito tras anular una nota también se obtienen del saldo racional, sin umbral de medio céntimo.

## Integración: preparación de conversiones y medición HTTP

La preparación de notas obtiene fuentes autorizadas y vigentes del mismo cliente, sucursal, moneda y tasa, bajo `ReadSnapshot`. Conserva la respuesta `data` y añade `grouped_items` y `conversion_context`. Agrupa por precio racional, unidad/presentación y condiciones fiscales/comerciales conservadas; el stock consultado no es una condición de precio. Mantiene separadas las partidas con lotes/seriales para no perder trazabilidad. Las cantidades e importes aditivos se acumulan como fracciones y se representan al terminar, con fuentes originales por partida.

El formulario restaura moneda y tasa de esa preparación antes de cargar las partidas; no consulta un precio actual para reemplazar el conservado. La caché de operandos es local, acotada y usa el operando completo, nunca su proyección. Un snapshot de versión inválida o un operando obligatorio ausente se rechaza incluso después de haber visto el mismo importe.

La preparación no completa la conversión persistida: el escritor deberá volver a resolver y bloquear sus fuentes, reconstruir los grupos autorizados y reconciliar cabeceras, cobros, inventario y conversiones parciales. Los IDs o fracciones enviados por el navegador no sustituyen esa autoridad. Esos consumidores siguen pendientes hasta su propia aceptación.

Los futuros intentos de cobro central deberán conservar cotización, proveedor, referencia e idempotencia. Antes del primer envío, una edición autorizada podrá renovar la cotización atómicamente. Después de enviar, se bloquean cambios de importe y nuevos intentos mientras exista resultado pendiente o incierto; primero se concilia. La fundación actual no acredita ese ciclo.

`ExactHttpAcceptanceTest` y su front controller privado permiten probar kernel, rutas, autenticación, sesión y CSRF contra MySQL temporal. Su comparación de selección de partidas alterna el controlador de referencia congelado y el actual, verifica hashes de dependencias PHP y registra p50/p95, memoria y consultas del kernel después de bootstrap; la latencia sí incluye bootstrap y transporte. Esta medición no acredita facturación/POS ni caja/reportes de 1.000/10.000 operaciones, que mantienen pendiente su matriz HTTP.

Los campos JSON conservados también se leen lexicalmente antes de operar. En descuentos/cargos antiguos que no tienen `amount_exact`, convertir el número JSON a float y luego a cadena puede perder céntimos aunque la columna principal sea DECIMAL. La preparación de conversiones reutiliza `JsonDecimals` y conserva objetos/listas, incluidos objetos vacíos; una decodificación nativa auxiliar sólo describe la forma de los contenedores, nunca sus operandos. Esto no recupera precisión perdida previamente dentro de MySQL o de un productor externo. La regresión HTTP de dos descuentos numéricos de 1234567890123.12 exige suma exacta 2469135780246.24.

La prueba AST `nullish_presence_guard` se limita a `==`/`!=` con el literal `null` en JavaScript: verifica presencia sin adaptación numérica. No aprueba comparaciones con `undefined` como identificador, booleanos o importes, ni se extiende a PHP. Los cálculos dentro de cualquiera de sus operandos conservan candidatos y revisión independientes.
