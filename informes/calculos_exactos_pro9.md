# Cálculos exactos de Pro9

Fecha: 16 de octubre de 2026. Extensión revisada el 17 de octubre de 2026.

**Implementación por etapas, todavía sin cierre global.** El motor, el almacenamiento inicial, los flujos comunes y la presentación PDF están modificados. Permanecen consumidores antiguos que requieren migración y validación. La existencia de snapshots en una tabla no demuestra que todos sus productores y lectores calculen exactamente.

La [extensión del 17 de octubre](calculos_exactos_extension_pro9.md) documenta los cambios posteriores de administración central, puntos, importación XLSX, cuotas, inventario, rendimiento y registro individual de excepciones. Sus resultados sustituyen los recuentos históricos de esta primera entrega, sin dar por terminados los consumidores pendientes.

## Causa del caso de Bs. 500

El cálculo fiscal anterior redondeaba la base a dos posiciones antes de obtener el IVA y reconstruir el total. Para una unidad con IVA incluido al 16 %: `500 / 1.16` se convertía en `431.03`; el IVA calculado sobre esa base se convertía en `68.96`; la suma guardada era `499.99`.

`LineCalculation` conserva ahora las fracciones: base `12500/29`, IVA `2000/29`, total `500/1`. Las columnas decimales son representaciones y el snapshot conserva los operandos para lecturas posteriores.

## Contrato y componentes

- [Habilidad](../.codex/skills/mantener-calculos-exactos-pro9/SKILL.md) y [contrato versionado](../.codex/skills/mantener-calculos-exactos-pro9/references/contrato.md). Se actualizaron las reglas relacionadas de persistencia, moneda, IVA, retenciones, esquema, productos y unidades.
- PHP: `app/Support/Exact/Decimal.php`, `LineCalculation.php`, `Persistence.php`, `CommercialCalculation.php`, `Settlement.php`, `Installments.php`, `PdfPrice.php` y `PdfAmounts.php`. Aritmética racional mediante Brick Math, validación sin coerción de MySQL, serialización y reparto por mayores residuos.
- JavaScript: `exchange-rate-math.js`, `exact-document.js`, `exact-commercial.js` y el cálculo compartido de partidas de `functions.js`. Operaciones con BigInt y fracciones normalizadas de texto.
- [Casos compartidos PHP/JavaScript](../tests/Fixtures/exact-calculations.json): IVA incluido, cantidad periódica, descuento periódico, conversión de precio neto, factores y valores inferiores a un céntimo.
- `ModelTenant` conserva fracciones al asignar resultados racionales y prepara snapshot/proyección en la transacción del guardado. Las lecturas de cálculo utilizan `exactValue`/`Decimal::read`; los atributos decimales públicos son cadenas sin ceros sobrantes. Una entrada fuera de rango o precisión se rechaza antes de la escritura del modelo.
- `RemoveClientCalculationSnapshots` elimina recursivamente los resultados enviados por HTTP en formularios, JSON y parámetros de consulta. Conserva `quantity_input`/`price_input` para que el servidor repita la operación. No modifica el cuerpo original usado para verificar firmas ni los snapshots generados internamente.

## Esquema inicial

[Registro tenant](../config/exact_calculations.php): 115 tablas y 539 campos decimales de negocio. El [registro central](../config/exact_calculations_system.php) añade seis tablas y once campos. Se utiliza `DECIMAL(30,12)` como regla y `DECIMAL(32,12)` para `plans.sales_limit`, conservando sus veinte enteros anteriores; las tablas incorporan `calculation_snapshot` JSON. Las tasas conservan `DECIMAL(18,8)` y su transporte de ocho posiciones.

Facturas, notas de venta y compras separan `total`, `total_payable` y `settlement_adjustment`. No se creó una migración incremental de conversión, no se ejecutó DDL sobre tenants existentes ni se recalcularon comprobantes históricos.

Los productores que conviertan importes a float o Number antes del motor requieren migración: el motor no puede recuperar cifras ya perdidas. El guardado mediante Query Builder, SQL directo o importaciones debe auditarse individualmente: no atraviesa necesariamente la preparación de `ModelTenant`.

## Consumidores modificados

| Área | Implementación disponible | Cierre pendiente |
| --- | --- | --- |
| Facturas | Recálculo fiscal de partidas y cabeceras, grupos IVA, descuentos, cargos, anticipos, equivalentes VES y snapshots | Verificar todos los productores alternativos e importaciones |
| Web y POS | Motor compartido, formularios de facturas, paneles de pago, Garage, cantidades derivadas de importes y reparto de cuotas | Eliminar las operaciones nativas restantes de validadores y componentes secundarios |
| Notas | Derechos y ajustes proporcionales exactos, cantidades devueltas, bases, IVA, inventario y reembolsos | Completar la matriz de reversión y concurrencia con los nuevos límites |
| Cobros | Importe recibido, principal convertido, importe aplicado y ajustes separados; saldo exacto y ajuste visible en el detalle | Completar todos los productos financieros y sus exportadores |
| Retenciones y fondos | Cálculo/propuestas racionales, conversiones y bases conservadas, ajustes sin movimientos ficticios | Revisar todos los lectores y formatos externos |
| Documentos comerciales | Partidas y cabeceras de compras, notas de venta, cotizaciones, pedidos, órdenes/cotizaciones de compra, oportunidades, contratos y activos fijos | Validación funcional completa de cada módulo y de sus conversiones |
| Inventario y costos | Rutas comunes de stock, órdenes de entrega, notas y costo promedio ponderado | Otros movimientos, variaciones, producción y consumidores de costos |
| Reportes | Agregados financieros y recursos comunes adaptados; snapshots para sumar valores originales | Indicadores, reportes especializados, filtros SQL y exportaciones restantes |
| HKA | Adaptación sobre copias, reparto final y conversión independiente de importes exactos por moneda | Revisar los demás contratos de importes del proveedor |
| PDF local | Precio unitario de 2 a 12 en A4/A5, tickets y documentos locales; dos por defecto y con opción desactivada; sumas y precio promedio de filas agrupadas exactos | Verificación funcional adicional de todas las plantillas y reparto entre partidas impresas |

