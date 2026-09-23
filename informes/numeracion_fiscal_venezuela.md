# Implementación de numeración fiscal venezolana

## Alcance aprobado

Rama desde develop: `codex/numeracion-fiscal-venezuela`. Instalaciones nuevas; ninguna conversión ni modificación de bases reales. Facturas, NC/ND y órdenes de entrega. Perfiles por canal/establecimiento; forma libre preimpresa como modalidad operativa. Impresora fiscal e imprenta digital permanecen en desarrollo y fuera de la aceptación productiva. Skill de proyecto y pruebas unitarias obligatorios.

## Auditoría de entrega vigente (2026-09-22)

- [x] Configuración por establecimiento/canal, capacidades y grupos dedicados.
- [x] Secuencias, lotes y controles con unicidad global por emisor y transacciones.
- [x] Estados, instantáneas fiscales, intentos e idempotencia.
- [x] Forma libre: impresión, agotamiento, inutilización, reemplazo y reimpresión.
- [x] Impresora fiscal e imprenta digital visibles como «en desarrollo» y bloqueadas fuera de DEMO.
- [x] Conciliación, resultado incierto y contingencia sin duplicar ventas.
- [x] Canales presencial/API/pedidos y documentos 01/07/08/09 mediante forma libre.
- [x] PDF, libros, búsqueda, exportaciones, correo y API.
- [x] Editor con Guardar/Cancelar, siguiente número real y estados de carga.
- [x] Retirada IGV 31556 y código interno de sucursal.
- [x] Indicador de modalidad sin promesa de conexión.
- [x] Catálogo de capacidades sin ofrecer FE/retenciones no implementados.
- [x] Actualización de skills que entren en conflicto y validación del nuevo skill.
- [x] Esquema temporal: creación, seeding, integridad, rollback y repetición.
- [x] Pruebas de concurrencia, permisos, aislamiento, recuperación y regresión comercial.
- [x] Verificación visual de fuentes compiladas y PDF de forma libre.
- [x] Contraste de la implementación con las providencias documentadas y la factura legal de referencia.
- [x] SCRUM-39 consultada como antecedente; sus criterios quedaron excluidos por instrucción expresa del usuario.

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

### Comprobación de recuperación — 20/09/2026

- Verificado HEAD `a5d5b1b59ff8cff157ae03cfb86e42474c201577` en `codex/numeracion-fiscal-venezuela`; el árbol estaba limpio al comenzar. El commit incluye las últimas modificaciones descritas en este chat: geografía de clientes, contingencia, vínculo/FK, rutas, UI, pruebas, skill e informe. No se identificó un cambio implementado de esas etapas que requiriera restauración. La revisión siguiente de identificadores en listados/exportaciones aún no había realizado ediciones.
- Comprobación actual del commit: 29 pruebas JS satisfactorias y **298 casos PHP descubiertos, 286 ejecutados, 1170 aserciones, 12 MySQL optativos omitidos**. Se verificó que los archivos del manifiesto existían. El servicio PHP estaba detenido; las pruebas se ejecutan con contenedores efímeros `docker compose run --rm -T --no-deps php`, sin arrancar servicios web ni modificar bases reales.

### Identificación fiscal efectiva en consulta y reportes

- Incorporados modelos de lectura de reservas/secuencias, `HasFiscalIdentity` y `FiscalIdentity`. `Document` y `Dispatch` presentan `number_full` desde la reserva física vigente sin modificar sus columnas comerciales originales. El resumen público distingue número, serie, control, equipo, estado y referencia original de contingencia; omite clave de operación, huella y datos internos del proveedor.
- Carga por lote de las relaciones en colecciones y reporte de documentos, incluyendo colecciones de Resources. Filtros por serie/número/control usan la misma reserva efectiva y mantienen el ámbito del query. Los documentos sin reserva conservan la búsqueda comercial y no inventan control.
- El listado de facturas consulta datos actuales en vez de recuperar páginas con cinco minutos de caché: una contingencia cambia la identidad y pertenencia al filtro sin actualizar las columnas de venta. Series de búsqueda obtenidas de secuencias fiscales; se retira la selección automática de la primera serie.
- Interfaz de factura/orden muestra control y referencia de contingencia; factura incorpora filtro de control y mensajes de validación que permiten corregirlo sin dejar el botón cargando. Reporte Excel de documentos y cuentas por cobrar comienzan a usar la identidad efectiva. La auditoría de los demás reportes sigue pendiente.
- Al repetir HTTP el 20/09, el fixture del 13/09 falló por antigüedad de emisión. El trabajador de pruebas fija ahora su reloj al 13/09; no se cambió la validación de fechas de la aplicación. El recorrido ampliado está en repetición.

- Verificación HTTP de consultas satisfactoria: **192 aserciones**, en 1m 25s. Después de la contingencia, `/documents/records?number=5` y `?control_number=00-2` devuelven la factura 4 mostrando número 5 y control `00-00000002`; buscar número 4 no la devuelve como vigente. El detalle presenta el mismo identificador. La columna comercial `documents.number` sigue en 4.
- La revisión alcanzó el módulo Report vigente: exportadores estándar/agrupado y vistas PDF/Excel toman la identidad efectiva y muestran el control. Se detectó que las vistas agrupadas consultaban `Series::FilterDocumentType`, por lo que podían excluir documentos sin serie o con nuevas series fiscales. Ahora derivan los grupos de los registros; prueba específica verifica serie física y serie vacía sin catálogo comercial.
- Pruebas focales de identidad: **9 casos, 30 aserciones**, incluyendo modelos reales, Resources, ausencia de consultas adicionales por fila después de precarga, ámbito de establecimiento, controles inválidos y agrupación. Pruebas JS de filtros: tres casos satisfactorios; la batería previa de interfaz verificó 32 casos.
- Compilación de interfaz satisfactoria en **59,73s**. Se está comprobando además la descarga Excel mediante la ruta del módulo Report y lectura del XML interno del XLSX generado. No se ha declarado completa la auditoría de todos los reportes/exportaciones ni la aceptación visual del preimpreso.

- Verificación HTTP con exportación satisfactoria: **194 aserciones**, en 1m 25s. La ruta real `/reports/sales/excel` generó un XLSX; se inspeccionó su XML de cadenas y se encontraron `Control: 00-00000002` y `Reserva original: 4`. El escenario conserva cuatro ventas/pagos/Kardex y cinco reservas, sin duplicar efectos al consultar o exportar. Se mantiene la distinción de dos pasos directos de fixture y peticiones HTTP reales.
- Regresión final de esta etapa: **307 casos PHP descubiertos, 295 ejecutados, 1200 aserciones, 12 MySQL optativos omitidos**, sin fallos; el escenario HTTP MySQL se ejecutó separadamente. **35 pruebas JS**, skill válido, `git diff --check` sin errores y 32 archivos de manifiesto presentes. No se modificó el esquema en esta etapa. Los cambios nuevos quedan sin commit.


