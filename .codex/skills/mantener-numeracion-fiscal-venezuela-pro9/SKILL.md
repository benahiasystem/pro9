---
name: mantener-numeracion-fiscal-venezuela-pro9
description: Mantener series, correlativos y grupos de dispositivos de Pro9 para Venezuela, con validación de sucursal, concurrencia e identificadores documentales. Usar al modificar Numeración y emisión, series o sus consumidores comerciales.
---

# Series y correlativos de Pro9

## Modelo vigente

- Para todas las modalidades, usar `series`, `series_configurations` y `series_device_groups`, según el modelo original de `main`. No utilizar secuencias, perfiles ni reservas fiscales para seleccionar o numerar documentos.
- `series_configurations.number` es el primer número configurado, por defecto 1. Si existen documentos, continuar desde el mayor número de la misma sucursal, tipo, serie y ambiente. Inicio 100: primer documento 100, siguiente 101. La configuración no representa el último número emitido.
- `SeriesNumbering::next` se ejecuta dentro de la transacción que guarda el documento: bloquear empresa → serie, validar sucursal/grupo, calcular el número y marcar `in_use`. El bloqueo se conserva hasta la inserción; una transacción fallida revierte también el uso. No reservar números en una lectura de formulario o en middleware que termine antes del guardado.
- Facturas/notas usan `DocumentObserver`; órdenes de entrega se numeran al insertar `Dispatch`, dentro de `ModelTenant::save`; notas de venta usan su evento `creating` dentro de `ModelTenant::save` (web, API y duplicaciones), e internos de inventario utilizan el mismo numerador desde sus transacciones. Mantener las rutas comerciales existentes y sus efectos de pagos/inventario.
- Rechazar números explícitos duplicados. En instalaciones nuevas, las claves únicas documentales incluyen sucursal, ambiente, tipo, serie y número; `unique_filename` de Factura incluye ambiente. No renumerar un documento registrado al editarlo.
- `SeriesNumbering::used` comprueba el flag y los registros de la sucursal/tipo/serie. Una serie utilizada no permite editar inicio, identidad o eliminarla, aunque falte el flag. No liberar números borrando configuración utilizada.
- `SeriesAdministration` limita la configuración a administradores del tenant y usa el mismo orden de bloqueo. Validar grupos dedicados y series en la misma sucursal antes de escribir; guardar asociaciones de grupos en una transacción.
- `SeriesResolver` mantiene series normales, dedicadas y contingencia. Un equipo con grupo activo usa sólo sus series; sin grupo, sólo series no dedicadas. La cookie de equipo y los vínculos se resuelven en servidor; un ID enviado por el navegador no autoriza otra sucursal/grupo. Los reportes no se limitan al grupo del equipo.

## Formulario

- `establishments/index.vue` abre `partials/series.vue` para todas las modalidades.
- Conservar Todos, Básico, Avanzado, Interno, Dedicado y Contingencia; el interruptor habilita Dedicado.
- Tabla: categoría, tipo de documento, «Serie», «Número» y acciones. Nuevo abre el alta dentro del mismo diálogo: tipo, serie editable inicialmente vacía, «Número», Normal/Dedicado/Contingencia y Guardar/Cancelar. No usar Auto/Manual ni consultar generación automática de códigos desde este diálogo.
- «Número» conserva el inicio configurado (1 por defecto), con popup «numeración interna del sistema» mediante hover, foco y clic. Las configuraciones usadas permanecen bloqueadas.
- Serie omitida, null, vacía o sólo espacios se normaliza a `''`; las series con contenido se recortan y pasan a mayúsculas, con límite de 20 caracteres: letras ASCII mayúsculas/minúsculas, números y guiones (-) y sin prefijo obligatorio para ningún tipo o modalidad; rechazar espacios internos y otros símbolos. Mostrar la serie vacía en tablas, selectores y confirmaciones, sin texto sustituto. En Element UI, una etiqueta visual vacía no debe caer al ID interno ni al marcador del filtro: usar espacio no separable exclusivamente en la etiqueta, nunca como serie persistida.
- La etiqueta «Número» sólo cambia la presentación; conservar `number` (serie) y `correlative` (inicio) en la API. Búsquedas y referencias admiten los 20 caracteres; los códigos sembrados se conservan. La generación automática de inventario ignora sufijos manuales largos.
- Dedicado permite crear/editar grupos, seleccionar sus series y desvincular equipos; el vínculo/recuperación del equipo también permanece en el perfil de usuario.
- Conservar el catálogo venezolano vigente, sin Boletas ni nombres fiscales peruanos. No habilitar una operación fiscal sólo porque figure en el catálogo.
- En este diálogo no mostrar editores de perfiles fiscales, asignaciones anticipadas ni consultas de numeraciones/conciliación HKA. Guardar series no llama a HKA. La consulta fiscal de una factura se realiza desde sus acciones propias, con la conexión cifrada de la empresa.

## Identificadores y esquema

