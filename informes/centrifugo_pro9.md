# Centrifugo en Pro9

Configuración local y verificación del 11/11/2026.

## Servicio y conexiones

- Docker: `pro9-centrifugo`, imagen fija `centrifugo/centrifugo:v6.10.3`, red `pro9_default`, reinicio `unless-stopped` y healthcheck `/health`.
- Laravel publica mediante `http://centrifugo:8000/api/publish`, con `X-API-Key`. No se publica el puerto 8000 en el host.
- El navegador utiliza `ws://ws.localhost/connection/websocket`. Nginx admite únicamente esa ruta en los hosts `ws.*`; la API, el healthcheck y las demás rutas del servicio quedan dentro de Docker.
- Motor en memoria para una instancia. Al suscribirse o reconectarse, Mozo recupera por HTTP el estado vigente de mesas, comandas y stock.
- Configuración del servidor: `docker/centrifugo/config.json`. Namespace habilitado: `restaurant`. No se habilitan suscripciones anónimas ni publicación desde clientes.

## Autenticación y autorización

`GET /api/restaurant/realtime/token` pertenece al grupo API tenant, con `auth:api`, `locked.tenant` y el límite de peticiones vigente. El controlador comprueba hostname/website, usuario tenant activo y administrador o rol de restaurante existente.

El backend genera dos JWT HS256 de cinco minutos:

- Conexión: `sub = website:{website_id}:user:{user_id}`.
- Suscripción: mismo sujeto, más `channel = restaurant:{fqdn}`.

El hostname, website y usuario se obtienen en servidor. Los parámetros del solicitante no eligen empresa, usuario ni canal. El website en el sujeto diferencia usuarios con el mismo ID local en distintas empresas. Centrifugo comprueba firma, caducidad y correspondencia entre sujeto/canal del token y la suscripción solicitada.

El endpoint devuelve `Cache-Control: private, no-store`. El secreto HMAC y la clave de publicación son distintos, se guardan en `.env` y no se entregan al navegador. La aplicación renueva ambos JWT mediante el endpoint autenticado y desconecta el transporte al cerrar sesión o cambiar el token de usuario. Una credencial ya emitida tiene como vigencia máxima cinco minutos; la renovación vuelve a comprobar los permisos actuales.

