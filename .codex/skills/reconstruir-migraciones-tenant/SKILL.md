---
name: reconstruir-migraciones-tenant
description: Mantener el esquema inicial consolidado y los datos vigentes de Pro9, ordenados, reversibles y verificables en bases temporales. Usar al modificar database/migrations/tenant, columnas, índices, claves foráneas o seeders.
---

# Esquema inicial tenant de Pro9

## Contrato

- El proyecto parte de cero, sin tenants ni información histórica. No copiar bases reales, reconciliar tablas de migraciones ni crear comandos de conversión o backfills.
- Se retiran los scripts `generate_tenant_migrations.php`, `generate_tenant_seed_data.php`, `validate_tenant_migrations.php` y `validate_tenant_seeders.php`: dependían de una base fuente o de reproducir migraciones históricas. La verificación vigente es `FiscalEmissionSchemaTest` sobre bases temporales creadas desde el consolidado.
- Mantener una migración por tabla. Crear todas las tablas antes de agregar las claves en `2026_08_17_000999_add_tenant_foreign_keys.php`; su `down()` debe retirarlas en orden inverso.
- Laravel administra `migrations`; no incluirla como tabla de aplicación.
- Cada tabla debe declarar directamente sus columnas, tipos, nullability, defaults, índices, motor, charset, collation y comentarios vigentes. No crear campos retirados para eliminarlos después.
- Al consolidar una modificación, capturar primero el esquema producido por la rama en una base temporal y comparar el resultado completo. Toda diferencia debe corresponder a una decisión explícita del cambio.
- Mantener un inventario de columnas junto al DDL. No usar contadores `AUTO_INCREMENT` extraídos de datos de prueba como estado inicial.
- Los parámetros de empresa y facturas incluyen modalidad y ambiente obligatorios según `mantener-modalidad-emision-fiscal-pro9`.
- Conservar estructuras vigentes de ecommerce, variaciones, inventario y preferencias aunque su creación haya estado antes en una migración incremental. `items.parent_item_id` tiene índice y FK autorreferenciada con borrado restringido.

## Datos iniciales

- `database/seeders/data/tenant_initial_data.php` contiene directamente los catálogos actuales. `venezuela_geopolitical_data.php` contiene la jerarquía venezolana.
- `TenantMigrationDataSeeder` carga ambos archivos, antes de `TenancyMockDataSeeder`. Puede repetirse por identidad; no remapea números de documento, monedas, ubicaciones ni grupos existentes.
- Los estados de ecommerce se definen una sola vez en los datos iniciales: trece estados con sus acciones y categorías. El alta no borra ni vuelve a crear ese catálogo.
- No sembrar el servicio de penalidad vinculado al motivo retirado de nota de débito `13`. Conservar el servicio de envío que utiliza ecommerce.
- No sembrar catálogos retirados ni referencias hacia ellos. Si una tabla tiene filas iniciales, comprobar todas sus claves foráneas después del seeding.
- Los datos `MOCK-` pertenecen exclusivamente a `TenancyMockDataSeeder`; no incorporarlos al catálogo inicial al capturar una instalación de prueba.

## Verificación

1. Usar exclusivamente bases temporales con nombres controlados; eliminarlas tanto en éxito como en error. No operar sobre bases reales.
2. Ejecutar `FiscalEmissionSchemaTest` con `PRO9_FISCAL_MYSQL_TESTS=1` dentro del contenedor PHP. Comprueba creación completa, `TenancyDatabaseSeeder`, integridad referencial global, rollback y segunda creación con esquema y datos iguales, normalizando sólo timestamps generados.
3. Mantener `TenantMigrationDataSeederTest`, pruebas de catálogos y de módulos consumidores alineadas con el estado final, sin exigir nombres de migraciones incrementales eliminadas.
4. Probar las operaciones actuales de los módulos afectados en una instalación temporal. Un esquema correcto no sustituye una prueba de creación, búsqueda o cálculo.
5. Validar sintaxis PHP y `git diff --check`. Registrar conteos y diferencias intencionales en el informe del cambio.

## Entrega

Documentar las tablas y datos que cambiaron, elementos retirados, funciones conservadas, resultados de pruebas y limitaciones. No afirmar que se verificaron tenants reales. No crear commits ni desplegar sin la instrucción correspondiente.

## Configuración inicial de pedidos

- Retirar `configurations.has_advanced_statuses` del DDL consolidado y del alta en `ClientController`: no tiene consumidores vigentes y sólo distinguía versiones anteriores del catálogo de estados. Los trece estados actuales se inicializan directamente; no añadir una bandera de conversión completada.
- `FiscalEmissionSchemaTest` verifica la ausencia de esa columna junto a la instalación, seeding, integridad, rollback y repetición.

## Columnas fiscales peruanas retiradas

- La creación inicial no incluye `companies.operation_amazonia`, `configurations.legend_footer`, `configurations.legend_forest_to_xml`, `configurations.default_document_type_03`, `configurations.name_product_pdf_to_xml` ni `document_items.name_product_xml`.
- `format_templates` no siembra `legend_amazonia`. Mantener los identificadores de los demás formatos; no renumerarlos para cubrir el hueco.
- Verificar estas ausencias en `FiscalEmissionSchemaTest`, además de buscar consumidores en PHP, Vue y plantillas antes de retirar una columna.

## Transporte fiscal e históricos retirados

- `documents` no crea `hash`, `qr`, `has_xml`, `has_cdr`, `send_server`, estados de envío/consulta, banderas de éxito ni campos de regularización.
- `dispatches` no crea `sunat_error_response`, `has_xml`, `has_cdr`, `ticket` ni `reception_date`. Conserva `hash` y `qr_url` porque identifican y enlazan la orden de entrega vigente.
- `perceptions`, `retentions` y `purchase_settlements` no crean `hash`, `has_xml` ni `has_cdr`; `voided` no crea `ticket`, `has_ticket` ni `has_cdr`.
- `configurations` no crea `send_auto`, `sunat_alternate_server`, `auto_send_dispatchs_to_sunat` ni `send_data_to_other_server`. No crear tablas `pse_providers`, `migration_configurations` ni `sale_note_migrations`.
- `companies` no crea credenciales SIRE (`sire_client_id`, `sire_client_secret`, `sire_username`, `sire_password`) y el módulo `Sire` no forma parte de la instalación.
- Los niveles centrales/tenant no incluyen `document_not_sent` ni `regularize_shipping`. `FiscalEmissionSchemaTest` debe verificar columnas y niveles ausentes además de la igualdad de los dos ciclos limpios.
- La política de instalación nueva también alcanza las migraciones del sistema relacionadas: `massive_invoices` se define completa en su migración creadora, conserva el módulo y usa `estado_emision`/`mensaje_emision`, sin migraciones incrementales ni columnas SUNAT/XML/CDR.
