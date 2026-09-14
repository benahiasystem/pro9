# Implementación de numeración fiscal venezolana

## Alcance aprobado

Rama desde develop: `codex/numeracion-fiscal-venezuela`. Instalaciones nuevas; ninguna conversión ni modificación de bases reales. Facturas, NC/ND y órdenes de entrega. Perfiles por canal/establecimiento; forma libre y adaptadores simulados para digital/máquina hasta elegir proveedores. Skill de proyecto y pruebas unitarias obligatorios.

## Auditoría de entrega (pendiente hasta disponer de evidencia)

- [ ] Configuración por establecimiento/canal, capacidades y grupos dedicados.
- [ ] Secuencias, lotes y controles con unicidad global por emisor y transacciones.
- [ ] Estados, instantáneas fiscales, intentos e idempotencia.
- [ ] Forma libre, impresión, agotamiento, inutilización y reimpresión.
- [ ] Adaptadores digital/máquina, simulación demo y bloqueo de producción.
- [ ] Conciliación, resultado incierto y contingencia sin duplicar ventas.
- [ ] Integración de todos los canales, notas y órdenes de entrega.
- [ ] PDF, libros, búsqueda, exportaciones, correo y API.
- [ ] Editor con Guardar/Cancelar, siguiente número real y estados de carga.
- [ ] Retirada IGV 31556 y código interno de sucursal.
- [ ] Indicador de modalidad sin promesa de conexión.
- [ ] Catálogo de capacidades sin ofrecer FE/retenciones no implementados.
- [ ] Actualización de skills que entren en conflicto y validación del nuevo skill.
- [ ] Esquema temporal: creación, seeding, integridad, rollback y repetición.
- [ ] Pruebas de concurrencia, permisos, aislamiento, recuperación y regresión comercial.
- [ ] Verificación visual de fuentes compiladas y PDF.
- [ ] Contraste final de normativa, incluida Gaceta de 00084/2026.

## Avance verificado — primera etapa

- Rama creada desde `develop` limpio; skill creado y `quick_validate.py` satisfactorio.
- Núcleo `FiscalControlNumber` y `FiscalNumberingRepository` con tres tablas consolidadas y claves en migración final. Todavía no conectado a los controladores de emisión: no confundir estas pruebas con integración final.
- Reserva de número documental y control separada; bloqueo del emisor, idempotencia, exclusión de rangos superpuestos entre sucursales, control de agotamiento e instantánea del lote.
- Retirada de `has_igv_31556` en sucursales, recursos, modelo, esquema, configuración, contabilidad y app móvil. Código de sucursal identificado como interno. Encabezado muestra modalidad e integración no verificada.
- `FiscalControlNumberTest`: 17 pruebas, 25 aserciones. `FiscalNumberingRepositoryTest`: 9 pruebas, 19 aserciones (SQLite, no prueba de concurrencia MySQL). `EstablishmentVenezuelaTaxTest`: 2 pruebas, 6 aserciones.
- Ejecución conjunta de núcleo y `FiscalEmissionSettingsTest`: 48 pruebas, 102 aserciones satisfactorias. Parser Vue/JavaScript en memoria satisfactorio en ambos formularios de sucursal; no se compiló el bundle.
- `FiscalEmissionSchemaTest` en el contenedor PHP, `PRO9_FISCAL_MYSQL_TESTS=1`: 2 pruebas, 1.348 aserciones satisfactorias; creación/siembra/integridad/rollback/recreación. Aviso preexistente de parámetro opcional antes de requerido en `Item.php:2865`.
- Próximo trabajo: completar perfiles y capacidades, adaptadores/estados, integrar reserva con creación comercial idempotente y reemplazar el editor de series. Mantener todos los puntos de aceptación anteriores abiertos hasta aportar evidencia completa.

## Avance verificado — perfiles y configuración

- Añadidas tablas consolidadas de perfiles y auditoría, y referencia de perfil en reservas; claves foráneas en migración final. Los perfiles se resuelven por sucursal/canal/documento/grupo sin fallback implícito de grupo dedicado.
- `FiscalProfileService` valida referencias, capacidades, configuración por modalidad, unicidad de perfil activo e inmutabilidad fiscal después de reservar. Cifra credenciales y conserva el secreto en ediciones vacías; borrado explícito y descarte al cambiar proveedor/modalidad. Auditoría sin valores secretos.
- `reserveForProfile` comprueba disponibilidad y ambiente antes de reservar. Bloquea proveedores no integrados en producción y forma libre sin lote/capacidad; conserva idempotencia incluso al archivar un perfil usado.
- Nuevos endpoints bajo `establishments/{establishment}/fiscal-numbering`: consulta, secuencias, lotes, perfiles y archivo. Todos usan guard administrativo tenant antes de consultar datos. Sigue pendiente verificar HTTP completo en una instalación temporal autenticada.
- Nuevo editor Vue enlazado desde sucursales: numeración documental, lotes y perfiles con Guardar/Cancelar, errores, estados de carga y archivo. Acceso separado a documentos internos/grupos. El editor anterior filtra tipos internos cuando se abre desde esta pantalla. No se compiló ni verificó visualmente el bundle nuevo.
- Pruebas acumuladas: 93 casos y 162 aserciones, incluidas 31 pruebas de autorización y 12 de perfiles. Sintaxis PHP, parser Vue/JavaScript y `git diff --check` satisfactorios.
- Segundo ciclo MySQL completo: 2 pruebas, 1.362 aserciones satisfactorias, con el mismo aviso preexistente de `Item.php:2865`.
- Continúa pendiente conectar estos perfiles y reservas con la creación comercial, adaptadores/estados, canales, notas, órdenes, PDF e idempotencia de toda la petición. Ningún avance de esta etapa acredita ese recorrido completo.

## Avance verificado — ciclo de emisión

- Añadidos contrato de adaptador, simulador con recibos persistidos y servicio de procesamiento de reservas. Las llamadas de emisión se rechazan dentro de transacciones comerciales abiertas y sin documento vinculado.
- Un intento en curso no se repite; un intento vencido o una respuesta incierta pasan por consulta. Una respuesta tardía queda registrada sin sobrescribir una conciliación más reciente. El simulador y sus referencias SIM/DEMO no asignan controles fiscales reales.
- Forma libre pasa a pendiente de impresión; confirmación e inutilización explícitas conservan la reserva y auditan al actor. Un control inutilizado no vuelve a estar disponible.
- `FiscalEmissionServiceTest`: 9 pruebas, 34 aserciones, incluidas pérdida de respuesta, consulta antes de reenvío, respuesta tardía y bloqueo de producción. Conjunto de regresión fiscal: 102 pruebas, 196 aserciones satisfactorias.
- Esquema MySQL temporal con intentos y recibos: 2 pruebas, 1.372 aserciones satisfactorias; creación/siembra/integridad/rollback/recreación. Persiste el aviso preexistente de parámetros en `Item.php:2865`.
- Pendiente: conexión de estos servicios con los controladores y efectos comerciales, autorización HTTP de acciones, impresión y todos los canales. Los vínculos documentales de estas pruebas de servicio son fixtures y no acreditan integración comercial.