### Órdenes de entrega por API — continuación del 20/09/2026

- `FiscalApiDispatchService` reserva la operación autorizada y materializa cliente/productos y orden en una transacción tenant. Reintentos enlazados recuperan el documento sin repetir materialización. El controlador procesa la emisión después del commit y genera PDF; incorpora identificadores efectivos, estado fiscal y señal de reintento.
- La API admite `clave_operacion`, resuelve establecimiento/canal/perfil en servidor, valida fechas, cantidades, conductor/transportista, unidades y jerarquía territorial. Se corrigió la lectura posicional del código de partida y el valor nulo del indicador opcional de transbordo. El alta de producto por una salida ya no inventa stock inicial.
- Listado y detalle API limitan sucursal y propietario para vendedor/integrador; filtros usan la reserva efectiva. Se agregaron rutas de consulta/proceso/impresión/contingencia para órdenes API. El controlador alternativo `Dispatch2Controller` también usa el registro fiscal; no se encontró una ruta activa que lo referencie.
- La batería completa `tests/Unit` detectó tres expectativas antiguas ya presentes en los contratos: nombres de categorías del antiguo selector, series fiscales del POS y conteos anteriores a las siete tablas fiscales. Se alinearon las expectativas con los cambios funcionales vigentes; no se modificó el esquema. Resultado: **610 casos descubiertos, 598 ejecutados, 12 omitidos y 12.680 aserciones**, sin fallos.
- La aceptación HTTP ampliada está en verificación. Sus primeros intentos detectaron el indicador nulo y el código `KGM` incorrecto del fixture; el catálogo vigente usa `KG`. No se declara todavía satisfactoria esta ampliación ni integración con proveedor real.

- Verificación HTTP final satisfactoria: **1 prueba, 207 aserciones, 1m 30s**. El escenario incluye 52 peticiones HTTP y dos pasos directos de fixture. La orden API `OE-10` se crea y reintenta con el mismo ID; declara `replayed` correctamente, obtiene estado `issued` DEMO y un único PDF. El integrador propietario consulta la orden, otro obtiene listado vacío y detalle 404; cambios bajo la misma clave, jerarquía territorial incorrecta y conductor ausente devuelven 422. Resultado persistido: cuatro facturas/pagos, una orden con un ítem, cinco movimientos Kardex/PDF, seis reservas; stock de 10 a 0 (cuatro facturas de dos unidades y una orden de dos), secuencia de orden siguiente 11. No aparecen reservas de las entradas inválidas ni mutación del cliente por el reintento conflictivo.
- Concurrencia MySQL satisfactoria: **8 pruebas, 99 aserciones, 27s**, con procesos independientes y bases temporales. Esta batería general de reservas no representa una carrera HTTP específica entre dos órdenes API. El skill valida y `git diff --check` no presenta errores. Los cambios de esta continuación son PHP/pruebas/documentación; conservan el build verificado de la etapa anterior.
- Pendientes generales conservados: restantes escritores comerciales, división asistida de forma libre, recuperación de control de contingencia inutilizado, aceptación visual y recorridos de todos los canales/reportes. La emisión digital probada usa simulador DEMO; no acredita una imprenta real ni habilita producción sin adaptador.


### Conversión API de Nota de venta a Factura — continuación

- Se retiró el guardado directo de `generateCPE`. La ruta exige Factura, fechas y condición de pago válidas, actor autorizado y nota del ámbito del actor; bloquea emisor y origen, resuelve el perfil fiscal en servidor y registra con `saveFiscal`. Conserva el vínculo de origen dentro de la transacción. Reintentos usan `operation_key`/`clave_operacion`; una clave distinta no puede volver a convertir una nota ya enlazada. El PDF y el proceso de emisión ocurren después del commit.
- Se rechazan notas anuladas/rechazadas o de otro ambiente. La respuesta añade ID, indicador de reintento, estado e identidad fiscal, y conserva los campos y enlaces anteriores. La factura recibe UUID propio, independiente del UUID de la nota.
- La batería unitaria general volvió a pasar: **610 descubiertas, 598 ejecutadas, 12 omitidas, 12.680 aserciones**. La ampliación HTTP de conversión está en verificación; el fixture de origen usa modelos y observadores reales y requiere caja abierta temporal. Este paso de preparación no se presenta como endpoint de creación de NV.
- Queda por unificar la protección de origen en las conversiones web/agrupadas, verificar carreras entre canales y resolver/verificar los pagos previamente cobrados. El endpoint conserva por ahora el contrato anterior de pagos vacíos de la factura; no se declara resuelta la conciliación contable de una NV pagada. Servicio técnico fue inspeccionado y continúa pendiente de implementación.

- Verificación HTTP final satisfactoria: **215 aserciones, 1m 25s**. El escenario completo tiene 56 peticiones HTTP y tres pasos directos de preparación. La conversión crea factura ID 5/número 6, el reintento devuelve el mismo ID con `replayed=true`, otra clave devuelve 422 sin reservar un número y otro integrador recibe 404. La nota conserva `document_id=5`; existe una sola factura para ese origen. Cinco facturas, cuatro pagos de factura, una orden, siete reservas y seis PDFs al final; secuencia de facturas siguiente 7.
- El fixture amplía stock inicial a 12: cuatro facturas consumen ocho unidades, una orden dos y la NV de origen otras dos. La conversión y su reintento mantienen stock final 0. El observador vigente registra Kardex tanto para NV como para factura vinculada (siete filas en este escenario), aunque no vuelve a descontar existencias; queda pendiente revisar esa representación junto con pagos y conversiones de otros canales.
- Concurrencia general: **8 pruebas, 99 aserciones, 12s**, satisfactoria. No es una prueba específica de conversión simultánea de la misma NV. Sintaxis PHP, skill y `git diff --check` válidos. Sin cambios de frontend en esta etapa ni modificaciones de bases reales; cambios sin commit.


### Servicio técnico — integración fiscal

