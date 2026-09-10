# Avance de retirada de compatibilidad histórica

## Corrección del listado de productos en facturas

Se restauró la representación de `form.items` en la tabla de creación de facturas. La retirada de ISC e impuesto a bolsas había eliminado accidentalmente el bloque completo de filas, aunque el producto seguía agregado en el estado del formulario. La tabla vuelve a mostrar descripción, afectación de IVA, unidad, cantidad, precio, descuento, total y acciones de edición/eliminación, sin reintroducir campos fiscales retirados.

La verificación funcional creó el comprobante `FF01-3` para `Clientes - Varios` con el producto `00003 - producto`; el documento aparece como `Registrado` en el listado y mantiene disponible su PDF.

Estado: implementación finalizada y verificada en fuentes y bases temporales. No se modificaron tenants reales, no se compiló el frontend y no se creó commit ni despliegue.

## Comprobación de IVA y políticas de documentos

- Vende Ya rechaza monedas retiradas o ausentes y afectaciones distintas de 10/20. Se eliminó su rama inalcanzable de cálculo de operaciones inafectas. Conserva VES/USD y cálculo gravado/exento con la tasa configurada.
- Las pruebas ejercitan base 100, IVA 16 y total 116; mezcla de líneas gravadas y exentas en USD con tasa configurada del 8 %; y rechazo de PEN, VED, EUR, moneda ausente y códigos fiscales retirados.
- El resumen del pago de estacionamiento conserva una presentación del porcentaje y monto del IVA. La antigua duplicación correspondía a la rama del impuesto a bolsas retirada; la prueba ahora comprueba la presentación única y sus bindings actuales.
- El procesamiento de Facturas y notas fiscales permite 01/07/08. La selección de ventas conserva 01/80 y el servicio técnico su alias vigente nv. Se prueba rechazo de tipos de otros flujos en el procesamiento fiscal.
- Los contratos de catálogos apuntan al archivo final de claves foráneas 000999 y comprueban ausencia del modelo y relaciones ISC, sin exigir compatibilidad con el catálogo retirado.
- La prueba de eliminación de documentos demo ejecuta la limpieza de dependencias actuales sin crear una tabla ficticia summary_documents. Continúa comprobando pagos, caja, inventario, notas y otras relaciones comerciales.

## Resultados ejecutados en esta revisión

En el contenedor PHP, con `DB_DATABASE=pro9_fiscal_suite_no_live`, caché/sesión/correo en array y cola síncrona:

- Filtro `SalesDocumentTypePolicyTest|CatalogNamesMigrationContractTest|LocalFiscalDocumentPolicyTest|VenezuelaIvaContractTest|VendeyaDocumentPayloadNormalizerTest`: **47 pruebas, 318 aserciones, correctas**.
- `TestDocumentsDeletionBehaviorTest`: **2 pruebas, 22 aserciones, correctas**, sobre SQLite en memoria.
- Skills `migrar-iva-venezuela` y `mantener-facturas-notas-venta-sin-boleta`: validadas con `quick_validate.py`.
- Diff de los archivos tratados en esta revisión sin errores de espacios. No se ejecutó build ni se modificaron bases reales.

## Pendientes para aceptación

### Plantillas PDF sin Boletas

- Actualizadas 44 plantillas: anticipos sin alternativa de Boleta y eliminación de mapas locales de tipos/identidad sin consumidores en las notas. Los tipos actuales siguen presentándose desde el documento y cliente.
- `CurrentPdfTemplateContractTest`: **1 prueba, 205 aserciones, correcta**. Recorre 203 plantillas, comprueba ausencia de Boletas/ISC/bolsas, compila Blade en memoria y analiza el PHP generado. No equivale a un PDF renderizado o a una revisión visual.
- Eliminadas referencias residuales de ISC en comentarios de Item y etiquetas de validación. Skills de ventas y operación local actualizadas y válidas; diff sin errores de espacios.

