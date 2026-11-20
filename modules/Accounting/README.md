# Accounting para SAB

SAB significa Sistema Administrativo Benahia y es la denominación vigente del sistema anfitrión.
La integración usa rutas `/sab`, las clases `SabController` y `SabSourceReader`
y códigos `SAB_*`. Los identificadores de origen y las huellas históricas
conservan sus valores almacenados.

Este módulo contiene la API y el dominio contable de Contabilidad Benahia. Se
ejecuta dentro de SAB: Laravel 9, PHP 8.2, PostgreSQL 17 y conexión Hyn `tenant`.
No incorpora otro servidor Laravel, otra tabla de usuarios ni otro login.

La PWA TypeScript se desarrolla y compila en AWS, en
`/home/ubuntu/contabilidad/application/web`. Su compilado se instala en
`public/contabilidad`, con historial de navegación servido por este módulo y
service worker limitado a `/contabilidad/`. Los archivos compilados no se
versionan en este repositorio.

## Acceso y datos

El acceso usa el `POST /api/login` existente y su Bearer con `auth:api`. Las
rutas del módulo están en `/api/accounting/v1`. Los permisos pertenecen a cada
libro; las tablas `accounting_*` incluyen organización, empresa y relaciones
compuestas. El segundo factor autoriza operaciones críticas por un plazo
limitado y no sustituye la identidad de SAB.

Los roles son explícitos por empresa. Administrar usuarios y configuración no
concede aprobación ni contabilización. Para asumir también esas funciones hay
que asignar el rol de contador. Los administradores y contadores verifican el
segundo factor antes de consultar datos; las operaciones críticas exigen una
verificación reciente. La revisión separada considera a quien preparó la última
versión, aunque otra persona haya creado el primer borrador.

`SabSourceReader` consulta los documentos del mismo tenant. Reutiliza los
valores exactos de SAB, conserva versiones de origen y detecta recepciones
repetidas. Recibir un documento no lo contabiliza. Las empresas ficticias no
se vinculan a datos reales por defecto.

El único enlace en el manejador global de excepciones delega las respuestas de
este prefijo al módulo. Las respuestas de las demás rutas conservan su manejo
existente. Las respuestas contables no se almacenan en caché ni muestran SQL.

## Instalación en un tenant

Con la rama del módulo y las dependencias actuales de SAB disponibles:

```sh
php artisan accounting:install --hostname=DOMINIO_SAB --owner-user=ID_ADMIN
```

El comando exige un tenant existente y un administrador activo de SAB; ejecuta
solo las migraciones de este módulo sobre la conexión `tenant` indicada. No
registra sus migraciones como migraciones globales del sistema. `--demo` exige
un entorno local o de pruebas y `ACCOUNTING_DEMO_MODE=true`.
La opción `--accountant` asigna expresamente la responsabilidad de contador al
propietario. Sin esa opción el propietario conserva las funciones administrativas.

## Documentos y configuración fiscal

El módulo permite registrar borradores manuales, recibir versiones de documentos
de SAB y validarlos con perfiles y reglas aprobadas por fecha. Conserva la
identidad del tercero, los importes originales, la tasa histórica y sus soportes
en una copia de la validación. No instala alícuotas tributarias predeterminadas.

Las facturas validadas generan propuestas con cuentas elegidas por el contador
y una explicación por línea. Al enviarlas a revisión se comprueba que la partida
por cobrar o pagar coincida con el total, tercero, moneda y tasa validados.
Revalidar retira las aprobaciones pendientes; regenerar una propuesta exige
motivo y conserva la evidencia anterior en auditoría. La contabilización vuelve
a comprobar el estado y la versión del documento de SAB.

Los registros fiscales disponibles son consultas preliminares separadas por
perfil e incluyen los documentos validados del intervalo. Los formatos fiscales
definitivos, aplicaciones de notas y retenciones aún deben completar
su implementación y conciliaciones; recibir o validar no equivale a contabilizar.

## Apertura y auxiliares

