---
name: reconstruir-migraciones-tenant
description: Consolida y valida las migraciones tenant de Pro9 desde la estructura efectiva de una base tenant. Usar al ordenar `database/migrations/tenant`, incorporar migraciones recientes, conservar esquema, índices, claves foráneas, comentarios y datos iniciales, o comprobar migración, seeding y rollback contra un tenant fuente.
---

# Reconstruir migraciones tenant de Pro9

## Contrato

- Tratar la base tenant fuente como autoridad; no inferir el esquema desde el historial.
- Crear una migración por tabla y una última migración para todas las claves foráneas. Excluir `migrations`.
- Conservar literalmente `SHOW CREATE TABLE`: columnas, orden, tipos, defaults, índices, motor, charset, collation, `AUTO_INCREMENT` y comentarios.
- Reproducir el historial sólo para extraer los datos que inserta o actualiza; no aplicar políticas de otros proyectos ni modificar catálogos.
- Mantener las migraciones antiguas en una copia temporal hasta completar ambas validaciones.

## Flujo

1. Consultar `tenancy.websites` y elegir el UUID del tenant fuente. No usar una base temporal ni un tenant registrado como destino de pruebas.
2. Copiar el historial a un directorio temporal dentro de `.codex/`; los respaldos deben contener sólo `*.php` ejecutables.
3. Generar estructura desde el tenant con `scripts/generate_tenant_migrations.php`. Indicar una salida vacía y una fecha estable.
4. Generar `database/seeders/data/tenant_initial_data.php` ejecutando el historial copiado con `scripts/generate_tenant_seed_data.php`.
5. Añadir `TenantMigrationDataSeeder` y llamarlo primero desde `TenancyDatabaseSeeder`.
6. Reemplazar únicamente `database/migrations/tenant/*.php` cuando los artefactos temporales estén generados.
7. Ejecutar los dos validadores. Ambos deben completar una migración limpia, rollback, segunda migración y comparaciones exactas.

## Comandos

Ejecutar mediante el contenedor PHP de Pro9:

```bash
php .codex/skills/reconstruir-migraciones-tenant/scripts/generate_tenant_migrations.php \
  --source=tenancy_bbc --legacy=database/migrations/tenant \
  --output=.codex/tenant-migrations-stage --date=YYYY_MM_DD

php .codex/skills/reconstruir-migraciones-tenant/scripts/generate_tenant_seed_data.php \
  --legacy=.codex/tenant-legacy-stage --target=pro9_tenant_seed_source_test \
  --output=database/seeders/data/tenant_initial_data.php --charset-source=tenancy_bbc

php .codex/skills/reconstruir-migraciones-tenant/scripts/validate_tenant_migrations.php \
  --source=tenancy_bbc --target=pro9_tenant_schema_test \
  --migrations=database/migrations/tenant

php .codex/skills/reconstruir-migraciones-tenant/scripts/validate_tenant_seeders.php \
  --legacy=.codex/tenant-legacy-stage --migrations=database/migrations/tenant \
  --data=database/seeders/data/tenant_initial_data.php
```

## Criterio de entrega

Informar el tenant fuente y los conteos de tablas, columnas, índices, claves foráneas y filas preservadas. Exigir cero diferencias de esquema y datos, integridad FK, rollback limpio y segunda ejecución idéntica. Documentar cualquier diferencia como bloqueo, no como éxito.
