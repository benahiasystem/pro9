---
name: mantener-tenancy-2-sab
description: Mantener el contexto multitenant Tenancy 2 de SAB, sus conexiones PostgreSQL, dominios, colas, almacenamiento y compatibilidad con empresas existentes.
---

# Tenancy 2 en SAB

SAB usa `tenancy/tenancy` 2.5 mediante los adaptadores de `app/Tenancy`. Conserva `websites`, `hostnames`, conexiones `system` y `tenant`, una base y rol PostgreSQL por empresa, y `website_id` en las colas existentes. No sustituyas estos contratos sin una migración explícita.

- Revisa `Environment`, `Database/Connection`, `TenantDispatcherMiddleware`, `TenantJobContext` y `PostgresServiceProvider` antes de cambiar la identificación o activación del tenant. Los cambios de contexto deben limpiar conexión, disco y caché y restaurar el contexto previo incluso ante errores.
- Conserva la derivación de contraseñas de roles existentes y el aprovisionamiento y restauración propios de SAB. Verifica el aislamiento entre dos empresas en HTTP, trabajos, Redis y archivos cuando afectes esos flujos; incluye dominio desconocido y fallos de activación.
- Los comandos `tenancy:migrate`, `tenancy:seed` y `tenancy:run` operan sobre empresas: revisa su alcance y usa bases temporales para pruebas. No ejecutes migraciones sobre datos reales como comprobación.
- Para la batería PostgreSQL, consulta `informes/migracion_tenancy_2.md` y `scripts/test_postgresql.php`. El perfil `--ves-documents-only` es una selección limitada; conserva casos de cobros USD sobre documentos VES y no sustituye la batería completa. Revisa los efectos del arnés y sus requisitos antes de ejecutarlo.
- Para los resultados históricos y límites de la migración, consulta `informes/resumen_migracion_laravel_10_tenancy_2.md` y los informes enlazados.