La apertura concilia los saldos por cuenta con el detalle de clientes y
proveedores. Conserva el importe original, su valor en VES y el antecedente
documental. Prepararla no mueve saldos: se somete al mismo circuito de revisión,
aprobación y contabilización de los comprobantes. Una factura anterior al corte
puede identificar una deuda inicial; no vuelve a reconocer su ingreso o gasto.
Los saldos iniciales de resultados se presentan por separado del resultado del
intervalo consultado.

Los cobros y pagos parciales distribuyen el valor contable pendiente y separan
la diferencia de cambio. La última aplicación toma el remanente contable exacto.
Cada propuesta conserva la versión del saldo que revisó: si otra operación lo
modifica, debe regenerarse y aprobarse de nuevo. El reverso íntegro restaura los
saldos aplicados y conserva su historia. Las cuentas de clientes y proveedores
requieren estos flujos o una factura; un asiento manual sin detalle no puede
descuadrar el auxiliar.

Esta primera liquidación exige soporte bancario, tasas y revisión tributaria
documentada. Todavía no integra retenciones, IGTF, notas, anticipos ni devoluciones
en sus aplicaciones. Esos casos siguen pendientes del flujo específico; no se
compensan automáticamente contra el banco ni se presume una exención.

## Cierre mensual y reapertura

`GET companies/{company}/periods?year=AAAA` lista los períodos. La revisión en
`periods/{id}/review` reconstruye el balance y los auxiliares a la fecha de
corte, considerando las fechas contables de aplicaciones y reversos. Los
controles comparan importes DECIMAL exactos del libro, sin reutilizar los
totales redondeados de presentación. Una diferencia menor que un céntimo
también bloquea el cierre.

No se cierra un mes futuro, un mes con períodos anteriores abiertos desde el
inicio del libro, diferencias de balance o auxiliares, incidencias de origen
abiertas ni comprobantes aprobados pendientes de contabilizar. Los restantes
borradores, documentos pendientes, observaciones fiscales y comprobantes
manuales requieren explicar su tratamiento. Se documentan además la
conciliación de origen, los papeles de trabajo de cajas y bancos y la revisión
fiscal. Esta constancia del contador no certifica por sí sola la integridad de
las integraciones o formatos fiscales aún pendientes.

`POST periods/{id}/close` exige permiso `close`, segundo factor reciente,
`If-Match`, `Idempotency-Key`, `review_token`, motivo, `attestations` y
`exceptions`. La transacción vuelve a calcular la revisión y rechaza un token
anterior a cambios de los datos. El cierre y los flujos del comprobante
comparten el bloqueo de la empresa; la contabilización bloquea además el
período y vuelve a comprobar que está abierto.

Cada cierre conserva en `accounting_period_reviews` el balance de
comprobación, la situación financiera, los resultados, los controles exactos,
las explicaciones y su hash canónico SHA-256. MySQL impide modificar o eliminar
estas revisiones. `periods/{id}/history` lista la historia;
`periods/{id}/history/{review}` devuelve la revisión y su hash para consulta y
descarga JSON. Los informes nuevos identifican las versiones de los períodos
que contribuyen a sus saldos.

`GET periods/{id}/reopening` presenta el alcance antes de confirmar.
`POST periods/{id}/reopen` exige permiso `reopen`, segundo factor, versión,
token, motivo e identidad de operación. Reabre también los cierres mensuales
posteriores, incrementa sus versiones y conserva cada cierre anterior. La PWA
muestra ese alcance y exige su revisión expresa. Los recibos `period.close` y
`period.reopen` permiten resolver una respuesta perdida; estas operaciones no
se incorporan a los envíos locales importables ni se ejecutan sin servidor.

Los períodos de un ejercicio con cierre anual se reabren mediante su conjunto
anual, con reversos revisados; el flujo mensual no lo omite.

## Cierre anual y traslado de saldos

`GET years/{year}/closing` revisa el ejercicio completo. Comprueba los meses
anteriores, las cuentas, terceros, centros de costo, auxiliares al corte,
documentos e incidencias. Diciembre debe permanecer abierto y enero del
siguiente ejercicio debe poder recibir su apertura. Si hay ejercicios
anteriores con movimientos, su cierre anual se completa primero.

