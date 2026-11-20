# Trazabilidad Pro6 → Pro9

Pro6 se consultó en modo de lectura: `/home/benahia/benahia/pro/pro6`, paquete `informes/migracion_postgresql_17/`, README, trazabilidad, seis parches históricos, `script/PostgreSQL.php`, comandos y configuración. Sus parches no se aplicaron y no se modificó su árbol. Adaptación dirigida a Laravel 9/PHP 8.2/Hyn 5.9.1.

| Origen/área en Pro6 | Implementación en Pro9 | Diferencia y prueba |
|---|---|---|
| PostgreSQL 17.5 en Docker | Compose Supabase 17.11.0.019, digest y AMD64 | `postgres_compose_probe.php`: arranque vacío, GCC, roles, persistencia, instalación/reanudación |
| Conexiones `system`/`tenant` | `config/database.php`, `PostgresServiceProvider` | Propietario, operación y aprovisionamiento separados; `PostgresImplementationTest` |
| Driver PostgreSQL histórico en `script/PostgreSQL.php` y parche Hyn | `app/Tenancy/PostgresDriver.php`, `TenantProvisioning` | Extensión fuera de vendor, límites de nombres, propiedad y compensación; alta real y fallos inyectados |
| Permisos de `public` | Bootstrap Docker y aprovisionamiento tenant | Roles sin superusuario; aislamiento SQL y recuperación con propietario nuevo |
| Migraciones MySQL convertidas en parches | Creadoras Schema Builder y FK final | 4380 columnas, 554 FK diferibles inicialmente inmediatas; esquema completo/rollback/repetición y copia nativa |
| `LIKE` → `ILIKE` | Gramática común y `TextSearch` | ICU para igualdad, `unaccent`/NFC con collation determinista para patrones de PostgreSQL 17; búsquedas y códigos CHAR |
| Consultas financieras | Fechas, COALESCE, JSON, concatenación, UNION y alias PostgreSQL | Conservar motor racional/snapshots de Pro9; suite fiscal y HTTP comercial |
| Diferencias transaccionales | `ReadSnapshot`, SQLSTATE, savepoints y bloqueos fiscales | RR dentro de transacción propia, reintento completo máximo 3; prueba real 40001 y concurrencia con 20 procesos |
| Respaldos todavía MySQL | `PostgresBackup`, manager, comandos restore | Dump nativo, manifiesto/checksum, central + dos empresas + archivos; activación y reversión ante fallo |
| Resolución dinámica tenant | Contexto de trabajos y guard de disponibilidad | Vaciar credenciales/conexiones al terminar y restaurar contexto en ejecución síncrona; pruebas de contexto |

Los resultados y los pendientes se registran por separado. Esta matriz identifica adaptación y prueba prevista/ejecutada; la existencia de un archivo de prueba no sustituye su resultado satisfactorio en las evidencias.

Los seis parches revisados quedan identificados por nombre y SHA-256 en `informes/evidencias/postgresql_17_implementacion/referencia_pro6.json`, junto con el controlador histórico. Las tres correcciones de cuentas por cobrar/pagar y Excel se contrastan con `Receivables`, `Payables`, los reportes financieros y sus pruebas de fracciones; las sustituciones históricas de `LIKE` se adaptan a la restricción de collations de PostgreSQL 17.