### Persistencia real de productos y revisión de plantillas

- La prueba de instalación incorpora creación mediante modelos de producto, servicio y variación, vínculo a un valor de talla, lectura de relaciones y conteo, y modificación de stock con `ItemWarehouse::addStock`. Crea explícitamente establecimiento/almacén de prueba y revierte sus datos. Los dos intentos iniciales detectaron errores de preparación de la prueba (nombre del campo value y falta de almacén), corregidos antes del resultado final.
- Ejecución final: **2 pruebas MySQL, 1187 aserciones, correctas**, con creación/seeding/integridad/rollback/repetición y persistencia de productos. PHP informa una deprecación preexistente por el orden de parámetros de `Item::getItemUnitTypesBarcode`; no hubo fallo de prueba.
- Eliminada la migración central redundante de renombrado de Órdenes de entrega; las altas iniciales ya contienen las etiquetas vigentes.
- Se conservan precios de presentación price1/price2/price3 y el comando de rellenado de listas porque POS y documentos actuales todavía los utilizan; no son soporte exclusivamente histórico. La skill identifica sus consumidores.
- Corregidos dos delimitadores de comentario HTML mal escritos en Servicio técnico. **84 componentes Vue modificados pasan análisis de script y plantilla**, sin build. Skills de productos, órdenes y SUNAT/SENIAT actualizadas y válidas.
- La prueba de modelos no acredita recorridos HTTP ni movimientos completos de inventario; esas comprobaciones y los PDF siguen pendientes.

### Configuración Mi Tienda y excepciones de notas retiradas

- Eliminados `series_document_bt`/`series_document_bt_id` de consultas, configuración, modelo, store, formularios y esquema inicial. Procesar Mi Tienda ya no exige una serie de Boleta; mantiene establecimiento y serie de Factura.
- Retirados `presetItemId`/`hasPresetItem` e `isCreditNoteAndType03`/`isNoteErrorDescription` del selector compartido de documentos. Eran exclusivos del servicio de penalidad y motivo de nota retirados. Se conserva edición de líneas y precio mayor a cero.
- Componentes y store JavaScript validados en memoria. Skills de ventas e IVA actualizadas y validadas; inventario de catálogos actualizado a 72 tablas/860 filas.
- Verificación MySQL explícita sobre bases temporales: **2 pruebas, 1167 aserciones, correctas**, incluyendo esquema, seeding, integridad referencial, rollback y repetición; no se operó sobre bases reales.
- Última suite Unit: **319 pruebas, 6949 aserciones, cero fallos, tres omitidas**. Las pruebas MySQL omitidas por defecto se ejecutaron por separado con el resultado anterior; sigue pendiente el bundle ausente y la aceptación funcional integral.

### Resolución de documentos y bot

- `DocumentType` deja de resolver Boletas o códigos desconocidos como Factura. Conserva Document para 01/07/08 y SaleNote para 80; los tipos no admitidos producen un error de validación. Selectores de compras/reportes excluyen códigos retirados, y la constante de Facturas incluye sólo 01.
- La API móvil y el caption PDF del bot muestran el tipo documental real. La consulta del bot devuelve estado local y no afirma aceptación SUNAT ni presenta XML/CDR. Actualizado su prompt; no se enviaron mensajes reales.
- `CurrentDocumentResolutionTest|CatalogNamesMigrationContractTest|SalesDocumentTypePolicyTest`: **23 pruebas, 153 aserciones, correctas**. Skills de ventas, operación local y catálogos actualizadas y validadas.

### Telefonía sin conversión de prefijos extranjeros