El contador elige la cuenta de patrimonio para incorporar el resultado y
documenta las conciliaciones y excepciones. `POST years/{year}/closing/prepare`
exige versión e identidad de operación, congela la base y prepara comprobantes
de resultado, cierre de saldos y apertura. Conserva cuenta, tercero y centro de
costo; omite las fases sin movimiento y no crea comprobantes de importe cero.
La apertura resume el libro en VES y conserva las partidas originales con su
moneda, tasas e historia. No crea nuevas cuentas por cobrar o pagar. Una
distribución o reclasificación posterior del resultado requiere su propio
ajuste preparado por el contador.

Cada comprobante recorre la revisión y aprobación habituales, con separación
de personas o autocontrol expreso según la política de la empresa. Sus líneas
no se editan individualmente y la API rechaza su contabilización aislada.
`POST year-closings/{id}/post` vuelve a comprobar la base, las versiones y las
aprobaciones. Contabiliza todas las fases dentro de la misma transacción,
comprueba saldos contables nulos al final del ejercicio y cierra diciembre y el
ejercicio. Si falla la apertura, se revierten también las fases anteriores,
los correlativos, las confirmaciones y el cambio de estado del ejercicio.

El Diario, el Mayor y el balance de comprobación incluyen los traslados
controlados. La situación financiera excluye los movimientos de cierre de
saldos y apertura que duplicarían su presentación económica, y lo identifica
en su nota de base. El resultado operativo excluye cierres, aperturas y sus
reversos. La revisión de diciembre conserva los tres informes anteriores al
traslado anual para consultar la posición del ejercicio cerrado.

Un ajuste o cambio de controles posterior a preparar el conjunto invalida su
base. `POST year-closings/{id}/cancel` retira la propuesta con motivo, conserva
sus comprobantes como `withdrawn` y permite preparar otra. No elimina los
antecedentes ni altera comprobantes contabilizados. MySQL protege la base,
propuesta, identidades y snapshots de `accounting_year_closings`.

`POST year-closings/{id}/reopening` prepara reversos de todas las fases y
presenta los períodos mensuales afectados. Si existe otro ejercicio posterior
cerrado, exige reabrirlo primero. Los reversos pueden revisarse y aprobarse con
el ejercicio cerrado; no pueden contabilizarse fuera del conjunto.
`POST year-closings/{id}/reopen` comprueba las versiones y aprobaciones y, en
una sola transacción, abre el ejercicio y sus períodos dependientes y
contabiliza los reversos. Conserva los originales, las revisiones y sus
huellas; no duplica deudas ni la actividad del ejercicio siguiente.

Todas las acciones anuales requieren servidor, permiso y segundo factor
reciente. Contabilizar exige además `post` y reabrir exige `reverse`. Las
confirmaciones propias `year.*` permiten consultar una respuesta perdida;
no forman parte de la cola local importable. `GET year-closings/{id}` consulta
el conjunto, sus versiones, comprobantes y snapshots conservados.

## Trabajo local

La preparación autoriza un dispositivo por empresa y usuario durante un máximo
de 72 horas. Incluye un intervalo configurable de hasta 90 días por defecto,
con comprobantes, documentos, catálogos, perfiles, reglas, tasas e informes
preparados para fechas explícitas. También incorpora el auxiliar actual, incluidas
deudas anteriores al intervalo, con su fecha de preparación e historia de
aplicaciones. El paquete admite hasta 2.000 partidas y 10.000 aplicaciones;
si excede estos límites se rechaza completo, sin presentar un saldo truncado.
Las consultas fuera del alcance requieren
SAB y no sustituyen el intervalo solicitado por otro informe en caché. El navegador cifra la copia y los borradores con
AES-GCM; deriva la clave de una frase local mediante PBKDF2. El Bearer y la
verificación adicional permanecen en memoria. La sincronización vuelve a
comprobar permisos, autorización del dispositivo y versión del borrador.

Sin conexión se permiten consultas preparadas y borradores. La aprobación,
contabilización, reverso y cierre requieren SAB. Un conflicto conserva el
borrador local. La copia preparada tiene límites explícitos de fechas,
comprobantes y tamaño; no representa una réplica completa del servidor.