- El formulario recibe perfiles fiscales por sucursal/punto de emisión y muestra el resumen en Factura; no selecciona serie comercial para ese tipo. La clave se genera con el mixin común y se conserva ante errores. NV mantiene sus series. Se retiró el texto que calificaba cualquier comprobante como electrónico.
- `GenerateDocumentController` prepara contexto fiscal, bloquea emisor y reserva antes de crear productos. En reintentos enlazados no vuelve a materializar ítems. Usa `saveFiscal` a través del controlador común y procesa emisión/PDF después de confirmar su transacción externa.
- `FiscalTechnicalServiceConversion` protege el origen también dentro del escritor común: mismo establecimiento/ambiente, rol administrativo/vendedor y propietario para vendedor; rechaza servicios ya vinculados a Factura o NV. El controlador aplica la misma comprobación al camino NV y restringe la lectura del registro por sucursal/propietario. No se modificó el esquema.
- Pruebas de origen: **9 casos, 10 aserciones**, incluyendo rollback del escritor, ambos tipos de vínculo previo y contextos incompatibles. Interfaz: **3 casos JS** sobre compilación de plantilla, ausencia de serie comercial en Factura y bloqueo sin perfil. Regresión unitaria completa: **619 descubiertos, 607 ejecutados, 12 optativos omitidos, 12.691 aserciones**, sin fallos.
- HTTP ampliado y compilación de producción están en ejecución; todavía no se registran como satisfactorios. La protección se aplica a nuevas conversiones; no constituye una conciliación de pagos previamente cobrados en el servicio técnico.

- HTTP final satisfactorio: **223 aserciones, 1m 12s**. Añade emisión de servicio técnico, reintento con mismo ID 6/número 7 y estado `issued` DEMO, segunda clave rechazada sin reserva persistida, vendedor ajeno rechazado y detalle ajeno 404. Resultado acumulado: seis facturas, cinco pagos, una orden, ocho reservas, ocho filas Kardex y siete PDFs; stock inicial 14/final 0, sin otro descuento al reintentar. El escenario incluye 63 peticiones HTTP y tres pasos directos de fixture.
- Concurrencia general satisfactoria: **8 casos, 99 aserciones, 9s**; no constituye una carrera específica sobre el servicio técnico. Build de producción satisfactorio en **37,94s**, manifiesto con 32 archivos presentes. Cambios sin commit. Permanecen pendientes las conversiones de otros canales, pagos previos del origen y aceptación visual completa.


### Cierre de origen y protección de cobros en Nota de venta

- La revisión de conciliación encontró dos defectos previos: la conversión API no marcaba `changed`, usado por reportes para excluir la venta comercial sustituida, y el endpoint de pagos permitía modificar el origen ya facturado. La conversión ahora marca `changed` y `document_id` en la misma transacción, sin depender del endpoint separado que usa el navegador.
- `FiscalSaleNotePaymentGuard` exige transacción, bloquea emisor → nota y verifica sucursal/propietario/rol y ausencia de conversión por ambos vínculos. Alta/edición y borrado de pagos usan el guard; la edición no puede mover un ID de pago de otra nota. El borrado vuelve a consultar tras el bloqueo y recalcula el saldo restante en lugar de marcar siempre impagado.
- Siete casos unitarios nuevos pasan con ocho aserciones: nota propia disponible, administrador de sucursal, propietarios/sucursal/rol ajenos y conversión detectada por `document_id`, `changed` o documento inverso.
- El fixture HTTP ahora incluye un cobro parcial previo de 100 con referencia financiera y de caja. Se está verificando que los intentos de alta/borrado posteriores no alteran ese cobro. **No se ha implementado todavía su aplicación al saldo de la factura**; el endpoint de conversión sigue emitiendo con lista propia de pagos vacía. La conciliación completa debe conservar una sola entrada de dinero, referencias históricas de caja/banco/voucher/enlace y saldo correcto de la factura, sin duplicar movimientos ni borrar la historia del origen.

- HTTP satisfactorio: **228 aserciones, 1m 23s**, 66 peticiones HTTP y tres fixtures directos. Se comprueba `changed=1`, alta/borrado de pago del origen convertido con 422 y actor ajeno con 403. El único pago NV de 100, el único registro financiero y su referencia de caja permanecen intactos. No acredita saldo conciliado de la factura ni edición de una NV pagada desde todos los demás escritores.
- Regresión unitaria: **626 descubiertas, 614 ejecutadas, 12 omitidas y 12.700 aserciones**, sin fallos. Concurrencia general: **8 casos, 99 aserciones, 14s**, sin fallos; pendiente carrera específica de cobro frente a conversión. Skill válido y `git diff --check` limpio. Etapa PHP/pruebas/documentación, sin recompilar ni alterar bases reales.


### Aplicación de cobros de NV al saldo de la factura

- Capturado el esquema real anterior en base temporal y comparado con el posterior: **332 tablas**, única diferencia en `document_payments`: columna nullable `source_sale_note_payment_id`, índice único y FK restrictiva a `sale_note_payments`. Se modifica el consolidado de instalación nueva y su rollback; no se ejecutan cambios en bases reales.
- `FiscalSaleNotePaymentAllocation` aplica cobros persistidos al saldo dentro de la transacción de conversión API, con bloqueo emisor → origen → factura, validación de vínculo/cliente/establecimiento/ambiente/moneda/total y referencia única por cobro. No crea GlobalPayment, CashDocumentPayment ni mueve voucher/enlace; mantiene la recepción original y la identifica en la factura. Actualiza `total_canceled` con aritmética decimal.
- Las aplicaciones y sus cobros fuente quedan protegidos en modelos contra edición/borrado. Los helpers de destino/caja y las asociaciones web/API omiten aplicaciones; las sumas físicas de efectivo/transferencia las excluyen, mientras el saldo comercial las incluye. Consulta de pagos expone origen y ubicación original del voucher; UI identifica la aplicación y oculta Eliminar. El controlador de pagos de factura valida ámbito de usuario/sucursal y pertenencia del ID editado.
- La conversión API aplica los cobros sólo durante el registro nuevo; un reintento posterior conserva pagos adicionales cobrados en factura. Se refresca el documento para PDF y se valida otra vez el formato de forma libre antes de confirmar la transacción externa.
- Pruebas unitarias de aplicación cubren parcial, total exacto, repetición, cambios de origen y contextos incompatibles; regresión unitaria actual **635 descubiertas, 623 ejecutadas, 12 omitidas, 12.714 aserciones**, sin fallos. La comparación de DDL sólo muestra el cambio descrito. Están en curso la regresión completa de instalación/rollback y el HTTP final con cobro del saldo restante.

- El recorrido HTTP ampliado pasó con **238 aserciones**: la factura reconoce 100 ya cobrados y saldo 132; se cobra ese saldo por el endpoint real, queda pagada con 232 y un reintento API conserva ese saldo. La aplicación no crea asociación de caja ni otro GlobalPayment; hay dos entradas financieras reales (100 de origen y 132 posteriores). Borrar la aplicación devuelve 422. El origen conserva su pago y vínculo de caja.
- La auditoría de cierre API detectó que sumaba totales de ventas y podía contar NV más factura. Ahora, para ambas, suma pagos asociados a esa caja y omite aplicaciones en factura. Se está verificando un cierre real de la caja temporal con resultado esperado 232; no se declara todavía comprobado ese último paso.
- Compilación satisfactoria en **48,08s**, 32 archivos del manifiesto presentes. Tres pruebas JS nuevas comprueban plantilla y conservación de la carpeta de voucher `sale_notes`/`documents`; el conjunto fiscal JS está en ejecución. La regresión completa de esquema sigue en curso.