- Centralizada la normalización JavaScript en `resources/js/helpers/phone.js`, alineada con `Localization::whatsappNumber`. Conserva 58, normaliza números locales y rechaza números internacionales explícitos de otros países, vacíos o sin dígitos útiles. PHP también rechaza una entrada formada sólo por ceros.
- Once componentes de documentos, órdenes, cotizaciones, Notas de venta, POS, pagos y QR usan el helper. Validan antes de abrir ventanas, descargar PDF o enviar peticiones. No se realizaron envíos reales.
- `node --test tests/js/phone.test.cjs`: **2 casos correctos**, incluyendo ejecución de los once manejadores reales con dependencias de ventana/red simuladas; no producen actividad externa ni dejan loading activo ante +51.
- Pruebas PHP de telefonía/localización/contrato Venezuela: **27 pruebas, 110 aserciones, correctas**. Once scripts y plantillas Vue validados en memoria; skill de telefonía actualizada y válida. Sin compilación.

### Instalación e importación: siguiente revisión

- Eliminados los cuatro scripts auxiliares que copiaban estructura de una base fuente o reproducían migraciones históricas. `FiscalEmissionSchemaTest` permanece como comprobación del consolidado en bases temporales.
- La importación mantiene posiciones 0..19 y URL de imagen opcional en 20. Si la columna contiene imágenes, U1 debe decir `URL Imagen`; la variante anterior sin encabezado se rechaza antes de importar. Prueba con XLSX real en dos pasadas: rechazo y aceptación al corregir U1. No se cambia el mapeo del importador.
- Separada la prueba de ruta UUID de la inspección del bundle. La segunda se omite explícitamente cuando no existe manifiesto; no se omiten las validaciones del archivo ni de la descarga. No se compiló ni se modificaron assets generados.
- Actualizadas las skills de importación, reconstrucción y adaptación, y el contrato de paridad para exigir el estado vigente de Pro9. Las tres pasan `quick_validate.py`.
- Última suite Unit: **312 pruebas, 6927 aserciones, cero fallos, tres omitidas**. Las omisiones son las dos pruebas MySQL de activación explícita y la comprobación de bundle ausente. Este resultado no equivale a verificación integral del plan.
- Resuelto posteriormente: `resolveSelectableAffectationType` en `resources/js/helpers/functions.js` devuelve sólo el ID 10/20 solicitado, presente en el catálogo recibido; no transforma afectaciones desconocidas. Los formularios de Factura, cotización y Nota de venta muestran un error antes de añadir una línea inválida.
- `calculateRowItem` rechaza IDs distintos de 10/20 y se retiraron sus ramas de inafectas y gratuidad de catálogos anteriores. `node --test tests/js/fiscal-row.test.cjs` ejecuta la fuente en memoria: **6 casos correctos**, cubriendo base/IVA/total, exento con cantidad, tasa configurable, VES/USD, descuento sobre base y rechazo de IDs retirados.
- En esa revisión pasaron **18 pruebas PHP, 195 aserciones** (`ProductModuleFlowContractTest|VenezuelaIvaContractTest`). Los tres componentes Vue pasan análisis de script y plantilla sin generar assets. Actualizadas y validadas las skills de IVA y productos, incluida la referencia del flujo.

### Revisión posterior de acciones fiscales y reportes

- Retirada la opción `send_xml_signed` de `ActionInput`, `ActionTransform` y los dos productores restantes. `ActionInput` deja de consultar configuración fiscal: conserva correo, formato PDF, impresora e IP de impresión. Eliminado `SummaryResult`, sin consumidores.
- Retiradas lecturas residuales de relaciones ISC en tres parciales del reporte general de artículos. Las pruebas recorren ahora todos los Blade de PDF, Report y Account para detectar campos fiscales retirados; no se limitan a los archivos que ya muestran ISC.
- Actualizados contratos de Órdenes de entrega e identidad para comprobar directamente las semillas vigentes y dejar de exigir migraciones o componentes retirados. Actualizadas las skills de operación local, órdenes y SUNAT/SENIAT y sus referencias pertinentes.
- Pruebas locales fiscales: **11 pruebas, 466 aserciones, correctas**. Órdenes, Hotel y etiquetas SUNAT/SENIAT: **13 pruebas, 442 aserciones, correctas**. Las tres skills modificadas pasan `quick_validate.py`.
- La ejecución completa inmediatamente anterior a la corrección de estos últimos contratos tuvo **310 pruebas, 6794 aserciones, 8 fallos y 2 omitidas**, sin errores ni pruebas riesgosas. Seis fallos correspondían a los contratos de órdenes, Hotel y etiquetas que después pasaron; queda repetir la suite completa tras resolver paridad de skills e inspección del bundle de importación.
- Comparadas las capturas de esquema inicial anterior y consolidado, ignorando contadores AUTO_INCREMENT y orden de líneas: las diferencias se limitan a las tablas summaries/summary_documents, campos e índices ISC/bolsas/envío de Boletas y quotations.number_year. Esta comparación es estructural; no sustituye las pruebas funcionales pendientes.