Las confirmaciones de los envíos se consultan en
`GET companies/{company}/operations/{action}/{key}`. Solo el usuario de esa
empresa que realizó el envío puede recuperarlas. Cancelar con
`POST .../cancel` exige un motivo y conserva una decisión que impide ejecutar
una solicitud tardía con esa identidad. Si SAB ya la confirmó, devuelve su
resultado; no elimina ni revierte la operación recibida.

Los comprobantes y documentos manuales tienen rutas `.../{id}/comparison` y
`.../{id}/resolve`. La decisión exige la versión de SAB revisada en `If-Match`,
una identidad idempotente y un motivo. Conservar SAB archiva la alternativa
local en auditoría sin modificar el registro. Aplicar la alternativa local usa
las validaciones y permisos habituales, crea una versión para revisión y retira
su aprobación. Un cambio posterior a la comparación vuelve a impedir el guardado.
La auditoría conserva la alternativa informada por el cliente y las versiones
autorizadas anteriores y posteriores de SAB. Los asientos definitivos y las
propuestas de apertura y liquidación conservan sus flujos específicos.

Los borradores contables recuperados con varias alternativas se comparan también
antes de tener un registro en SAB. Elegir exige un motivo y prepara otra copia
del borrador con la misma identidad lógica. Las versiones anteriores quedan
como antecedentes de consulta; reutilizarlas prepara otra elección y conserva
la cadena anterior. Una elección local no mueve saldos ni envía operaciones.
Un envío pendiente debe confirmarse o cancelarse antes de elegir otra versión.
El guardado del nuevo borrador, los antecedentes y la decisión es una sola
transacción de IndexedDB: ante falta de espacio o una edición concurrente no se
sustituyen parcialmente las alternativas.

Al sincronizar, el módulo conserva las decisiones informadas por el cliente en
la auditoría de la misma empresa y usuario, con confirmaciones que no permiten
reemplazar su contenido ni aplicarlas a otra entidad. Los importes de los
antecedentes son evidencia informada; el servidor valida y calcula el borrador
que realmente guarda. Si SAB ya conoce esa identidad, se exige comparar con su
versión actual. Elegir no aprueba ni contabiliza. Cada decisión admite hasta
20 alternativas y cada envío hasta 100 decisiones y 2 MB de historial; el límite
se informa sin truncar ni borrar datos. La selección auditada de capturas
incompletas de documentos manuales sigue pendiente de su interfaz específica.

Los editores de comprobantes y documentos manuales conservan automáticamente
capturas cifradas, incluidos campos incompletos, cuando está desbloqueada la
copia del mismo usuario y empresa. Estas capturas todavía no son documentos
validados ni comprobantes guardados en SAB. Al recuperarlas se conserva la
versión de partida y la identidad del intento de creación. Si otra pestaña
cambia la captura, ambas alternativas permanecen disponibles; el agotamiento de
espacio se informa sin anunciar un guardado ni borrar la versión anterior.
Un documento preparado sin conexión se completa y guarda explícitamente en
SAB después de reconectar.

La interfaz se bloquea tras 15 minutos sin actividad y también admite bloqueo
manual. Coordina la actividad y el bloqueo entre pestañas del mismo usuario.
Oculta las vistas y los diálogos, intenta conservar la captura pendiente y
retira la referencia activa a la clave local. Los campos que aún no pudieron
guardarse permanecen en memoria, ocultos y sin interacción; se advierte de ello
antes de cerrar. El respaldo cifrado sigue disponible desde la pantalla de
bloqueo.

La copia se reabre con su frase. Una sesión conectada vuelve a consultar su
autorización; cuando necesita entrar de nuevo usa la cuenta original de SAB y
su segundo factor. Si existía una copia cifrada, también exige su frase y renueva
el mismo intervalo antes de reanudarla. Una contraseña válida de otro usuario no
abre el trabajo anterior. Las respuestas de operaciones ya enviadas permanecen
pendientes de mostrar hasta desbloquear; el bloqueo no repite su envío.

