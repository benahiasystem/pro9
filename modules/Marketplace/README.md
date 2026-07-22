# Módulo Marketplace

Vitrina pública de las tiendas de una comunidad. Los negocios publican su catálogo desde la app móvil, el administrador aprueba, y los vecinos encuentran productos y contactan por WhatsApp.

**No es un ecommerce.** No hay stock, pagos en línea, órdenes ni comisiones: el marketplace nunca cobra ni registra una venta. El cierre siempre ocurre por WhatsApp entre comprador y tienda.

Sí hay dos cosas que se le parecen, y conviene entender sus límites:

- **Precios opcionales**, opt-in por tienda. Cada negocio decide si publica los suyos; por defecto no. Son informativos: el precio final y la entrega se acuerdan en el chat.
- **Un pedido (carrito)** que agrupa productos y los manda en **un solo WhatsApp** por tienda. No es un checkout: no hay total vinculante, no se cobra nada y el servidor ni se entera — el carrito vive en el navegador.

---

## Modelo de despliegue

**Un reseller = un dominio = un despliegue = un marketplace.**

Todo vive en la conexión `system` de hyn/multi-tenant. Ninguna consulta toca una base de datos de tenant, y **no existe columna `reseller_id` en ninguna tabla**: el aislamiento ya lo garantiza el despliegue.

La app móvil se compila apuntando al dominio del reseller con un token fijo. Las apps de ese dominio se convierten en tiendas de *ese* marketplace.

---

## Capacidades

### Para la tienda (app móvil)

- **Sincroniza todo su catálogo** con una sola llamada: datos de la tienda + productos + imágenes. De cada producto viajan también su **precio** y su **descripción**, si los tiene.
- **Decide si publica precios** con un toggle en el formulario de su tienda (*Mostrar precios*, apagado por defecto). Apagarlo los oculta al instante sin resincronizar.
- **Consulta su estado** en cualquier momento (pendiente, aprobada, rechazada, deshabilitada) y recibe el motivo escrito por el administrador tal cual.
- **Aceptación implícita de términos y condiciones**: al sincronizar por primera vez queda registrada. Si el reseller no tiene T&C activos, el enlace no se muestra en ninguna parte.
- **Syncs ligeros**: las imágenes se envían solo cuando cambian (comparando hash). El primer sync puede pesar megas; los siguientes, unos KB.

### Para el administrador (panel del sistema)

- **Interruptor global**: publicar o apagar el marketplace completo. Apagarlo no destruye nada.
- **Aprobar, rechazar o deshabilitar tiendas.** Rechazar y deshabilitar exigen un motivo, que la tienda lee en su app.
- **Vista previa del catálogo** de cada tienda, incluyendo productos inactivos y bloqueados.
- **Bloquear productos individuales de forma permanente.** El bloqueo sobrevive a cualquier número de sincronizaciones.
- **Gestionar denuncias**: bloquear el producto, deshabilitar la tienda o descartar. El catálogo de cada tienda muestra el número de denuncias por producto, que es lo que decide cuál bloquear.
- **Bloqueo automático por denuncias**: umbral configurable (100 por defecto). Al alcanzarlo, el producto se retira solo y queda el rastro en el motivo. `0` desactiva la función.
- **Ver el ranking de recomendaciones**, por tienda y por producto, junto a las denuncias. Es el contrapeso: un producto denunciado que además acumula recomendaciones suele ser una denuncia interesada, no un problema real. Aquí el número es **exacto**; el público lo ve abreviado.
- **Renombrar u ocultar categorías** sin romper enlaces.
- **Ajustes**: nombre de la comunidad, titular de la portada, textos SEO, saludo de WhatsApp (de un producto **y** del pedido/carrito), **símbolo de moneda** (para las tiendas que muestran precios), paginación, límite de catálogo, motivos de denuncia y **umbral del ranking** (cuántos vecinos distintos hacen falta para que una tienda destaque; 10 por defecto, `0` lo desactiva).

El titular de la portada se edita en dos campos —el texto y el remate destacado, que se pinta en rosa y cursiva— con vista previa en vivo. Viene precargado con *«Lo que venden tus vecinos, a un WhatsApp.»*; si se vacían ambos, el titular desaparece de la portada.

### Para el público

Pantalla única en `/marketplace`, con el design system de Búho:

