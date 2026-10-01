---
name: mantener-numeracion-fiscal-venezuela-pro9
description: Mantener series, correlativos y grupos de dispositivos de Pro9 para Venezuela, con validación de sucursal, concurrencia e identificadores documentales. Usar al modificar Numeración y emisión, series o sus consumidores comerciales.
---

# Series y correlativos de Pro9

## Modelo vigente

- Para todas las modalidades, usar `series`, `series_configurations` y `series_device_groups`, según el modelo original de `main`. No utilizar secuencias, perfiles ni reservas fiscales para seleccionar o numerar documentos.
- `series_configurations.number` es el primer número configurado, por defecto 1. Si existen documentos, continuar desde el mayor número del mismo tipo, serie y ambiente. Inicio 100: primer documento 100, siguiente 101. La configuración no representa el último número emitido.
- `SeriesNumbering::next` se ejecuta dentro de la transacción que guarda el documento: bloquear empresa → serie, validar sucursal/grupo, calcular el número y marcar `in_use`. El bloqueo se conserva hasta la inserción; una transacción fallida revierte también el uso. No reservar números en una lectura de formulario o en middleware que termine antes del guardado.
- Facturas/notas usan `DocumentObserver`; órdenes de entrega se numeran al insertar `Dispatch`, dentro de `ModelTenant::save`; notas de venta usan su evento `creating` dentro de `ModelTenant::save` (web, API y duplicaciones), e internos de inventario utilizan el mismo numerador desde sus transacciones. Mantener las rutas comerciales existentes y sus efectos de pagos/inventario.
- Rechazar números explícitos duplicados. En instalaciones nuevas, las claves únicas documentales incluyen ambiente, tipo, serie y número; `unique_filename` de Factura incluye ambiente. No renumerar un documento registrado al editarlo.
- `SeriesNumbering::used` comprueba el flag y los registros del tipo/serie. Una serie utilizada no permite editar inicio, identidad o eliminarla, aunque falte el flag. No liberar números borrando configuración utilizada.
- `SeriesAdministration` limita la configuración a administradores del tenant y usa el mismo orden de bloqueo. Validar grupos dedicados y series en la misma sucursal antes de escribir; guardar asociaciones de grupos en una transacción.
- `SeriesResolver` mantiene series normales, dedicadas y contingencia. Un equipo con grupo activo usa sólo sus series; sin grupo, sólo series no dedicadas. La cookie de equipo y los vínculos se resuelven en servidor; un ID enviado por el navegador no autoriza otra sucursal/grupo. Los reportes no se limitan al grupo del equipo.

## Formulario

- `establishments/index.vue` abre `partials/series.vue` para todas las modalidades.
- Conservar Todos, Básico, Avanzado, Interno, Dedicado y Contingencia; el interruptor habilita Dedicado.
- Tabla: categoría, tipo de documento, serie, correlativo y acciones. Nuevo abre el alta dentro del mismo diálogo: tipo, Auto/Manual, serie, correlativo, Normal/Dedicado/Contingencia y Guardar/Cancelar.
- Dedicado permite crear/editar grupos, seleccionar sus series y desvincular equipos; el vínculo/recuperación del equipo también permanece en el perfil de usuario.
- Conservar el catálogo venezolano vigente, sin Boletas ni nombres fiscales peruanos. No habilitar una operación fiscal sólo porque figure en el catálogo.
- No mostrar editores de perfiles fiscales ni asignaciones anticipadas, consulta de numeraciones o conciliación HKA. Guardar series no llama a HKA. Mantener la conexión cifrada HKA de la empresa para una integración posterior.

## Identificadores y esquema

- Serie, número documental y número de control son independientes. `FiscalIdentity`, `HasFiscalIdentity` y `FiscalPdfData` leen los atributos del documento, sin reservas ni reemplazos históricos.
- `documents.control_number` y `dispatches.control_number` son texto nullable en el esquema inicial; preservar prefijos y ceros. No inventar un control a partir del correlativo ni presentar el registro local como emisión HKA confirmada.
- Guardado, conversiones, consultas, reportes e impresión utilizan esos identificadores propios. El correlativo no se obtiene de controles HKA.
- La retirada de tablas fiscales se aplica sólo al consolidado para instalaciones nuevas. No crear conversiones/backfills, importar historia, modificar ni borrar tablas de tenants existentes. Aplicar [reconstruir-migraciones-tenant](../reconstruir-migraciones-tenant/SKILL.md).
- La emisión automática HKA queda pendiente y requiere una implementación posterior explícita. Leer [conectar-api-hka](../conectar-api-hka/SKILL.md) y [numeración HKA](../gestionar-numeracion-documentos-hka/SKILL.md) cuando corresponda.

## Verificación

- `SeriesNumberingTest`: inicios 100/101 para Factura, crédito, débito, entrega, NV e internos; reversión, duplicados, permisos, sucursales/tenants, grupos y contingencia.
- `SeriesMySqlConcurrencyTest` con `PRO9_FISCAL_MYSQL_TESTS=1`: dos procesos contra una base aleatoria temporal; guardados automáticos distintos y números explícitos duplicados. No usar una base real.
- `FiscalEmissionSchemaTest` con esa variable: instalación/seeding/integridad/rollback/repetición, ausencia de tablas retiradas y persistencia comercial con el modelo de series.
- `FiscalIdentityTest` y `FiscalPdfDataTest`: control propio y representación independiente de serie/número; `FiscalEmissionSettingsTest` conserva autenticación/cifrado HKA.
- `node --test tests/js/*.test.cjs`: filtros, alta/cancelación/errores y grupos. Validar las fuentes Vue sin compilar; aplicar [frontend-build](../frontend-build/SKILL.md).