La recuperación de un respaldo local exige sesión activa en SAB y coincidencia
de empresa y usuario. Renueva la copia de consulta con datos y permisos del
servidor, verifica los datos cifrados y conserva las versiones diferentes de
los borradores existentes. El respaldo versión 4 incluye capturas incompletas,
envíos, elecciones y antecedentes cifrados, y admite las versiones 1, 2 y 3.
Reimportar la misma alternativa no
la duplica ni elimina las capturas posteriores. Este
respaldo conserva cada identidad física del resultado elegido y de sus
antecedentes, aunque el contenido coincida o cambie el orden del archivo. El
respaldo corresponde al trabajo del navegador; la recuperación integral de base
de datos y adjuntos del servidor sigue pendiente.

Las operaciones de cifrado y las respuestas de autorización pendientes conservan
la identidad de la sesión que las inició. Un cierre o cambio de sesión impide
que una preparación, recuperación o desbloqueo anterior publique su clave local
en la sesión siguiente.

## Verificación aislada

`phpunit.xml` exige una base MySQL y un host de pruebas específicos antes de
crear o limpiar tablas. No ejecutar esta suite contra una instalación activa.
`http_smoke.py` usa las credenciales ficticias del directorio privado
`.accounting-isolated` y comprueba el contenedor y la marca del servidor de
pruebas antes de escribir.
`documents_http_smoke.py` verifica el flujo de factura con el enrutamiento y
autenticación reales de SAB. `documents_browser_smoke.cjs` lo recorre desde la
PWA compilada en AWS, incluyendo configuración fiscal y adaptación a móvil.
`restore_browser_smoke.cjs` verifica exportación cifrada, conservación de
alternativas, rechazo de propietario distinto e integridad, y recuperación en
un navegador limpio seguida de un reinicio sin conexión.
`auxiliary_http_smoke.py` y `auxiliary_browser_smoke.cjs` verifican apertura,
antecedentes y cobro parcial con diferencia de cambio y revisión independiente.
`auxiliary_offline_browser_smoke.cjs` comprueba lectura cifrada del auxiliar,
identificación inmediata de una respuesta local, recarga sin servidor y límites
documentales del paquete.
`editor_recovery_browser_smoke.cjs` comprueba recuperación tras cerrar el
navegador, separación por empresa, documentos incompletos, cuota agotada,
edición concurrente, compatibilidad de respaldos y conservación de la versión
original ante cambios de otro usuario. También retiene la captura hasta que
se confirma el guardado, bloquea cambios de empresa durante ese envío y
comprueba que el editor móvil desplace solo su tabla de líneas.
`session_lock_browser_smoke.cjs` verifica el plazo de inactividad, bloqueo
manual y entre pestañas, campos conservados ante falta de espacio, diálogos
ocultos, reapertura de la copia y una respuesta de SAB confirmada durante el
bloqueo. `users_browser_smoke.cjs` verifica creación de empresa y asignación
explícita del rol contable desde la PWA.
`conflicts_http_smoke.py` comprueba confirmaciones y cancelaciones propias,
identidades lógicas, permisos de resolución y comparación versionada en SAB.
`conflicts_browser_smoke.cjs` recorre la decisión con ambas alternativas,
respuestas perdidas, conservación de cambios posteriores y recuperación de los
envíos cifrados desde otro navegador sin repetir operaciones.
`local_selections_http_smoke.py` comprueba la recepción del historial, su
identidad inmutable y la reversión transaccional de una elección alterada.
`local_selections_browser_smoke.cjs` verifica elección sin servidor, consulta de
antecedentes, restauración, reutilización, solicitudes inciertas, dos pestañas,
agotamiento de espacio y migración de IndexedDB desde la versión anterior.
Las suites que consumen las identidades ficticias serializan su acceso mediante
el archivo de bloqueo del directorio privado.
`closings_http_smoke.py` comprueba permisos, MFA, revisión, idempotencia,
concurrencia y reapertura de meses posteriores. `closings_browser_smoke.cjs`
recorre los controles y la recuperación de una respuesta perdida desde el
compilado de AWS; contrasta el informe conservado con los movimientos de una
versión posterior y verifica las restricciones sin conexión y el diseño móvil.
`annual_http_smoke.py` comprueba preparación, aprobación separada,
contabilización y reapertura anual, recibos, permisos retirados y ámbito de
empresa. `annual_browser_smoke.cjs` usa dos usuarios desde la PWA compilada y
verifica confirmación incierta, controles individuales, saldos del siguiente
ejercicio, revisiones descargables, reapertura y uso móvil. La fixture anual
solo puede ejecutarse bajo los guardas del clon privado; no añade endpoints.