Fuentes oficiales: [autenticación JWT](https://centrifugal.dev/docs/server/authentication), [autorización del canal](https://centrifugal.dev/docs/server/channel_token_auth), [configuración por entorno](https://centrifugal.dev/docs/server/configuration).

## Variables

```dotenv
CENTRIFUGO_URL=http://centrifugo:8000
CENTRIFUGO_WS_URL=ws://ws.localhost
CENTRIFUGO_API_KEY=<clave-privada-aleatoria>
CENTRIFUGO_TOKEN_HMAC_SECRET_KEY=<otro-secreto-privado-aleatorio>
CENTRIFUGO_ALLOWED_ORIGINS="http://*.localhost"
```

`CENTRIFUGO_ALLOWED_ORIGINS` contiene orígenes separados por espacios, conforme al formato de arrays de strings de Centrifugo. Ejemplo para un dominio con HTTPS: `"https://*.ejemplo.com"`. Las claves se traducen en Compose a `CENTRIFUGO_HTTP_API_KEY` y `CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY`.

Para inicializar este entorno local, el script conserva los valores existentes y genera únicamente las claves que falten, sin mostrarlas:

```bash
python3 scripts/configure-centrifugo.py
docker compose up -d centrifugo
docker compose exec nginx nginx -t
docker compose exec nginx nginx -s reload
```

En una instalación nueva completa, después de inicializar `.env`, `docker compose up -d` también inicia las dependencias. Si existe una caché de configuración de Laravel, regenerarla conforme al procedimiento de despliegue.

Para otro dominio hay que definir su URL WebSocket y sus orígenes, dirigir `ws.{dominio}` al proxy y configurar TLS en el proxy para `wss://`. Lo verificado aquí corresponde al entorno HTTP local `bbc.localhost`.

## Fuentes y compilación de Mozo

Este repositorio incluye la distribución compilada de la aplicación independiente Mozo. La integración editable está en `resources/js/mozo/realtime.js` y utiliza `centrifuge@5.8.0`, fijado en el lockfile.

Con las dependencias npm instaladas:

```bash
npm run build:mozo-realtime
```

La compilación genera un módulo con hash y `public/mozo/realtime-manifest.json`. Un import map, presente tanto en la vista Blade como en el HTML estático de Mozo, sustituye el módulo de tiempo real original por el autenticado. Conserva los exports que usan las pantallas existentes y comparte sus instancias Vue/Pinia. No recompila los assets Vue 2 de Pro9.

Si se reemplaza la distribución de Mozo, ejecutar nuevamente este build y verificar las importaciones de Vue/Pinia y los stores del nuevo proveedor. El script toma los paths de la distribución activa; sus aliases deben seguir siendo compatibles. La vista Blade comprueba que el manifiesto corresponda al entry actual. Los clientes que conservaran el documento antiguo en caché requieren recargar para recibir el import map nuevo.

`CentrifugoService` comprueba tanto HTTP como el resultado JSON de cada publicación. Los rechazos y excepciones se registran sin claves, tokens ni payloads comerciales; una indisponibilidad del servicio conserva la política de publicación no fatal.

## Evidencia

Prueba aislada, sin Kernel ni conexiones reales a bases/colas/red:

```bash
docker exec pro9-php vendor/bin/phpunit tests/Unit/CentrifugoSecurityTest.php
```

Resultado: 12 pruebas, 28 aserciones correctas. Comprueban firma y caducidad, sujetos distintos entre empresas con el mismo ID de usuario, parámetros de canal ignorados, rechazo a visitantes/clientes ecommerce/usuarios bloqueados/sin rol, acceso legítimo de mozo y administrador, hostname discordante, secreto ausente y errores HTTP/JSON al publicar.

Verificación integral contra Docker, la API tenant, Centrifugo y Chromium:

```bash
# Playwright Core y Chromium deben estar disponibles; los paths se pueden configurar.
PLAYWRIGHT_PACKAGE=/tmp/pro9-centrifugo-qa/package.json \
  node scripts/verify-centrifugo.cjs
```

El script admite `PRO9_QA_HOST`, `PRO9_QA_OUTPUT` y `CHROMIUM_PATH`. Usa en memoria el token existente de un administrador local; no lo registra ni crea archivos con credenciales.

Casos acreditados en `informes/evidencias/centrifugo_2026_11_11/verification.json`:

- HTTP 401 al visitante y 200 privado al usuario autenticado; un parámetro de canal ajeno no altera el canal emitido.
- Centrifugo rechaza conexión anónima, JWT caducado/alterado, suscripción sin JWT, token de otra empresa con el mismo ID local y publicación desde el cliente.
- La API de publicación por el dominio WebSocket devuelve 404.
- Mozo conecta y muestra una mesa temporal creada por el endpoint real; recibe y aplica los snapshots reales de comandas y stock. Las alteraciones para comprobar estos dos últimos stores se realizan sólo en la memoria del navegador de prueba.
- Tras reiniciar Centrifugo, se abre un transporte nuevo y se recuperan las mesas desde el backend después de vaciar sólo el store del navegador.
- Se comprueban los comandos de renovación de conexión y suscripción con JWT iniciales de cinco segundos. Las siguientes credenciales se obtienen del endpoint autenticado real, cuya vigencia configurada sigue siendo 300 segundos.
- Cerrar sesión desconecta el cliente. No se producen errores JavaScript de página.
- La mesa temporal se elimina al finalizar y se publica el estado restaurado. No se generan ventas, cobros ni movimientos de inventario.

Captura: `informes/evidencias/centrifugo_2026_11_11/mozo-conectado.png`.
