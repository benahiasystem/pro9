---
name: adaptar-sistema-venezuela
description: Coordinar y documentar una adaptación funcional integral de Pro9 a Venezuela con trazabilidad, compatibilidad histórica, migraciones consolidadas y pruebas.
---

# Adaptar integralmente Pro9 a Venezuela

## Reglas obligatorias

- Tomar Pro8 como referencia funcional, revisar su rama `migration_venezuela` y adaptar cada cambio a la arquitectura vigente de Pro9.
- Delimitar todo bloque de código incorporado o modificado con comentarios de inicio y fin que contengan `########`.
- No editar bundles compilados manualmente: regenerar `public/build` mediante `npm run build`.
- Centralizar `VE`, `+58`, `VES`, `Bs.`, `USD` y `14/0229/000619` en `config/venezuela.php` y `App\Support\Venezuela\Localization`.
- Mostrar `IVA` sin renombrar contratos internos, columnas ni cálculos heredados `igv`.
- No afirmar integración oficial con SENIAT cuando el cambio sea solo funcional o terminológico.

## Cobertura

- País y territorio: Venezuela, `America/Caracas`, Estado/Municipio/Parroquia y catálogo territorial cargado desde el tenant.
- Personas: país/nacionalidad, RIF, cédula, extranjero, direcciones, sitio web y observaciones.
- Moneda: VES/Bs./Bolívares como moneda nacional, USD como secundaria, sin recalcular importes históricos.
- Telefonía: +58 y enlaces `tel:`/`wa.me` sin prefijos duplicados.
- POS: botón `PAGAR` siempre visible, selección de FACTURA/BOLETA/NOTA DE VENTA y accesos opcionales protegidos.
- Importación: validar íntegramente `public/formats/items.xlsx` antes de `ItemsImport` y entregar un XLSX corregible cuando haya errores.
- Datos mock: crear únicamente registros identificados con `MOCK-` y hacerlo de forma idempotente.

## Migraciones consolidadas

1. Mantener una migración por tabla y una única migración final de claves foráneas.
2. Preservar tipos, comentarios, defaults, índices, motores y collations del esquema efectivo.
3. Restaurar el catálogo histórico mediante `TenantMigrationDataSeeder` antes de cualquier dato mock.
4. Actualizar el archivo de datos iniciales de forma reproducible cuando cambien país, territorio, documentos o monedas.
5. Validar migración, seed, esquema exacto, datos exactos, integridad referencial y rollback en una base temporal.

## Pruebas mínimas

- `PersonRequestVenezuelaTest`
- `VenezuelaLocalizationTest`
- `VenezuelaSourceContractTest`
- `VenezuelaGeopoliticalContractTest`
- `VenezuelaCurrencyLocalizationTest`
- `VenezuelaPhoneLocalizationTest`
- `TenantMigrationDataSeederTest`
- Todas las pruebas `ItemImport*`

Ejecutar además lint PHP, validación de skills, `git diff --check`, auditoría de PE/PEN/VED/+51, suite unitaria, build Vite y validación real de migrate/seed/rollback.

## Entrega

- Mantener una rama y un worktree por skill, basados en `develop`.
- Revisar cada rama por separado antes de integrarla.
- Informar commits, pruebas, datos preservados, limitaciones ambientales y cualquier fallo ajeno al alcance.