- Cierre API verificado satisfactoriamente: **239 aserciones, 2m 04s**. La caja temporal termina con saldo **232** (100 de NV + 132 recibidos después en factura), no 464 por sumar dos documentos. El mismo escenario verifica saldo de factura cero, reintento sin pérdida del cobro posterior y una sola aplicación del pago fuente. Comprende 74 peticiones HTTP y tres pasos directos de fixture.
- Regresión completa de esquema satisfactoria: **4 casos, 1553 aserciones, 8m 14s**, incluida creación tenant/system, integridad, rollback y recreación. La ampliación final de cierre se ejecutó separadamente después. Bases temporales eliminadas al terminar.
- Verificación final adicional: **623 pruebas unitarias ejecutadas / 635 descubiertas, 12 omitidas, 12.714 aserciones**; **41 pruebas JS**; **8 casos de concurrencia general, 99 aserciones**. Build, manifiesto de 32 archivos, skill y `git diff --check` válidos. No quedan procesos de esta etapa en ejecución.
- La aplicación de cobros queda conectada a la conversión API individual de NV. Sigue pendiente extender/verificar la política en conversiones web/agrupadas y servicios con pagos previos, realizar carreras específicas de cobro frente a conversión y la aceptación visual completa. No se declara conciliación universal de todos los canales.

### Conversión web individual de NV y aplicación transaccional de cobros

- Verificado nuevamente HEAD `a5d5b1b5` y rama `codex/numeracion-fiscal-venezuela`. Los cambios posteriores siguen presentes sin commit; no se restauraron archivos ni se descartaron modificaciones.
- `FiscalSaleNoteConversion` se ejecuta en el escritor fiscal común para conversiones individuales web/API. Bajo bloqueo de emisor y origen comprueba propietario/sucursal/ambiente, cliente, moneda, importe, productos y cantidades, estado y vínculos previos. Rechaza IDs de cobros originales reenviados como nuevos, importes no positivos y cobros superiores al saldo restante. El reintento enlazado omite el escritor.
- El escritor crea la factura sin duplicar recibos fuente, aplica los cobros persistidos, registra los nuevos y actualiza saldo y vínculo `document_id`/`changed` antes del commit. La validación PDF de forma libre ocurre después de estos efectos. Se elimina la asociación individual posterior fuera de transacción del controlador web.
- La interfaz muestra perfil fiscal y cobros previos informativos, envía sólo cobros nuevos y conserva la clave de operación al fallar. No fuerza cambio de moneda a 1 ni utiliza otra petición para marcar el origen convertido. `option_tables` entrega los perfiles autorizados del punto de emisión.
- Veinte pruebas nuevas del guard pasan con 21 aserciones; cuatro JS de la conversión pasan, y el conjunto fiscal JS suma **45 pruebas aprobadas**. Build satisfactorio en **56,12s**, 32 archivos referenciados por manifiesto presentes. Skill validado y diff sin errores de espacios.
- El HTTP ampliado pasó con **250 aserciones, 1m 29s**: conserva el escenario API previo y añade otra NV con 100 cobrados, conversión web con 132 nuevos, reintento con el mismo ID y rechazo de segunda clave; saldo final cero y stock sin segunda salida por conversión. El fixture crea nombres únicos para cada NV. Falta cerrar la repetición final que incorpora además las dos aserciones de caja del pago nuevo y de la aplicación.
- Concurrencia general: **8 casos, 99 aserciones**, aprobados. No representa todavía una carrera específica entre edición/cobro de NV y conversión. Regresión unitaria previa al añadido de tres importes inválidos: **652 descubiertas, 640 ejecutadas, 12 omitidas, 12.733 aserciones**. Se está ejecutando la final.
- Pendientes: conversión agrupada de NV, conciliación de otros orígenes/canales, recuperación duradera tras commit, división de forma libre y aceptación visual completa. Este apartado no declara completado el objetivo global ni integración real de proveedores.
- Regresión unitaria final satisfactoria: **655 descubiertas, 643 ejecutadas, 12 omitidas, 12.736 aserciones**. Las omitidas requieren la ejecución MySQL separada. Próximo convertidor identificado: `sale_notes_relateds` en `invoice.vue` e `invoice_generate.vue`, con asociación aún posterior en `DocumentController`; requiere bloqueo de todas las fuentes y aplicación de pagos por origen sin duplicar movimientos.
- HTTP final aprobado: **252 aserciones, 1m 21s**. Verifica además que la aplicación del cobro de la segunda NV no tenga entrada de caja y que el nuevo cobro de 132 tenga exactamente una asociación con su caja. Son 78 peticiones HTTP y cuatro pasos de fixture. Ningún proceso de esta etapa queda pendiente; cambios sin commit y sin modificar bases reales.

### Conversión agrupada de notas de venta

- `FiscalSaleNoteConversion` admite una fuente individual o una lista agrupada de IDs únicos; rechaza mezcla, duplicados y valores inválidos. Bloquea emisor → fuentes por ID ascendente y verifica ámbito, estado, enlaces, moneda/cliente, suma decimal de importes y cantidades. El escritor fiscal aplica cobros por todas las fuentes y marca sus vínculos antes del commit; el controlador deja de asociarlas fuera de la transacción.
- `FiscalSaleNotePaymentAllocation::applyMany` comprueba la lista completa persistida en la factura, contexto y suma de importes; conserva referencias únicas por pago original. Un conflicto en la segunda fuente revierte también la aplicación de la primera. Aplicar un subconjunto no está permitido.
- Selección de notas y consulta de ítems ahora filtran por sucursal, ambiente y propietario para no administradores y excluyen notas ya convertidas. Se verifica la selección completa y la igualdad de cliente/moneda antes de devolver ítems. La lista incluye cobros originales como información; formularios `invoice`/`invoice_generate` descuentan ese importe del cobro sugerido y conservan moneda/tipo de cambio del origen.
- Pruebas específicas: **47 casos y 57 aserciones**, incluidos duplicados, mezcla de origen individual/agrupado, fuentes incompatibles, aplicación una sola vez, rechazo de subconjunto y rollback de aplicaciones. Regresión PHP: **673 descubiertas, 661 ejecutadas, 12 omitidas, 12.759 aserciones**, sin fallos.
- Primer HTTP agrupado satisfactorio: **263 aserciones, 1m 24s**. Dos NV de 232 con cobros previos de 100 cada una pasan a factura de 464, dos aplicaciones y cobro nuevo de 264; reintento conserva factura, segunda clave se rechaza, todas las fuentes quedan enlazadas, no hay segunda salida de stock ni entradas de caja para aplicaciones. Se amplió después con consulta de fuentes/ítems y rechazo de propietario ajeno; esa repetición está en curso.
- Siete pruebas JS nuevas verifican cálculo de saldo en ambos formularios, factura ordinaria, saldo totalmente pagado y plantilla de selección. Build y concurrencia general están en curso. Continúan pendientes la aceptación visual completa y variantes agrupadas con descuentos, precios diferentes, unidades/presentaciones y lotes; el guard no permite emitir un total que difiera de las fuentes, pero eso no demuestra todavía que la preparación visual de todas esas variantes sea funcional.
- HTTP final aprobado: **266 aserciones, 1m 32s**, con 85 peticiones HTTP y seis pasos de fixture. La selección devuelve sólo las dos fuentes disponibles y sus 200 cobrados; consulta de ítems propia devuelve dos filas y la ajena responde 403. Incluye todos los escenarios anteriores de emisión, contingencia, API, conversión individual, caja y agrupación.
- Verificación final: **52 pruebas JS**, **8 casos de concurrencia general con 99 aserciones**, compilación en **47,87s**, manifiesto con 32 archivos presentes, skill válido y diff limpio. Los procesos de esta etapa terminaron. Cambios sin commit, sin modificar bases reales.
- Próxima revisión concreta: la preparación agrupada del modal combina por `item_id` usando un precio y la carga en `invoice_generate` calcula filas antes de recuperar la moneda de las notas. Se debe preservar el contenido económico del origen para precios distintos/descuentos/monedas; los casos HTTP actuales usan precios iguales y VES, por lo que no acreditan esas variantes.

