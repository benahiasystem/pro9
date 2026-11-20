# Integración con develop — 16 de noviembre de 2026

La rama `codex/postgresql-supabase-17-11` se adelantó hasta `develop` (`b7a57bb20fcb26081f36e9cce0a4a3300c1aaaba`) y quedó configurada para seguir esa rama local. El remoto develop (`1a50307a99771a65a245fba640ea4f1b77cd401a`) ya está incluido; develop local contiene 29 commits adicionales.

Se preservó todo el índice anterior en `refs/codex/checkpoints/postgresql-before-develop-20261116` (stash `c673011212ebb59e2b2c6c0bce23e446787160b8`). La reaplicación produjo 52 conflictos; se resolvieron conservando cambios funcionales de develop y adaptaciones PostgreSQL. Se verificaron sin diferencias 3.357 rutas guardadas fuera de las superposiciones y artefactos generados. No hay rutas sin fusionar.

Se mantuvieron las nuevas funciones de SAB, Centrifugo, vuelto distribuido y SellNow. El runtime conserva PostgreSQL 17.11 amd64, credenciales separadas, healthchecks y aislamiento. Los bundles fusionados se regeneraron y se verificaron en Chrome el 17 de noviembre; consultar la actualización de alcance siguiente.

## Listado entregado antes de retomar el objetivo

1. Completar y verificar la compatibilidad de las novedades de develop con PostgreSQL, incluidas migraciones, contabilidad, SellNow y vuelto distribuido.
2. Verificar instalación, rollback, catálogos, referencias bigint y aislamiento exclusivamente en bases temporales PostgreSQL 17.11.
3. Continuar exactitud sólo en facturación y sus PDF; no ampliar cambios de cálculos a otros módulos.
4. Evaluar la auditoría de facturación con alcance explícito, conservando visibles los candidatos globales y sus evidencias; no aprobarlos automáticamente ni renovar hashes sin revisión.
5. Regenerar assets autorizados y comprobar contratos PHP/JavaScript, interfaz y consumidores integrados.
6. Repetir aceptación final, respaldos y restauración en bases temporales; corregir fallos y comprobar inventario completo y limpieza.
7. Actualizar documentación, matriz Pro6–Pro9, evidencias y staging sobre la fuente final.

El rendimiento del POS permanece aplazado. Las baterías históricas no acreditan las fuentes posteriores a esta integración. La implementación continúa abierta.

## Actualización del 17 de noviembre

El usuario indicó conservar los formatos de reportes. Se retiraron los cambios de maquetación y se recuperó el diseño de develop, manteniendo compatibilidad PostgreSQL y precisión de facturación en las celdas originales. Los tres formularios de importación XLSX conservan su comportamiento original. La corrección, las pruebas ejecutadas y sus límites están en [conservación de formatos](conservacion_formatos_20261117.md).

La implementación continúa abierta; los ensayos anteriores a la integración no acreditan toda la fuente actual. Deben resolverse las revisiones estrictas de facturación restantes y repetirse la batería completa en bases temporales, con rendimiento POS aplazado.
