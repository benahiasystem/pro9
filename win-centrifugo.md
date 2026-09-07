# Centrifugo en local (Windows + Laragon)

Guía para dejar funcionando el WebSocket en tiempo real de las apps **mozo** y
**vendeya** en un entorno de desarrollo con Laragon.

Sin esto la app igual funciona, pero pierde el tiempo real: el indicador de la
barra superior de mozo queda en amarillo ("sincronización caída"), las mesas y
comandas no se refrescan solas entre dispositivos y la consola del navegador
repite `WebSocket connection to 'wss://ws.TU-DOMINIO/connection/websocket' failed`.

---

## Cómo encaja cada pieza

```
Laravel  --HTTP /api/publish-->  Centrifugo  <--wss--  mozo / vendeya
         (127.0.0.1:8000)                    (a través de Apache)
```

- **Laravel publica** eventos por la API HTTP de Centrifugo. Esa ruta es interna
  y sale de `CENTRIFUGO_URL` + `CENTRIFUGO_API_KEY` del `.env`.
- **Las apps se conectan** a `wss://ws.{dominio-base}`, un subdominio que Apache
  proxea hacia Centrifugo. Esa URL **no** sale del `.env`: la deriva
  `RestaurantController::buildConfigResponse()` a partir del dominio del tenant
  y la entrega en `/config.json`.
- Cada tenant tiene su propio canal: `restaurant:{fqdn}`.

Ejemplo con el tenant `demo.miempresa.test`:

| Cosa | Valor |
|---|---|
| `APP_URL_BASE` en `.env` | `miempresa.test` |
| Subdominio del WebSocket | `ws.miempresa.test` |
| Canal del tenant | `restaurant:demo.miempresa.test` |

---

## 1. Instalar Centrifugo

**Versión recomendada: 6.9.2** (rama v6).

La rama importa: el cliente `centrifuge-js` está fijado en **5.7.0 exacto** en el
`package.json` de mozo, y las versiones anteriores a 5.5.0 no hablan el protocolo
de Centrifugo v6 (fallan con `bad request`, código 3501). No mezclar ramas.

Elige **una** de estas tres opciones — el `win-centrifugo.bat` las busca en este
mismo orden:

### a) Con scoop (lo más cómodo)

```powershell
scoop install centrifugo
```

Queda en el PATH y se actualiza con `scoop update centrifugo`.

### b) Descarga manual del zip

1. Bajar `centrifugo_6.9.2_windows_amd64.zip` de
   <https://github.com/centrifugal/centrifugo/releases/tag/v6.9.2>
2. Descomprimir y copiar `centrifugo.exe` a la carpeta `centrifugo\bin\` **dentro
   de este proyecto** (créala si no existe):

```
<proyecto>\centrifugo\bin\centrifugo.exe
```

Esa carpeta está en `.gitignore`, así que el `.exe` no se sube al repo.

### c) Una copia que ya tengas en otro lado

```powershell
setx CENTRIFUGO_BIN "C:\ruta\a\centrifugo.exe"
```

Abre una terminal nueva después de `setx` para que tome la variable.

Para comprobar la versión instalada:

```powershell
centrifugo version
```

---

## 2. Configurar el `.env` del proyecto

Agrega estas dos líneas:

```dotenv
CENTRIFUGO_URL=http://127.0.0.1:8000
CENTRIFUGO_API_KEY=pega-aqui-una-clave-larga
```

Genera la clave con PowerShell:

```powershell
$b = New-Object byte[] 32
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b)
[Convert]::ToBase64String($b).TrimEnd('=').Replace('+','-').Replace('/','_')
```

Luego limpia la caché de configuración:

```powershell
php artisan config:clear
```

> **No hace falta crear ningún archivo de configuración de Centrifugo.**
> `win-centrifugo.bat` lo genera en cada arranque a partir del `.env`
> (`APP_URL_BASE` para los orígenes permitidos, `CENTRIFUGO_API_KEY` para la API
> y el puerto de `CENTRIFUGO_URL`). El `.env` es la única fuente de verdad.
>
> Si necesitas una configuración propia, apunta la variable `CENTRIFUGO_CONFIG`
> a tu archivo y el `.bat` la usará tal cual sin generar nada.

---

## 3. Apache: habilitar los módulos de proxy

Edita `C:\...\laragon\bin\apache\httpd-2.4.XX-winXX-VSXX\conf\httpd.conf` y
**descomenta** estas tres líneas (quítales el `#`):

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
```

Hazlo **antes** del paso 4. Si agregas el vhost con los módulos apagados, Apache
no arranca (`Invalid command 'ProxyPass'`) y te quedas sin el sitio.

---

## 4. Apache: vhost del subdominio `ws`

Crea `C:\...\laragon\etc\apache2\sites-enabled\01-ws.TU-DOMINIO.conf`,
reemplazando `TU-DOMINIO` por tu `APP_URL_BASE` y las rutas por las tuyas:

```apache
<VirtualHost *:443>
    ServerName ws.TU-DOMINIO

    SSLEngine on
    SSLCertificateFile      C:/Aplicaciones/laragon/etc/ssl/laragon.crt
    SSLCertificateKeyFile   C:/Aplicaciones/laragon/etc/ssl/laragon.key

    ProxyRequests Off
    ProxyPreserveHost On

    # Handshake WebSocket (mod_proxy_wstunnel)
    ProxyPass        /connection/websocket ws://127.0.0.1:8000/connection/websocket
    ProxyPassReverse /connection/websocket ws://127.0.0.1:8000/connection/websocket

    # Resto de endpoints de Centrifugo
    ProxyPass        / http://127.0.0.1:8000/
    ProxyPassReverse / http://127.0.0.1:8000/