### Conservación de filas económicas al agrupar NV

- Detectado y corregido el camino que agrupaba sólo por producto, conservaba un precio y volvía a consultar el catálogo. `FiscalSaleNoteItems` agrupa únicamente filas simples equivalentes, conserva instantáneas completas y suma los importes ya guardados con `bcadd`; no recalcula precio ni IVA a partir de la nueva cantidad. Filas con precios/IVA/almacén/unidad diferentes o con descuentos, cargos, atributos, lotes o presentaciones permanecen separadas.
- El endpoint autorizado de ítems acepta `group_items` y entrega filas completas. El modal usa esa respuesta en la carga de instantáneas existente, sin pasar por el buscador de catálogo. Los formularios cargan moneda/tipo de cambio antes de preparar ítems, esperan su preparación y omiten la reconversión automática al recuperar el cliente.
- **16 pruebas nuevas, 22 aserciones**, verifican sumas decimales, redondeos persistidos, precios/IVA/unidades/detalles separados y objetos JSON de los accessors Eloquent. Tres pruebas JS adicionales comprueban moneda previa a carga y transporte de filas completas con precios y descuentos, sin precios de catálogo.
- HTTP ampliado aprobado: **268 aserciones, 1m 30s**. La agrupación ahora usa un mismo producto con precios 116 y 174: fuentes por 232 y 348, factura por 580, cobros originales 200 y nuevo cobro 380. El endpoint conserva dos filas y ambos precios; se verifican saldo, reintento, caja, vínculos y ausencia de segunda salida de existencias. También mantiene los escenarios anteriores.
- Regresión PHP/JS y build están en curso. Permanecen por tratar los descuentos/cargos globales de cabecera (conservar descuentos de línea no resuelve los globales), variantes completas con moneda extranjera y las pantallas de suscripciones que usan otros modales. La prueba JS de carga de moneda no equivale a emitir fiscalmente una factura USD real desde navegador.
- Verificación final satisfactoria: **689 pruebas PHP descubiertas, 677 ejecutadas, 12 omitidas y 12.782 aserciones**; **55 pruebas JS**; build en **57,38s** con 32 archivos del manifiesto presentes; skill y `git diff --check` válidos. No quedan procesos de esta etapa pendientes. Cambios sin commit y bases reales intactas. Esta etapa no modificó reservas ni orden de transacciones; la última prueba de concurrencia general sigue siendo la de la etapa agrupada anterior.

### Ajustes globales de cabecera en conversiones

- `FiscalSaleNoteEconomics` captura y suma los totales persistidos, y conserva descuentos/cargos globales con sus bases, factores e importes originales. El guardado fiscal recupera esa captura de las notas bloqueadas antes de crear la factura. No utiliza valores económicos informativos del navegador como autoridad ni aplica otra vez ajustes ya incluidos en los totales.
- El endpoint de preparación entrega también la captura de cabecera. El modal la transporta y los formularios la aplican únicamente a la misma selección de IDs; al volver a una factura ordinaria deja de aplicarse. Recalcular conserva el total y los ajustes, sin redistribuir descuentos. La pantalla informa que conserva los importes del origen y muestra los descuentos/cargos de cabecera.
- Cuatro pruebas PHP nuevas con 12 aserciones cubren suma exacta, bases/factores distintos, conservación de ajustes sin nueva deducción y rechazo de fuentes duplicadas. Tres pruebas JS nuevas cubren recálculos sucesivos, inmutabilidad de la captura, selección ajena y restablecimiento de una factura ordinaria.
- Regresión PHP aprobada: **693 descubiertas, 681 ejecutadas, 12 omitidas, 12.796 aserciones**. **58 pruebas JS** y **8 casos de concurrencia general con 99 aserciones** aprobados. Build en **55,15s**. El HTTP final con descuento/cargo globales está en curso.
- Sigue pendiente validar íntegramente que las filas recibidas al emitir coincidan económicamente con las fuentes (el guard actual compara cantidades y total conjunto), completar variantes de moneda extranjera y los demás escritores/pantallas del alcance. Conservar la cabecera no equivale a demostrar esas condiciones.
- HTTP final aprobado: **275 aserciones, 1m 39s**. La fuente 3 contiene descuento global 12 y total 220; la fuente 4, cargo global 12 y total 360. La factura conserva total 580, ambos ajustes con sus descripciones y los cobros previos/nuevo, sin duplicar sus efectos. Manifiesto de 32 archivos, skill y diff válidos. Procesos terminados; cambios sin commit y bases reales intactas.

### Validación económica de filas de origen

