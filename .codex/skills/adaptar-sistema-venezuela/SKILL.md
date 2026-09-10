---
name: adaptar-sistema-venezuela
description: Coordinar y documentar Pro9 para instalaciones nuevas en Venezuela, con esquema consolidado, catálogos vigentes y pruebas. Usar para cambios que combinen país, geopolítica, RIF/cédula, IVA, moneda, telefonía, POS, caja, documentos, compras, reportes o configuración.
---

# Adaptar integralmente Pro9 a Venezuela

## Principios

- Implementar cambios en fuentes de Pro9; no editar bundles compilados manualmente ni respaldos. Aplicar `frontend-build`: la compilación queda a cargo del usuario.
- Este proyecto no tiene tenants ni información histórica que convertir. Modificar directamente las creaciones de tablas y sus datos iniciales; no crear backfills ni comandos para tenants existentes.
- Retirar herramientas de migración entre servidores, regularización/reenvío fiscal, módulos PSE, el módulo peruano SIRE y estados externos de SUNAT. Conservar únicamente barreras defensivas que rechacen solicitudes XML/CDR antiguas y las funciones comerciales locales vigentes.
- Mantener la facturación masiva como función comercial: carga, emisión por tenant, listado, filtros, Excel y PDF. Sus estados son locales (`estado_emision`/`mensaje_emision`) y no deben comunicar aceptación SUNAT ni ofrecer XML/CDR.
- Sembrar directamente VE/VES y validar el contrato vigente, sin convertir entradas PE/PEN/VED.
- Mantener claves internas heredadas cuando cambiarlas rompa esquema, XML o APIs; adaptar la presentación.
- Centralizar `VE`, `+58`, `VES`, `Bs.`, `USD` y la ubicación inicial en `config/venezuela.php` y `App\Support\Venezuela\Localization`.
- Mostrar `IVA` en interfaz sin renombrar columnas, cálculos ni contratos internos `igv`.
- Delimitar todo bloque de código incorporado o modificado con comentarios de inicio y fin que contengan `########`.
- No afirmar integración oficial con SENIAT si sólo se adaptó terminología. No reutilizar secretos ni endpoints de otros proyectos.

## Cobertura de esta adaptación

- País y territorio: usar VE, zona horaria `America/Caracas`, Estado/Municipio/Parroquia y ubicación inicial `14/0229/000619`.
- Clientes: normalizar país y nacionalidad, validar RIF/cédula/Extranjero, guardar direcciones venezolanas y conservar Sitio Web/Observaciones.
- Moneda: usar VES/Bs./Bolívares y USD; revisar documentos, compras, POS, caja, finanzas, ecommerce, restaurante y reportes. No interpretar monedas retiradas como VES.
- Métodos de pago: sembrar exclusivamente desde `database/seeders/data/tenant_initial_data.php` los IDs `01`–`07` y `09`–`13` del contrato venezolano; usar `05` para Crédito a 30 días, no crear `08`, y no crear una migración incremental ni conservar registros históricos para este catálogo.
- Telefonía: mostrar +58, normalizar teléfonos y construir enlaces `tel:`/`wa.me` y payloads QR sin prefijos duplicados.
- POS: mantener `PAGAR` visible, permitir FACTURA/NOTA DE VENTA y proteger accesos opcionales a QZ y turnos de negocio.
- Importación: validar íntegramente `public/formats/items.xlsx` antes de `ItemsImport` y entregar un XLSX corregible cuando haya errores.
- Datos de prueba: usar `TenancyMockDataSeeder` sólo para registros identificados con `MOCK-`; no confundirlos con datos productivos.
- Eliminación de documentos de prueba: limitar la acción a administradores, exigir la confirmación literal `ELIMINAR` en frontend y backend, y borrar relaciones dentro de una transacción tenant antes del registro principal. Nunca incluir documentos históricos ni productivos fuera del alcance marcado como prueba.

## Migraciones consolidadas

1. Usar `reconstruir-migraciones-tenant` al modificar el esquema inicial; comparar instalaciones temporales completas, sin copiar un tenant real.
2. Mantener una migración por cada tabla de aplicación y excluir la tabla técnica `migrations`.
3. Crear todas las tablas sin claves foráneas y agregarlas en una migración final para evitar errores de orden o ciclos.
4. Conservar comentarios, tipos, defaults, índices, motores y collations mediante el DDL efectivo de MySQL.
5. Cargar los catálogos vigentes mediante `TenantMigrationDataSeeder`, antes de `TenancyMockDataSeeder`. No transformar registros existentes ni conservar estados transitorios de módulos.
6. No conservar referencias a migraciones incrementales retiradas; los contratos deben localizar la migración consolidada por nombre de tabla.

