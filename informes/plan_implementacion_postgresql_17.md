# Plan de implementación de PostgreSQL 17 en Pro9

Fecha: 9 de noviembre de 2026. Estado: planificación técnica para instalaciones nuevas. La aplicación continúa usando su configuración actual; los cambios de código descritos aquí se realizarán al implementar.

## Objetivo y decisiones

Sustituir MySQL por PostgreSQL 17 en la administración central, empresas, módulos, respaldos y herramientas de Pro9, conservando los contratos comerciales y de precisión vigentes. El despliegue seguirá usando Docker Compose, con una base central y una base independiente por empresa. Supabase determina la versión mayor objetivo; su alojamiento y servicios no forman parte de esta entrega.

Se conservarán Laravel 9, Hyn Tenancy 5.9.1 y PHP 8.2. Se prepararán instalaciones nuevas; no se convertirán bases existentes ni se recalcularán documentos. Los cambios actuales del usuario se preservarán como parte de la referencia funcional.

La versión comprobada en una instancia nueva fue PostgreSQL 17.11, con la imagen `postgres:17.11-bookworm@sha256:665dc7b37ac52a17e298511414c21d23ffa4e6a6ef6225b3e5d59c7afb35e994`. Antes de implementar se comprobará el parche vigente de la rama 17 y se fijará el digest correspondiente. No se cambiará automáticamente de versión mayor. Véanse la [política oficial de versiones](https://www.postgresql.org/support/versioning/) y el [soporte documentado de PostgreSQL 17 en Supabase](https://supabase.com/docs/guides/platform/upgrading).

## Inventario que utilizará la implementación

El [inventario de cambios](/home/benahia/benahia/pro/Pro9/informes/evidencias/postgresql_17_2026_11_09/inventario_cambios.json) contiene rutas, SHA-256, líneas candidatas, acciones, pruebas asociadas, columnas por tabla y claves foráneas. Distingue archivos que requieren edición de consumidores que quedan cubiertos por una adaptación común.

| Frente | Estado identificado | Cambio previsto |
|---|---|---|
| Central | 159 migraciones existentes | Mantener su secuencia; corregir la sincronización de secuencias y añadir los dos soportes centrales indicados más adelante. |
| Tenant | 348 creadoras, una migración final de FKs y una incremental de RIF | Reescribir las creadoras y FKs; integrar RIF en `companies`; terminar con 349 archivos tenant. |
| Columnas tenant | 4.380 columnas en las creadoras actuales | Aplicar el mapa de tipos del inventario y agregar `companies.rif_document` al consolidado. |
| Integridad tenant | 554 declaraciones de FK en la migración final | Conservar relaciones y acciones; usar comprobación diferible durante las semillas. |
| Búsquedas | 339 líneas activas candidatas en 144 archivos PHP | Cubrir Query Builder con una gramática común y portar los casos de SQL crudo. |
| Funciones MySQL | 20 líneas activas candidatas en doce archivos | Portar funciones, incluyendo `IF`, `FIELD`, `DATEDIFF` y `CHAR(9/10/13)`. |
| Uniones SQL | Nueve líneas candidatas en seis archivos | Verificar columnas, tipos, JSON, deduplicación y orden final. |
| Errores SQL | 34 líneas candidatas en 28 archivos | Sustituir códigos de MySQL por clasificación SQLSTATE. |
| Dependencias de runtime | 73 líneas candidatas en 20 archivos PHP | Portar conexiones, metadatos, snapshots, respaldos y herramientas. |

Las cantidades de búsquedas y errores corresponden a candidatos de revisión, no a reemplazos mecánicos. Se excluyeron comentarios PHP. El inventario también registra falsos positivos: `date_format` de PHP y un `union` de colecciones obtenido mediante `pluck`, que conservarán su comportamiento.

Antes de editar se compararán los hashes con el estado de trabajo. Si un archivo cambió, se releerá y se actualizará únicamente su partida del inventario; no se restaurará ni sobrescribirá la referencia anterior. El [estado inicial](/home/benahia/benahia/pro/Pro9/informes/evidencias/postgresql_17_2026_11_09/estado_inicial.json) conserva la revisión y los cambios previos.

## Referencia obligatoria de Pro6

Se revisará la implementación actual de `/home/benahia/benahia/pro/pro6`, su [paquete documental](/home/benahia/benahia/pro/pro6/informes/migracion_postgresql_17/README.md), la trazabilidad y los seis parches históricos. Pro6 permanecerá en modo de lectura.

| Referencia | Solución que se aprovechará | Adaptación necesaria |
|---|---|---|
| Docker PostgreSQL 17.5 | Servicio PostgreSQL y volumen persistente | Parche 17 vigente, digest fijo, healthcheck TCP y aislamiento por proyecto Compose. |
| Conexiones `system` y `tenant` | Separación lógica de conexiones | Mantener la resolución dinámica de Hyn; no crear un tenant estático con credenciales centrales. |
| Controlador PostgreSQL personalizado | Creación de empresas y permisos de `public` | Adaptador fuera de `vendor`, propietarios correctos y credenciales separadas. |
| Hooks de Composer | Necesidad de conservar la adaptación al reinstalar dependencias | Registro mediante proveedor y extensiones de Hyn; no sobrescribir dependencias. |
| Búsquedas `ILIKE` | Insensibilidad a mayúsculas | Preservar también acentos y Unicode con normalización y collation determinista. |
| Correcciones financieras | Fechas, nulos, JSON, alias y tipos de `UNION` | Reimplementar sobre los helpers exactos actuales de Pro9. |
| Respaldos históricos | Flujo administrativo de respaldos y demos | Sustituir las dependencias MySQL que todavía existen en Pro6. |

Cada solución aprovechada quedará asociada a su origen, archivo destino, diferencia y prueba de Pro9. Se conservarán los catálogos venezolanos, tipos documentales, snapshots y contratos actuales; los agregados antiguos de Pro6 no sustituirán las fracciones exactas de Pro9.

## Comprobaciones realizadas para definir el plan

Los [sondeos de planificación](/home/benahia/benahia/pro/Pro9/informes/evidencias/postgresql_17_2026_11_09/sondeos_planificacion.json) se ejecutaron con datos sintéticos en instancias nuevas de PostgreSQL 17.11. No modificaron fuentes de la aplicación ni bases reales.

| Comprobación | Resultado | Consecuencia para la implementación |
|---|---|---|
| Transporte PDO | `NUMERIC` y JSON se recuperan como texto; booleanos como `bool` | Conservar decimales y normalizar booleanos según el transporte previo. |
| Búsqueda con collation no determinista | `ILIKE` devuelve SQLSTATE `0A000` | Aplicar una collation determinista explícita a la expresión de búsqueda. |
| Búsqueda normalizada | `unaccent` e `ILIKE` determinista encuentran `José` mediante `jose` | Usar esta estrategia en la abstracción común. |
| Helper actual `ReadSnapshot` | Dos lecturas obtuvieron `[1, 2]` mientras otra conexión escribía | Adaptar el comienzo de la transacción PostgreSQL. |
| `REPEATABLE READ` PostgreSQL explícito | Las mismas lecturas obtuvieron `[1, 1]` | Fijarlo antes de la primera consulta y preservar el ámbito propio. |
| Migraciones centrales | Las 159 operaciones `up` completaron el ensayo con sincronización de secuencias en el arnés | Ajustar las cargas de IDs explícitos sin reescribir toda la secuencia central. |
| FK diferida durante semillas | Inserción hija antes de su padre, seguida de comprobación al commit | Usar `DEFERRABLE INITIALLY IMMEDIATE` y diferir sólo durante la carga. |
| Aprovisionamiento sin superusuario | Creación, renombrado, cambio de contraseña y eliminación funcionaron con los permisos y membresías definidos | Separar el rol de aprovisionamiento y conservar membresía explícita sobre sus roles tenant. |
| Aislamiento | El rol tenant no pudo crear bases ni conectarse a la base protegida de otra empresa | Revocar accesos de `PUBLIC` y probar permisos desde una conexión tenant real. |
| Respaldo | `pg_dump` 13 rechazó el servidor; las herramientas 17 restauraron datos, JSON, collations y secuencias | Actualizar los clientes y probar restauración real. |
| Persistencia Docker | Los datos restaurados permanecieron al recrear el contenedor con el mismo volumen | Montar el volumen en la ubicación de PostgreSQL 17. |

Estos resultados prueban mecanismos concretos. La instalación tenant completa, el rollback central y los recorridos comerciales integrales se ejecutarán durante la implementación. Las instancias y volúmenes utilizados para los sondeos se eliminaron.

## Cambios por frente

### 1 Infraestructura y configuración

1. Versionar la configuración Compose reproducible; actualmente `docker-compose.yml` está excluido por `.gitignore`. Retirar esa exclusión para la nueva configuración, conservando secretos fuera del repositorio.
2. Sustituir el servicio MySQL por `postgres`, con imagen y digest fijos, `pg_isready -h 127.0.0.1` y dependencia de disponibilidad. La comprobación TCP evita confundir el servidor temporal de inicialización con el servidor definitivo.
3. Montar un volumen nuevo en `/var/lib/postgresql/data`. Mantener los volúmenes MySQL anteriores sin convertirlos ni eliminarlos. La instalación nueva utilizará otro proyecto Compose y otros volúmenes.
4. Retirar nombres de contenedor fijos y resolver los servicios por DNS de Compose. Permitir que el ensayo conviva con la instalación actual sin colisiones de nombres o puertos. La prueba de aplicación usará un puerto local independiente; PostgreSQL no publicará puertos por defecto.
5. Añadir `docker-compose.postgresql.local.yml` para acceso opcional desde `127.0.0.1:5433` al puerto interno 5432. Mantener Nginx, Redis y BCV con sus contratos actuales.
6. Reconstruir la base PHP sobre Debian Bookworm, conservando PHP 8.2 y las capacidades actuales: GD con AVIF y alfa, Intl, BCMath, XML, ZIP, cURL, PDO SQLite, PCNTL, ImageMagick, Ghostscript y las herramientas de procesos actuales. Instalar `pdo_pgsql`, `pgsql`, `libpq` y clientes PostgreSQL 17. Eliminar la dependencia de la imagen local sin definición reproducible `pruebas-php` y retirar `pdo_mysql` del runtime final.
7. Usar el repositorio PGDG de Bookworm y fijar los paquetes de clientes durante la construcción. El repositorio actual soporta Debian 12 y 13, mientras que la imagen actual de Pro9 usa Debian 11. Conservar las pruebas existentes de imágenes y PDF al reconstruirla. [Paquetes oficiales](https://www.postgresql.org/download/linux/debian/).
8. Mantener `DB_CONNECTION=system`; cambiar `system` a `pgsql`. Hyn seguirá generando `tenant` dinámicamente. Añadir conexiones separadas para aprovisionamiento y migración central, sin heredar sus credenciales hacia una empresa.
9. Actualizar `.env.example`, `.env.dusk.example`, `config/queue.php`, `phpunit.xml` y scripts operativos. Declarar `ext-pdo_pgsql` y retirar `ifsnop/mysqldump-php` de Composer. Actualizar el lock mediante Composer dentro del contenedor preparado, no mediante edición manual.
10. Usar UTF-8 y `America/Caracas`. No cambiar el significado de las fechas documentales ni de las horas ya esperadas por los consumidores.

Aceptación: construcción desde cero sin imágenes locales implícitas, instalación paralela sin colisiones, disponibilidad TCP correcta y persistencia después de recrear contenedores.

### 2 Integración Laravel y compatibilidad de transporte

Crear `PostgresServiceProvider` y registrar un resolver para `pgsql`. El resolver construirá una conexión propia derivada de la conexión PostgreSQL de Laravel, con gramáticas de consulta y esquema propias. SQLite seguirá usando su integración normal para las pruebas unitarias que lo requieran.

La conexión conservará `NUMERIC` y JSON como cadenas y normalizará los booleanos de filas a 0/1 donde así se transportaban con MySQL. Los casts declarados por los modelos seguirán produciendo booleanos cuando corresponda. Cubrir también cursores y lecturas por lotes; no transformar booleanos dentro del JSON ni convertir importes a flotante. La tabla técnica `migrations` utilizará collation `C`, para que su creación y permanencia no dependan de las collations de aplicación.

La gramática de esquema conservará rangos unsigned, tipos de claves y collations en las migraciones centrales que ya usan Schema Builder. La gramática de consulta y `TextSearch` compartirán la misma normalización para operadores de búsqueda, incluidos selectores JSON. Las comparaciones exactas conservarán su contrato y las búsquedas estrictas dispondrán de una expresión explícita con collation `C`.

Preservar el orden anterior de nulos donde influya en listados: `NULLS FIRST` al ascender y `NULLS LAST` al descender, con desempates estables en paginación. Revisar por separado los `orderByRaw`; no modificar texto SQL mediante reemplazos globales.

Aceptación: contratos de tipos PHP/JSON equivalentes a la referencia, precisión intacta y todas las conexiones PostgreSQL usando las mismas adaptaciones, también en CLI y pruebas.

### 3 Roles y ciclo de vida tenant

El bootstrap de instalación preparará estos ámbitos de credenciales:

| Ámbito | Permisos |
|---|---|
| Bootstrap Docker | Inicialización de roles y base central; no se utilizará para peticiones comerciales. |
| Propietario y migrador central | Creación del esquema central y sus objetos; credenciales de instalación separadas del runtime. |
| Operación central | Lectura y escritura de tablas centrales y uso de sus secuencias; sin `CREATEDB`, `CREATEROLE` ni acceso a datos tenant. |
| Aprovisionamiento | `CREATEDB`, `CREATEROLE`, sin superusuario; administración de los roles que crea. |
| Empresa | Propietario de su base y objetos; `NOCREATEDB`, `NOCREATEROLE`, sin acceso a central u otras empresas. |

Para crear una base con propietario tenant, el aprovisionador concederá la membresía necesaria sobre el rol creado mediante `SET TRUE` e `INHERIT TRUE`, conservando el `ADMIN` concedido al creador por PostgreSQL. No intentará volver a conceder `ADMIN` a su propio otorgante. Revocar `CONNECT` y permisos de esquema concedidos por `PUBLIC` en las bases de aplicación.

Crear un adaptador propio de Hyn y un servicio `TenantProvisioning`. Se registrarán mediante la colección de drivers y eventos del paquete. El alta mantendrá validaciones, autorizaciones, identidad, hostname, claves y semillas actuales.

Flujo de creación: registrar intento central, validar recursos, crear rol, conceder membresía, crear base fuera de transacción con propietario, inicializar extensiones/collations, migrar, sembrar, asociar y finalizar metadatos, invalidar cachés y habilitar el primer acceso. La transacción central no cubrirá DDL remoto ni operaciones de otra base.

Añadir una tabla central `tenant_provisioning_runs` y su modelo: UUID de operación como PK, website bigint nullable con FK `ON DELETE SET NULL`, identificador tenant de hasta 63 bytes, operación, estado, recursos propios JSON, código de error nullable y timestamps. Conservar el registro si se elimina el website. Permitirá distinguir recursos creados por un intento de recursos preexistentes, compensar y recuperar una operación interrumpida.

Estados: `prepared`, `provisioned`, `migrated`, `ready`, `compensating`, `compensated` y `failed`. Un índice único parcial por identificador tenant para estados diferentes de `ready` y `compensated` bloqueará otra operación incompatible hasta recuperar el intento pendiente o fallido. Añadir recuperación por UUID con `--dry-run`, limitada a recursos cuya propiedad pueda acreditarse con el registro. El registro no contendrá contraseñas, payloads fiscales ni SQL con secretos.

Validar identificadores ASCII y su longitud real de hasta 63 bytes antes de crear recursos; devolver un error de validación si el prefijo más el subdominio excede el límite, sin truncamiento silencioso. Conservar los subdominios y convenciones actuales dentro de ese límite.

En renombrado: validar destino, cerrar conexiones propias, renombrar base y rol, actualizar la contraseña derivada que corresponda y purgar cachés/conexiones; compensar pasos incompletos. En eliminación: desconectar, retirar sólo recursos del tenant objetivo y conservar el comportamiento configurado de borrado de archivos. La rotación de credenciales se realizará mediante el mismo adaptador; el comando `passwords` dejará de imprimir contraseñas y DDL con secretos.

Mantener `EnsureTenantConnection`, el arranque de `AppServiceProvider`, primer acceso, MultiUser y limpieza de contexto en workers. No incorporar el scope antiguo de Pro6 que ignora la columna solicitada y siempre lee `session_lifetime`.

Aceptación: panel y API crean empresas, fallos en cualquier etapa son recuperables, una solicitud repetida no duplica recursos y dos tenants no comparten datos, sesiones ni contexto de jobs.

### 4 Esquema central y secuencias

Mantener las 159 migraciones centrales existentes. Añadir una migración inicial de soporte PostgreSQL anterior a las tablas que usan sus collations y otra para `tenant_provisioning_runs`, después de las tablas de tenancy. La secuencia central objetivo tendrá 161 migraciones, si no hay cambios de alcance posteriores.

Crear `SequenceSynchronizer`. Se invocará después de cargas con IDs explícitos, antes de que una migración posterior inserte IDs automáticos, y al terminar semillas/restauraciones. No bastará con ejecutarlo únicamente al final de toda la instalación: el ensayo detectó colisiones intermedias en `modules`.

El inventario identifica las once migraciones centrales con candidatos de IDs explícitos. Se revisarán las tablas realmente insertadas en cada una y se sincronizarán únicamente sus secuencias técnicas. Conservar IDs de catálogos. Sustituir el `AUTO_INCREMENT = 7` de giros de negocio por el reinicio PostgreSQL equivalente. La sincronización contemplará tablas vacías y nunca asignará cero a una secuencia cuyo mínimo sea uno.

La instalación usará el migrador central y restaurará la configuración de runtime al finalizar. El bootstrap se ejecutará antes de crear el repositorio de migraciones; la migración inicial comprobará después su versión de forma idempotente. Su rollback retirará únicamente objetos de soporte propios, sin `CASCADE` sobre objetos ajenos. El comando de instalación sólo aceptará una base nueva o un estado de instalación propio recuperable; no ejecutará `migrate:fresh` sobre una base arbitraria.

Aceptación: las migraciones y semillas completas funcionan mediante Artisan, los siguientes IDs son válidos, rollback y segunda instalación producen el mismo esquema y catálogos.

### 5 Consolidado tenant

Reescribir las 348 creadoras y la migración de FKs en PostgreSQL. Integrar `rif_document` JSON nullable en la creadora de `companies` y retirar la incremental del 3 de noviembre de 2026. Esta diferencia ya existe entre el árbol actual y los contratos que esperan 349 archivos tenant; se resolverá conservando el campo y el contrato de instalaciones nuevas.

| Tipo actual | Tipo objetivo |
|---|---|
| Clave técnica autoincremental | `bigint` con secuencia; inserción explícita permitida durante semillas. |
| Referencia a clave técnica | `bigint`, igual al destino. |
| `int unsigned` | `bigint`, conservando el rango mediante `CHECK`. |
| Enteros unsigned pequeños | `smallint` o `integer` según el rango, con `CHECK`; el mapa por columna está en el inventario. |
| `bigint unsigned` de negocio | `numeric(20,0)` con límites originales; no reducir su dominio al entero firmado. |
| `tinyint(1)` usado como indicador | `boolean`, con validación y transporte compatibles. |
| `decimal(p,s)` | `numeric(p,s)` con la misma precisión y escala. |
| `enum` | `varchar` con `CHECK` de sus valores actuales. |
| `text`, `mediumtext`, `longtext` | `text`. |
| `datetime`, `timestamp` | `timestamp without time zone`; defaults equivalentes. |
| `date`, horas | `date` y `time without time zone`, comprobando consumidores de horarios. |
| `json` | `json`; conservar tokens decimales y snapshots. |
| Dimensiones `tag_templates.width/height` | `numeric(8,2)`, conservando rango, escala y representación externa de estas dimensiones. |

Conservar `numeric(30,12)`, tasas `numeric(18,8)` y el rango especial `plans.sales_limit` con `numeric(32,12)`. PostgreSQL también redondea entradas que exceden la escala de una columna; conservar la validación anterior al SQL y los snapshots racionales como autoridad.

Mantener las tres columnas generadas almacenadas de retenciones y sus índices únicos. Traducir el `ON UPDATE CURRENT_TIMESTAMP` de auditorías mediante un trigger reversible, comprobando cambio real de datos, actualización sin cambios y valor explícito de la columna.

Los nombres de índices deberán ser únicos dentro del esquema. Renombrar los duplicados `payment_index` y `origin_index` con prefijo de tabla. Usar nombres deterministas con hash para identificadores mayores de 63 bytes. Reemplazar el prefijo de 191 caracteres del índice no único de nombre de producto por un índice completo. Mantener unicidad con múltiples nulos donde sea el comportamiento vigente.

Crear todas las tablas antes de las 554 FKs. Declararlas `DEFERRABLE INITIALLY IMMEDIATE`, conservando `RESTRICT`, `CASCADE`, `SET NULL` y sus acciones de actualización. Retirarlas en orden inverso en `down`. Las restricciones únicas utilizadas por `ON CONFLICT` seguirán siendo inmediatas.

Las semillas se cargarán en una transacción con las FKs temporalmente diferidas. Mantener catálogos, geografía, IDs y datos MOCK separados, actualizar por identidad al repetir y sincronizar secuencias después de las inserciones explícitas. Cualquier referencia inválida hará fallar el commit; no desactivar triggers, `session_replication_role` ni integridad global.

Aceptación: las 348 tablas y sus datos iniciales se crean, todas las relaciones se validan, la segunda carga no duplica filas y rollback/recreación conservan tipos, defaults, columnas generadas, índices y catálogos.

### 6 Búsquedas y SQL de aplicación

Preparar `pro9_ci_ai` mediante ICU para igualdad y unicidad de texto de negocio sin distinción de mayúsculas o acentos. Preparar `pro9_search` determinista para patrones. Mantener collation `C` en comparaciones binarias requeridas por identificadores, secretos y orden funcional de códigos.

`TextSearch` normalizará a NFC y aplicará `unaccent` a campo y patrón; ambos lados tendrán collation determinista explícita y parámetros enlazados. La gramática PostgreSQL aplicará ese contrato a búsquedas habituales de Query Builder. Los campos no textuales usarán conversión local sólo para las búsquedas legadas que la requieran; las comparaciones exactas conservarán su tipo. Conservar la semántica actual de `%`, `_`, escape y agrupación de `where`/`orWhere`.

Se conservarán los índices existentes y se medirán las búsquedas de productos y clientes con los volúmenes definidos. Si una búsqueda incumple el presupuesto, la optimización se limitará a claves normalizadas almacenadas y mantenidas por trigger para los campos afectados, con `pg_trgm`; no se declarará directamente inmutable a `unaccent` para forzar un índice. La consulta y el índice deberán usar la misma clave y demostrar su uso mediante `EXPLAIN`. Cualquier columna auxiliar quedará oculta de los contratos públicos.

Portar expresamente estos casos del inventario:

- Facturación masiva: sustituir `DATE_FORMAT` del filtro mensual por un rango de fechas sobre la columna original.
- Notificaciones y Hotel: `IFNULL` por `COALESCE`, conservando cuándo la concatenación devuelve nulo. Usar `||` y casts locales cuando se necesite la propagación de nulos de MySQL; no reemplazarla indiscriminadamente por `concat` de PostgreSQL.
- Actividades y anulaciones: `GROUP_CONCAT` por `string_agg` con el mismo orden, alias válidos y cast de hora PostgreSQL.
- Suscripciones y pedidos: extracción JSON mediante operadores/Query Builder PostgreSQL y búsqueda común parametrizada.
- Importaciones de productos y restaurante: `CHAR(9/10/13)` por `chr(9/10/13)` en la normalización del código interno, conservando el formato Excel y el resto de las validaciones.
- Medios de pago: reemplazar `BINARY id` por orden explícito con collation `C`.
- Prioridades de identidad y Marketplace: sustituir `FIELD` por `CASE` parametrizado, conservando el orden, los desempates y prioridad cero para valores no incluidos.
- Saldos fiscales: sustituir los `IF` de `FiscalNoteBalance` por `CASE`, conservando los operandos y la relación con los helpers exactos actuales.
- Métricas de reclamos: sustituir `DATEDIFF` por resta de fechas de calendario antes de `AVG`; no convertirlo en horas transcurridas divididas entre 24.
- API móvil y reportes: revisar agregaciones, agrupaciones y expresiones de ordenación que dependían de reglas MySQL.

En las uniones, proyectar igual cantidad, orden y tipos de columnas; aplicar el orden final fuera de las ramas. Mantener los `UNION` de deduplicación cuando corresponda. Para el consolidado de líneas con JSON y `source_type` distintos, usar `UNION ALL`: las claves de origen ya separan factura y nota, y PostgreSQL no dispone de igualdad nativa para `json`. No convertir globalmente snapshots a `jsonb`. Mantener los helpers actuales de fracciones, materialización, filtros y exportación.

Aceptación: listados y búsquedas preservan resultados, permisos y paginación; cada candidato del inventario queda convertido, cubierto por la gramática o clasificado como conservación justificada.

### 7 Errores, transacciones y concurrencia

Crear `SqlErrors` con clasificación por SQLSTATE: `23505` para duplicado, `23503` para referencia, `23514` para `CHECK`, `40001` para serialización, `40P01` para deadlock y `55P03` para bloqueo no disponible. Reemplazar las comparaciones de `23000`, `1062`, `1213` y `1205` del inventario, conservando mensajes funcionales y códigos HTTP.

Mantener `READ COMMITTED` para escrituras normales y los bloqueos de los servicios. `ReadSnapshot::run` iniciará la transacción y fijará `REPEATABLE READ` antes de la primera consulta PostgreSQL. Permitirá anidación de su propio ámbito y rechazará transacciones ajenas sin ese contrato. No cambiará el aislamiento de sesión; materializará el reporte antes de cerrar la lectura.

Reintentar la transacción completa ante serialización/deadlock, con un máximo de tres intentos y espera acotada. Un duplicado esperado usará `ON CONFLICT` o savepoint; no se continuará dentro de una transacción abortada. Las llamadas HKA, pagos y otros efectos externos permanecerán fuera de bloques reintentables, conservando sus estados pendientes/inciertos y su conciliación.

Mantener el bloqueo de empresa que protege `SeriesNumbering` y los índices documentales. Las secuencias técnicas podrán tener huecos; no sustituirán ni definirán los correlativos fiscales. La inserción de tasa BCV conservará la primera fila con `ON CONFLICT DO NOTHING` y volverá a leerla.

Los comandos de precisión de tasas dejarán de ejecutar DDL MySQL. En una instalación PostgreSQL nueva verificarán el contrato `(18,8)` y terminarán sin conversión de históricos. No se ejecutarán sobre empresas reales durante esta tarea.

Aceptación: las carreras no duplican documentos, aplicaciones, movimientos, retenciones ni tasas; un error revierte el conjunto y libera los bloqueos.

### 8 Respaldos, restauración y operación

Crear `PostgresBackup` y portar los comandos de respaldo individual, integral, demos y restauración temporal. Mantener rutas y parámetros administrativos existentes; modificar únicamente consumidores que dependen de `.sql`, MySQL o del formato interno del archivo.

Formato: ZIP con archivos PostgreSQL de formato personalizado `.dump`, archivos tenant y `manifest.json`. El manifiesto tendrá versión de formato, motor, versión mayor/menor, revisión de aplicación, fecha, bases incluidas, archivos y SHA-256. No contendrá contraseñas, `APP_KEY` ni `TENANCY_KEY`; la recuperación documentará su provisión separada. Rechazar formatos incompatibles antes de modificar un destino.

Ejecutar clientes 17 mediante `Symfony Process` con argumentos separados. Pasar credenciales mediante un archivo temporal privado o el entorno acotado del proceso, nunca en argumentos o logs. Considerar fallos de espacio, permisos, timeout y códigos de salida. Los paquetes incompletos no se publicarán como respaldos válidos.

Restaurar en una base independiente, recrear rol/propiedad, usar `--no-owner` con el rol destino y verificar esquema, datos, fracciones, archivos y siguiente ID antes de habilitarla. Un fallo conservará el destino original. El cambio final de nombres/conexiones ocurrirá con escrituras detenidas, purga de cachés y compensación definida.

El respaldo individual podrá usar la lectura consistente de `pg_dump`. El respaldo integral coherente requerirá una ventana sin escritores: detener la aplicación comercial y sus workers/scheduler, drenar operaciones activas y ejecutar la copia desde un contenedor de mantenimiento separado. El procedimiento y sus comandos quedarán en el manual; una colección de dumps online no se etiquetará como corte global coherente.

Actualizar el texto MySQL de la pantalla de respaldos, los selectores de demos y el manejo de errores para el nuevo formato. Conservar el flujo administrativo de archivos/ZIP. Los cambios Vue se validarán contra assets actuales antes del cierre visual.

Aceptación: restauración individual y de central más dos empresas en una instalación nueva, con permisos correctos y continuidad de IDs, saldos, archivos y contexto tenant.

### 9 Pruebas, herramientas y documentación

Crear un ejecutor `scripts/test_postgresql.php` que gestione un servidor y un runtime PHP de pruebas propios, puertos/volúmenes temporales y credenciales sintéticas. El PHP del host no dispone actualmente de `pdo_pgsql`, por lo que el ejecutor no dependerá de esa extensión del host ni del contenedor comercial. Limitar recursos y paralelismo; limpiar sólo recursos creados por la ejecución, también ante error o señal.

Usar `PRO9_POSTGRES_TESTS=1`, eliminar lecturas de `.env` real de los fixtures y compartir una base de pruebas común para configuración, conexiones y limpieza. Portar `FiscalEmissionSchemaTest`, sus traits, concurrencia de series/BCV, pruebas HTTP y suites de cobros. Mantener el contenido de los casos existentes, sustituyendo su dependencia del motor.

Crear `SchemaInspector` para columnas, tipos canónicos, índices, FKs, secuencias y snapshots PostgreSQL. Sustituir `SHOW TABLES/COLUMNS/INDEX/CREATE` en las pruebas por sus APIs; no emular respuestas MySQL dentro del driver. Los snapshots compararán estructura canónica PostgreSQL y excluirán sólo diferencias variables justificadas.

Reemplazar `FiscalFixtureSnapshot` por `pg_dump`/`pg_restore` privado de proceso, preservando secuencias, extensiones, restricciones y columnas generadas. La restauración partirá de una base vacía: usará los objetos de soporte del dump, sin ejecutar primero el bootstrap que los duplicaría. La ruta sin snapshot sí ejecutará bootstrap, migraciones y semillas. La clave del fixture incluirá versión de motor y hashes de migraciones/semillas; una copia modificada no afectará la fuente ni otra prueba.

Añadir auditoría de SQL PostgreSQL y un job GitHub Actions sobre la imagen fijada. El job comprobará versión 17, instalación completa, casos obligatorios ejecutados y limpieza. Fallará si faltan pruebas, reaparece sintaxis incompatible en rutas activas o un candidato queda sin clasificar. Registrar exclusiones individualmente y no confundir código PHP con SQL.

Actualizar scripts fiscales, verificación HKA, benchmark y actualización operativa. Retirar del runtime el parcheador masivo `tenant:migrations-make-idempotent`, cuyo fallback está ligado a errores MySQL; no ejecutarlo sobre el nuevo consolidado. Mantener Pro6 como referencia y las evidencias históricas con su motor original. Actualizar las instrucciones locales que actualmente exigen MySQL, conservando sus contratos fiscales, de seguridad y de precisión.

Revisar los cambios de expresiones que afecten `exact_rounding_registry` y sus hashes con evidencia nueva. No regenerar aprobaciones automáticamente ni atribuir al cambio de motor el cierre de consumidores comerciales que ya estuvieran pendientes.

## Pruebas de aceptación de la implementación

| Código | Casos obligatorios | Criterio |
|---|---|---|
| PG-INFRA | Construcción limpia; salud TCP; proyecto paralelo; recreación de contenedor | Motor/clientes 17, sin colisiones ni pérdida de datos. |
| PG-CONNECTION | Central/tenant/CLI/cursores; decimales, booleanos y JSON | Tipos de API compatibles y ausencia de pérdida de precisión. |
| PG-CENTRAL-INSTALL | Las 161 migraciones previstas, semillas, siguientes IDs y rollback | Dos ciclos completos equivalentes mediante el flujo real de instalación. |
| PG-INSTALL | Las 348 tablas tenant, semillas, 554 FKs e instalación repetida | 349 archivos consolidados; integridad al commit y ninguna tabla residual después de rollback. |
| PG-SCHEMA | Límites unsigned/decimal, defaults, `CHECK`, nulos, índices y generadas | Valores válidos aceptados e inválidos rechazados sin redondeo silencioso desde la aplicación. |
| PG-TENANCY | Panel, API, primer acceso, subdominio reutilizado, renombrado, credenciales y eliminación | Recursos y contexto correctos, incluidos dos tenants con los mismos IDs internos. |
| PG-PROVISIONING | Fallo en cada etapa, nombre ocupado, reintento y proceso interrumpido | Compensación/recuperación sólo de recursos propios; sin credenciales en logs. |
| PG-SEARCH | Mayúsculas, acentos, NFC/NFD, `ñ`, nulos, `%`, `_`, escape, JSON y valores no textuales | Resultados acordes con la referencia y sin `0A000`. |
| PG-SQL | Doce archivos de funciones, alias, prioridades `FIELD`, `IF`, días de calendario, concatenación/nulos y uniones tipadas | Listado, PDF y XLSX coherentes; ningún reemplazo indiscriminado. |
| PG-IMPORT | Código interno con tabulaciones/saltos de línea y caracteres de control | Misma detección de existentes y mismas validaciones/formato Excel. |
| PG-EXACT | `0,1 + 0,2`, `1/3`, doce posiciones, tasas de ocho y valores mayores que el entero seguro JS | Conservación de numerador/denominador, rangos, snapshots y saldos. |
| PG-COMMERCE | Factura, NC/ND, notas de venta, cuotas, conversiones, anulación, IVA/ISLR y garantías | Totales, saldos, relaciones e inventario equivalentes. |
| PG-PAYMENTS | VES/USD, vuelto, comisiones, histórico, reversiones y reutilización | Principal recibido/aplicado correcto, sin ingresos duplicados. |
| PG-CONCURRENCY | 20 solicitudes con conexiones independientes y barreras; numeración, cobros, BCV, stock y retenciones | Sin duplicados, sobreaplicación o persistencia parcial; reintentos acotados. |
| PG-SNAPSHOT | Escritor concurrente durante listado y generación PDF/XLSX | Detalle y totales comparten un corte, incluido cambio entre lotes. |
| PG-MODULES | Prueba mínima de cada módulo habilitado y recorridos ampliados de POS/Garage, compras, inventario, Hotel, Restaurante, Ecommerce y suscripciones | Ninguna ruta habilitada queda excluida por el motor sin justificación explícita. |
| PG-INTEGRATIONS | HKA, BCV, pagos y webhooks con respuestas controladas | Estados, autenticación, idempotencia y recuperación conservados; sin emisiones/cobros reales de prueba. |
| PG-BACKUP | Restauración individual y central más dos empresas; archivo corrupto, falta de espacio y fallo intermedio | Hashes, permisos, datos, archivos y siguientes IDs válidos antes de habilitar destino. |
| PG-PERFORMANCE | HTTP, POS, búsquedas y reportes; 1.000 y 10.000 documentos; varios tenants | p95 dentro del presupuesto con recursos y carga equivalentes. |

El ejecutor tendrá suites `install`, `concurrency`, `http`, `backup`, `performance` y `all`, y argumentos de PHPUnit después de `--`. Las suites de rendimiento usarán al menos 30 muestras después de calentamiento, registrando p50/p95, memoria, consultas y esperas. Presupuesto: p95 PostgreSQL menor o igual a referencia más el máximo entre 20 % de la referencia y 100 ms. La referencia HTTP incluirá bootstrap, kernel, autenticación y transporte.

Ejecutar también PHPUnit general, pruebas JavaScript, auditorías exactas, sintaxis PHP y `git diff --check`. Las pruebas PostgreSQL críticas no podrán saltarse por una bandera antigua MySQL. Separar fallos previos de regresiones, sin ocultar un bloqueo del nuevo motor.

La validación visual necesita assets que correspondan a las fuentes. La regla local [frontend-build](/home/benahia/benahia/pro/Pro9/.codex/skills/frontend-build/SKILL.md) indica: «No ejecutar comandos de build por iniciativa propia: la compilación la ejecuta el usuario». Por ello, el usuario o su watcher producirá los assets antes de las pruebas visuales que involucren cambios Vue; el implementador comprobará el manifiesto cargado y no editará bundles.

## Secuencia de entrega y recuperación

1. Congelar referencia funcional en una copia aislada que incluya cambios actuales, manifiesto y assets requeridos; completar comparación Pro6–Pro9 y pruebas MySQL de referencia.
2. Entregar infraestructura, proveedor PostgreSQL, tipos/transporte y bootstrap de roles. Gate: PG-INFRA y PG-CONNECTION.
3. Entregar migraciones centrales, secuencias, consolidado tenant y semillas. Gate: instalación completa, integridad, rollback y repetición.
4. Entregar aprovisionamiento y recuperación. Gate: ciclo de vida, aislamiento y fallos inyectados.
5. Entregar búsquedas, SQL, errores y lecturas consistentes. Gate: importaciones, reportes, operaciones comerciales y concurrencia.
6. Entregar respaldos, herramientas, CI, documentación y validación visual con assets actuales.
7. Ejecutar la matriz completa y el presupuesto de rendimiento en una instalación nueva sin MySQL accesible.

La activación posterior utilizará un entorno nuevo y sus volúmenes propios. Antes de habilitar escrituras se conservará una instalación vacía validada y su configuración. Si se requiere recuperación después de operar, se usará una versión compatible de Pro9 y el respaldo PostgreSQL correspondiente; cambiar el driver de vuelta a MySQL no constituye recuperación de los datos nuevos.

El cierre exige: motor y clientes 17, funcionamiento con MySQL detenido o inaccesible desde el entorno nuevo, instalación y rollback completos, matriz obligatoria aprobada, restauración integral comprobada, presupuesto de rendimiento cumplido e inventario de candidatos resuelto. Pro6 y las bases anteriores conservarán su estado.

Entregables de implementación: código, imágenes/configuración reproducibles, migraciones, herramientas de respaldo/recuperación, ejecutor, CI, matriz de resultados, manual operativo y trazabilidad Pro6–Pro9. Estimación preliminar: 25–40 jornadas laborables, ajustable tras la referencia integral y sin reducir la cobertura.

## Límites de esta planificación

La planificación y el inventario son los entregables actuales. Los sondeos descritos ya se realizaron; los cambios de aplicación y las pruebas de aceptación anteriores corresponden a la implementación futura. Se mantienen fuera de alcance la conversión de bases existentes, el alojamiento en Supabase, la modernización general de Laravel y la alta disponibilidad.