- `FiscalSaleNoteItemComparison` reemplaza la comprobación limitada a cantidades. El guard bloquea las filas de origen por ID y compara cantidades, precios/valores unitarios, tipo de precio, unidad/factor, afectación/porcentajes, bases e importes de impuestos, valores, descuentos/cargos y totales, normalizados a la precisión de persistencia.
- Permite agrupar filas simples equivalentes. Usa grupos separados por precio, unidad y tratamiento fiscal, por lo que cambios opuestos de IVA entre precios distintos no se cancelan. Para ajustes compara tipo, factor, base e importe y conserva la cantidad de instancias; no acepta fusionar dos descuentos en una sola instancia manteniendo sólo su total.
- **24 pruebas específicas, 24 aserciones**, aprobadas: agrupación válida, diferencias de representación decimal/JSON, precios e impuestos alterados con cabecera intacta, unidad/factor, descuentos/cargos añadidos, compensaciones entre precios, números inválidos y precio pequeño recibido como float. Las **35 pruebas del guard, 37 aserciones**, también pasan.
- Se añadió una petición HTTP con IVA de fila alterado y total de factura intacto antes de la conversión válida. Debe rechazarla sin dejar reserva ni efectos; la regresión completa y el recorrido HTTP están en curso.
- Este comparador cubre contenido económico. No acredita aún igualdad completa de metadata de lotes/series ni protección de todas las rutas que pueden editar o anular una NV después de convertirla; ambos permanecen en el alcance pendiente.
- Regresión PHP aprobada: **717 descubiertas, 705 ejecutadas, 12 omitidas, 12.821 aserciones**. Concurrencia general: **8 casos, 99 aserciones**. El HTTP anterior, ahora con el nuevo comparador, conserva sus **275 aserciones** aprobadas; está en curso la repetición que incluye el intento manipulado antes de convertir.
- Próximo punto de auditoría localizado: `sale-notes/delete-relation-invoice` elimina actualmente el vínculo entre NV y factura sin comprobar reserva fiscal; `storeWithData` también necesita revisar la edición de un origen convertido y su orden de bloqueo. No deben poder liberar una fuente ya consumida por una factura fiscal. Esta etapa sólo inspeccionó esas rutas, aún no las corrigió.
- HTTP final aprobado: **277 aserciones, 1m 33s**. El intento con impuesto de fila alterado y cabecera intacta se rechaza sin reserva; después la conversión válida y su reintento conservan los resultados anteriores. Skill y `git diff --check` válidos. No quedan procesos pendientes. Etapa PHP/pruebas/documentación, sin nuevos cambios frontend ni necesidad de recompilar; bases reales intactas y cambios sin commit.

### Protección de mutaciones del origen convertido (2026-09-22)

- Al retomar, los cambios del guard seguían en el directorio, pero no existía el log temporal ni ningún proceso PHP de la ejecución anterior. Se volvió a verificar el código y se ejecutaron pruebas nuevas; HEAD sigue en `a5d5b1b5`.
- `FiscalSaleNoteMutationGuard` centraliza bloqueo emisor → nota, permisos por sucursal/propietario y rechazo de orígenes convertidos o anulados/rechazados. Lo usan edición web/API, borrado de ítem, anulación, pagos (mediante el wrapper existente) y la antigua desvinculación. Esta última ya no libera fuentes consumidas; sin vínculo devuelve éxito sin cambiar datos.
- Ediciones rechazan cambio de sucursal del origen y descartan vínculos `document_id`/`changed` del payload. La materialización de catálogos API se hace dentro de la transacción, después de autorizar el origen. La sustitución crea ítems propios sin reutilizar IDs recibidos, y API elimina pagos/ítems con sus eventos, reconstruye la recepción de caja y recalcula saldo.
- Primer HTTP de rechazo aprobado: **281 aserciones, 1m 39s**; intentos de desvincular, anular, borrar ítem y editar web/API de una NV convertida no modifican sus vínculos, pagos, ítems ni stock. Incluye rechazo de propietario ajeno y origen agrupado.
- HTTP positivo adicional aprobado: **289 aserciones, 1m 37s**. Una quinta nota no convertida se edita por API conservando un ítem y un cobro; enviar ID 1 en sus ítems no modifica la línea de la nota 1 convertida. El pago sustituido deja de tener referencia de caja y el nuevo tiene una sola asociación con la caja abierta; saldo parcial correcto.
- La anulación pasa de GET a POST. Se actualizan listado, drawer y ambos listados de suscripciones; el helper permite método explícito, devuelve resultado al cancelar/fallar y muestra el mensaje de validación, evitando que el drawer quede cargando. Tres pruebas JS específicas pasan y el conjunto fiscal suma **61 aprobadas**.
- Regresión PHP: **719 descubiertas, 707 ejecutadas, 12 omitidas, 12.824 aserciones**; concurrencia general **8 casos, 99 aserciones**, aprobadas. Se está verificando anulación de la nota no convertida una sola vez, rechazo del reintento y GET 405, además del build actualizado.
- Pendiente aún auditar todos los escritores alternativos/modelos, recurrencia y edición de metadatos, y realizar carreras específicas edición/anulación frente a conversión. Esta cobertura no declara inmutabilidad universal de todos los caminos posibles ni completa el alcance fiscal global.
- HTTP final aprobado: **291 aserciones, 1m 29s**. POST anula la NV 5 sin convertir, devuelve exactamente sus dos unidades y deja estado 11; repetir responde 422 sin nueva devolución, y GET de anulación responde 405. Las fuentes convertidas permanecen intactas. Build en **46,40s**, 32 archivos del manifiesto presentes, skill y diff válidos. No quedan procesos de esta etapa pendientes; cambios sin commit y sin modificar bases reales.

### Revisión del entorno visual y ampliación de aceptación de notas (2026-09-22)

- Reconfirmado HEAD `a5d5b1b59ff8cff157ae03cfb86e42474c201577`; los cambios posteriores siguen presentes en el árbol de trabajo sin commit. No se restauró ni descartó ningún archivo.
- La navegación actual a `http://bbc.localhost/establishments` devuelve 404. Consulta de sólo lectura: no existe `hostnames.fqdn = bbc.localhost` en la conexión system actual y las rutas no están cacheadas. Las rutas de establecimientos y configuración fiscal sí están declaradas. Esta instalación ya no permite reproducir la inspección visual anterior; no se crea ni modifica un tenant real para resolverlo.
- Se amplía la prueba HTTP temporal para notas de crédito por devolución y descuento, y nota de débito por aumento de valor, con secuencias independientes y reintentos. Resultado pendiente de la ejecución de esta etapa.
- Resultado HTTP ampliado: **324 aserciones satisfactorias, 1m 34s**, en bases temporales. Notas NC-20 (devolución), NC-21 (descuento) y ND-40; cada reintento conserva ID/PDF/número, no crea cobros, y sólo NC-20 genera una devolución de dos unidades. La referencia de NC-21 conserva el control `00-00000001`, número y fecha de su factura. Log: `/tmp/pro9-fiscal-notes-http-final.log`.
- El primer recorrido detectó un error en la expectativa de la prueba: el PDF de la NV actualizada también estaba presente. Se corrigió a comprobar tres PDF adicionales respecto al estado anterior a las notas; no se cambió la emisión para satisfacer el contador.

### Aceptación visual en BBC recién registrada (2026-09-22)