## Instalación nueva

Crear las tablas una sola vez y agregar sus claves en `000999_add_tenant_foreign_keys`. No ejecutar modificaciones sobre bases reales. Validar en bases temporales creación, datos iniciales, todas las claves foráneas, rollback y repetición idéntica. Las funciones de auditoría futura, aislamiento y bloqueo de ambiente siguen vigentes.

## Alta y primer acceso de un tenant

Modalidad y ambiente se rigen por [mantener-modalidad-emision-fiscal-pro9](../mantener-modalidad-emision-fiscal-pro9/SKILL.md). Crear directamente `fiscal_environment` y `fiscal_emission_mode`, ambos obligatorios. La política de instalación nueva aplica a todos los módulos.

<!-- ######## INICIO CONTRATO DE INICIALIZACIÓN TENANT ######## -->

- No resolver `CurrentHostname` directamente durante `AppServiceProvider::boot()`: diferir cualquier configuración dependiente del tenant hasta `app->booted()` o hasta middleware.
- Mantener `EnsureTenantConnection` antes de sesión y autenticación. Si falta `database.connections.tenant`, debe consultar el hostname vigente por el host de la petición y activar su website mediante `Environment`.
- Al crear o eliminar un cliente, invalidar `tenancy.hostname.{fqdn}`, `tenancy.website.{uuid}` y `tenant_session_lifetime_{fqdn}`. Esto es obligatorio si se puede reutilizar un subdominio eliminado.
- Verificar el primer acceso con una petición nueva a `/login`; debe responder `200` aun cuando Redis contenga previamente un hostname obsoleto para el mismo FQDN.

<!-- ######## FIN CONTRATO DE INICIALIZACIÓN TENANT ######## -->

## Pruebas de contrato

- Ejecutar `PersonRequestVenezuelaTest` para clientes nacionales, extranjeros, proveedores y formatos de documento.
- Ejecutar `VenezuelaLocalizationTest` para VE, +58, VES/USD, símbolos, teléfonos y códigos territoriales.
- Ejecutar `TenantConnectionBootstrapContractTest` cuando se toque creación, eliminación, caché, providers o middleware de tenants.
- Ejecutar `VenezuelaSourceContractTest` para fuentes activas, estructura consolidada, defaults, parroquias sin código, esquema de personas, POS, moneda y datos mock.
- Ejecutar `VenezuelaGeopoliticalContractTest`, `VenezuelaCurrencyTest`, `VenezuelaPhoneLocalizationTest`, `TenantMigrationDataSeederTest` y todas las pruebas `ItemImport*`.
- Ejecutar `VenezuelaInitialCatalogContractTest` para verificar el catálogo exacto de `payment_method_types`, sus banderas activas y la ausencia de `08`.
- Mantener estas pruebas enfocadas en el estado final; no confundir un país disponible como nacionalidad extranjera con un default geográfico PE.

## Secuencia

1. Leer la tarjeta, sus criterios y la evidencia canónica de Pro8.
2. Ejecutar preflight de esquema, datos referenciados, catálogos, integraciones y artefactos generados.
3. Aplicar las skills específicas de clientes, geopolítica, moneda, telefonía e importación cuando correspondan.
4. Adaptar presentación: IVA, RIF/cédula, Estado/Municipio/Parroquia, Bs. y +58, sin cambiar cálculos fiscales sólo por renombrar etiquetas.
5. Mantener visible la acción `PAGAR` del POS dentro del panel de cobro; separar el desplazamiento del carrito y del detalle de totales para que la acción no salga del viewport.
6. Validar desde POS que la acción abra el paso de pago y permita seleccionar `FACTURA`, sin guardar una venta de prueba salvo que sea necesario.
7. Añadir validaciones backend para impedir combinaciones o proveedores no soportados; bloquear Culqi cuando la moneda nacional sea VES.
8. Ejecutar migraciones tenant dentro del contenedor PHP, lint, pruebas unitarias, compilación frontend y validación en el hostname del tenant.
9. Auditar fuentes activas y bundle generado; excluir respaldos, fuentes vendorizadas y catálogos canónicos de errores externos.

## Entrega

- Informar tarjetas cubiertas, migraciones, datos preservados, pruebas ejecutadas y limitaciones de integración.
- Informar cualquier prueba ajena al cambio que continúe fallando y no ocultarla con cambios de alcance distinto.
- Mantener una rama y un worktree por skill, basados en `develop`, cuando el cambio se trabaje en paralelo.