</VirtualHost>
```

**El prefijo `01-` en el nombre del archivo no es decorativo.** El vhost que
genera Laragon (`auto.TU-DOMINIO.conf`) trae `ServerAlias *.TU-DOMINIO`, que
también matchea `ws.`. Apache incluye los `.conf` en orden alfabético y gana el
primero que matchea, así que el nuestro tiene que quedar antes de `auto.`.

Verifica la sintaxis antes de reiniciar:

```powershell
& "C:\...\laragon\bin\apache\httpd-2.4.XX-winXX-VSXX\bin\httpd.exe" -t
```

Debe responder `Syntax OK`. Después reinicia Apache desde el menú de Laragon.

### Sobre el certificado

El `laragon.crt` tiene que cubrir `*.TU-DOMINIO`. Laragon lo regenera al crear
sitios, así que normalmente ya está. Compruébalo:

```powershell
certutil -dump "C:\...\laragon\etc\ssl\laragon.crt" | Select-String "DNS"
```

Esto importa más de lo que parece: **un error de certificado en `wss://` no se
puede aceptar con un clic** como en una pestaña normal. El navegador corta la
conexión en silencio y solo ves el `failed` en consola.

---

## 5. Agregar el host

Como administrador, agrega a `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1      ws.TU-DOMINIO
```

---

## 6. Arrancar

Doble clic en **`win-centrifugo.bat`** (en la raíz del proyecto). Deja la ventana
abierta mientras trabajas; `Ctrl+C` para detener.

Al arrancar muestra qué binario y qué config está usando:

```
[INFO] Binario: C:\...\centrifugo.exe
[INFO] Config:  <proyecto>\centrifugo\config.json
[INFO] Interno: http://127.0.0.1:8000  (Laravel publica aqui)
[INFO] Publico: wss://ws.TU-DOMINIO/connection/websocket  (via Apache)
```

Apache tiene que estar levantado: el `.bat` solo arranca Centrifugo, el proxy
`wss` lo hace Apache.

---

## Verificar que quedó bien

1. Abre mozo en el navegador y mira el **indicador de la barra superior**: verde
   es que está suscrito y recibiendo eventos; amarillo es que el socket está caído.
2. En la consola del navegador no debe repetirse el error de `WebSocket connection
   to ... failed`.
3. Prueba manual de la API HTTP (con Centrifugo corriendo):

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/publish" -Method Post `
  -Headers @{ "X-API-Key" = "TU-CENTRIFUGO_API_KEY" } `
  -ContentType "application/json" `
  -Body '{"channel":"restaurant:TU-TENANT.TU-DOMINIO","data":{"event":"ping","payload":{}}}'
```

Debe responder `{"result":{}}`. Si devuelve **401**, la key del `.env` no coincide
con la que está usando Centrifugo (regenera la config cerrando y volviendo a
abrir el `.bat`).

---

## Problemas comunes

| Síntoma | Causa | Solución |
|---|---|---|
| `WebSocket ... failed` en bucle | Centrifugo no está corriendo | Arranca `win-centrifugo.bat` |
| **502** en el handshake | Apache proxea pero Centrifugo está caído | Igual que arriba |
| **403** en el handshake | El origen no está en `allowed_origins` | `APP_URL_BASE` del `.env` no coincide con el dominio real; corrígelo y reinicia el `.bat` |
| `Invalid command 'ProxyPass'` y Apache no arranca | Módulos de proxy apagados | Paso 3 |
| El `wss` va al sitio normal en vez de a Centrifugo | El vhost quedó después de `auto.*` | Renombra el archivo con prefijo `01-` |
| Error de certificado / falla sin mensaje claro | `laragon.crt` no cubre `*.TU-DOMINIO` | Regenera el certificado desde Laragon |
| `permission denied` código **103** al suscribir | Config de namespace incompleta | Necesita `allow_subscribe_for_client` **y** `allow_subscribe_for_anonymous`; el `.bat` ya los pone, no edites el `config.json` a mano |
| Centrifugo arranca con variables raras | Cargó el `.env` de Laravel | Centrifugo lee el `.env` del directorio actual; por eso el `.bat` hace `cd` a `centrifugo\` antes de lanzarlo. No lo ejecutes a mano desde la raíz del proyecto |
| Laravel no publica y no da error | `CentrifugoService::publish()` traga las excepciones | Revisa `storage\logs` buscando `Centrifugo publish failed` |

---

## Notas

- La carpeta `centrifugo\` del proyecto está en `.gitignore`: el `config.json`
  generado contiene la API key y el `bin\` puede contener el ejecutable.
- No edites `centrifugo\config.json` a mano: se regenera en cada arranque. Para
  personalizar, usa `CENTRIFUGO_CONFIG`.
- En producción no aplica nada de esto: ahí Centrifugo corre como servicio y el
  subdominio `ws.` se resuelve por DNS real.
