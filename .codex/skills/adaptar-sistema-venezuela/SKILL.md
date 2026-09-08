---
name: adaptar-sistema-venezuela
description: Coordinar y documentar una adaptación funcional integral de Pro9 a Venezuela con trazabilidad, compatibilidad histórica, migraciones y pruebas. Usar para cambios que combinen país, geopolítica, RIF/cédula, IVA, moneda, telefonía, POS, caja, documentos, compras, reportes, configuración o bundles generados.
---

# Adaptar integralmente Pro9 a Venezuela

## Principios

- Implementar cambios en fuentes de Pro9; no editar bundles compilados manualmente ni respaldos. Regenerar `public/build` con `npm run build` cuando el repositorio versiona los artefactos.
- Usar migraciones incrementales, transaccionales e idempotentes.
- Reemplazar los registros PE/PEN/VED por VE/VES cuando la política del proyecto sea una migración total.
- Mantener claves internas heredadas cuando cambiarlas rompa esquema, XML o APIs; adaptar la presentación.
- Centralizar `VE`, `+58`, `VES`, `Bs.`, `USD` y la ubicación inicial en `config/venezuela.php` y `App\Support\Venezuela\Localization`.
- Mostrar `IVA` en interfaz sin renombrar columnas, cálculos ni contratos internos `igv`.
- Delimitar todo bloque de código incorporado o modificado con comentarios de inicio y fin que contengan `########`.
- No afirmar integración oficial con SENIAT si sólo se adaptó terminología. No reutilizar secretos ni endpoints de otros proyectos.

## Cobertura de esta adaptación

- País y territorio: usar VE, zona horaria `America/Caracas`, Estado/Municipio/Parroquia y ubicación inicial `14/0229/000619`.
- Clientes: normalizar país y nacionalidad, validar RIF/cédula/Extranjero, guardar direcciones venezolanas y conservar Sitio Web/Observaciones.
- Moneda: usar VES/Bs./Bolívares, conservar USD, migrar PEN y VED sin recalcular importes y revisar documentos, compras, POS, caja, finanzas, ecommerce, restaurante y reportes.
- Telefonía: mostrar +58, normalizar teléfonos y construir enlaces `tel:`/`wa.me` y payloads QR sin prefijos duplicados.
- POS: mantener `PAGAR` visible, permitir seleccionar FACTURA/BOLETA/NOTA DE VENTA y proteger accesos opcionales a QZ y turnos de negocio.
- Importación: validar íntegramente `public/formats/items.xlsx` antes de `ItemsImport` y entregar un XLSX corregible cuando haya errores.
- Datos de prueba: usar `TenancyMockDataSeeder` sólo para registros identificados con `MOCK-`; no confundirlos con datos productivos.
- Eliminación de documentos de prueba: limitar la acción a administradores, exigir la confirmación literal `ELIMINAR` en frontend y backend, y borrar relaciones dentro de una transacción tenant antes del registro principal. Nunca incluir documentos históricos ni productivos fuera del alcance marcado como prueba.

## Migraciones consolidadas

1. Usar `reconstruir-migraciones-tenant` cuando se reemplace el historial completo por la estructura efectiva de un tenant.
2. Mantener una migración por cada tabla de aplicación y excluir la tabla técnica `migrations`.
3. Crear todas las tablas sin claves foráneas y agregarlas en una migración final para evitar errores de orden o ciclos.
4. Conservar comentarios, tipos, defaults, índices, motores y collations mediante el DDL efectivo de MySQL.
5. Restaurar las filas históricas mediante `TenantMigrationDataSeeder` y ejecutar este seeder antes de `TenancyMockDataSeeder`.
6. No conservar referencias a migraciones incrementales retiradas; los contratos deben localizar la migración consolidada por nombre de tabla.

## Tenants históricos existentes

<!-- ######## INICIO PUENTE DE COMPATIBILIDAD TENANT HISTÓRICO ######## -->

Las migraciones consolidadas no deben ejecutarse directamente sobre un tenant cuyo esquema ya existe con nombres de migración históricos. Antes de afirmar que la adaptación está completa:

1. Crear y comprobar un respaldo SQL recuperable del tenant.
2. Ejecutar primero `php artisan tenant:migrate-venezuela {uuid} --dry-run`.
3. Reconciliar únicamente la línea base consolidada `000001` a `000328`; nunca marcar `000329` ni `000330` como ejecutadas por adelantado.
4. Ejecutar `php artisan tenant:migrate-venezuela {uuid}` para transformar datos monetarios, territoriales y documentales existentes.
5. Verificar en la base real, no sólo en una base temporal: `VE`, 25/335/1138, RIF/Cédula/Extranjero, VES/Bs./Bolívares, USD activa y ausencia de PE/PEN/VED.
6. Limpiar cachés y comprobar en navegador el POS y los formularios del hostname del tenant.

<!-- ######## FIN PUENTE DE COMPATIBILIDAD TENANT HISTÓRICO ######## -->

## Alta y primer acceso de un tenant

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