- Una configuración vacía por sucursal y tipo documental, compartida entre Normal/Dedicado/Contingencia. Los códigos con contenido mantienen unicidad empresarial por tipo mediante validación bajo bloqueo de empresa; el índice SQL distingue sucursal/tipo/código. Conservar los códigos sembrados.
- Resolver, calcular máximos, detectar duplicados y comprobar uso con sucursal persistida. Guías y transferencias de inventario guardan la sucursal del almacén de origen, no la del usuario. Guardar todos los documentos con serie normalizada.
- Sin serie, `number_full` muestra sólo el número con un mínimo de ocho dígitos (25 → `00000025`); con serie, `FF01-00000025`. Las claves privadas de archivo usan `SIN_SERIE_S{establishment_id}` para distinguir sucursales; los nombres visibles de descarga y adjuntos omiten ese marcador mediante `DocumentFileName`, sin renombrar archivos guardados; los nombres de series con contenido se conservan. Las consultas ambiguas de serie vacía requieren sucursal.
- Las referencias «serie-número» se leen con `FiscalIdentity::parseNumberFull`: el último guion separa el correlativo; los anteriores pertenecen a la serie. Usar el mismo contrato para anticipos, búsquedas, asociaciones e importaciones.
- En filtros de reportes usar `FiscalIdentity::EMPTY_SERIES_FILTER` (`__without_series__`) para seleccionar exclusivamente documentos con serie vacía; su etiqueta visible queda vacía y null/vacío significa limpiar el filtro. Este marcador no es una serie persistida.

- Serie, número documental y número de control son independientes. `FiscalIdentity`, `HasFiscalIdentity` y `FiscalPdfData` leen los atributos del documento, sin reservas ni reemplazos históricos.
- `documents.control_number` y `dispatches.control_number` son texto nullable en el esquema inicial; preservar prefijos y ceros. No inventar un control a partir del correlativo ni presentar el registro local como emisión HKA confirmada.
- Guardado, conversiones, consultas, reportes e impresión utilizan esos identificadores propios. El correlativo no se obtiene de controles HKA.
- En Vende Ya (`pos/garage`), conservar el `series_id` elegido al cambiar cliente o recargar las opciones de pago. Al filtrar por tipo documental, seleccionar la primera serie sólo si la selección ya no figura entre las opciones autorizadas recibidas del servidor; nunca reemplazar una selección válida por `FF01`.
- La retirada de tablas fiscales se aplica sólo al consolidado para instalaciones nuevas. No crear conversiones/backfills, importar historia, modificar ni borrar tablas de tenants existentes. Aplicar [reconstruir-migraciones-tenant](../reconstruir-migraciones-tenant/SKILL.md).
- La emisión de facturas digitales DEMO usa [emitir-facturas-notas-hka](../emitir-facturas-notas-hka/SKILL.md). Persistir control con `setControl` sólo después de éxito de negocio e identidad coherente, conservando prefijos y ceros. Mantener serie/correlativo y UUID durante consultas/reintentos; un rechazo por numeración remota no autoriza cambiar la serie, recrear la venta ni inventar controles. Las asignaciones/rangos remotos siguen el alcance de [numeración HKA](../gestionar-numeracion-documentos-hka/SKILL.md).

## Presentación de números

- Usar `FiscalIdentity::displayNumber` y `numberFull` para números visibles de documentos internos; en Vue, `helpers/document-number.js`. `displayReference` adapta referencias textuales de consultas de reportes.
- Completar números decimales no negativos hasta ocho caracteres, conservando valores más largos, vacíos y provisionales. Mantener series, prefijos y sus guiones. Aplicar a encabezados, referencias, correos, reportes y vistas previas.
- No modificar `number`, correlativos, filenames, número de control, payloads congelados ni identificadores de consulta HKA. A4/A5 originales HKA y PDF ya almacenados conservan su contenido. Compras y comprobantes externos recibidos conservan su numeración.
- Los consumidores que reciben `number_full` para asociaciones deben seguir usando `parseNumberFull` y consultar la columna numérica, con autorización por sucursal; no comparar el texto visible con el identificador fiscal remoto.

## Verificación

- `SeriesNumberingTest`: inicios 100/101 para Factura, crédito, débito, entrega, NV e internos; reversión, duplicados, permisos, sucursales/tenants, grupos y contingencia; series omitidas/null/vacías/espacios, duplicados entre modalidades y emisión 1/2 independiente por sucursal.
- `SeriesMySqlConcurrencyTest` con `PRO9_FISCAL_MYSQL_TESTS=1`: dos procesos contra una base aleatoria temporal; guardados automáticos distintos y números explícitos duplicados; serie vacía concurrente en una sucursal y en dos sucursales. No usar una base real.
- `FiscalEmissionSchemaTest` con esa variable: instalación/seeding/integridad/rollback/repetición, ausencia de tablas retiradas y persistencia comercial con el modelo de series.
- `FiscalIdentityTest` y `FiscalPdfDataTest`: control propio y representación independiente de serie/número; `FiscalEmissionSettingsTest` conserva autenticación/cifrado HKA.
- `node --test tests/js/*.test.cjs`: filtros, alta/cancelación/errores y grupos. Validar las fuentes Vue sin compilar; aplicar [frontend-build](../frontend-build/SKILL.md).
- `garage-series-selection.test.cjs`: cambio de cliente, filtros repetidos y envío de pago mantienen la serie elegida (incluida la opción de serie vacía); cambio de tipo o retirada de opciones invalida la selección. `SeriesNumberingTest` verifica que el ID recibido se resuelve y numera con su serie, sin usar `FF01`.

- `DocumentNumberDisplayTest` y `tests/js/document-number-display.test.cjs`: ocho dígitos, placeholders, números largos, series con guiones, documentos internos y conservación de identificadores externos.

- `DocumentFileNameTest` y `empty-series-display.test.cjs`: nombres visibles, lectura de claves privadas por sucursal, adjuntos comerciales, ZIP sin colisiones y etiquetas vacías seleccionables sin ID ni placeholder.