- El usuario registró nuevamente `bbc.localhost` y autorizó expresamente usarla para las pruebas; inició sesión en la pestaña del navegador. Esta autorización permite los datos DEMO descritos aquí y sustituye para esta instalación de prueba la limitación anterior de no escribir en BBC. No se ejecutaron reconstrucciones ni migraciones destructivas.
- Verificados desde la UI: carga del editor fiscal, rechazo de inicial cero, Cancelar sin crear secuencia, Guardar y persistencia. Se corrigió el mensaje backend que mostraba `initial number` por «El número inicial debe ser mayor o igual a 1» y se comprobó en pantalla.
- Datos de prueba creados desde la UI: secuencia centralizada de Factura `DEMO`, inicial 1; perfil `Prueba DEMO presencial`, medios digitales/proveedor Simulador DEMO; caja `PRUEBA-DEMO`, saldo inicial cero; servicio `PRUEBA-DEMO-001`, precio 116 VES (base 100, IVA 16); factura `DEMO-1` para el cliente MOCK sembrado, con referencia de pago `PRUEBA-DEMO-SIN-COBRO-REAL`. Estos datos permanecen para revisión; no representan cobro ni emisión fiscal real.
- La factura muestra emisión confirmada DEMO, un intento y control no asignado. PDF A4 abierto en navegador, una página: identificación separada, leyenda «DEMO — SIN VALIDEZ FISCAL», cliente, unidad SERV, importes 100/16/116 y referencia del pago legibles. No se envió correo, WhatsApp ni orden de impresión física.
- Reabierto establecimientos después de emitir: inicial 1, último asignado 1, próximo 2, estado En uso y «Editar inicio» deshabilitado. Esto acredita ese recorrido en la instalación real del navegador, además del kernel de prueba.
- Pendientes de aceptación visual más amplia: forma libre, notas, grupos/canales, recuperación e inventario; el PDF observado cubre una factura DEMO A4. El formulario de producto conserva etiquetas IGV y el resumen del siguiente número en el formulario abierto puede quedar desactualizado hasta refrescar su catálogo; revisar estos puntos durante la aceptación restante. No se declara completado el objetivo global.

### Forma libre y actualización del próximo número en BBC (2026-09-22)

- Prueba visual autorizada en BBC DEMO: lote `90-00000001` a `90-00000002`, imprenta `PRUEBA DEMO - NO ES IMPRENTA REAL`, RIF ficticio `J000000000`, autorización `PRUEBA SIN AUTORIZACION REAL`, fechas 22/09/2026. Son datos de simulación, no referencias de una imprenta autorizada.
- Archivado el perfil digital DEMO conservando la primera factura; creado `Prueba DEMO forma libre`, presencial, misma secuencia DEMO, lote de prueba y capacidad 10. Factura `DEMO-2`, servicio de prueba 116 VES y referencia `PRUEBA-FORMA-LIBRE-SIN-COBRO`: recibió el primer control del lote y quedó pendiente de impresión.
- PDF A4 revisado en navegador antes y después de confirmar: una página, número documental 2 y control `90-00000001`, datos del lote, leyendas DEMO y los mismos importes. Se simuló la confirmación en DEMO; no se imprimió papel ni se declaró una prueba física de preimpresión. Reabrir el PDF conserva los identificadores y refleja emisión confirmada.
- Consulta backend de sólo lectura después de reabrir: dos documentos, dos pagos (232 VES de prueba), dos reservas confirmadas, siguiente documento 3, siguiente control `90-00000002` y una auditoría de confirmación. La reimpresión no creó otra operación comercial.
- Hallazgo corregido: `invoice_generate.vue` conservaba la estimación del número anterior después de guardar. `DocumentController` devuelve `profile_id` y `next_number` consultado de la secuencia persistida; el formulario actualiza sólo ese perfil. `updateFiscalProfileEstimate` no incrementa localmente en reintentos ni retrocede ante una respuesta tardía. Tres pruebas nuevas; regresión JavaScript fiscal: **64 satisfactorias**. Compilación y comprobación HTTP/visual del ajuste en curso.
- Ajuste validado: HTTP **328 aserciones satisfactorias, 2m 24s** (`/tmp/pro9-fiscal-estimate-http.log`); compilación **1m 43s**, manifiesto 32 entradas sin archivos faltantes (`/tmp/pro9-fiscal-estimate-build.log`). Tras recargar el bundle y emitir `DEMO-3`, la pantalla pasó de próximo estimado 3 a 4 sin recargar; control asignado `90-00000002`.
- Inutilización visual de `DEMO-3` con motivo explícito «PRUEBA DEMO: simulación de daño del formato, sin impresión real». El estado cambió a Control inutilizado conservando número y control; no vuelve a ofrecer confirmación de impresión.
- Dos intentos posteriores con lote agotado muestran «Configure un lote de controles disponible antes de emitir» y liberan el botón de guardado conservando el formulario. Consulta backend de sólo lectura: **3 documentos, 3 pagos, 3 reservas**, ningún pago con referencia `PRUEBA-RECHAZO-LOTE-AGOTADO`, próxima numeración 4 y ordinal del lote 9000000003 por encima del final 9000000002. El control inutilizado no se recicló.
- Al finalizar estas pruebas quedó activo nuevamente `Prueba DEMO presencial` (simulador digital) y archivado `Prueba DEMO forma libre`. El lote agotado y los tres documentos permanecen para trazabilidad. La caja de prueba sigue abierta; sus cobros son únicamente datos DEMO. No se envió correo, WhatsApp ni impresión física.
- Esta etapa acredita el ciclo visual descrito y corrige la estimación en el formulario principal. Siguen pendientes los demás canales, reemplazo tras inutilización, recuperación duradera, división de forma libre y la auditoría completa del alcance; no supone validez fiscal de una integración real.

### Recuperación de reservas fiscales comprometidas (2026-09-22)

- Añadido `FiscalEmissionService::recoverPending` y comando `fiscal:recover`, ejecutado por el planificador mediante `tenancy:run` cada minuto sin superposición. Requiere tenant explícito y transacción comercial confirmada; no invoca escritores de documentos, pagos o inventario.
- El barrido captura el ID máximo inicial y recorre bloques de 100 reservas enlazadas en estado reservado, procesando o incierto. Reutiliza la reclamación transaccional existente; consulta antes de reenviar intentos caducados/inciertos y difiere un reenvío autorizado por ausencia al siguiente barrido. Las reservas dañadas se contabilizan y no impiden avanzar. El resultado sólo contiene tenant, estados, conteos e IDs; no incluye excepciones ni respuestas privadas del proveedor.
- Forma libre pasa a pendiente de impresión sin confirmación automática. Estados terminales y reservas sin documento comercial quedan excluidos. La recuperación de PDF, entrega por correo e impresión sigue pendiente; este mecanismo cubre exclusivamente la emisión de reservas ya persistidas.
- Verificación: **15 pruebas, 67 aserciones** del servicio/comando, incluida interrupción con respuesta perdida, intento activo/caducado, rechazo sin tenant, rechazo dentro de transacción, dos barridos idempotentes y 102 entradas con la primera dañada. Concurrencia MySQL: **8 pruebas, 99 aserciones**, satisfactorias. Invocar el comando sin tenant en la aplicación real devuelve rechazo y salida 1. Regresión HTTP: **328 aserciones satisfactorias, 1m 39s**, registro `/tmp/pro9-fiscal-recovery-http.log`.
- No se ejecutó recuperación contra todas las empresas reales ni se generaron nuevos documentos BBC en esta etapa. La ejecución periódica requiere que el despliegue mantenga activo su planificador de Laravel.