- **Buscador global** con sugerencias en vivo (hasta 4 productos y 3 tiendas), debounce de 300 ms. Ignora tildes y mayúsculas, y busca también por código interno y código de barras.
- **Pestañas** Productos / Tiendas, **filtro de categorías** en pills y **chips** de filtros activos.
- **Grilla de productos** con paginación; sin foto, muestra la inicial del producto y el nombre de la tienda.
- **Modal de producto** con categoría, código, **descripción**, tienda y la CTA de WhatsApp.
- **Precios opcionales**: si la tienda los habilita, el precio se muestra en la tarjeta y en el modal, con el símbolo de moneda que fija el admin. Si no, no viaja al navegador.
- **Ficha de tienda** en `/marketplace/tienda/{slug}` con logo, descripción, dirección, WhatsApp, compartir y su catálogo filtrado. Muestra la **antigüedad** a trazo grueso («Creado hace 3 meses», nunca en días; la fecha exacta va en el tooltip) y una insignia **«Nuevo»** durante el primer mes.
- **Insignia «Nuevo»** en las tarjetas de la grilla de tiendas del home, además de la ficha: la tienda destaca durante su primer mes.
- **Denuncias** de un producto o de la tienda, con motivo de la lista configurada.
- **Recomendaciones**: un corazón por producto, reversible y sin diálogo ni confirmación. Alimenta el ranking de la tienda y el orden **«Más recomendados»** de la portada. El producto no muestra número; la tienda sí, una vez alcanza el umbral.
- **Pedido (carrito)**: reúne productos de una o varias tiendas y, al enviar, cada tienda recibe **un solo WhatsApp** con sus ítems y cantidades. Botón flotante con contador, drawer con pasos de cantidad y «Vaciar». Persiste entre páginas. No es un checkout: sin total vinculante, sin stock y sin pago en línea.
- **Deep-link** `?p={id}` para abrir un producto directamente; la URL se mantiene al abrir el modal, así que se puede compartir.
- Skeletons de carga y estados vacíos distintos para «aún no hay nada publicado» y «no hay resultados para estos filtros».

Cada tienda tiene un enlace compartible con Open Graph, pensado para pegarse en WhatsApp; si deja de estar aprobada responde **410** con una salida amable, no un 404 seco, porque el enlace ya circuló.

### La CTA es siempre WhatsApp

No hay checkout ni pasarela: todo termina en un chat. Para **un producto**, el enlace se arma en el servidor (`Services/WhatsAppLink`) e incluye el saludo configurable, el nombre, su **código interno** y la URL de la tienda. El código es lo que le permite al vendedor ubicar el producto de inmediato en su app, que es de donde salió el catálogo.

Para **un pedido** (varios productos de una tienda), el mensaje se arma en el cliente (`wa-cart.js`) porque las cantidades son dinámicas: mismo formato, con la lista de ítems y sus cantidades bajo el saludo del carrito.

---

## Decisiones de diseño que conviene conocer

### La aprobación es solo para el alta

Una vez aprobada, la tienda publica cambios **al instante y sin revisión**. Cambiar nombre, RUC, WhatsApp o logo no vuelve a pasar por el administrador. El control permanente es el botón «Deshabilitar», disponible siempre.

### El bloqueo de un producto es permanente

`StoreSyncService` **nunca** toca `status`, `blocked_at`, `blocked_reason` ni `reports_count` de un ítem bloqueado. Sí actualiza su nombre, imagen y categoría. Y los ítems bloqueados **jamás se eliminan**, ni siquiera si desaparecen del catálogo que envía la app — si se borraran, la tienda podría saltarse el bloqueo simplemente resincronizando.

Solo el administrador lo revierte. Lo mismo vale para el **bloqueo automático** al superar el umbral de denuncias: es la misma marca `blocked`, con el motivo puesto por el sistema.

### El pulgar dice la verdad: identidad por cookie, no por IP

Un visitante puede recomendar cada producto **una vez** y **quitarlo** cuando quiera. Es reversible, como un «me gusta» de toda la vida, y sin número en el producto: solo se enciende o se apaga.

La clave es que el pulgar **refleja el estado real**, no una suposición. La primera vez que alguien entra, `EnsureMarketplaceVisitor` le emite una cookie firmada (`mkt_visitor`) con un uuid; esa es su identidad. El feed devuelve, en cada respuesta, `recommended: [ids]` — cuáles de los productos de esa página ya recomendó **ese** visitante — y el front enciende esos pulgares. No hay `localStorage` que adivine ni descartes en silencio.

