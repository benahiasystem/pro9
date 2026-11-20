# Resumen de evidencias de PostgreSQL y retirada de NRUS

## PostgreSQL 17.11

El cierre registrado el 19 de noviembre de 2026 en `codex/postgresql-supabase-17-11` documenta instalación y recuperación en entornos temporales, aislamiento por empresa y adaptación del sistema a PostgreSQL.

- La batería registró **2.657 pruebas PHP y 88.590 aserciones**, sin fallos, errores ni omisiones en PHPUnit. También aprobaron **1.206 pruebas JavaScript**, **223 comprobaciones de facturación en Chrome** y **12 comparaciones de rendimiento**.
- El resultado global fue código 1 por siete referencias desactualizadas en la auditoría PHP de facturación. Después de actualizarlas, las auditorías PHP y JavaScript de facturación registraron cero pendientes en ese alcance. Las auditorías globales conservaron pendientes fuera de facturación.
- El cierre excluyó adjuntos de Contabilidad y facturas emitidas en USD. La medición de rendimiento del POS quedó aplazada. Los sondeos de instalación y recuperación registraron salida 0.

El [resumen del cierre](postgresql_17/resumen_20261119/README.md) conserva los identificadores de ejecución y los detalles de estos resultados históricos.

## Retirada de NRUS e integración

La integración en `migration-postgresql`, commit `6e20e303`, incorporó la retirada de NRUS y migraciones para las configuraciones existentes. Las pruebas dirigidas de las migraciones central y de tenant aprobaron **2 pruebas y 11 aserciones** en PostgreSQL temporal.

El 20 de noviembre de 2026, la batería fiscal ejecutada sobre la rama integrada aprobó **373 pruebas y 33.386 aserciones** en PostgreSQL temporal. Del inventario de 402 casos se excluyeron 29 asociados a comprobantes en USD. Se excluyeron también las pruebas de Contabilidad. La selección conservó casos de facturas en VES con cobros mixtos. La ejecución terminó con código 0 y limpieza del contenedor y volumen temporales.

También aprobaron **113 pruebas JavaScript** y **47 pruebas PHP con 509 aserciones** de series y contratos de venta. Vite compiló la interfaz y el manifiesto quedó sin archivos faltantes.

Las migraciones se verificaron en bases temporales. Su aplicación a bases existentes es un paso operativo independiente.
