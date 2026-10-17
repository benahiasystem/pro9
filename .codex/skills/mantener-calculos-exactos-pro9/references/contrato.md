# Contrato de precisión — 16 de octubre de 2026

## Autoridad y representación

La instrucción del usuario sustituye las reglas anteriores de importes DECIMAL(12,2), cantidades de cuatro posiciones y precios de seis para instalaciones nuevas. Toda operación decimal de negocio debe conservar su valor exacto. La representación decimal final puede tener hasta 12 posiciones; una división periódica conserva adicionalmente su fracción simplificada.

`calculation_snapshot` tiene forma `{version: 1, inputs: {...}, values: {campo: {n: "numerador", d: "denominador"}}}`. Denominador positivo, enteros de texto y reducción por máximo común divisor. El snapshot y el registro se escriben bajo la misma transacción y bloqueo del flujo original. La edición renueva sólo los resultados afectados y no altera comprobantes ya congelados.

Las entradas decimales tienen máximo 12 posiciones y 18 enteros; las tasas mantienen su contrato DECIMAL(18,8). Las entradas fuera de precisión se rechazan; no se truncan. Los valores calculados conservan fracciones aunque excedan la representación decimal. HALF_UP se permite sólo al serializar una representación finita, renderizar o liquidar, nunca para alimentar otro cálculo.

## Lectura y agregados

Recuperar los operandos del snapshot exacto antes de sumar, restar, multiplicar, dividir o comparar. Una entrada original sin fracción se interpreta exactamente desde su cadena decimal. Los SQL SUM de proyecciones y Collection::sum con aritmética binaria no sustituyen una agregación exacta cuando haya resultados periódicos. No recuperar supuestos decimales históricos.

## Liquidación y distribución

Separar `total` exacto, `total_payable` a céntimos y `settlement_adjustment = total_payable - total`. El saldo liquidable se calcula contra el importe cobrable. Guardar el ajuste y mostrarlo sin producir movimientos ficticios. El reparto por mayores residuos asigna primero unidades mínimas inferiores y reparte las restantes por residuo descendente, desempate por posición original; los negativos usan las magnitudes y conservan signo.

Las adaptaciones HKA operan sobre una copia; congelar los valores transmitidos, respetar precisión de precio/cantidad/importes del proveedor y conservar originales y estados. No extender este cambio a una autorización de emisión real.

## PDF

Reutilizar `change_decimal_quantity_unit_price_pdf` y `decimal_quantity_unit_price_pdf`. Desactivado: dos decimales; activado: entero entre 2 y 12. Semilla/DDL/formulario: desactivado y cantidad 2. Sólo precio unitario; otros importes PDF a dos posiciones. Cantidades conservan reglas de unidad. No modificar cálculos ni almacenamiento al cambiar la preferencia. Los PDF originales de HKA conservan sus bytes.
