# Contrato de precisión — actualizado el 17 de octubre de 2026

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
- Ventas consolidadas suman los totales guardados de cada partida, distinguen origen/ID y unidades, descuentan NC y excluyen notas de venta convertidas. El consolidado es VES con desglose original VES/USD y tasa de cada documento. Falta de moneda o tasa histórica es un error explícito.
- La proyección monetaria de reportes conserva originales y reparte residuos firmados sólo para presentar. Filas de crédito conservan su signo y la suma visible coincide con el total representado. No guardar esa proyección como operación comercial.
- Cuentas por pagar distinguen `total`, `total_payable`, `exact_balance`, `total_to_pay` y `settlement_adjustment`. Las tarjetas de totales cubren el conjunto filtrado completo; la página contiene sólo sus filas. Un tercio liquidado en 0,33 no genera un cobro pendiente ficticio de 1/300.
- El inventario de fuentes documenta dependencias, generados y clientes externos separados. Cada guardia técnica aprobada por AST sigue teniendo su ID, expresión, fuente y evidencia. Una comparación monetaria, un cast o un redondeo anidado no hereda la aprobación de la guardia que lo contiene.
- Presupuesto inicial de regresión: p95 ≤ referencia + max(20 % de referencia, 100 ms), con entorno/carga equivalentes. Una comparación de consumidores backend con SQLite no acredita HTTP, MySQL, POS ni otros módulos.
