# Resumen de la migración Laravel 10 y Tenancy 2

Este documento resume los cambios integrados desde `codex/tenancy-2` en `migration-postgresql`. Los resultados indicados son históricos, obtenidos en la rama de origen; no prueban por sí solos la fusión con los cambios posteriores de la rama de destino.

## Cambios

- Actualización a Laravel 10.50.4, PHP 8.2 y Laravel Modules 10.0.6, con ajustes de dependencias y compatibilidad PostgreSQL. Se conservaron PHPUnit 9 y Collision 6 por el ejecutor de la batería. Véase [migración a Laravel 10](migracion_laravel_10.md).
- Sustitución de Hyn por Tenancy 2.5 con adaptadores de SAB. Se mantienen las empresas y dominios existentes, conexiones, roles PostgreSQL, almacenamiento, aprovisionamiento y el identificador `website_id` de los trabajos. Se adaptaron modelos, rutas, comandos y servicios al nuevo contexto. Véase [migración a Tenancy 2](migracion_tenancy_2.md).
- Ajustes y pruebas del contexto real en MultiUser y BCV, PDF y series; revisión del orden de la migración que retira NRUS; y validación y compactación de filtros de la batería fiscal. Se incorporaron contratos versionados de esquema, casts y referencia de rendimiento.
- La rama de destino conserva sus cambios para precios y cobros en USD con documentos en VES. El perfil de aceptación limitado a documentos VES incluye cobros USD, pero no sustituye una verificación completa de la integración.

## Evidencia y límites

Los informes describen ensayos locales con empresas ficticias, aislamiento entre tenants, colas, ventas y reversión de versión; la selección fiscal dirigida de Tenancy 2 registró 1.757 aserciones. El informe de Laravel 10 registra tres fallos previos compartidos en pruebas relacionadas con la semilla central, alta y restauración, así como un fallo de `view:cache` en ambas versiones. Laravel 10 está fuera de soporte de seguridad.

Las rutas `storage/app/laravel10-preproduction` y `storage/app/tenancy2-preproduction` citadas en los informes se refieren al worktree original y no son evidencias versionadas trasladadas por Git. Los informes y los tres archivos JSON versionados sí forman parte de la integración. Las evidencias locales no versionadas requieren revisión independiente antes de trasladarse o publicarse.

El resumen local de la batería fiscal del commit `5520f5e8` registra 2.495 pruebas PHPUnit y 87.618 aserciones sin fallos en las fases fiscal, general y de rendimiento. También registra 1.206 pruebas JavaScript y verificaciones en Chrome. El arnés devolvió código 1 por las auditorías de precisión fiscal PHP y JavaScript, cuyos reportes coincidieron con la referencia histórica de Tenancy 2. La selección excluyó casos de documentos USD y de Contabilidad, y mantuvo los cobros USD y mixtos sobre documentos VES. El detalle está en `sab-pgtest-924cbd98cdd7-resumen.md`, bajo `informes/evidencias/postgresql_17_implementacion/resultados/` del worktree original.

## Verificación local de la integración

El contenedor PHP de `migration-postgresql` usa PHP 8.2.35 con Composer y `pdo_pgsql`. Se instaló el `composer.lock`, se regeneró el descubrimiento de paquetes y se reinició PHP-FPM para restablecer también el directorio temporal de mPDF. El login central y el de la empresa existente respondieron HTTP 200 y un dominio desconocido devolvió 404. Se comprobó el acceso a PostgreSQL 17.11, Redis y el disco de la empresa, y se generó un PDF en el contenedor en servicio.

En PostgreSQL temporal pasaron la aceptación HTTP (1.497 aserciones), 22 casos de contexto y operación multitenant (875 aserciones), la instalación central, el alta, la restauración de dos empresas y las pruebas dirigidas de PDF. La prueba HTTP detecta el contrato VES del esquema nuevo. El caso de PDF USD usa un esquema temporal histórico y pasó 343 aserciones. La verificación de la integración fue dirigida.