**Por qué cookie y no IP:** en el wifi de un edificio —el caso de uso exacto— todos los vecinos comparten IP pública. Con la IP como identidad, el primero en recomendar consumiría el pulgar de todos, y la pantalla no tendría forma de decírselo. La cookie distingue navegadores. La IP se guarda solo como rastro para auditar un ranking sospechoso; no manda ninguna regla. Se quitó también el filtro de autorrecomendación por IP: bloqueaba a vecinos reales que compartían red con la tienda y no frenaba a un dueño con datos móviles; con el ranking por personas distintas, un dueño solo puede darse **un** voto, que es irrelevante.

**El `recommended` del visitante viaja FUERA del payload cacheado.** El feed se cachea 10 min y se comparte entre miles de visitantes; el pulgar encendido es de cada uno. Se resuelve con una consulta ligera **después** de leer el cache. Mezclarlos haría que un visitante viera los pulgares de otro — es el error que hay que no cometer al tocar `FeedController::index`.

### El ranking cuenta personas, y solo cuando ya es notable

`marketplace_stores.recommendations_count` **no es la suma de los pulgares de sus productos**: son los **visitantes distintos** que han recomendado algo suyo. Así un solo entusiasta que recorra el catálogo entero cuenta como **uno**. Es lo que convierte el número en un aval y no en un marcador inflable. Lo recompone `CatalogCounters::refreshStore()` con un `COUNT(DISTINCT visitor_id)`; se **excluyen los productos bloqueados** pero cuentan los inactivos (un producto retirado del catálogo no borra el reconocimiento ya ganado).

Y hay un **umbral** (`ranking_threshold`, 10 por defecto): por debajo de él la tienda **no muestra el número ni gana posición** — se ordena junto a las que tienen cero. Una tienda con dos recomendaciones no debe verse «mejor» que una que aún no tiene ninguna; la preferencia solo aparece cuando el número ya significa algo. El gate se aplica en dos sitios coordinados: el orden del feed (`CASE WHEN count >= umbral THEN count ELSE 0 END`) y el presenter, que manda `0` por debajo para que el front ni pinte la insignia. El front nunca conoce el umbral: solo recibe «notable o no». Con el umbral en `0` no hay gate.

### Los contadores se recuentan atómicamente, no se incrementan

Poner o quitar un pulgar dispara `CatalogCounters::refreshRecommendations()`, que **recuenta** el del producto y el de la tienda en vez de hacer `+1`/`-1`: así no hay incrementos que se desvíen si algo falla a medias.

El recuento y la escritura van en **una sola sentencia** `UPDATE … SET x = (SELECT …)`, no un `COUNT` en PHP seguido de `save()`. Es la ruta más caliente del módulo —cada pulgar la ejecuta— y contar-en-PHP-y-guardar-después abre una ventana de *lost update*: dos vecinos recomendando a la misma tienda casi a la vez pueden intercalarse y el que contó primero (con el valor viejo) pisa al que contó el total real. El `UPDATE` atómico cierra esa ventana. Es el mismo patrón que ya usaba `refreshCategories`.

### Recomendar no invalida el cache

A diferencia de los contadores de categoría, aquí un número desactualizado unos minutos no rompe nada: no lleva a una búsqueda vacía, solo muestra un ranking ligeramente atrasado. Purgar el módulo entero en cada pulgar dejaría el cache inservible justo en las horas de más tráfico, así que el `POST` no llama a `MarketplaceCache::bump()`.

### Riesgo aceptado: inflación del ranking por borrado de cookies

La cookie del visitante está **firmada**, así que nadie puede falsificar un `visitor_id` ajeno. Pero cada identidad nueva solo necesita **un** pulgar para contar como visitante distinto, y borrar cookies genera identidades nuevas: con el `throttle:60,60` por IP, alguien decidido puede fabricar del orden de 60 avales por minuto desde una misma IP.

