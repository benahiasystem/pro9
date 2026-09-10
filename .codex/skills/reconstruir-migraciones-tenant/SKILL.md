---
name: reconstruir-migraciones-tenant
description: Reconstruir la estructura y los datos iniciales de una base tenant de Pro9 como migraciones Laravel y seeders nuevos, ordenados, reversibles y verificables. Usar al consolidar o reemplazar database/migrations/tenant, clonar tablas, columnas, índices, claves foráneas, comentarios y registros insertados por migraciones históricas, o al validar migrate/seed/rollback contra un tenant fuente.
---

# Reconstruir migraciones de un tenant Pro9

## Contrato

- Para la modalidad fiscal SOAP/PFX de Pro9, la autoridad es el contrato de instalación nueva de [mantener-modalidad-emision-fiscal-pro9](../mantener-modalidad-emision-fiscal-pro9/SKILL.md): no hay tenant fuente ni conversión histórica que reproducir. Modificar el consolidado y comprobar creación/seeding/rollback/segunda creación en bases temporales con `FiscalEmissionSchemaTest`. Las instrucciones de copiar un tenant o reproducir su historial de esta skill no aplican a ese caso.
- Tomar como autoridad la estructura efectiva de un tenant, no la intención de las migraciones históricas.
- Crear una migración por tabla y una migración final para claves foráneas.
- Excluir `migrations`: Laravel debe crear y administrar esa tabla.
- Conservar exactamente nombres, orden de columnas, tipos, longitudes, unsigned, nullability, defaults, expresiones, índices, claves, motores, charset, collation y `AUTO_INCREMENT`.
- Conservar todos los `COMMENT` de tablas y columnas presentes en MySQL.
- Preservar los PHPDoc explicativos de las migraciones anteriores y añadir un inventario generado de columnas. No inventar significados ausentes.
- Separar las claves foráneas de `CREATE TABLE` y agregarlas al final para evitar dependencias circulares.
- Reproducir las migraciones históricas en una base temporal para capturar el estado final de todos sus `insert` y `update`.
- Guardar esos registros en `database/seeders/data/tenant_initial_data.php` y restaurarlos mediante `TenantMigrationDataSeeder`.
- Mantener Perú en `countries` sólo cuando sea necesario para nacionalidades; nunca como valor predeterminado. Para la política venezolana total, la migración final debe eliminar PE del tenant efectivo.
- No borrar las migraciones antiguas hasta generar y verificar el reemplazo en un directorio temporal.
- Delimitar cualquier modificación manual de código con comentarios válidos que contengan `########### INICIO` y `########### FIN`; no alterar el DDL capturado sólo para añadir marcas.

## Flujo

1. Identificar el tenant fuente y comparar su esquema con otros tenants disponibles para detectar divergencias.
2. Contar tablas, columnas, índices, claves foráneas, vistas, triggers, rutinas y eventos.
3. Copiar el historial a un directorio temporal dentro de `.codex/`; los respaldos deben contener sólo `*.php` ejecutables.
4. Ejecutar `scripts/generate_tenant_migrations.php` desde el contenedor PHP, indicando base fuente, directorio histórico y salida temporal.
5. Confirmar que el generador encontró una migración histórica por cada tabla cuando se deban preservar PHPDoc.
6. Revisar el reporte: tablas generadas, comentarios conservados, claves foráneas extraídas y ciclos de dependencia.
7. Reemplazar únicamente `database/migrations/tenant/*.php`; los archivos rastreados siguen siendo recuperables mediante Git.
8. Ejecutar `scripts/generate_tenant_seed_data.php` usando una copia Git de las migraciones retiradas; ignorar archivos `.php.bak`.
9. Integrar `TenantMigrationDataSeeder` antes de cualquier seeder de datos mock.
10. Ejecutar `scripts/validate_tenant_migrations.php` para la estructura y `scripts/validate_tenant_seeders.php` para los registros.
11. Exigir migración y seeding limpios, comparación exacta, rollback completo y una segunda ejecución idéntica.
12. Ejecutar las pruebas unitarias de contratos funcionales que dependan de migraciones o catálogos.

## Generación

Ejecutar mediante el contenedor PHP de Pro9:

```bash
php .codex/skills/reconstruir-migraciones-tenant/scripts/generate_tenant_migrations.php \
  --source=tenancy_bbc \
  --legacy=database/migrations/tenant \
  --output=.codex/tenant-migrations-stage \
  --date=YYYY_MM_DD
```

El generador debe fallar si la salida no está vacía, si una tabla o base tiene un nombre inseguro, si no puede interpretar todas las claves foráneas o si el esquema contiene objetos distintos de tablas base que no pueda representar.

## Datos iniciales

```bash
php .codex/skills/reconstruir-migraciones-tenant/scripts/generate_tenant_seed_data.php \
  --legacy=.codex/tenant-legacy-stage \
  --target=pro9_tenant_seed_source_test \
  --output=database/seeders/data/tenant_initial_data.php \
  --charset-source=tenancy_bbc
```

- Reproducir sólo migraciones `*.php`, nunca respaldos `*.php.bak`.
- Desactivar temporalmente las comprobaciones FK durante la reproducción histórica y comprobar después que no existan registros huérfanos.
- Ordenar tablas por dependencias y conservar claves de identidad para que el seeder sea repetible.
- Aplicar únicamente ajustes de política explícitos: actualmente VE/VES, RIF, territorio venezolano y ausencia de PEN/VED/PE en el estado efectivo del tenant.

## Validación

```bash
php .codex/skills/reconstruir-migraciones-tenant/scripts/validate_tenant_migrations.php \
  --source=tenancy_bbc \
  --target=pro9_tenant_schema_test \
  --migrations=database/migrations/tenant

php .codex/skills/reconstruir-migraciones-tenant/scripts/validate_tenant_seeders.php \
  --legacy=.codex/tenant-legacy-stage \
  --migrations=database/migrations/tenant \
  --data=database/seeders/data/tenant_initial_data.php
```

- Permitir como destino sólo nombres que terminen en `_schema_test`; nunca usar un tenant registrado.
- Comparar cada `SHOW CREATE TABLE`, excepto la tabla `migrations`.
- Verificar también conteos de tablas, columnas, índices, claves foráneas y comentarios.
- Después del rollback debe quedar únicamente `migrations`, vacía.
- Eliminar la base temporal al finalizar, tanto en éxito como en error.
- No declarar éxito con una comparación parcial ni con un único `migrate`.
- Comparar fila por fila el seeder con el resultado de las migraciones históricas.
- Normalizar sólo timestamps generados durante la comparación histórica.
- Verificar PE/VE, VES/USD, ausencia de PEN/VED, conteos territoriales e integridad de todas las claves foráneas.
- Ejecutar `migrate`, seeder, rollback, segundo `migrate`, segundo seeder y segunda comparación.
- Ejecutar también `TenancyDatabaseSeeder` para comprobar la integración real del seeder consolidado.

## Entrega

- Informar tenant fuente, número de migraciones, tablas, columnas, índices, claves foráneas, comentarios y registros preservados.
- Informar resultados separados de migración, seeding, comparación de estructura/datos, rollback, segunda ejecución y pruebas unitarias.
- Documentar cualquier objeto no representado o diferencia; cero diferencias es condición de finalización.