## Avance verificado — coordinación del registro comercial

- `FiscalCommercialService` engloba reserva y escritor comercial en la misma transacción tenant, recupera el documento vinculado en reintentos y comprueba tipo, sucursal, serie, número, ambiente y modalidad persistidos. Las órdenes de entrega se vinculan a dispatches.
- `Facturalo::saveFiscal` incorpora este coordinador para factura/NC/ND. Su contexto de reserva se limpia incluso ante excepciones. `FiscalDocumentBinding` aplica la instantánea mediante una propiedad privada no asignable desde payload; `ModelTenant` conserva la modalidad del perfil y el observador evita reasignar numeración por series antiguas para estos documentos.
- `FiscalCommercialServiceTest`: 5 pruebas, 19 aserciones sobre escritor único, rollback de efectos, contenido cambiado, identificación incompatible y vínculo de orden de entrega. `FiscalDocumentBindingTest`: 3 pruebas, 7 aserciones. Regresión acumulada: 110 pruebas, 222 aserciones satisfactorias; sintaxis de Facturalo y diff sin errores.
- El escritor de efectos de las pruebas es una fixture; todavía falta probar pagos/inventario reales con Facturalo y conectar los controladores/formularios al nuevo método. La ruta anterior permanece hasta migrar esos consumidores. Esta etapa no acredita emisión de punta a punta.

## Avance — alta web de facturas

- La validación web resuelve perfil presencial y grupo desde el usuario autenticado; ignora cambios de canal/perfil/numeración recibidos del cliente y exige clave de operación. La huella del contenido incluye actor, artículos y pagos; no depende del orden de claves ni de identificadores generados.
- El formulario principal muestra el perfil, la modalidad y el próximo número estimado; genera una clave aleatoria por formulario y la conserva tras errores. El controlador usa `saveFiscal`, recupera operaciones ya registradas y procesa la emisión después del commit. El PDF fiscal también se genera después del commit, para no deshacer la reserva si falla el renderizado.
- Documentos y órdenes de entrega admiten series de hasta 32 caracteres y número bigint unsigned en el esquema inicial. Los accessors de identificación no anteponen guion cuando no hay serie.
- Pruebas de contexto web y huella: 7 casos, 14 aserciones satisfactorias; parser del formulario Vue y helper JavaScript satisfactorio sin generar assets. Agregada prueba de presentación con/sin serie para factura y orden de entrega.
- Regresión acumulada: 118 pruebas y 240 aserciones satisfactorias. Esquema MySQL temporal ampliado: 2 pruebas, 1.372 aserciones satisfactorias, con el aviso preexistente de `Item.php:2865`. Sintaxis PHP de los controladores/transformadores modificados y diff verificados.
- Pendiente verificar esta entrada con HTTP y efectos comerciales reales en instalación temporal, migrar los demás formularios/canales y ofrecer acciones/estado fiscal en los diálogos. El alta web anterior ahora requiere el contrato de operación; sus consumidores restantes deben migrarse antes de la entrega.

## Avance — acciones fiscales del comprobante

- `FiscalDocumentEmissionController` expone consulta, procesamiento/conciliación, confirmación de impresión e inutilización. Restringe todas las acciones a sucursal y documento; vendedores solo acceden a documentos propios/asignados, e inutilizar exige administrador. La identidad del actor procede de la sesión.
- El diálogo de opciones incluye estado fiscal, ambiente DEMO explícito, número documental/control, historial de intentos y acciones según permisos. Las consultas no exponen instantáneas, huellas ni credenciales. La edición comercial por Facturalo rechaza documentos con reserva fiscal para preservar su contenido.
- `FiscalDocumentEmissionControllerTest`: 15 casos, 23 aserciones. Parser de ambos componentes Vue satisfactorio sin generar assets. Se corrigió durante la regresión un conflicto de nombre con el controlador existente de configuración, que se conserva.
- Pendiente: HTTP autenticado completo en instalación temporal, PDF fiscal y reimpresión coherentes con estos estados, restantes canales y compilación/aceptación visual autorizadas.

## Avance — identificación fiscal en PDF

- La reserva conserva nombre, razón comercial y RIF del emisor sin credenciales. `FiscalPdfData` proyecta los datos de la instantánea para impresión y clona la identidad del emisor sin modificar la configuración actual.
- Las plantillas incorporan un bloque fiscal con número documental, serie opcional, control separado, estado, identificación del equipo cuando aplica y datos/rango de imprenta. DEMO queda identificado expresamente. Se corrigió el cálculo de identificación en 43 plantillas de factura, notas y órdenes de entrega.
- La descarga fiscal regenera el PDF para reflejar el estado actual y no rellena términos históricos con la configuración vigente. El renderizado de forma libre rechaza salida de más de una página bajo un solo control. **Falta** la división/prevalidación antes de la reserva; este rechazo posterior por sí solo no satisface ese requisito.
- `FiscalPdfDataTest`: 4 casos, 19 aserciones, incluyendo identidad histórica, escape HTML y reimpresión sin consumir numeración. Regresión conjunta con comprobación de plantillas: 138 pruebas y 725 aserciones satisfactorias.
- Renderizado e inspeccionado visualmente el bloque fiscal en PDF de 80 mm con datos ficticios (`storage/app/fiscal-qa-yy1iUr.pdf`); texto legible y sin recortes. Es una prueba del bloque, no una aceptación visual de facturas completas ni del alineado sobre formas preimpresas.
- Continúan pendientes PDF completos, referencias fiscales de notas, división por capacidad, restantes canales y aceptación de la instalación temporal compilada.

## Avance — notas de crédito y débito

- El formulario de notas usa perfiles fiscales y clave de operación persistente durante reintentos. Los endpoints de consulta/alta de notas restringen factura afectada por usuario y sucursal.
- `FiscalNoteReference` valida factura interna confirmada, cliente, moneda, ambiente, sucursal, actor y fechas. Captura número documental, serie, control o equipo y fecha dentro de la instantánea de la nota. Las referencias externas exigen administrador y sus identificadores explícitos; no se inventa un control para referencias de máquina fiscal.
- El bloque PDF muestra los datos fiscales capturados de la factura afectada. Se ajustaron 31 referencias de plantillas para no imponer relleno ni guion con serie vacía.
- `FiscalNoteReferenceTest`: 10 casos, 18 aserciones. Añadida prueba de control/fecha de factura afectada en PDF. Regresión completa de esta etapa: 149 pruebas, 746 aserciones satisfactorias; formulario Vue analizado sin generar assets y diff verificado.
- Pendiente: prueba de notas con efectos comerciales reales en instalación temporal y migración de restantes formularios/canales; esta etapa no acredita aún esos recorridos.

