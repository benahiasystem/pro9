# Serie opcional y numeración interna por sucursal

Entrega del 1 de octubre de 2026.

## Comportamiento

El diálogo de Sucursales muestra «Serie» y «Número». La serie comienza vacía, es editable y no utiliza Auto/Manual ni consultas de generación automática. El icono de información muestra «numeración interna del sistema» mediante hover, foco y clic. El valor configurado conserva su significado de primer número (1 por defecto) y permanece bloqueado cuando la configuración está usada.

Una serie omitida, null, vacía o con espacios se guarda como `''`. Los códigos con contenido se recortan, se convierten a mayúsculas y conservan las límite de 20 caracteres con letras ASCII, números y guiones (-), sin prefijo obligatorio; se rechazan espacios internos y otros símbolos. Los nombres técnicos `number` y `correlative` de la API permanecen.

Existe una configuración sin serie por sucursal y tipo de documento, compartida entre Normal/Dedicado/Contingencia. Los códigos con contenido mantienen unicidad empresarial por tipo mediante validación dentro del bloqueo de empresa. Los códigos sembrados no cambiaron.

## Numeración e identidad

`SeriesNumbering` resuelve y bloquea empresa → serie dentro de la transacción de guardado. El cálculo del máximo y la detección de duplicados distinguen sucursal, ambiente, tipo y serie. Dos sucursales sin serie emiten 1, luego 2, de manera independiente. Una reversión no consume un número ni deja la configuración usada. Se conservan las restricciones de sucursal y grupo dedicado.

Guías y transferencias de inventario persisten la sucursal del almacén de origen. La comprobación de uso de las configuraciones también incluye sucursal y tipo.

Sin serie, la identificación visible muestra únicamente el número con un mínimo de ocho dígitos, sin guion inicial (25 → `00000025`). Con serie se muestra, por ejemplo, `FF01-00000025`. Los nombres de archivos incorporan `SIN_SERIE_S{id_sucursal}` para evitar colisiones; los archivos con serie conservan sus nombres. Se adaptaron selectores, conversiones, búsquedas, anticipos, recursos, reportes y plantillas PDF. En reportes, `__without_series__` distingue seleccionar la opción de serie vacía de limpiar el filtro; no se persiste como código de serie. Las consultas de un documento sin serie requieren sucursal o un identificador interno/externo inequívoco; la búsqueda pública devuelve sus coincidencias identificadas por sucursal.

El número de control de imprenta sigue siendo texto independiente y nullable. No se integra emisión HKA ni numeración de máquinas fiscales.

## Esquema consolidado

Se modificaron las migraciones creadoras para instalaciones nuevas, sin migraciones incrementales ni conversiones históricas:

- `series`: código NOT NULL DEFAULT '' e índice único sucursal/tipo/código.
- `documents`, `dispatches` y `guides`: claves únicas sucursal/ambiente/tipo/serie/número.
- `inventories_transfer`: sucursal obligatoria, índice, FK y clave única sucursal/ambiente/tipo/serie/número.
- `guides`: sucursal obligatoria, índice y FK.
- `sale_notes`: clave única sucursal/ambiente/serie/número.

La comparación completa del DDL de HEAD y las fuentes modificadas, reconstruidos en bases MySQL temporales independientes, produjo 333 tablas en ambos casos. Sólo cambiaron las seis tablas anteriores. Las claves foráneas se agregan y retiran en la migración final consolidada. No cambiaron los datos iniciales.

## Verificación

- `SeriesNumberingTest`: 34 pruebas y 190 aserciones; todos los tipos, inicios personalizados, normalización, duplicados, sucursales, permisos, grupos, reversión y bloqueo de configuraciones usadas.
- Identidad, datos PDF, renderizado PDF, contratos de plantillas y configuración fiscal: 43 pruebas y 607 aserciones. Incluye filtros sin serie, archivos distintos por sucursal y PDF con y sin serie.
- JavaScript: 26 pruebas; alta vacía, ausencia de generación automática, popup, etiquetas, ordenación, filtros y flujos de conversión.
- Suite unitaria completa: 442 pruebas, 13126 aserciones, sin fallos. Los nueve casos MySQL omitidos en esta ejecución se ejecutaron por separado.
- Las fuentes PHP y Vue/JavaScript se validaron sin compilar; el PDF sin serie también se inspeccionó renderizado.
- `FiscalEmissionSchemaTest` y `SeriesMySqlConcurrencyTest`: 9 pruebas y 1661 aserciones, sin fallos; instalación, catálogos, integridad, rollback/repetición, emisión real en el esquema temporal y procesos simultáneos con serie vacía en una o dos sucursales.