Las cabeceras comerciales se recalculan después de modificar o borrar partidas. La edición de cantidades se verifica con el flujo comercial de reemplazo de partidas; las rutas de actualización directa y sus efectos de inventario requieren revisión adicional. Los agregados consultan las partidas con bloqueo. Las operaciones de cobro mantienen las transacciones y los bloqueos de su flujo. Las cuotas y salidas que requieren céntimos reparten el total completo con desempate por orden de partida.

Las cuotas de factura, nota de venta y compra comparten el guardado de `scheduled_exact`, el importe liquidable `amount`, su ajuste y los pesos originales. Los repartos automáticos envían pesos iguales como entradas; una edición manual de importes invalida esos pesos si ya no concuerdan. No reconstruir las participaciones exactas desde los céntimos repartidos. Los demás productores y los lectores de cuotas todavía necesitan revisión individual.

El recálculo fiscal de partidas reutilizadas internamente conserva las fracciones de cantidad/precio cuando coinciden con sus representaciones y restaura sus entradas originales. Un campo editado deja de heredar la fracción anterior. Esta recuperación no acepta snapshots HTTP del cliente.

## Fronteras de representación

| Frontera | Regla |
| --- | --- |
| Columnas decimales | Proyección HALF_UP hasta 12 posiciones; la fracción del snapshot sigue siendo la autoridad |
| Operación posterior a lectura | Recuperar la fracción, sin usar la proyección ni un texto formateado |
| Efectivo y liquidación | Una representación final a céntimos; conservar entrada, principal y ajuste separado |
| PDF unitario | Configuración existente de Opciones avanzadas; entero 2–12; desactivada y 2 por defecto |
| Otros importes PDF | Dos decimales al renderizar, sin convertir a float ni cambiar datos persistidos |
| HKA | Respetar precisión del protocolo, conservar el payload transmitido y repartir diferencias en una copia |

Los PDF originales del proveedor no se reescriben. La configuración local no modifica cantidades, impuestos, totales, saldos o cobros. Las cantidades conservan sus restricciones por unidad; los seriales requieren enteros.

## Auditoría reproducible y pendientes

[Inventario de campos y candidatos de revisión](calculos_exactos_pro9_inventario.json). Se puede regenerar con:

```bash
php scripts/audit_exact_calculations.php > /tmp/pro9-exact-php-inventory.json
node scripts/audit_exact_calculations.cjs > /tmp/pro9-exact-js-inventory.json
```

Los candidatos no equivalen automáticamente a defectos: incluyen formato, enteros de configuración y consumidores antiguos. El inventario enlazado es histórico. La auditoría ampliada del 17 de octubre incluye aritmética y comparaciones nativas, casts, SQL, módulos, controles Vue y expresiones Blade compiladas. No sustituye el seguimiento de operandos entre funciones, consultas y procesos externos; cada resultado pendiente requiere revisión.

Antes de cerrar el plan hay que revisar, entre otros, Dashboard, ecommerce, WhatsApp, hotel, restaurante, suscripciones, comisiones, producción, importaciones y reportes especializados; sustituir los cálculos activos pendientes y comprobar sus consumidores completos. Los `SUM` SQL sobre proyecciones no sustituyen sumas de fracciones. Los filtros de saldo SQL todavía necesitan revisión para resultados periódicos inferiores a la precisión de la columna.

No considerar las etapas 2–5 cerradas por las pruebas del motor o por la ampliación del esquema.

## Validación y entrega

Las pruebas añadidas cubren 500 con IVA, agrupación de cien partidas, reparto de 100/1000 en tres cuotas y de un descuento de 1, JSON de fracciones periódicas, conversiones, cantidades calculadas desde importes, costo ponderado, límites y configuración PDF inválida. Se añadieron verificaciones MySQL de persistencia, rollback, ciclo comercial y lectura seguida de una nueva operación.

Las regresiones MySQL se ejecutan exclusivamente con `PRO9_FISCAL_MYSQL_TESTS=1` sobre bases temporales creadas por `FiscalEmissionSchemaTest`. La prueba de instalación compara siembra, rollback y repetición. Los transportes HKA de estas pruebas utilizan respuestas simuladas.

La evidencia de ejecución se registra en [el resumen de pruebas](calculos_exactos_pro9_pruebas.md) y en la extensión. La compilación de assets corresponde al usuario según `frontend-build`; el agente no ejecutó comandos de compilación. Durante la extensión un proceso externo regeneró archivos de `public/build`; se preservaron esos cambios.
