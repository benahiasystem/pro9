# Módulo Marketplace

Vitrina pública de las tiendas de una comunidad. Los negocios publican su catálogo desde la app móvil, el administrador aprueba, y los vecinos encuentran productos y contactan por WhatsApp.

**No es un ecommerce.** No hay precios, stock, carrito, pagos, órdenes ni comisiones. El precio y la entrega se coordinan directamente por WhatsApp entre comprador y tienda.

---

## Modelo de despliegue

**Un reseller = un dominio = un despliegue = un marketplace.**

Todo vive en la conexión `system` de hyn/multi-tenant. Ninguna consulta toca una base de datos de tenant, y **no existe columna `reseller_id` en ninguna tabla**: el aislamiento ya lo garantiza el despliegue.

La app móvil se compila apuntando al dominio del reseller con un token fijo. Las apps de ese dominio se convierten en tiendas de *ese* marketplace.

---

## Capacidades

### Para la tienda (app móvil)

- **Sincroniza todo su catálogo** con una sola llamada: datos de la tienda + productos + imágenes.
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
- **Renombrar u ocultar categorías** sin romper enlaces.
- **Ajustes**: nombre de la comunidad, titular de la portada, textos SEO, saludo de WhatsApp, paginación, límite de catálogo y motivos de denuncia.

El titular de la portada se edita en dos campos —el texto y el remate destacado, que se pinta en rosa y cursiva— con vista previa en vivo. Viene precargado con *«Lo que venden tus vecinos, a un WhatsApp.»*; si se vacían ambos, el titular desaparece de la portada.

### Para el público

Pantalla única en `/marketplace`, con el design system de Búho:

- **Buscador global** con sugerencias en vivo (hasta 4 productos y 3 tiendas), debounce de 300 ms. Ignora tildes y mayúsculas, y busca también por código interno y código de barras.
- **Pestañas** Productos / Tiendas, **filtro de categorías** en pills y **chips** de filtros activos.
- **Grilla de productos** con paginación; sin foto, muestra la inicial del producto y el nombre de la tienda.
- **Modal de producto** con categoría, código, tienda y la CTA de WhatsApp.
- **Ficha de tienda** en `/marketplace/tienda/{slug}` con logo, descripción, dirección, WhatsApp, compartir y su catálogo filtrado.
- **Denuncias** de un producto o de la tienda, con motivo de la lista configurada.
- **Deep-link** `?p={id}` para abrir un producto directamente; la URL se mantiene al abrir el modal, así que se puede compartir.
- Skeletons de carga y estados vacíos distintos para «aún no hay nada publicado» y «no hay resultados para estos filtros».

Cada tienda tiene un enlace compartible con Open Graph, pensado para pegarse en WhatsApp; si deja de estar aprobada responde **410** con una salida amable, no un 404 seco, porque el enlace ya circuló.

### La CTA es siempre WhatsApp

No hay carrito ni checkout. El enlace se arma en el servidor (`Services/WhatsAppLink`) e incluye el saludo configurable, el nombre del producto, su **código interno** y la URL de la tienda. El código es lo que le permite al vendedor ubicar el producto de inmediato en su app, que es de donde salió el catálogo.

---

## Decisiones de diseño que conviene conocer

### La aprobación es solo para el alta

Una vez aprobada, la tienda publica cambios **al instante y sin revisión**. Cambiar nombre, RUC, WhatsApp o logo no vuelve a pasar por el administrador. El control permanente es el botón «Deshabilitar», disponible siempre.

### El bloqueo de un producto es permanente

`StoreSyncService` **nunca** toca `status`, `blocked_at`, `blocked_reason` ni `reports_count` de un ítem bloqueado. Sí actualiza su nombre, imagen y categoría. Y los ítems bloqueados **jamás se eliminan**, ni siquiera si desaparecen del catálogo que envía la app — si se borraran, la tienda podría saltarse el bloqueo simplemente resincronizando.

Solo el administrador lo revierte. Lo mismo vale para el **bloqueo automático** al superar el umbral de denuncias: es la misma marca `blocked`, con el motivo puesto por el sistema.

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
├── Database/Migrations/              # 5 tablas + defaults de settings
├── Models/                           # Store, Item, Category, Report, Setting
├── Http/
│   ├── Controllers/Api/              # SyncController, StatusController
│   ├── Controllers/Admin/            # Store, Report, Category, Setting
│   ├── Middleware/                   # EnsureMarketplaceEnabled
│   └── Requests/                     # SyncRequest
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
│   │   ├── js/public/                # Marketplace.vue + components/MktIcon.vue
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
| `marketplace_settings` | Configuración editable, con tipo |

Ninguna tiene `price`, `stock` ni `reseller_id`.

### `PublishedScope`

Global scope en `Item`: solo son públicos los ítems `active` de tiendas `approved`. Es global precisamente para que sea imposible olvidarlo en el front.

El sync y el admin necesitan ver todo, así que usan **`Item::unscoped()`**.

### Los contadores hay que recalcularlos en toda acción que cambie qué se publica

`marketplace_stores.items_count` y `marketplace_categories.items_count` están denormalizados para que el filtro del front no tenga que contar en cada carga. Eso obliga a recalcularlos no solo en el sync, sino **también** al aprobar, rechazar, deshabilitar o habilitar una tienda, y al bloquear o desbloquear un producto.

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
| `POST` | `/marketplace/denuncia` | Registrar denuncia. `throttle:5,60` por IP |

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
| `API.md` | **Equipo móvil.** Contrato completo de los 2 endpoints |
| `FRONTEND.md` | Quien monte otra pantalla pública con Vue + Vite |
| `test.http` | Recorrido del ciclo de vida con REST Client de VSCode |

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
