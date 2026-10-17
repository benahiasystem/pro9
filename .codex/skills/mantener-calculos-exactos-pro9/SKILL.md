---
name: mantener-calculos-exactos-pro9
description: Mantener la aritmética decimal exacta, snapshots, almacenamiento y presentación de Pro9 al modificar precios, cantidades, impuestos, descuentos, cobros, inventario, conversiones, saldos, PDFs o reportes.
---

# Cálculos exactos de Pro9

Aplicar el [contrato de cálculo](references/contrato.md) al modificar operaciones decimales de negocio. La evidencia y los pendientes están en [el informe de implementación](../../../informes/calculos_exactos_pro9.md); no inferir que un consumidor está migrado porque existe el motor común.

- Operar con `App\Support\Exact\Decimal`/`BigRational` en PHP y `ExactAmount`/`BigInt` en JavaScript. No usar float, Number, parseFloat, round, toFixed ni textos formateados para encadenar cálculos.
- Conservar numerador/denominador simplificados en `calculation_snapshot`, con versión e inputs originales, dentro de la transacción comercial. `Decimal::read` y `exactValue` recuperan la fracción; nunca reconstruirla desde una representación de 12 posiciones si existe el snapshot.
- `DECIMAL(30,12)` y las cadenas visibles son representaciones; las tasas conservan ocho decimales exactos. Rechazar entradas fuera de rango/precisión antes de guardar, sin redondeo silencioso de MySQL.
- Calcular en servidor desde entradas autorizadas; nunca aceptar un snapshot de resultados del navegador como autoridad fiscal o comercial.
- Usar reparto por mayores residuos y desempate por orden original cuando una salida necesite precisión finita. Verificar conservación del total y ausencia de importes negativos.
- Cobro/proveedor que exija céntimos: una sola adaptación final HALF_UP, separada del total exacto y con ajuste visible y trazable. No fabricar pagos, efectivo, descuentos ni saldos para ocultarlo.
- PDF local: precio unitario configurable en Opciones avanzadas, de 2 a 12, desactivado y 2 por defecto. Los demás importes continúan con dos decimales y las cantidades con su regla por unidad. La configuración sólo afecta presentación. No reescribir PDF originales HKA.
- Aplicar cambios de esquema únicamente al consolidado de instalaciones nuevas; no actualizar bases existentes ni recalcular documentos. Seguir reconstruir-migraciones-tenant y frontend-build; no compilar assets por iniciativa propia.

## Verificación

Probar precio IVA incluido 500, agrupación de cien líneas, descuento 1 entre tres líneas, cuotas 100/1000 entre tres, lectura/operación/guardado de fracciones periódicas, conversiones de ida/vuelta, notas parciales y paridad PHP/JavaScript. Verificar esquema y persistencia en MySQL temporal, PDF con 2/6/12 decimales, rollback e idempotencia según el flujo. Registrar consumidores aún no migrados; no afirmar cumplimiento global a partir de pruebas del motor.