## Avance — POS y primera compilación

- Ejecutado `npm run build` con autorización explícita del usuario: satisfactorio en 2m 18s. Avisos de API Sass obsoleta, fuentes resueltas en ejecución y tamaño de chunks; log `/tmp/pro9-fiscal-build.log`. Este build incluye los avances anteriores al POS de esta sección.
- Catálogo de selección fiscal compartido por facturas y POS, filtrado por establecimiento/canal/grupo y secuencia activa, sin credenciales. POS conserva el catálogo de series exclusivamente para notas de venta.
- Pago normal, pago rápido y Vende Ya/Garage usan perfil fiscal para facturas; se eliminó la exigencia de una serie antigua en estos flujos. Un mixin conserva la clave entre reintentos; los padres POS guardan/restauran la clave con el borrador. El diálogo POS incluye las acciones fiscales del documento.
- Siete componentes Vue analizados sin errores; cuatro pruebas JavaScript sobre reintentos, clave restaurada, falta de perfil y notas de venta satisfactorias. Se añadieron dos casos de selección de perfiles en PHP.
- Los cambios POS quedaron incluidos en la segunda compilación indicada más abajo. Siguen pendientes prueba comercial real, otros emisores/formularios y aceptación visual completa en instalación temporal.

## Avance — órdenes de entrega

- El alta web de órdenes de entrega resuelve el perfil por sucursal y grupo, exige clave de operación y reserva los identificadores mediante `saveFiscal`. La numeración deja de depender del prefijo `T`; un reintento recupera la misma orden.
- El vínculo privado de reserva valida tipo 09, establecimiento y ambiente, y genera el nombre de archivo sin introducir campos exclusivos de factura en `dispatches`. La edición de órdenes ya vinculadas se rechaza.
- El formulario muestra el perfil y conserva la clave durante los reintentos. Sus opciones incorporan consulta, procesamiento, confirmación de impresión e inutilización, con autorización por propietario/sucursal y privilegio administrativo para inutilizar.
- Corregidas las unidades de peso del selector a KG/TON conforme al catálogo vigente del proyecto.
- Pruebas focalizadas de vínculo, servicio comercial y acciones de factura/orden: 28 casos, 63 aserciones satisfactorias. Regresión PHP: 168 casos ejecutados, 947 aserciones; dos casos MySQL se ejecutan separadamente. Cuatro pruebas JavaScript satisfactorias.
- Reejecución MySQL temporal completada: 2 casos y 1.372 aserciones satisfactorias en 3m 15s, incluidos creación y reversión del esquema. Continúa el aviso preexistente de parámetros en `Item.php:2865`.
- Pendiente: aceptación HTTP comercial completa, otros formularios de despacho y verificación visual sobre instalación temporal. Estos resultados no acreditan todavía todos los flujos.

## Complemento del plan: inspección de establishments

Inspección de la página abierta `http://bbc.localhost/establishments`, del diálogo Series de Oficina Principal y del formulario Nuevo, sin guardar ni modificar datos. La sesión muestra todavía el editor anterior; esta revisión no acredita visualmente el nuevo componente del repositorio.

| Observación en pantalla | Criterio de aceptación que se incorpora al plan |
| --- | --- |
| La columna «Número» contiene FF01, FC01, FD01 y TT01. | Nombrar explícitamente serie, número documental y número de control; mostrar ejemplos separados y no renombrar FF01 como control. |
| Factura y nota de venta figuran «en uso» con correlativo 1 bloqueado. | Mostrar por separado número inicial, último asignado y próximo disponible, calculados con datos persistidos. Verificar contra documentos reales de una base temporal; esta pantalla por sí sola no demuestra cuál es el próximo número. |
| Se mezclan categorías Básico SENIAT, Avanzado SENIAT e Interno; aparece FE01. | Separar configuración fiscal de numeración comercial/inventario y ofrecer solo tipos con emisión implementada. No usar las categorías como indicación de autorización del SENIAT. |
| Existe un interruptor «Dedicado». | Cubrir grupos de dispositivos en la resolución del perfil y probar aislamiento entre sucursales y grupos, sin elegir otra secuencia silenciosamente. |
| El alta presenta Auto/Manual y Normal/Contingencia. | Explicar qué se genera automáticamente; configurar contingencia mediante un perfil explícito con disponibilidad y motivo, sin inferirla del prefijo de serie. |
| Hay acciones mediante iconos sin nombre accesible en el alta y en las filas. | Usar Guardar/Cancelar y nombres accesibles para editar/archivar; verificar teclado, errores por campo y que cancelar no persista cambios. |
| El encabezado dice «Modo: DEMO Conectado a Medios digitales». | Separar ambiente, modalidad configurada y estado de integración comprobado. DEMO no acredita conexión ni emisión real. |
| La barra de depuración registra un 404 en solicitudes de header. | Identificar la petición exacta durante la aceptación y comprobar que no impida cargar la configuración ni produzca un estado de conexión engañoso; no atribuir todavía la causa al módulo fiscal. |

Estos puntos complementan los requisitos existentes y permanecen pendientes de aceptación con el frontend actualizado.

## Segunda compilación autorizada

`npm run build` terminó correctamente en 1m 40s e incluye POS y órdenes de entrega. Verificado el manifiesto: sus 32 archivos referenciados existen. Permanecen avisos de obsolescencia Sass y tamaño de chunks, sin error de compilación. Log: `/tmp/pro9-fiscal-build.log`.

## Avance — formulario activo de creación de órdenes

- La vista Blade de alta y conversiones utiliza `dispatches/create.vue`. Se adaptó también esta entrada al catálogo de perfiles fiscales, conservando la clave durante los reintentos, y su diálogo `finish.vue` muestra las acciones fiscales de la orden.
- El cliente predeterminado solo se restablece después de guardar correctamente; un error de validación o de red conserva el formulario. Una segunda comprobación tras la validación asíncrona evita solicitudes simultáneas por doble clic.
- Tres pruebas ejecutan el método real de envío del componente: error y reintento con contenido idéntico, doble clic durante validación y error de red. Pasan junto con las cuatro pruebas del mixin fiscal (7 en total).
- Recompilación de esta etapa satisfactoria en 1m 38s, incluidos el formulario activo y su diálogo final.
- La revisión identifica además entradas heredadas de facturas y contingencias que deben adaptarse; no se declara completa la cobertura de canales ni la aceptación HTTP de conversiones.

## Contraste de la providencia 00084