No se mitiga más porque hacerlo exigiría cuentas, captcha o algún tipo de verificación, y eso rompería la premisa de todo el marketplace: **mínima interacción, sin fricción**. Las dos defensas que sí existen son proporcionadas al riesgo (una comunidad cerrada, no un ranking con dinero de por medio): el **umbral** obliga a reunir varias identidades antes de que la tienda destaque siquiera, y la **IP queda registrada en cada fila**, así que un ranking sospechoso se audita de un vistazo (muchos `visitor_id` distintos desde una sola IP es la firma del abuso). Es la misma clase de decisión consciente que el [riesgo de autenticación](#riesgo-aceptado-en-la-autenticación) de la API.

### Un pulgar es idempotente

`recomendar: true` usa **`insertOrIgnore`**, no `firstOrCreate`. En Laravel 9 `firstOrCreate` es un `SELECT` seguido de `INSERT` sin atrapar la violación de clave única (eso llegó con `createOrFirst` en L10), así que dos POST paralelos del mismo visitante y producto pasarían ambos el `SELECT` y el segundo `INSERT` reventaría con un **500** contra `mkt_reco_once_per_visitor`. `insertOrIgnore` descarta el duplicado en la propia sentencia: doble clic o dos pestañas abiertas simplemente no hacen nada.

El endpoint lleva el estado **explícito** (`recomendar` true/false), no es un toggle ciego, para que el resultado dependa de lo que pidió el cliente y no del estado que hubiera en el servidor. El orden entre un «pon» y un «quita» concurrentes lo garantiza el cliente, que serializa las peticiones (guard `busy` en `MktRecommend`): no dispara una hasta que vuelve la anterior.

### El pedido se arma y se envía desde el cliente

El carrito vive **entero en el navegador** (`Resources/assets/js/public/cart.js`, persistido en `localStorage`). No hay tabla, ni endpoint, ni estado en el servidor: es una lista de la compra, coherente con que el marketplace no maneja stock, órdenes ni pagos. Tampoco calcula un total, aunque la tienda muestre precios: el importe lo confirma el vendedor en el chat.

Persiste porque **el front navega con recargas de página completas** (ir a una tienda es un `location.href`): un carrito en memoria se perdería al cambiar de vista. Por eso cada ítem guarda un **snapshot** de lo que necesita para agruparse y armar el mensaje —nombre, código y los datos de su tienda—, sin depender de que el producto siga en la página actual.

El mensaje de WhatsApp se arma en el cliente (`wa-cart.js`), no en el servidor como el de un solo producto, porque **las cantidades son dinámicas**. Para eso el payload del producto expone `store.whatsapp` (no es una fuga: ese número ya viajaba dentro de `wa_link`). El saludo que encabeza el mensaje es el ajuste **`whatsapp_cart_greeting`**, gemelo del de un producto: toda la copia que llega al WhatsApp de la tienda vive en Ajustes.

**Se agrupa por tienda y cada una envía por separado**: una tienda no puede recibir el pedido de otra. Si el carrito cruza varias, el drawer lo avisa y pinta un botón «Enviar pedido» por grupo. La marca «Enviado» es solo de sesión —no podemos saber si el mensaje se mandó de verdad— y no se persiste.

Los ítems del carrito son un snapshot y **no se validan contra el servidor**: si un producto se bloquea o se retira después, sigue en la lista hasta que el vecino la ajuste; la tienda lo aclara por WhatsApp. Es el mismo criterio de mínima fricción del resto del módulo.

### Los precios son opt-in por tienda, y el gate es de presentación

El marketplace nació sin precios —«se coordina por WhatsApp»— y esa sigue siendo la opción por defecto. Cada tienda decide con un toggle en su app (`show_prices`, default **false**) si publica los precios de su catálogo.

El precio de cada ítem **se guarda siempre** que la app lo envíe; lo que depende del toggle es si se **muestra**. `PublicPresenter::item` expone `price` solo cuando la tienda tiene `show_prices` activo; si no, ni siquiera llega al navegador. Así, apagar los precios los oculta al instante sin perder el dato, y encenderlos no exige resincronizar.

El símbolo lo pinta el front con el ajuste `currency_symbol` (default `S/`), configurable una vez para todo el marketplace. El número va como decimal; el formato (dos decimales, separador de miles) vive en `format.js`.

### La descripción del producto se omite si repite el nombre

La app de inventario históricamente fusionaba nombre y descripción en un solo campo. Ahora tiene un campo de descripción propio, pero los productos viejos aún llegan con `description == name`. `PublicPresenter::item` devuelve `description` en `null` cuando coincide con el nombre: repetir el título en el modal no aporta nada. La app hace el mismo filtro antes de enviar, así que el caso normal ni siquiera viaja.

### La antigüedad de la tienda es a trazo grueso

`PublicPresenter::ageLabel()` nunca dice «hace 2 días»: por debajo del mes solo hay «Creado hace menos de un mes» (el badge «Nuevo» ya da el matiz), luego meses, y a partir del año, años. Al vecino le basta la escala para hacerse una idea de si el negocio lleva tiempo; la precisión al día sería ruido. La fecha exacta queda en el `title` (tooltip) por si alguien la quiere. El mismo `created_at` decide el badge «Nuevo» (`isNew()`: dentro del último mes).

Se calcula en el presenter, así que en las tarjetas del home viaja dentro del payload cacheado (10 min) — irrelevante a escala de meses — y en la ficha, que no se cachea, siempre es fresco.

### Los ítems nunca se borran

Los que desaparecen del payload pasan a `inactive`. Es lo que permite que un bloqueo sobreviva.

### El slug de la tienda es inmutable

Se genera una sola vez, en el primer sync. Aunque la tienda cambie de razón social, su URL pública no cambia: los enlaces ya compartidos en WhatsApp no se pueden romper.

### La taxonomía se construye sola

La app envía la categoría como texto libre; el servidor la convierte en slug y hace `firstOrCreate`. Así, «Herramientas Eléctricas», «herramientas electricas» y «HERRAMIENTAS  ELÉCTRICAS» colapsan en una sola categoría. El administrador no crea nada; solo puede renombrar el nombre visible u ocultarla.

El `slug` no es editable: es la llave con la que el sync resuelve categorías, y cambiarlo crearía una categoría nueva en la siguiente sincronización.

Limitación aceptada: un error de tipeo genera una categoría nueva. Con el volumen previsto, el administrador la oculta.

### Apagar el marketplace nunca es un error para la app

Con el marketplace apagado, la API responde **200** con `marketplace_enabled: false`, no un `4xx`/`5xx`. La app debe leer esa bandera **antes** que `status`: es lo único que distingue «el marketplace está apagado» de «tu tienda está pendiente». El público ve un **503** con una página amable, y el administrador sigue teniendo acceso completo para poder aprobar tiendas antes de abrir.

### Riesgo aceptado en la autenticación

El token es global del reseller, compartido por todas las apps. La identidad de la tienda viaja en el payload (`external_uuid`), no en la credencial, así que **cualquier app con el token podría suplantar el `external_uuid` de otra tienda**. Es una decisión consciente del negocio dado el contexto (comunidad cerrada, información pública una vez aprobada). No se implementa mitigación.

---

## Arquitectura

```
modules/Marketplace/
├── Config/config.php                 # solo disk y route_prefix; el resto en BD
├── Database/Migrations/              # 6 tablas + defaults de settings
├── Models/                           # Store, Item, Category, Report,
│                                     # Recommendation, Setting
├── Http/
│   ├── Controllers/Api/              # SyncController, StatusController
│   ├── Controllers/Admin/            # Store, Report, Category, Setting
│   ├── Controllers/Web/              # Marketplace, Feed, Report, Recommend
│   ├── Middleware/                   # EnsureMarketplaceEnabled,
│   │                                 # EnsureMarketplaceVisitor (cookie)
│   └── Requests/                     # SyncRequest, ReportRequest, RecommendRequest
├── Providers/                        # MarketplaceServiceProvider, RouteServiceProvider
├── Scopes/PublishedScope.php
├── Services/                         # StoreSync, CategoryResolver, ImageStorage,
│                                     # CatalogCounters, WhatsAppLink,
│                                     # MarketplaceCache, Settings
├── Support/StorePayload.php          # contrato que lee la app móvil
├── Support/PublicPresenter.php       # forma de lo que ve el público
├── Resources/
│   ├── assets/
│   │   ├── js/marketplace.js         # entry Vite público, standalone
│   │   ├── js/public/                # Marketplace.vue + components/ +
│   │   │                             # recommendations.js, cart.js (pedido),
│   │   │                             # wa-cart.js (mensaje), format.js
│   │   ├── js/admin/                 # Vue 2 + Element UI (bundle `system`)
│   │   ├── sass/                     # _design-system.scss (extraído) + marketplace.scss
│   │   ├── fonts/                    # Figtree + JetBrains Mono (woff2)
│   │   └── img/buho-logo.svg
│   └── views/                        # admin/index, public/index, public/unavailable
├── Routes/                           # api.php, admin.php, web.php
├── test.http                         # pruebas REST Client
└── README.md
```

### Tablas (conexión `system`)

| Tabla | Para qué |
|---|---|
| `marketplace_stores` | Tiendas, su estado y datos de sincronización |
| `marketplace_items` | Productos. Unique `(store_id, external_id)` |
| `marketplace_categories` | Taxonomía plana, un solo nivel |
| `marketplace_reports` | Denuncias del público |
| `marketplace_recommendations` | Pulgares del público. Unique `(visitor_id, item_id)` |
| `marketplace_settings` | Configuración editable, con tipo |

`marketplace_items` tiene `price` y `description` (ambos opcionales, los envía la app). Ninguna tabla tiene `stock`, `reseller_id`, ni nada de órdenes o pagos.

### `PublishedScope`

Global scope en `Item`: solo son públicos los ítems `active` de tiendas `approved`. Es global precisamente para que sea imposible olvidarlo en el front.

El sync y el admin necesitan ver todo, así que usan **`Item::unscoped()`**.

### Los contadores hay que recalcularlos en toda acción que cambie qué se publica

`marketplace_stores.items_count`, `marketplace_stores.recommendations_count` y `marketplace_categories.items_count` están denormalizados para que el filtro y el orden del front no tengan que contar ni sumar en cada carga. Eso obliga a recalcularlos no solo en el sync, sino **también** al aprobar, rechazar, deshabilitar o habilitar una tienda, y al bloquear o desbloquear un producto.

Todo pasa por `Services/CatalogCounters`. Si se añade una acción nueva que cambie el conjunto publicado, tiene que llamarlo: si no, el filtro muestra números que no cuadran con los resultados, o una categoría con contador > 0 que no lleva a ningún producto.

El conteo por categoría usa el mismo criterio que `PublishedScope` (ítem activo **y** tienda aprobada), justo para que el número de la pill coincida con lo que devuelve el feed.

### El bundle público es standalone

`Resources/assets/js/marketplace.js` es un entrypoint **propio de Vite**, no se cuelga de `system.js` ni de `app.js`, y **no importa Element UI ni Bootstrap**. Esa es su razón de existir: la pantalla pública no debe cargar el panel entero. Si algún día acaba importándolos, el patrón se rompió.

Precedente en el repo: `modules/ClaimsBook/Resources/assets/js/app.js`.

El admin, en cambio, sí va por el otro camino: se cuelga de `system.js` mediante el alias `@viewsModuleMarketplace` y reusa Vue + Element UI ya cargados.

### El design system es un artefacto, no código a mantener

`sass/_design-system.scss` está **extraído** de `prompts/Marketplace_Publico.html` (el bundle del mockup) y lleva cabecera de «no editar a mano». Se conservaron solo los tokens y las clases que la pantalla usa: de 317 KB a 14 KB.

Sí se puede editar — no está protegido. La razón de no hacerlo es que es una **copia**, no un original: cuando diseño publique un mockup nuevo habrá que volver a extraerlo, y cualquier cambio hecho a mano aquí se perdería en silencio. Por eso los ajustes propios de la pantalla van en `sass/marketplace.scss`, que sí sobrevive a esa re-extracción.

Lo específico de la pantalla vive en `sass/marketplace.scss`.

Los iconos **no** usan la fuente de Tabler (828 KB para 14 formas): van inline como SVG en `public/components/MktIcon.vue`. Las fuentes (Figtree y JetBrains Mono, subsets `latin` y `latin-ext`) se sirven locales desde `assets/fonts`. **Cero CDN.**

### Cache

⚠️ **El módulo no usa `Cache::store('redis')`.**

`app/Providers/CacheServiceProvider.php` reescribe el prefijo de Redis en cada request con el UUID del tenant resuelto por hostname (`facturador_cache` en CLI, vacío en el dominio del sistema, el UUID en dominios de tenant). Como los datos del marketplace son de sistema y no pertenecen a ningún tenant, con el store compartido **cada contexto escribiría en un espacio de claves distinto y la invalidación no funcionaría**.

Por eso `MarketplaceServiceProvider` liga un repositorio propio con prefijo fijo `mkt`, y todo el módulo pasa por `MarketplaceCache`.

Invalidación por versión global: un solo `INCR` sobre `mkt:v` invalida todo el cache del módulo sin tocar el del resto de Pro8. Se dispara en cada escritura (sync, aprobación, bloqueo, cambio de categoría, ajustes).

---

## Rutas

### API para la app — `auth:system_api`

| Método | Ruta |
|---|---|
| `POST` | `/api/v1/marketplace/sync` |
| `GET` | `/api/v1/marketplace/status/{external_uuid}` |

Eso es toda la API. No hay más endpoints.

### Admin — `auth:admin` + `reseller.system.admin`

Pantalla en **`/marketplace/admin`**.

> El prefijo es `marketplace/admin` y no `admin/marketplace` porque `EnsureResellerSystemAdminPermissions` deriva la clave de permiso del **primer segmento** de la URL. Así la clave es `marketplace`, que puede concederse a un admin de reseller vía `module_permissions`; con `admin/...` sería `admin`, que no es un módulo otorgable.

### Público — dominio del sistema

| Método | Ruta | Para qué |
|---|---|---|
| `GET` | `/marketplace` | La pantalla. Acepta `?p={id}` para abrir un producto |
| `GET` | `/marketplace/tienda/{slug}` | La misma pantalla filtrada por tienda. Enlace compartible; 410 si no está aprobada |
| `GET` | `/marketplace/feed` | JSON del componente. Con `?suggest=1` devuelve las sugerencias del buscador |
| `POST` | `/marketplace/denuncia` | Registrar denuncia. `throttle:10,60` por IP |
| `POST` | `/marketplace/recomendacion` | Poner o quitar un pulgar (`recomendar` true/false). Idempotente. `throttle:60,60` por IP |

Las rutas públicas pasan además por `marketplace.visitor`, que emite la cookie `mkt_visitor` con la identidad anónima del visitante.

> **Ojo con el throttle:** `app/Exceptions/Handler.php` convierte a **500** cualquier excepción en peticiones JSON, así que el límite de denuncias responde `500 {"message":"Too Many Attempts."}` y no un 429. Es comportamiento global de la app, no del módulo; por eso el front lo reconoce por status **o** por mensaje.

---

## Instalación

```bash
php artisan module:migrate Marketplace
npm run build
```

El marketplace **nace apagado** (`is_enabled = false`). Se enciende desde Ajustes cuando ya hay tiendas aprobadas.

Para catálogos con imágenes, el primer sync puede pesar ~10 MB:

- PHP: `post_max_size = 32M`, `memory_limit ≥ 256M`, `max_execution_time = 120`
- Nginx: `client_max_body_size 32M`

---

## Documentación

| Archivo | Para quién |
|---|---|
| `README.md` | Este. Qué hace el módulo y por qué está montado así |
| `test.http` | **Equipo móvil.** Recorrido del ciclo de vida con REST Client de VSCode; es el contrato del sync, con todos los campos y su significado |

Del lado de la app, el dominio está documentado en su propio repositorio: `docs/domains/marketplace.md` (flujos, invariantes y contrato visto desde el cliente).

---

## Rendimiento medido

Con 1.023 productos publicados en 27 tiendas:

| | Sin cache | Cacheado |
|---|---|---|
| Portada | 37 ms · 7 consultas | 1 ms · 0 consultas |
| Búsqueda | 19 ms | — |
| Grilla de tiendas | 28 ms · 8 consultas | 1 ms · 0 consultas |
| Sugerencias | 13 ms · 4 consultas | — |

El número de consultas es constante, no crece con la cantidad de resultados. Todos los índices declarados se usan; en `marketplace_stores` el optimizador prefiere un scan porque la tabla es pequeña, que es lo correcto.

Sync de 500 productos con imágenes de ~7 KB: **2,1 s** y 62 MB de memoria. El sync posterior sin imágenes: **66 KB y 0,8 s**, 73× más ligero.

---

## Estado

Todas las fases completadas.

| Fase | Estado |
|---|---|
| 1 — Base (migraciones, modelos, cache, settings) | ✅ |
| 2 — API de sincronización | ✅ |
| 3 — Admin | ✅ |
| 4 — Design system y bundle público | ✅ |
| 5 — Front público (búsqueda, grillas, modales) | ✅ |
| 6 — Cierre y documentación | ✅ |

### Añadido después del cierre

| Función | Estado |
|---|---|
| Recomendaciones (corazón por producto, ranking por visitantes distintos, umbral) | ✅ |
| Pedido / carrito agrupado por tienda, un WhatsApp por tienda | ✅ |
| Antigüedad de la tienda e insignia «Nuevo» del primer mes | ✅ |
| Precios opt-in por tienda + símbolo de moneda configurable | ✅ |
| Descripción del producto en el modal | ✅ |