Las bases de prueba usan nombres aleatorios y se eliminan al terminar. No se modificaron bases reales ni assets de `public/build/`. Se actualizaron las skills de numeración, esquema y consulta de contratos HKA. La comprobación visual del diálogo en navegador queda pendiente de compilar las fuentes, según la instrucción del usuario.

## Ampliación de series a 20 caracteres

La configuración acepta de 1 a 20 letras/números en cualquier tipo y modalidad, además de serie vacía. Se normalizan espacios externos y mayúsculas. «Factura N°» se renombra a «Número» en tabla, alta y explicación; se conservan `number` y `correlative` en la API, el inicio configurado, el popup y el bloqueo de uso.

Se ampliaron los límites de búsquedas internas/públicas y referencias de órdenes de entrega y retenciones/percepciones. La política de ventas rechaza Boletas por su tipo `03`, sin interpretar BB/BC/BD introducidos manualmente como tipos documentales. El generador de inventario ignora sufijos manuales largos y busca un código disponible si el siguiente ya existe.

`retentions.series`, `perceptions.series` y `purchase_settlements.series` cambian de `char(4)` a `varchar(20)` en las migraciones iniciales y su inventario de columnas. La comparación completa contra las fuentes existentes antes de esta ampliación produjo 333 tablas en ambos esquemas temporales, con diferencias sólo en esas tres tablas. Se conservan los datos sembrados y no se actualizan bases reales.

Las pruebas cubren series vacías y de 1/4/20 caracteres, todos los tipos y modalidades, rechazo de 21 caracteres/símbolos/espacios internos, búsquedas y referencias, emisión/archivo y PDF de 20 caracteres, generación automática segura y regresiones de numeración/concurrencia. El PDF largo se renderizó e inspeccionó sin truncamiento. Resultados de esta ampliación: suite unitaria de 446 pruebas y 13269 aserciones sin fallos (9 casos MySQL omitidos y ejecutados por separado); esquema/concurrencia MySQL de 9 pruebas y 1670 aserciones sin fallos; JavaScript de 27 pruebas sin fallos. Sintaxis PHP, 46 fuentes Vue/JavaScript y `git diff --check` válidos. No se compiló ni se modificó `public/build/`; la revisión visual del formulario en navegador sigue pendiente del build.

## Validación de letras, números y guiones

La serie permite únicamente A–Z, a–z, 0–9 y el guion ASCII `-`, con el límite de 20 caracteres y la opción vacía. Se conserva la normalización a mayúsculas y el recorte de espacios externos. Se rechazan espacios internos, acentos, guiones Unicode, puntos, barras, guion bajo y otros símbolos. El formulario muestra el error antes de enviar; el servidor valida también las peticiones directas y los consumidores de búsqueda/configuración.

Las referencias completas separan serie y correlativo por el último guion, conservando todos los guiones de la serie. Se unificó la lectura en `FiscalIdentity::parseNumberFull` para anticipos/reversiones, asociación de órdenes de entrega, consulta API/bot e importaciones. Las pruebas verifican series con guiones consecutivos/iniciales/finales, ambos tipos de letras, duplicados normalizados, rechazos de símbolos, emisión y PDF.

No se modifican columnas ni datos reales por esta validación y no se compilan assets.

Verificación final de guiones: 459 pruebas unitarias y 13361 aserciones sin fallos (9 casos MySQL omitidos en esa ejecución); `FiscalEmissionSchemaTest` ejecutado aparte en bases temporales: 2 pruebas y 1616 aserciones, con emisión y archivo de una serie de 20 caracteres con guion; JavaScript: 28 pruebas sin fallos. Sintaxis PHP, fuentes Vue/JavaScript y `git diff --check` válidos. La comprobación visual del diálogo sigue pendiente del build solicitado al usuario.

## Presentación actualizada — 9 de octubre de 2026

El formato visible se centraliza en `FiscalIdentity` y `helpers/document-number.js`, incluyendo documentos internos, correos, referencias y reportes. El correlativo guardado, las búsquedas numéricas, filenames y payloads HKA no cambian; los PDF originales HKA y comprobantes externos conservan su numeración. Detalle y validación en [numeracion_visible_ocho_digitos.md](numeracion_visible_ocho_digitos.md).

## Serie vacía — 9 de octubre de 2026

Las series vacías se muestran sin etiqueta sustituta en formularios, selectores, reportes y PDF. Los filtros conservan `__without_series__` como valor interno distinto de limpiar el filtro. Las descargas, impresión y adjuntos comerciales usan un nombre visible sin `SIN_SERIE`, con el correlativo de ocho dígitos; las rutas privadas y archivos registrados conservan sus claves por sucursal. Detalle en [serie_vacia_presentacion.md](serie_vacia_presentacion.md).