El worktree autorizado es `codex/contabilidad-api-v1`, basado en
`861228462e208d2060c9148b87fd7d7d3f354da5`. Las bases reales se copian para
probar en esta PC y no se transfieren a AWS. El directorio privado, los volcados,
las credenciales y los compilados están excluidos de Git.

Las importaciones RF14 usan `POST /api/accounting/v1/companies/{company}/imports/preview`
con un archivo multipart real y `kind=third-parties|entries|opening|documents`.
Las rutas de plantillas, historial, filas, revisión XLSX, revalidación y confirmación
pertenecen al mismo módulo y a la conexión tenant de SAB. No hay otra API.
La confirmación exige `If-Match`, `preview_hash`, selección de unidades completas
e `Idempotency-Key`; su recibo se consulta como `operations/import.apply/{key}`.
La revalidación usa `import.revalidate`. Estas acciones requieren servidor y no
se incorporan a la cola sin conexión.

Cada fila identifica el RIF de la empresa y una `referencia_importacion` estable.
La huella de archivo y sus opciones recuperan una validación existente. La
referencia y el contenido de cada unidad aceptada se conservan por empresa;
reintentar o corregir otro comprobante no duplica ni sobrescribe el original.
La vista previa ensaya los mismos servicios dentro de puntos de restauración y
revierte sus registros y auditoría financiera. Al confirmar se revalidan permisos
y catálogos bajo bloqueo del libro: un error funcional rechaza la unidad completa;
un fallo de infraestructura revierte el lote seleccionado entero. Los registros
contables y documentos se crean como borradores, sin aprobación ni contabilización.
Las notas requieren una factura original ya registrada: primero se importa la
factura y luego se revalida la nota. No se sustituye una apertura existente.