Completar la retirada y revisión de referencias ejecutables restantes, verificar equivalencia del esquema consolidado salvo las retiradas previstas, resolver el resto de la suite y completar las comprobaciones funcionales del plan: instalación, productos/variaciones/importación, clientes, ventas/compras/POS, PDF, stock, ecommerce, órdenes y reportes. Actualizar las demás skills y referencias conforme a los cambios finales. La compilación sigue a cargo del usuario según frontend-build.

### Entradas de notas: eliminación del grupo de Boletas

`DocumentInput` y `DocumentUpdateInput` asignan el grupo 01 directamente y validan que la nota afecte una Factura, local o externa. Ambos rechazan tipos fiscales no admitidos antes de consultar configuración. Se corrigió además la asignación con doble `$` del identificador afectado y se usa `findOrFail` para una referencia local inexistente.

Verificación: `CurrentNoteInputTest`, `SalesDocumentTypePolicyTest` y `SalesWithoutReceiptSourceContractTest`: **17 pruebas, 67 aserciones**, correctas. Incluye ambas entradas y notas externas de crédito/débito; queda pendiente la prueba de persistencia de notas locales dentro de la aceptación integral. No se ejecutaron cambios en bases reales.

### Referencias locales y anulaciones

Se eliminó el parámetro de selección de resúmenes de `voidedDocuments` y se ajustó su único consumidor. La consulta conserva código externo, fecha y grupo 01. Se retiró un selector comentado de Boleta en restaurante y se corrigieron mensajes de series NRUS para concordar con su lista actual.

`CurrentLocalDocumentReferenceTest` y `CurrentNoteInputTest`: **6 pruebas, 43 aserciones** correctas. La primera usa Eloquent sobre SQLite en memoria con relaciones del modelo y verifica referencias locales válidas/inválidas, inexistentes y criterios de anulación. No sustituye la prueba integral de persistencia y efectos comerciales.

Suite unitaria posterior a estos cambios (`/tmp/pro9-fresh-unit-6.log`): **326 pruebas, 7197 aserciones, sin fallos y 3 omitidas**. Las omisiones corresponden a las pruebas MySQL de ejecución explícita y al bundle ausente; no constituyen evidencia de esas verificaciones.

### Configuración inicial y permiso retirado del transportista

La instalación nueva ya no crea `configurations.has_advanced_statuses`: la columna no tenía consumidores y sólo marcaba la transición desde el catálogo anterior de cuatro estados. Los trece estados actuales siguen definidos directamente en los datos iniciales. También se retiró la migración central `2026_09_04_000004_disable_carrier_dispatches.php`; la migración de niveles dejó de insertar y reasignar `dispatch_carrier`, por lo que ya no crea un permiso para borrarlo después. Permanecen `dispatches`, `dispatchers`, `drivers` y `transports` con sus identificadores originales.

Verificación MySQL aislada (`/tmp/pro9-fresh-schema-7.log`): **2 pruebas, 1192 aserciones**, correctas en 2 min 21 s. Incluye migraciones centrales, creación y seeding tenant, todas las claves foráneas, persistencia básica de producto/variación/stock, rollback y una segunda creación. La base temporal aleatoria fue eliminada por el `tearDown`; no se abrió ni modificó un tenant real. Las pruebas de contrato de catálogo, alta y Órdenes de entrega sumaron **8 pruebas, 266 aserciones** correctas.