Leída visualmente la providencia SNAT/2026/00084 completa en la página 4 (impresa 472.688) de la [reproducción de Gaceta 43.435](https://www.gescosoftware.com/content/files/2026/08/GO43435_260813_135207.pdf). Sus dos artículos derogan 000121 y establecen vigencia desde publicación el 12/08/2026. Actualizadas las referencias del skill con página y huella del ejemplar. La reforma del RIF del mismo ejemplar corresponde a 00080; no forma parte de 00084. Esta comprobación cierra el contraste pendiente de dicha derogación, sin acreditar por sí sola el cumplimiento funcional de Pro9 ni la revisión integral de las demás normas.

## Límites de verificación

### Emisión por API autenticada

- `POST /api/documents` conserva el formato comercial de entrada y exige `operation_key` (también acepta `clave_operacion`). El cliente conserva esa clave y el contenido en reintentos. La transformación la propaga hasta Facturalo; la validación fiscal se activa desde el middleware, no desde un indicador del payload.
- El servidor selecciona canal digital para cuentas integrator y presencial para admin/seller, con sucursal autenticada y grupo resuelto. Rechaza actor cliente/inexistente o sucursal ajena; ignora las imposiciones de perfil, canal, serie y número del payload. Esta asignación de canales es una decisión de producto explícita.
- El controlador registra con `saveFiscal`, procesa emisión y genera PDF después del commit. Devuelve `data.fiscal` (estado, control y reserva) y `data.replayed`. Un reintento no repite automáticamente correo ni creación de orden de impresión; `print.reason=already_registered` y `print.retry_requires_review=true` indican que no debe ejecutarse un fallback automático de impresión.
- Añadidas rutas autenticadas `GET /api/documents/{id}/fiscal` y `POST .../process`, `.../confirm-print`, `.../invalidate-print`, reutilizando permisos por documento/sucursal. Integradores pueden procesar sus documentos; inutilizar continúa reservado al administrador. Las referencias internas de notas admiten al integrador dentro del mismo ámbito; las externas siguen exigiendo administrador.
- Pruebas focalizadas de contexto/referencias/permisos: **45 casos, 79 aserciones satisfactorias**. Integración directa MySQL actualizada para distinguir registro nuevo/reintento: **1 caso, 61 aserciones satisfactorias**. Sintaxis de controlador, transformación, validación, middleware y rutas verificada.
- Pendientes: recuperación duradera de correo/impresión si el primer proceso se interrumpe después del commit, consumidores móviles que no envían clave, llamadas internas de ecommerce/pedidos que aún usan la validación antigua y prueba HTTP completa del contrato. No se declara completo el canal API por este avance.

### Notas fiscales y efectos de inventario

- Corregido un efecto heredado: los motivos de crédito 04 (descuentos) y 09 (corrección de precios/cálculos), y las notas de débito, dejaban movimientos físicos de inventario. `FiscalInventoryPolicy` separa esos ajustes de las notas de anulación total/devolución (01/07).
- La reversión por rechazo/anulación respeta esa distinción. El cambio posterior de rechazado a anulado o una actualización ajena al estado no repite la reversión.
- Ampliada la integración directa de Facturalo con `InventoryKardexServiceProvider` e `InventoryVoidedServiceProvider`: crea y reintenta notas de crédito por anulación, descuento y corrección, y una nota de débito; comprueba referencia fiscal capturada, un único registro de nota, ausencia de pagos duplicados y existencias/Kardex según el motivo. Cada escenario de nota usa una transacción temporal reversible.
- Pruebas de política/referencia: 19 casos, 27 aserciones satisfactorias. Prueba ampliada de integración real MySQL: **1 caso, 59 aserciones satisfactorias**, en 1m 03s. Incluye la factura, su reintento, emisión DEMO, falta de stock y escenarios de notas. Sintaxis PHP, diff y skill verificados.
- Pendientes: recorrido HTTP de notas, cantidades/saldos acumulados entre varias notas, packs/lotes y aceptación visual de PDF; esta prueba no acredita esas variantes.

### Integración de Facturalo con pagos y Kardex reales

- Añadido `FiscalEmissionSchemaTest::test_facturalo_persists_real_payment_and_inventory_once_on_fiscal_retry`, sobre el esquema tenant completo y sus seeders, en una base temporal eliminada al finalizar.
- Usa Facturalo sin sustituir su escritor, `DocumentObserver` y `InventoryKardexServiceProvider`. La factura de dos unidades genera un documento, una línea, un pago de 232 y un movimiento de Kardex; el stock pasa de 10 a 8. Reintentar con la misma operación conserva esos conteos y la siguiente numeración.
- La emisión DEMO se procesa dos veces y conserva un único recibo simulado. Una segunda venta que excede el stock disponible se rechaza y revierte documento, pago, Kardex y reserva; las existencias permanecen en 8.
- Ejecución focalizada con `PRO9_FISCAL_MYSQL_TESTS=1`: **1 prueba, 18 aserciones satisfactorias**, en 1m 01s. Continúa el aviso preexistente de firma de parámetros de `Item.php:2865`.
- Esto acredita la integración directa de Facturalo y los componentes comerciales citados. Todavía no incluye middleware/HTTP, interfaz, caja abierta, adjuntos, variantes de documentos, proveedores reales ni PDF completo; esos recorridos siguen pendientes.

### Validación de forma libre antes del commit

- `FiscalCommercialService` admite una validación del documento ya vinculado, dentro de la misma transacción y antes del commit. Los reintentos de operaciones persistidas no repiten esa validación ni el escritor.
- `Facturalo::saveFiscal` usa esta fase en forma libre: verifica el máximo de líneas configurado y renderiza el PDF mediante `createPdf(..., 'validate')`. Ese modo comprueba páginas y retorna antes de publicar el archivo. Un fallo revierte la reserva provisional, el consumo de control y las escrituras comerciales de la transacción.
- Prueba con mPDF y salto real de página: se rechazan dos páginas y se verifica que documento, reserva y efectos de prueba desaparecen, y que secuencia y lote conservan su próximo número. Probados también el límite exacto de líneas, exceso, configuración inválida y reintento de una operación validada.
- Pruebas focalizadas PHP: 29 casos, 77 aserciones satisfactorias. Reejecutadas las cuatro pruebas concurrentes MySQL después de modificar el servicio transaccional.
- Esta fase mejora la comprobación posterior descrita en el avance inicial de PDF. **Sigue pendiente** la división asistida de documentos y su aceptación completa con plantillas reales, pagos e inventario de Facturalo. No se declara satisfecho el requisito completo de división por capacidad.

### Concurrencia de reservas y registro comercial en MySQL

- Añadidos cuatro casos con dos procesos PHP independientes y conexiones MySQL distintas. Una transacción coordinadora bloquea el emisor hasta que ambos procesos están listos; se comprueba que ninguno completa la operación antes de liberar el bloqueo.
- Cubiertos: dos ventas distintas con números/controles diferentes, dos reintentos idénticos con un único documento y escritor, una clave con contenidos incompatibles, y dos ventas compitiendo por el último control del lote.
- Ejecutado `docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php php vendor/bin/phpunit tests/Unit/FiscalMySqlConcurrencyTest.php`: **4 casos y 43 aserciones satisfactorias**. Cada caso crea y elimina su propia base `pro9_fiscal_concurrency_*`; el trabajador rechaza bases fuera de ese patrón y recibe su configuración por entrada estándar.
- La regresión PHP posterior al cambio de infraestructura ejecutó 177 casos con 961 aserciones satisfactorias; los casos optativos MySQL se ejecutan separadamente. El nuevo cuarto caso concurrente también pasó en la ejecución focalizada final.
- Los efectos de pago/inventario de estas pruebas son filas de prueba del escritor comercial. Esta evidencia verifica reserva, bloqueo e idempotencia del servicio sobre MySQL; **no sustituye** la aceptación HTTP de Facturalo con pagos, existencias y observadores reales.

### Avance de conversión de orden a factura

- `generate-document.vue` muestra el perfil fiscal, recibe el catálogo por sucursal/grupo y genera una clave por formulario. Conserva hora y contenido en reintentos; los errores de red no destruyen el formulario.
- La consulta de datos de conversión exige usuario administrador/vendedor y sucursal; el vendedor accede solo a sus órdenes.
- `FiscalDispatchConversion` se ejecuta dentro de la transacción de reserva de Facturalo. Bloquea la orden, valida cliente/ambiente/sucursal/actor, rechaza referencias ya facturadas y vincula ambos registros. Una clave distinta no permite facturar nuevamente la misma orden; los reintentos de la misma operación siguen recuperándose antes de ejecutar el escritor comercial.
- Pruebas focalizadas PHP de conversión, registro comercial y contexto web: 19 casos, 43 aserciones satisfactorias. Pruebas JavaScript de la conversión y los formularios fiscales: 10 casos satisfactorios, incluidos hora/contenido idénticos tras pérdida de respuesta.
- Compilación de esta etapa satisfactoria en 2m 29s; archivos del manifiesto verificados y sintaxis PHP correcta.
- Pendiente demostrar el recorrido HTTP completo con pagos e inventario reales en una instalación temporal; las pruebas actuales no sustituyen esa aceptación ni la prueba concurrente MySQL.

El usuario autorizó explícitamente compilar para completar la tarea. La compilación está realizada; la aceptación visual y funcional completa sigue pendiente sobre una instalación temporal. No hay proveedores reales elegidos. Las pruebas simuladas no acreditan conexión, autorización ni emisión fiscal real.


### Atribución fiscal de pedidos automáticos

- El perfil digital admite un usuario emisor explícito, seleccionable en Numeración y emisión. El servidor limita la selección a administradores/integradores activos de la misma sucursal.
- `FiscalOrderContext` resuelve el canal digital y asigna la clave estable `ecommerce-order-{id}-invoice`; rechaza sucursales ambiguas y recupera el perfil histórico en reintentos. El flujo de facturas de `OrderDocumentFromStatusService` usa ese contexto sin elegir al primer usuario disponible.
- Pruebas focalizadas de pedidos, perfiles y autorización: 57 casos y 92 aserciones satisfactorias. Regresión fiscal PHP: 223 casos descubiertos, 216 ejecutados, 7 optativos MySQL omitidos, 1032 aserciones. JavaScript: 10 casos satisfactorios.
- Pendientes específicos: aceptación HTTP de pedidos, coordinación del descuento de existencias ya aplicado al pedido y recuperación del procesamiento posterior a transacciones externas. Esta modificación no acredita el flujo completo de ecommerce.

- Compilación posterior al selector de usuario emisor: `npm run build` satisfactoria en 51,48 s; verificados los 32 archivos referenciados por el manifiesto. Avisos de Sass y tamaño de paquetes sin impedir la generación. `git diff --check` y validación del skill satisfactorios.

### Protección de existencias en pedidos ya facturados

- El cambio de estado del pedido rechaza acciones de descuento, liberación de existencias y anulación directa cuando existe una factura vinculada. También consulta la reserva fiscal por clave de pedido para cubrir el intervalo posterior al registro de factura y anterior al guardado del vínculo en el pedido. El rechazo ocurre antes de cambiar su estado.
- `FiscalOrderStockGuardTest` verifica pedido sin factura, vínculo existente y factura registrada antes del vínculo, sin bloquear otro pedido. Junto con el contexto de pedidos: 8 casos, 15 aserciones satisfactorias.
- Este control no sustituye la coordinación transaccional entre generación y cambios concurrentes de estado. Sigue pendiente resolver el descuento previo de pedidos, unificar almacenes/cantidades desde datos persistidos y verificar ese recorrido HTTP. No se declara resuelto aún el descuento duplicado durante la conversión.

### Vínculo transaccional de pedidos y facturas

- `FiscalOrderConversion` bloquea el pedido dentro de la transacción de reserva y comprueba una huella canónica de la compra persistida antes de invocar el escritor. Rechaza pedidos eliminados, con factura o nota de venta, así como claves pertenecientes a otro pedido.
- Facturalo recibe el contexto del pedido como argumento interno separado de los datos del comprobante. La factura y el vínculo `document_external_id`/`number_document` se guardan juntos; el servicio de pedidos ya no realiza ese vínculo después de emitir o generar PDF. Los reintentos se recuperan antes del escritor mediante la reserva existente.
- Probados vínculo y rechazo de duplicados, reversión conjunta ante fallo posterior, modificación concurrente del contenido, otra nota de venta y clave incorrecta. Pruebas focalizadas con el contexto y registro comercial: 26 casos, 76 aserciones satisfactorias.
- Se conserva como pendiente la coordinación de movimientos de stock previamente ejecutados por los estados del pedido y su carrera concurrente con la generación. La transacción del vínculo por sí sola no acredita ese requisito ni la aceptación HTTP.

- Integración ampliada de Facturalo en MySQL temporal: **1 caso, 68 aserciones satisfactorias**, en 1m 05s. El escenario de pedido sin descuento previo genera una sola factura adicional, pago y Kardex, stock de 8 a 6 y vínculo coincidente; el reintento no repite efectos. El escenario se revierte al finalizar. Esta prueba invoca el escritor directamente, no el flujo HTTP/digital del pedido.
- Revalidación de MySQL: concurrencia **4 casos, 43 aserciones**; esquema completo e integración anterior **3 casos, 1433 aserciones**, en 3m 24s. El caso ampliado de 68 aserciones se ejecutó después por separado. Todas las bases usadas son temporales.
- Regresión fiscal PHP posterior: **232 casos descubiertos, 225 ejecutados, 7 MySQL optativos omitidos, 1048 aserciones**, sin fallos. Sintaxis, diff y skill válidos. No se modificaron fuentes de interfaz en esta etapa; no requiere nueva compilación.

### Reserva previa de existencias de pedidos

- El esquema inicial de `orders` añade únicamente `stock_reservation` JSON nullable para registrar la sucursal, los productos/almacenes/cantidades aplicados y la selección de almacén por producto. Es información escrita por el servicio, no un campo mass-assignable del pedido.
- `FiscalOrderStockReservation` verifica la selección contra los artículos y cantidades persistidos, exige almacenes de la sucursal, agrupa componentes de packs, omite servicios y bloquea existencias insuficientes. Guarda reserva y estado en la misma transacción; repetir descuento/liberación no repite efectos. La liberación usa cantidades capturadas, aunque cambie la composición del pack.
- Los estados de descuento, liberación y anulación sin NV usan este servicio. La conversión fiscal libera la reserva previa dentro de la transacción antes de crear la factura, y conserva el almacén reservado en sus líneas. Fallar la factura revierte también esa liberación. El observador de venta usa el almacén explícito de la línea, incluida la rama de packs.
- Ajustada la interfaz de pedidos para no marcar éxito ni cerrar el formulario ante un rechazo del servidor. Compilación satisfactoria en 48,94 s; los 32 archivos del manifiesto están presentes.
- Pruebas de reserva cubren reintentos, datos alterados, almacén de otra sucursal, cantidad negativa, selección incompleta, servicios, packs, insuficiencia y reversión ante fallo de factura. Regresión fiscal PHP: 241 casos descubiertos, 234 ejecutados, 7 MySQL optativos omitidos y 1072 aserciones, sin fallos.
- Aún pendientes: aceptación HTTP con permisos y dos procesos compitiendo entre estado y factura, presentaciones/variantes de ecommerce, sincronización de existencias de restaurante y conversión de reservas a NV. Las comprobaciones actuales no acreditan esas variantes.

- Esquema MySQL actualizado: **3 casos y 1443 aserciones**, satisfactorios, en 3m 17s. Integración posterior con almacén secundario: **1 caso y 73 aserciones**, satisfactorios, en 1m 05s. El secundario pasa de 20 a 18 al reservar, permanece en 18 al facturar/reintentar, y el principal permanece en 8. La línea de factura conserva el ID del almacén secundario; reserva y bandera quedan liberadas.
- Reejecutadas las pruebas concurrentes generales de numeración: **4 casos y 43 aserciones**, satisfactorios. No confundirlas con la carrera específica estado de pedido/factura, todavía pendiente.
- Tres pruebas JavaScript ejecutan el método real del formulario de pedidos: selección enviada, rechazo sin cerrar/marcar reserva, éxito confirmado y fallo de red. Todas satisfactorias. Se corrigió durante la revisión una llamada accidental a `catch` sobre el resultado de `push`; las pruebas de comportamiento y la compilación final incluyen esa corrección.

- Compilación final del formulario corregido satisfactoria; manifiesto de 32 archivos verificado. Regresión JavaScript fiscal: 13 pruebas satisfactorias. Añadido rechazo explícito de booleanos como cantidades: la suite de reserva queda en 10 casos y 26 aserciones satisfactorias.

### Conciliación de reservas en Nota de venta

- `FiscalOrderSalesNoteContext` prepara el flujo automático de pedidos dentro de la transacción de `SaleNoteController`: bloquea emisor/pedido, verifica la compra persistida, impide generar NV si ya existe factura, recupera una NV existente y concilia la reserva antes de crear sus líneas. El argumento de origen es interno, separado del payload del comprobante.
- Las líneas de NV conservan el almacén reservado; su observador usa el almacén explícito también en packs. La selección de sucursal del pedido con reserva toma la sucursal capturada cuando no está indicada en la compra.
- La liberación vuelve a leer el pedido bloqueado: dos llamadas con el mismo objeto antiguo no devuelven stock dos veces.
- Pruebas focalizadas de pedidos: **30 casos, 66 aserciones satisfactorias**. Cubren contexto de NV, recuperación de NV existente, pedido facturado, contenido cambiado, reversión ante fallo y liberación con objeto obsoleto. Sintaxis y diff correctos.
- Estas pruebas verifican el servicio de preparación y sus transacciones. Quedan pendientes la aceptación HTTP/PDF del controlador completo de NV, los consumidores manuales de `order_id` y el flujo de anulación de NV; no se declara verificada toda la operación de Notas de venta.

- El controlador de NV conserva el emisor validado del contexto interno, evitando sustituirlo por `auth()->id()` nulo durante un proceso automático. Se exige emisor activo de la sucursal; una sucursal incorrecta no libera la reserva. La selección inicial de emisor de NV todavía procede del flujo comercial previo y queda pendiente unificar su configuración explícita con el contrato de pedidos automáticos.
- Regresión de Facturalo después de releer la reserva bajo bloqueo: **1 caso, 73 aserciones satisfactorias**, en 1m 01s. La regresión fiscal previa al último caso de emisor ejecutó 241 pruebas (7 MySQL optativas omitidas), con 1086 aserciones. Suite focalizada final de pedidos: **31 casos, 69 aserciones satisfactorias**. Sin cambios de interfaz en esta etapa.

### Contrato de respuestas HTTP y validación de numeración

- La revisión del manejador global identificó que una excepción Symfony de acceso denegado terminaba como 500 para peticiones JSON. `Handler` conserva ahora el estado HTTP y las cabeceras de esas excepciones; no usa su código PHP como estado de respuesta.
- `FiscalHttpExceptionResponseTest` invoca el guard real de numeración y el manejador real, verificando 403 sin datos de depuración, 429 con `Retry-After`, 404 y la forma vigente del 422. La fábrica de respuestas está sustituida únicamente para construir respuestas JSON de Laravel sin arrancar el tenant completo. Son pruebas del contrato de respuesta, no aceptación de autenticación por HTTP ni arranque completo del kernel.
- El formulario de Numeración y emisión admite las tres formas vigentes de error por campo (`errors`, `message` como objeto y mapa directo). Conserva el valor inválido y muestra también los conflictos generales de configuración. Cuatro pruebas ejecutan su método real de guardado, todas satisfactorias.
- Permanece pendiente la aceptación HTTP con kernel, resolución tenant y middleware completos sobre una instalación temporal. La revisión de ese arranque condujo a estas correcciones concretas; no se declara completada esa aceptación.

- Verificación de esta etapa: 35 pruebas focalizadas de permisos/respuestas (41 aserciones); regresión fiscal PHP, 253 descubiertas, 246 ejecutadas, 7 MySQL omitidas y 1099 aserciones, sin fallos. Las 17 pruebas JavaScript pasaron. Compilación final satisfactoria y 32 archivos del manifiesto presentes; diff sin errores.

### Concurrencia de estados de pedido y conversión fiscal

- Ampliado el trabajador MySQL con operaciones de reserva/liberación de pedido y conversión fiscal. Cada caso usa dos procesos y conexiones independientes detenidos inicialmente por el bloqueo del emisor; ninguno termina antes de liberarlo.
- Probadas las carreras reservar/facturar y liberar/facturar. Se acepta que la acción de stock termine antes de la factura o sea rechazada si la factura gana; el resultado exige una factura, una reserva fiscal, dos filas de efectos comerciales de prueba, vínculo de pedido, reserva de stock liberada y existencias finales de 8 desde 10. No se presupone un orden del planificador.
- Suite concurrente ampliada: **6 casos, 73 aserciones satisfactorias**. El escritor comercial sigue siendo una fixture; esta prueba específica de bloqueos no sustituye la integración real de Facturalo ya documentada ni la aceptación HTTP.
- El contexto público web/API rechaza claves que comiencen por `ecommerce-order-`, incluidas mayúsculas, para evitar colisiones con operaciones internas de pedidos en la colación MySQL. El contexto interno recibe el ID por argumento y exige clave, canal digital y tipo de factura coincidentes. Un campo enviado por el consumidor no habilita ese acceso.
- Pruebas focalizadas de contextos web/API/pedido: **19 casos, 46 aserciones satisfactorias**. Incluyen conservación del flujo interno y rechazo del prefijo público sin crear reservas.

- Regresión fiscal final: 257 casos descubiertos, 248 ejecutados, 9 MySQL optativos omitidos y 1103 aserciones, sin fallos. Los 6 concurrentes se ejecutaron por separado en esta etapa. Skill válido y diff sin errores; no hubo cambios de interfaz que requieran recompilar.

### Kernel HTTP real para configuración fiscal

- Añadido `tests/Support/fiscal_http_worker.php`: recibe por entrada estándar una conexión cuyo nombre debe cumplir el patrón de bases temporales fiscales, reemplaza todas las conexiones configuradas y arranca proveedores, rutas y kernel reales de Laravel. Se fijan el hostname y el usuario del guard de sesión; no se simulan los controladores ni los middleware de las peticiones.
- El fixture construye el esquema tenant consolidado, sus catálogos y la configuración/empresa/usuarios que normalmente crea el alta. La primera ejecución mostró que faltaba la fila de configuración; se corrigió el fixture. Los avisos del proceso se separan del resultado JSON mediante un marcador explícito.
- Primera prueba satisfactoria: las respuestas reales fueron 401 para anónimo, 403 para vendedor, 200 para administrador, 422 al intentar iniciar en cero y 200 para una secuencia válida; se comprobó que únicamente esta última quedó persistida.
- Alcance: contrato HTTP de configuración con sesión y hostname fijados. Siguen pendientes login, identificación real del tenant, navegación visual, emisión completa de documentos por HTTP y separación física de las bases system/tenant durante esa aceptación. No se da por completa la aceptación integral.

- Prueba HTTP ampliada satisfactoria: **1 caso, 9 peticiones y 12 aserciones**, en 1m. Incluye creación de perfil con credenciales de prueba, consulta sin divulgarlas, almacenamiento distinto del texto original y archivo del perfil reflejado en la consulta posterior. Se ejecutó exclusivamente contra una base temporal eliminada al terminar. Skill y diff válidos; no se modificaron assets en esta etapa.

### Emisión y reintento por kernel HTTP

- El caso HTTP ahora crea bases system y tenant separadas desde sus migraciones reales. El sistema recibe hostname, cliente y plan de prueba; se mantiene el hostname seleccionado y el usuario del guard como entradas controladas del arnés. Ambas bases se eliminan al terminar.
- Las peticiones de factura atraviesan validación web, normalización, FormRequest, controlador, plan comercial, Facturalo, observadores, emisión DEMO y generación PDF. El disco tenant del trabajador apunta a un directorio temporal y se limpia al salir.
- Encontrado y corregido un fallo real de `validationOpenCash`: `array_search` sin coincidencia devuelve `false`, que la comparación `>= 0` trataba como selección de caja. Se usa búsqueda estricta y comparación `!== false`; tres pruebas verifican pagos sin caja y listas vacías sin consultar una sesión de caja.
- Prueba HTTP ampliada satisfactoria: **1 caso, 12 peticiones y 151 aserciones** (incluye comprobaciones de carga de migraciones), en 1m 10s. Crea y reintenta una factura: mismo ID, un documento/pago/Kardex, stock de 10 a 8, estado fiscal `issued` en simulación y un solo archivo PDF. Conserva las pruebas de permisos, secuencia inválida, perfiles y credenciales.
- No acredita proveedor real, login, identificación automática del tenant, caja abierta, recepción fiscal de notas/órdenes, integraciones API ni contenido visual completo del PDF. Esas variantes siguen pendientes.

- Regresión unitaria de esta etapa: 261 casos descubiertos, 251 ejecutados, 10 MySQL optativos omitidos y 1106 aserciones, sin fallos. La ejecución completa de `FiscalEmissionSchemaTest.php` está en curso; todavía no se registra como satisfactoria.

- La regresión completa que estaba en curso terminó satisfactoriamente: `FiscalEmissionSchemaTest.php`, **4 pruebas y 1466 aserciones**, en 4m 38s. Incluyó el primer caso HTTP de factura digital y las pruebas de esquema tenant/system e integración directa; la ampliación posterior de forma libre se verifica separadamente.

### Forma libre por HTTP

- Ampliado el caso del kernel a **20 peticiones**. Después de la factura DEMO digital, crea un lote de prueba y un perfil de forma libre, registra otra factura, la reintenta y confirma dos veces la impresión mediante el endpoint correspondiente.
- Verificado estado inicial `awaiting_print`, control `00-00000001`, estado final `issued`, siguiente control 2 y siguiente número documental 3. Los dos comprobantes conservan dos pagos/Kardex en total, stock final de 6 desde 10 y dos archivos PDF; los reintentos no añaden efectos.
- Ejecución satisfactoria: **1 caso y 159 aserciones** (incluye carga de migraciones), en 1m 15s. La confirmación de impresión es una acción simulada del usuario de prueba; no acredita impresión física ni alineación con papel preimpreso. La división asistida, inutilización por HTTP y aceptación visual completa continúan pendientes.

### API con autenticación Bearer real

- El trabajador HTTP admite cabecera `Authorization: Bearer` y restablece los guards entre peticiones. Las llamadas API no reciben un usuario inyectado: el guard de token consulta la cuenta integradora temporal.
- Primera ampliación satisfactoria: **26 peticiones, 165 aserciones** en un caso, en 1m 13s. Sin token y con token incorrecto responde 401; con token válido crea y reintenta una factura del canal digital usando `clave_operacion`. El segundo resultado conserva el ID y declara `replayed`; la consulta fiscal confirma `issued` DEMO. Los tres documentos del escenario total mantienen tres pagos/Kardex/PDF y stock final 4 desde 10.
- Se añadieron pruebas de cambio de contenido bajo la misma clave, prefijo interno de pedidos y acceso de otro integrador. Este último usa el contrato existente 404 de recurso no disponible, no 403 de rol prohibido. La ejecución ampliada está en verificación.
- Hallazgo pendiente para el siguiente recorrido API con RIF y dirección: `Requests/Api/Validation/Functions::person` aún deriva estado y municipio recortando el código de parroquia. Los códigos venezolanos requieren consultar las relaciones `districts.province_id` y `provinces.department_id`; no se declara cubierta esa variante mediante el caso actual de cédula sin parroquia.

- La ampliación API anterior concluyó satisfactoriamente: **29 peticiones y 168 aserciones**, en 1m 15s. Confirma rechazo de contenido cambiado, clave interna reservada y consulta ajena sin exponer datos.

### Relaciones geográficas del cliente en documentos

- Sustituida la derivación por prefijos en `Api/Validation/Functions::person` y `Web/Validation/Functions::person` por `PersonLocation`, compartido y con conexión explícita del tenant. Consulta los padres reales y exige una jerarquía activa y única cuando se suministra parroquia; una ubicación omitida conserva valores nulos.
- Eliminado `DocumentTransform::parseLocation`, método privado sin llamadas que retenía el cálculo peruano. No se modifica el catálogo territorial ni una base real.
- Pruebas unitarias satisfactorias: **14 casos y 15 aserciones**, incluyendo `000619 → 0229 → 14`, ubicación opcional, país incompatible, tipos/códigos inválidos, niveles inactivos, padre ausente y duplicidad.
- El caso HTTP se amplió para emitir por API con RIF y parroquia `000619`, comprobando persistencia de la jerarquía real. Su ejecución está en curso.
- Auditoría adicional: `OperationDataInput` de liquidaciones de compra y `SetDataHelper::createDistrict` conservan lógica por prefijos; no forman parte del recorrido de factura probado y requieren revisar sus consumidores antes de declarar cubierta toda la geografía.

- La verificación HTTP con RIF y parroquia finalizó satisfactoriamente: **29 peticiones, 171 aserciones**, en 1m 13s. Confirma cliente de tipo RIF y ubicación `14/0229/000619`, emisión DEMO, PDF y reintento sin efectos duplicados. Bases temporales eliminadas al finalizar.
- Regresión fiscal y geográfica: **275 casos descubiertos, 265 ejecutados, 1121 aserciones, 10 pruebas MySQL optativas omitidas**, sin fallos; el caso HTTP MySQL anterior se ejecutó por separado. Skill válido y `git diff --check` sin errores. Los últimos cambios son PHP/pruebas/documentación y utilizan la compilación de interfaz ya verificada.

### Contingencia vinculada sin nueva venta

- Incorporados `FiscalContingencyService` y `FiscalReservation::effective`. La reserva física se vincula mediante `parent_reservation_id`, único y con FK autorreferenciada; la original conserva su documento comercial, numeración, instantánea e intentos y pasa a estado `contingency`. La física se crea `awaiting_print`, con causa, actor y referencia original.
- La operación bloquea emisor y reserva, exige administrador activo de la sucursal, perfil de forma libre del canal contingencia y punto compatible. Rechaza respuesta incierta/en curso o emisión confirmada; después de un intento requiere resultado conciliado de ausencia o rechazo. Valida capacidad y PDF antes de confirmar la transacción. Un fallo revierte los nuevos números sin borrar la venta original.
- Rutas web/API de documentos y web de órdenes de entrega, consulta del estado efectivo, confirmación/inutilización del control vinculado, datos PDF y referencias de notas conectados. La interfaz incorpora selección de perfil y causa, conserva datos ante error y muestra contingencia. No cambia de modalidad automáticamente.
- Captura de esquema antes/después en bases temporales: **332 tablas**, única diferencia en `fiscal_number_reservations`: columna `parent_reservation_id` nullable, índice único y FK. La regresión de creación, seeding, rollback y recreación completa está en ejecución.
- Primeras pruebas unitarias del servicio: **14 casos, 38 aserciones**. Regresión fiscal: **289 casos descubiertos, 279 ejecutados, 1159 aserciones y 10 MySQL omitidos**, sin fallos, antes de añadir casos complementarios de permisos/lote.
- Concurrencia MySQL: **8 casos y 99 aserciones**, en 24s. Dos procesos de contingencia con misma causa recuperan una reserva; con causas distintas solo uno se acepta. Conservan una venta, un control y una auditoría. Son fixtures comerciales, no una prueba de pagos reales.
- Caso HTTP ampliado satisfactorio: **181 aserciones**, en 1m 13s. Incluye 36 peticiones HTTP y dos registros directos de prueba mediante InputRequest/Facturalo para representar una venta comprometida antes del envío al proveedor; estos dos pasos no son endpoints HTTP. Se verifican permisos, reserva física/reintento, confirmación, cuatro documentos/pagos/Kardex/PDF, cinco reservas fiscales y stock final 2 desde 10.
- Límites pendientes: recuperación de una forma libre de contingencia inutilizada, conciliación con un proveedor real, representación de identificadores efectivos en todos los listados/exportaciones y aceptación visual completa del preimpreso. Esta etapa no acredita cumplimiento integral ni impresión física.

- Pruebas complementarias verificadas: la regresión final ejecutó **284 casos, 1166 aserciones**, con 12 MySQL optativos omitidos; los ocho de concurrencia ya fueron ejecutados separadamente. Se corrigió únicamente una expectativa de texto del test de lote agotado: el servicio lo rechazaba correctamente antes de asignar control. El contexto público pasó además **9 casos y 18 aserciones**, incluyendo prefijos internos `contingency-` en ambas cajas.
- Interfaz: 27 pruebas JS iniciales satisfactorias; seis pruebas focales finales cubren el formulario y mensajes de validación, incluidos errores del handler global. Build final satisfactorio en **48,58s**; manifiesto verificado con 32 archivos presentes. La regresión completa de esquema continúa en curso.

- La regresión completa de esquema finalizó satisfactoriamente: **4 pruebas y 1498 aserciones**, en 4m 54s. Incluye instalación tenant/system, carga de datos, integridad, rollback/recreación, Facturalo con efectos comerciales reales y el escenario HTTP ampliado de contingencia. Bases temporales eliminadas al terminar. No quedan procesos de verificación pendientes de esta etapa.
