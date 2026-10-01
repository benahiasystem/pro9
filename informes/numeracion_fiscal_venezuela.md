# Restauración de series y correlativos

## Entrega vigente — 1 de octubre de 2026

La configuración de todas las modalidades vuelve al modelo original de `main`, tomando como base el código anterior al módulo de secuencias y conservando los catálogos venezolanos y la conexión HKA de la empresa.

## Formulario

`resources/js/views/tenant/establishments/partials/series.vue` se abre desde Sucursales y contiene Todos, Básico, Avanzado, Interno, Dedicado y Contingencia. El interruptor habilita Dedicado. La tabla muestra categoría, documento, serie, correlativo y acciones.

Nuevo permite elegir el tipo documental, serie Auto/Manual, correlativo inicial y Normal/Dedicado/Contingencia dentro de la misma ventana. Cancelar no guarda. Dedicado permite recuperar, crear y editar grupos, asociar sus series y desvincular equipos; el vínculo del equipo permanece disponible en el perfil de usuario. Se mantuvieron los nombres y documentos del catálogo venezolano, sin Boletas.

Se retiraron los formularios y acciones de perfiles fiscales, asignación anticipada, consulta de numeraciones HKA y conciliación. Los formularios comerciales vuelven a seleccionar series sin exigir un perfil fiscal.

## Contratos vigentes

- `series`: identidad documental, sucursal, clase normal/dedicada/contingencia y grupo.
- `series_configurations.number`: primer número configurado, por defecto 1. Sin documentos, inicio 100 produce 100; con documentos, se continúa desde el mayor número del mismo ambiente, tipo y serie.
- `series_device_groups`: asociaciones de series y vínculo del equipo. El servidor valida sucursal y grupo autorizado.
- `SeriesNumbering`: exige la transacción que inserta el documento y bloquea empresa → serie hasta el guardado. Valida números positivos y rechaza duplicados explícitos; una reversión tampoco consume el correlativo.
- `SeriesAdministration`: administrador del tenant, con el mismo orden de bloqueo para edición y eliminación. Una serie utilizada, incluso sin `in_use` histórico, no permite cambiar inicio/identidad ni eliminarla. Las asociaciones de grupos se guardan atómicamente.
- Facturas y notas usan `DocumentObserver`; órdenes de entrega se numeran al insertar `Dispatch`; notas de venta se numeran en su evento `creating` dentro de `ModelTenant::save`, incluyendo web, API y duplicaciones; internos utilizan el mismo servicio dentro de sus transacciones. Se conservaron los efectos comerciales de pagos e inventario.
- Consultas, reportes, conversiones e impresión utilizan los atributos propios: serie, número documental y número de control. `control_number` es independiente y nullable; conservar sus prefijos y ceros. No se deduce ni asigna un control al crear una serie o un documento local.

Se recuperaron las rutas `/series`, `/series/records/{establishment}`, `/series/{series}/correlative` y `/series/groups/*`. Las rutas del módulo fiscal descartado ya no tienen consumidores y se retiraron.

## Esquema inicial

Las instalaciones nuevas contienen el modelo de series y los campos `documents.control_number` y `dispatches.control_number`. Las claves únicas documentales distinguen ambiente, tipo, serie y número; las series tienen unicidad de tipo y código. El identificador único del archivo de factura incluye ambiente.

Se retiraron del consolidado las tablas y relaciones exclusivas de secuencias, perfiles, lotes preimpresos, reservas fiscales, intentos, auditorías de ese módulo, recibos simulados y asignaciones anticipadas HKA. No se añadió una migración de conversión ni se modificó el esquema o los datos de tenants existentes.

## HKA

La autenticación, credenciales cifradas, ambiente y última conexión verificada de la empresa se mantienen. No se llaman servicios de numeración HKA desde Series. La emisión automática HKA, incluida la asociación del control durante la emisión, requiere una entrega posterior y no está implementada en este cambio.

## Verificación

- Pruebas unitarias PHP: 430 pruebas y 13036 aserciones satisfactorias, con 5 pruebas omitidas en esa ejecución; se añadieron y ejecutaron por separado las pruebas de concurrencia descritas abajo.
- JavaScript: 20 pruebas satisfactorias, incluyendo filtros, alta/cancelación, errores, grupos y uso del mismo diálogo en todas las modalidades.
- Concurrencia MySQL: 3 pruebas y 22 aserciones; dos procesos generan 100/101, un número explícito duplicado se rechaza y la eliminación espera al guardado antes de bloquearse por uso.
- Esquema MySQL temporal: 2 pruebas y 1570 aserciones; instalación, seeding, integridad, rollback y repetición. Se guardaron facturas 100/101, notas de crédito/débito, orden de entrega y notas de venta 100/101; se verificaron conversiones a factura, entradas/salidas/transferencias internas, pagos, inventario y control independiente.
- Revisión en Chrome: diálogo original, alta integrada, grupos y adaptación a pantalla estrecha. Las pruebas de navegador no guardan ni eliminan configuración real.
- La ejecución general encontró un error del ejemplo de prueba web por la base `multifacturalo_dusk` ausente. No se creó ni modificó esa base para resolver el ejemplo. Los 5 casos omitidos en la suite unitaria corresponden a los 2 casos de esquema y 3 de concurrencia, ejecutados por separado en MySQL.

Las bases de integración y concurrencia son aleatorias, temporales y se eliminan al terminar. No se importaron, asignaron ni liberaron controles reales. No se ejecutó una compilación; los assets visibles proceden del watcher del usuario.