### Alcance productivo y reemplazo de forma libre (2026-09-22)

- Decisión del usuario: por ahora la puesta en producción usa únicamente **forma libre preimpresa**. Los modos digital y máquina fiscal quedan fuera del alcance productivo; sus simuladores DEMO no acreditan integración real.
- Incorporado `FiscalPrintReplacementService`: después de inutilizar un formato, un administrador de la misma sucursal puede asignar otro número documental y otro control con un perfil de forma libre compatible. Se conserva una sola venta, sus pagos y el movimiento de inventario; el control dañado permanece inutilizado, con motivo y auditoría, y cada repetición consume un control nuevo.
- `FiscalReservation::effective` recorre la cadena completa de contingencias y reemplazos. PDF, listados y referencias usan el último formato vigente, mientras las reservas anteriores preservan la trazabilidad. El PDF nuevo indica qué control inutilizado reemplaza.
- Añadidos endpoints web/API para facturas, notas y órdenes de entrega, más el botón **Asignar nuevo control**. Si no existe un perfil activo con lote disponible, la pantalla exige configurarlo. La reserva se valida contra el PDF de una sola página antes de confirmar y se revierte completa si el render falla.
- La regla se apoya en los artículos 33, 36 y 44 de la Providencia 0071: documentos distintos cuando una operación no cabe en una página, conservación de originales y copias anulados, y controles consecutivos y únicos. El reemplazo no reutiliza el control ni elimina el registro inutilizado.
- La configuración general y el editor por establecimiento presentan **Forma libre** como única modalidad operativa de esta etapa. Impresora fiscal e imprenta digital aparecen como «en desarrollo» y no se incluyen en la aceptación productiva. El canal digital sigue identificando un origen automatizado y puede seleccionar un perfil de forma libre; no obliga a usar imprenta digital.
- Aceptación visual autorizada en BBC DEMO: se registró el lote ficticio `90-00000003` a `90-00000005`, se archivó el perfil digital anterior y se activó `Prueba DEMO reemplazo forma libre`. Desde el diálogo del comprobante inutilizado se reservó y confirmó el reemplazo: identidad efectiva `DEMO-4`, control `90-00000003`, con aviso de conservar inutilizado `90-00000002`.
- Comprobación persistida después del reemplazo: **3 documentos, 3 pagos y 4 reservas**; la reserva original número 3/control `90-00000002` permanece `inutilized`, la hija número 4/control `90-00000003` quedó `issued`, la secuencia avanzó a 5, el lote a `90-00000004` y existe una auditoría `replace_print`. No se creó otra venta, pago ni movimiento comercial. La confirmación fue una simulación DEMO; no hubo papel, cobro, correo ni WhatsApp reales.
- Verificación automatizada de esta etapa: recorrido HTTP temporal **347 aserciones**, incluyendo reserva, autorización, idempotencia, reemplazo, identidad efectiva y confirmación; concurrencia MySQL **8 pruebas y 99 aserciones**; regresión PHP completa **738 pruebas, 12.916 aserciones y 12 pruebas MySQL optativas omitidas**; interfaz JavaScript **66 pruebas**. Build final satisfactorio, manifiesto con **32 entradas y ningún archivo faltante**. `git diff --check` y el validador oficial de skills resultaron satisfactorios.
- El encabezado de BBC fue alineado con la configuración activa: «Modalidad: Forma libre · Impresión manual por establecimiento». La vista móvil deja de mostrar la referencia heredada a SUNAT y presenta el ambiente fiscal y la modalidad configurada.
- La aceptación visual posterior reveló que el diálogo y el PDF ya usaban el reemplazo, pero el listado seguía mostrando la reserva inutilizada. Se corrigió `FiscalIdentity` para precargar y recorrer toda la cadena de contingencias/reemplazos, y los filtros ahora resuelven la hoja efectiva hasta su reserva comercial raíz. En BBC el listado muestra `DEMO-4 / 90-00000003`; buscar el control nuevo devuelve el documento 3 y buscar el control inutilizado `90-00000002` no lo devuelve como identidad vigente.
- El PDF A4 de ese documento fue regenerado y revisado: una sola página, estado confirmado, número 4, control `90-00000003`, rango e imprenta de prueba y leyenda «Reemplaza el control inutilizado 90-00000002». Una prueba unitaria adicional cubre una cadena de dos sustituciones y verifica que listado y filtros ignoren el control intermedio inutilizado.

### Cierre de aceptación automatizada de forma libre (2026-09-22)

- La aceptación productiva queda expresamente limitada a **forma libre preimpresa**. Impresora fiscal e imprenta digital permanecen en desarrollo; sus simuladores no se usan como evidencia de aceptación de esta entrega.
- `FiscalCommercialServiceTest::test_preprinted_free_form_registers_and_confirms_every_document_type_in_scope` crea perfiles de forma libre para Factura 01, Nota de crédito 07, Nota de débito 08 y Orden de entrega 09. Los cuatro consumen controles consecutivos de un único lote, pasan por pendiente de impresión y confirmación, conservan cuatro operaciones comerciales y generan sus auditorías de impresión.
- Regresión PHP final después de añadir esa aceptación: **739 pruebas, 12.933 aserciones y 12 pruebas MySQL optativas omitidas**, satisfactoria. No se modificaron fuentes frontend en este cierre; se conserva el build ya validado de 32 entradas sin archivos faltantes.
- La rama actual contiene el commit `c3ba25ef6` sobre `a5d5b1b59`, ambos descendientes directos de `develop` en `ea264971d`. Esto confirma que las modificaciones recuperadas no se perdieron y quedaron consolidadas en `codex/numeracion-fiscal-venezuela`.
- SCRUM-39 fue consultada después de recuperar el acceso. Describe una migración histórica y limitada de etiquetas visibles «Serie» a «N° de Control». Por instrucción expresa posterior del usuario, sus criterios de aceptación e instrucciones no forman parte de esta entrega; no se aplicaron a los 17 flujos históricos. El contrato vigente es la adecuación fiscal venezolana solicitada directamente, con forma libre preimpresa como única modalidad operativa.