### PDF local, Amazonía y valor por defecto de venta

Se retiraron las ramas de tipo documental `03` de las plantillas PDF, junto con QR y hash fiscales de Facturas, notas y pedidos. Los QR comerciales de Órdenes de entrega y formularios se conservan. También se eliminó completa la opción peruana de Amazonía: columnas de empresa/configuración, generación de leyendas, formato inicial, parciales de pie, controles Vue y condiciones por departamento 16. La leyenda libre `legend_footer_sale` permanece.

Se retiraron `name_product_pdf_to_xml` y `name_product_xml` del esquema y de sus entradas/salidas; `name_product_pdf` continúa disponible para impresión. La configuración ya no almacena `default_document_type_03`: el selector y Venta rápida sólo eligen Factura (`01`) o Nota de venta (`80`). Los mensajes API y el correo de Factura dejaron de presentarse como comprobantes electrónicos.

`CurrentPdfTemplateContractTest`, `CurrentPdfRenderingTest`, `SalesWithoutReceiptSourceContractTest` y `LocalFiscalDocumentPolicyTest`: **12 pruebas, 505 aserciones**, correctas. La muestra real [factura-actual-verificacion.pdf](../output/pdf/factura-actual-verificacion.pdf) tiene una página A4 de 31 398 bytes; `pdfinfo`, extracción de texto y render PNG confirman RIF, VES e IVA sin Boleta, SUNAT, hash, QR fiscal, cortes ni superposiciones. La muestra usa datos ficticios y no toca un tenant real.

### Retirada de transporte fiscal y migraciones entre servidores

Se eliminó el transporte histórico de documentos desde controladores API/web y `Facturalo`: creación/firma/envío XML, consulta CDR/ticket, reenvío, regularización y estados de servidor. Las respuestas y recursos conservan el registro local, PDF, correo, impresión, pagos e inventario. También se retiraron los comandos masivos, lectores/firmadores/plantillas XML, el árbol `app/CoreFacturalo/WS`, el módulo `PseService` y su activación.

El esquema consolidado ya no crea los campos de transporte en `documents`, `dispatches`, `perceptions`, `retentions`, `purchase_settlements` y `voided`. `configurations` dejó de contener `send_auto`, `sunat_alternate_server`, `auto_send_dispatchs_to_sunat` y `send_data_to_other_server`. Se eliminaron los niveles `document_not_sent` y `regularize_shipping`, el widget SUNAT, eventos webhook de aceptación/observación/rechazo externo y la función de migrar Notas de venta a otro servidor. Se mantienen `dispatches.hash` y `dispatches.qr_url` para seguimiento comercial, las barreras de descarga/almacenamiento contra XML/CDR, QZ Tray y QR/WhatsApp vigentes.

La facturación masiva se conserva. La tabla se consolida en su migración creadora con `estado_emision` y `mensaje_emision`; se eliminan `estado_sunat`, `mensaje_sunat`, `xml_link` y `cdr_link`. El controlador, el listado y el Excel muestran estado local, y la descarga admite sólo PDF. También se retiró la ruta `massive-invoice/config`, que apuntaba a una acción inexistente, sin afectar carga, procesamiento, filtros, exportación o descarga.

Se retiró asimismo el módulo peruano SIRE, sus componentes, rutas, menú, activación y credenciales en `companies`. Esta eliminación se aplica al esquema inicial y no ejecuta ninguna transformación sobre tenants reales.

Verificación posterior:

- Sintaxis PHP de todas las fuentes modificadas: correcta en PHP del contenedor.
- Análisis de script y plantilla: **99 componentes Vue correctos**, sin generar bundle.
- Contratos fiscales/catálogos/Venezuela: **32 pruebas, 1.012 aserciones**, correctas.
- Instalación MySQL temporal: **2 pruebas, 1.314 aserciones**, correctas en 2 min 23 s. Incluye migraciones centrales, esquema y seeding tenant, integridad referencial, persistencia básica, rollback y segundo ciclo idéntico. Sólo apareció la deprecación preexistente del orden de parámetros de `Item::getItemUnitTypesBarcode`.
- No se modificó ningún tenant real. No se ejecutó build, commit ni despliegue.
- El inventario inicial vigente contiene **72 tablas y 858 filas**; la reducción de dos filas corresponde exclusivamente a los niveles retirados `document_not_sent` y `regularize_shipping`.


## Verificación final — 10 de septiembre de 2026

- Facturación masiva permanece registrada con siete rutas vigentes: pantalla, formato, carga, proceso, registros, Excel y descarga. La ruta `massive-invoice/config`, que no tenía acción en el controlador, fue retirada.
- Sus fuentes activas no contienen `estado_sunat` ni `mensaje_sunat`. El esquema, modelo, controlador, exportación e interfaz usan `estado_emision`/`mensaje_emision`; se admite sólo descarga PDF.
- Suite unitaria completa: **329 pruebas, 7.438 aserciones, correctas; 3 omitidas**. Las omisiones corresponden a verificaciones de activación explícita ya ejecutadas por separado y al bundle no generado.
- Instalación MySQL desde cero: **2 pruebas, 1.330 aserciones, correctas**. Incluye migraciones centrales, 325 tablas tenant, datos iniciales, integridad referencial, operaciones básicas de productos/variaciones/stock, rollback y segundo ciclo idéntico. La prueba eliminó sus bases temporales.
- Validación de fuentes: **686 archivos PHP** sin errores de sintaxis, **102 componentes Vue** con script y plantilla válidos, `modules_statuses.json` válido y siete skills relacionadas aprobadas con `quick_validate.py`.
- Pruebas JavaScript: **8 casos correctos**, correspondientes a cálculo fiscal vigente y telefonía/WhatsApp sin actividad externa.
- `git diff --check`: correcto. La consulta `artisan route:list` no puede inicializar toda la aplicación sin una conexión tenant dinámica; el contrato de rutas del módulo se valida por fuente en `LocalFiscalDocumentPolicyTest`.
- No se ejecutó `npm run build` por la política `frontend-build`; por ello los bundles de `public/build` no forman parte de esta verificación.


## Corrección del manifiesto Vite — 10 de septiembre de 2026

El login fallaba porque `public/build/manifest.json` no existía. El primer build reveló una referencia residual: `GlobalHelpButton.vue` importaba `resources/js/helpers/help_summaries.json`, retirado previamente. Se eliminó el import y el estado computado sin consumidores, conservando los tours y la apertura del centro de ayuda.

Con autorización derivada de la solicitud explícita de corregir el error, `npm run build` terminó correctamente y regeneró el manifiesto y sus assets. El manifiesto contiene `resources/js/system.js` y `resources/js/app.js`; todos los archivos referenciados existen. `GET http://localhost/login` responde **HTTP 200** y la comprobación visual muestra el formulario “Acceso al Sistema”, sin la excepción de Vite. Permanecen advertencias no bloqueantes de Sass legado, `@import`, fuentes de Element UI y tamaño de chunks.


## Corrección de carga de creación de documentos — 10 de septiembre de 2026

`GET /documents/create` quedaba vacío porque la solicitud `documents/tables` fallaba con `DocumentController::getIsClient does not exist`. Al retirar envíos históricos entre servidores se había eliminado `OfflineTrait` del controlador, aunque `index`, `tables` e `item_tables` conservan el uso vigente de la bandera `is_client`. Se restauró únicamente el trait compartido en `DocumentController`; no se recuperaron rutas, estados ni descargas fiscales. `LocalFiscalDocumentPolicyTest` protege la disponibilidad del método para estas acciones.
