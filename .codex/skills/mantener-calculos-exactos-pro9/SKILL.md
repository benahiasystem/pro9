---
name: mantener-calculos-exactos-pro9
description: Mantener la aritmética decimal exacta, snapshots, almacenamiento y presentación de Pro9 al modificar precios, cantidades, impuestos, descuentos, cobros, inventario, conversiones, saldos, PDFs o reportes.
---

# Cálculos exactos de Pro9

Aplicar el [contrato de cálculo](references/contrato.md) al modificar operaciones decimales de negocio. La evidencia y los pendientes están en [el informe de implementación](../../../informes/calculos_exactos_pro9.md) y en [la entrega de consumidores](../../../informes/calculos_exactos_cierre_consumidores_pro9.md); no inferir que un consumidor está migrado porque existe el motor común.

- Operar con `App\Support\Exact\Decimal`/`BigRational` en PHP y `ExactAmount`/`BigInt` en JavaScript. No usar float, Number, parseFloat, round, toFixed ni textos formateados para encadenar cálculos.
- Conservar numerador/denominador simplificados en `calculation_snapshot`, con versión e inputs originales, dentro de la transacción comercial. `Decimal::read` y `exactValue` recuperan la fracción; nunca reconstruirla desde una representación de 12 posiciones si existe el snapshot.
- `DECIMAL(30,12)` y las cadenas visibles son representaciones; las tasas conservan ocho decimales exactos. Rechazar entradas fuera de rango/precisión antes de guardar, sin redondeo silencioso de MySQL.
- Calcular en servidor desde entradas autorizadas; nunca aceptar un snapshot de resultados del navegador como autoridad fiscal o comercial.
- Usar reparto por mayores residuos y desempate por orden original cuando una salida necesite precisión finita. Verificar conservación del total y ausencia de importes negativos.
- Cobro/proveedor que exija céntimos: una sola adaptación final HALF_UP, separada del total exacto y con ajuste visible y trazable. No fabricar pagos, efectivo, descuentos ni saldos para ocultarlo.
- PDF local: precio unitario configurable en Opciones avanzadas, de 2 a 12, desactivado y 2 por defecto. Los demás importes continúan con dos decimales y las cantidades con su regla por unidad. La configuración sólo afecta presentación. No reescribir PDF originales HKA.
- Aplicar cambios de esquema únicamente al consolidado de instalaciones nuevas; no actualizar bases existentes ni recalcular documentos. Seguir reconstruir-migraciones-tenant y frontend-build; no compilar assets por iniciativa propia.
- El alcance incluye tenant y sistema central: planes, cuentas, órdenes de pago y facturación masiva. Preservar el rango entero declarado de cada campo: `plans.sales_limit` necesita `DECIMAL(32,12)`, pues antes admitía veinte enteros. No reducir rangos al aplicar `DECIMAL(30,12)` por defecto.
- Puntos: acumular, canjear, editar y revertir fracciones exactas, con bloqueo e idempotencia. La opción de puntos enteros afecta únicamente su presentación. El servidor obtiene el costo del canje del catálogo y congela su fracción; no confiar en puntos usados enviados por el cliente.
- Excel: importar la cadena numérica original de OOXML antes de validar u operar. Exportar importes como celdas de texto y adjuntar una hoja de numerador/denominador. Un formato de celda de doce posiciones no hace exacta una celda numérica de Excel.
- Auditar PHP, JavaScript de todos los módulos, Blade, expresiones Vue, controles, casts y SQL. Registrar cada excepción por expresión estable, categoría, justificación y archivos de evidencia existentes en `config/exact_rounding_registry.json`. `php scripts/audit_exact_calculations.php --check` y `node scripts/audit_exact_calculations.cjs --check` deben fallar ante entradas sin revisar o errores de análisis. No justificar por nombre de archivo ni confundir candidato con fallo confirmado.
- Rendimiento: evitar consultas por fila, reutilizar la lectura de snapshots y procesar agregados en lotes acotados. Medir por separado motor y recorridos HTTP/POS/reportes; una mejora del motor no acredita la latencia de toda la aplicación. No sustituir fracciones por float como optimización.
- Entradas JSON: conservar los tokens decimales con `PreserveJsonDecimals` antes de los normalizadores HTTP; el cuerpo original sigue disponible para firmas. Esto no recupera precisión perdida en una app externa. Las lecturas de consultas parciales usan `Decimal::readRequired`, sin inventar ceros.
- Validar también después de los eventos `creating`/`updating`, antes del SQL: un cálculo en `saving` no puede eludir precisión, rango o snapshot. `ReportDataset` conserva un conjunto temporal privado por ejecución; sus filtros y ordenación exacta preceden a la paginación.
- Auditar con la política de fuentes y exclusiones justificadas de `config/exact_audit_sources.json`. Las evidencias del registro incluyen SHA-256; conservar aprobaciones huérfanas o regenerar hashes sin revisar no acredita el cierre. `classify_exact_type_guards.php --write` sólo materializa pruebas AST de comparaciones de tipo/texto no numérico, por expresión; no aprueba aritmética monetaria.
- Vendeya, Mozo y LiveApp tienen cierre externo separado por decisión del usuario; sus endpoints presentes en Pro9 siguen dentro del alcance. No declarar cerrado todo Pro9 mientras queden consumidores disponibles pendientes.

## Verificación

Probar precio IVA incluido 500, agrupación de cien líneas, descuento 1 entre tres líneas, cuotas 100/1000 entre tres, lectura/operación/guardado de fracciones periódicas, conversiones de ida/vuelta, notas parciales y paridad PHP/JavaScript. Verificar esquema y persistencia en MySQL temporal, PDF con 2/6/12 decimales, rollback e idempotencia según el flujo. Registrar consumidores aún no migrados; no afirmar cumplimiento global a partir de pruebas del motor.