El lector admite XLSX o CSV UTF-8 de hasta 2 MiB, 2.000 filas y 200 unidades.
Lee los decimales numéricos desde OOXML mediante XMLReader/ZipArchive y
Brick Math, sin el lector de PhpSpreadsheet ni conversiones a float. Bloquea
fórmulas, DTD, entidades, macros, objetos y relaciones externas; no extrae ZIP ni
acepta rutas o URLs del cliente. Las fechas numéricas respetan el sistema 1900 o
1904 y rechazan el día ficticio 60 de 1900. Los identificadores se exigen como
texto. Las plantillas y las revisiones utilizan el escritor instalado de
PhpSpreadsheet con celdas de texto explícitas y sin evaluación de fórmulas.
Referencias técnicas: [sistemas de fechas de Excel](https://support.microsoft.com/en-us/excel/date-systems-in-excel)
y [texto explícito en PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/en/1.30.5/topics/recipes/).
La copia normalizada con su huella queda en el almacenamiento privado tenant;
no se publica su ruta ni se sirve mediante enlaces públicos.

`ImportFileReaderTest.php` cubre precisión original, fechas, texto identificador,
CSV, contenido externo y fórmulas. Los casos de importación de la suite del
módulo verifican atomicidad, auxiliares, datos cambiados, recibos, permisos,
cuatro plantillas y revisión reimportable. `imports_http_smoke.py` usa multipart
real y roles retirados; `imports_browser_smoke.cjs` recorre el compilado de AWS,
confirmación incierta, corrección, historial, aislamiento, descargas y móvil.

Los informes RF22 y sus descargas pertenecen al mismo módulo. La consulta
`GET reports/{kind}` admite Diario, Mayor, balance de comprobación, auxiliar
por cuenta/tercero, partidas al corte, controles de documentos e incidencias,
situación financiera y resultados preliminares. El Mayor exige una cuenta;
el auxiliar exige cuenta o tercero. Las búsquedas `report-lookups` usan el
permiso de informes, delimitan la empresa y permiten consultar catálogos
históricos inactivos. Los filtros admitidos afectan a filas y controles; los
incompatibles se rechazan. Cada página utiliza las mismas consultas que la
exportación completa, sin limitar el archivo a sus primeras 100 o 500 filas.

Las partidas se reconstruyen desde su origen y aplicaciones con sus reversos
según la fecha contable del corte; no se sustituyen por el saldo restante de
hoy. Incluyen deudas anteriores al intervalo. Los saldos iniciales del auxiliar
incluyen los grupos sin movimientos actuales. Los estados preliminares permiten
un intervalo comparativo, identifican clasificación y presentación y distinguen
resultados del período de saldos acumulados. Los libros incluyen solo lo
contabilizado. El control de documentos identifica las versiones del origen
que tienen asiento; incluir documentos pendientes es una elección explícita y
rotulada. Este control no constituye un libro fiscal. El resumen de incidencias
exporta campos seleccionados, sin su payload privado.

`POST report-exports` requiere tipo, filtros, el `review_token` de la consulta
e `Idempotency-Key`. Bajo bloqueo del libro se revisan nuevamente acceso y
versión, y se conserva la lectura completa en JSONL privado con SHA-256,
controles, número de filas, clasificación, versiones de períodos y creador.
La lectura es repetible, incluso en reintentos por interbloqueo, sin cambiar el
aislamiento predeterminado de la sesión de SAB. Una transacción ajena al módulo
debe iniciar la lectura consistente antes de consultar informes.
La captura no contabiliza ni genera efectos financieros. `report.capture`
permite recuperar su recibo; requiere servidor y no entra en la cola offline.

`GET report-exports`, `/{id}` y `/{id}/rows` consultan el historial congelado.
`/{id}/download/pdf|xlsx|csv` genera desde esos datos, conserva cada archivo y
su huella y audita la descarga. Los registros listos y sus artefactos son
inmutables en MySQL. Un archivo ausente o alterado se rechaza; no se reemplaza
silenciosamente. Cada descarga exige acceso vigente, usa cabeceras privadas y
carece de URL pública. El frontend verifica tamaño, tipo y SHA-256 antes de
guardarlo. Editar el libro, catálogos o empresa después no reemplaza una copia
ya conservada. Los eventos de consulta/descarga no invalidan la revisión
financiera de cierres.

## Soportes privados y auditoría (RF24, entrega incremental)

Todo este flujo pertenece a `Modules\Accounting` y usa el usuario y la conexión
tenant de SAB. No requiere una API contable independiente.

- `GET evidence/journal_entry|source_document/{id}` consulta el caso, sus
  archivos, versiones anteriores, registros relacionados y manifiesto de la
  revisión. `POST .../attachments` requiere permiso `attachments`, multipart
  `file`, descripción `title`, motivo `reason`, `If-Match` e `Idempotency-Key`.
  `supersedes_id` añade una versión y conserva íntegra la anterior.
- `GET attachments/{id}/download|preview` exige acceso vigente a la empresa.
  El original se descarga como adjunto; la vista solo admite una imagen PNG
  recodificada. Se verifica SHA-256 en servidor y cliente y no hay URL pública.
- V1 acepta PDF, PNG/JPG, XLSX, CSV, TXT y XML de hasta 5 MiB. El tipo real,
  estructura y límites se comprueban; XML no resuelve entidades y XLSX rechaza
  macros, objetos incrustados y vínculos de datos externos. No se ejecutan
  fórmulas del soporte ni se presenta un PDF o XML arbitrario dentro del editor.
- `ACCOUNTING_ATTACHMENT_SCANNER` configura un ClamD privado por INSTREAM.
  El analizador debe confirmar el archivo y tener firmas de hasta 168 horas.
  Sin análisis, con firmas vencidas o ante una respuesta incierta se rechaza
  la carga; no se simula una verificación satisfactoria. El proceso de prueba
  `Tests/prepare_attachment_scanner.py` solo configura la red aislada del clon.
  [Operación oficial de ClamAV](https://docs.clamav.net/manual/Usage/Scanning.html).
- Los originales se guardan fuera de la web y la base, con nombres generados,
  permisos privados y ruta relativa bajo un identificador del tenant físico,
  organización y empresa. Los metadatos y versiones tienen protección de
  UPDATE/DELETE. No existe un borrado automático de evidencia confirmada.
- Un soporte nuevo retira la aprobación de borradores dependientes y la
  validación documental pendiente. No inventa una nueva revisión del origen ni
  cambia importes. La aprobación conserva un manifiesto; la contabilización
  comprueba nuevamente los archivos y sus huellas. Un asiento definitivo
  conserva todos sus campos al recibir anexos posteriores.
- `attachment.add` dispone de consulta/cancelación de recibos en `operations`.
  Es una acción con servidor; nunca entra en la cola de operaciones offline.
  La PWA puede conservar cifrados únicamente los metadatos del formulario y
  su identidad de envío. No guarda bytes de archivos en IndexedDB ni en el
  service worker. El respaldo cifrado de capturas pasa a formato 5 y mantiene
  lectura de formatos 1–4; no cambia el esquema de IndexedDB.
- `GET audit`, `audit/options`, `audit/{id}` y `audit/integrity` requieren
  permiso `audit`. La paginación conserva la secuencia máxima consultada.
  El detalle devuelve JSON como texto escapado, omitiendo credenciales y rutas
  privadas; las cifras decimales textuales conservan todos sus dígitos.
- Los eventos nuevos usan huella v2 con codificación tipada y orden canónico,
  estable tras el almacenamiento JSON de MySQL, e identidad del actor al
  producirse el evento. El verificador revisa contenido, secuencia, enlaces y
  cabecera. Los eventos v1 permanecen intactos y se identifican como históricos
  de verificación limitada; no se reescriben ni se certifican falsamente.

Esta entrega no completa la V1. Continúan pendientes la consulta/reconciliación
integral de antecedentes del origen y anexos fiscales, el respaldo y restauración
completos del servidor (RF25), y los demás alcances de `implementation.json`.
El tratamiento de rutas históricas de informes también debe endurecerse antes
de validar una restauración integral entre tenants; no se reubican copias
congeladas silenciosamente.

PDF utiliza el mPDF instalado en SAB; XLSX se escribe en flujo con XMLWriter
y ZipArchive, con celdas de texto explícito, sin fórmulas ni conversión de
importes a float. CSV UTF-8 usa punto decimal, separador de punto y coma y
registros de identificación, detalle y control; requiere importar referencias
e importes como texto en hojas de cálculo. Los textos con prefijos de fórmula
reciben protección por apóstrofo ([referencia OWASP](https://owasp.org/www-community/attacks/CSV_Injection)).
Se preservan todos los dígitos de importes grandes. Los tres formatos incluyen
empresa/RIF, filtros, generación, versiones y controles. No se redondean
movimientos contabilizados fuera de la precisión VES para ocultarlos: la
exportación se bloquea. El límite explícito de datos/archivo es 100 MiB, con
rechazo completo, sin truncamiento. XLSX divide hojas al alcanzar su máximo
de filas y rechaza textos que no caben en una celda; CSV conserva esos textos.

`ReportIntegrationAssertions.php` verifica reversos, históricos, comparativos,
502 líneas, aperturas sin movimiento, precisión, fórmulas literales, integridad
y los nueve tipos por tres formatos. `TenantDBIsolationTest.php` usa otra
conexión a la base temporal para comprobar una lectura repetible tras un
reintento. Las suites `reports_http_smoke.py` y `reports_browser_smoke.cjs`
comprueban las descargas HTTP reales y la PWA compilada en AWS, con datos
ficticios dentro de la copia privada. Sin conexión solo se consultan los
estados incluidos en la copia preparada; no se conservan versiones definitivas.

La implementación sigue en desarrollo. Las importaciones y estos informes no
completan por sí solos los flujos fiscales, conciliaciones de origen, adjuntos,
auditoría visible y respaldo integral. Restan créditos y anticipos de
apertura, notas y tributos en liquidaciones, la elección conjunta de formularios
todavía incompletos y casos adicionales de documentos manuales y autorización
local frente a cambios del reloj.
