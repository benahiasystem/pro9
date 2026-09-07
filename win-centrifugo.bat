@echo off
REM ============================================================================
REM  Arranca Centrifugo (WebSocket en vivo para mozo / vendeya).
REM
REM  No depende de scoop ni de rutas fijas: resuelve el binario y genera su
REM  configuracion a partir del .env del proyecto, asi funciona igual en
REM  cualquier equipo.
REM
REM  Binario, en este orden:
REM    1. Variable de entorno CENTRIFUGO_BIN (ruta completa al .exe)
REM    2. centrifugo en el PATH        -> scoop install centrifugo
REM    3. centrifugo\bin\centrifugo.exe dentro del proyecto (copia local)
REM
REM  Configuracion:
REM    Se genera en centrifugo\config.json leyendo APP_URL_BASE,
REM    CENTRIFUGO_URL y CENTRIFUGO_API_KEY del .env. Para usar una propia,
REM    define CENTRIFUGO_CONFIG con la ruta a tu archivo.
REM ============================================================================

echo ======================================
echo    CENTRIFUGO - WEBSOCKET EN VIVO
echo ======================================
echo.

setlocal

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
set "ENV_FILE=%PROJECT_DIR%\.env"
set "WORK_DIR=%PROJECT_DIR%\centrifugo"

REM --- 1. Localizar el binario ------------------------------------------------

set "BIN="

if defined CENTRIFUGO_BIN (
    if exist "%CENTRIFUGO_BIN%" (
        set "BIN=%CENTRIFUGO_BIN%"
    ) else (
        echo [AVISO] CENTRIFUGO_BIN apunta a una ruta inexistente:
        echo         %CENTRIFUGO_BIN%
        echo.
    )
)

if not defined BIN (
    for /f "delims=" %%A in ('where centrifugo 2^>nul') do (
        if not defined BIN set "BIN=%%A"
    )
)

if not defined BIN (
    if exist "%WORK_DIR%\bin\centrifugo.exe" set "BIN=%WORK_DIR%\bin\centrifugo.exe"
)

if not defined BIN (
    echo [ERROR] No se encontro el ejecutable de Centrifugo.
    echo.
    echo         Elige una de estas opciones:
    echo.
    echo         a^) Instalarlo con scoop:
    echo               scoop install centrifugo
    echo.
    echo         b^) Bajar el zip de https://github.com/centrifugal/centrifugo/releases
    echo            y copiar centrifugo.exe a:
    echo               %WORK_DIR%\bin\
    echo.
    echo         c^) Apuntar a una copia que ya tengas:
    echo               set CENTRIFUGO_BIN=C:\ruta\a\centrifugo.exe
    echo.
    pause
    exit /b 1
)

REM --- 2. Configuracion --------------------------------------------------------

if defined CENTRIFUGO_CONFIG (
    if not exist "%CENTRIFUGO_CONFIG%" (
        echo [ERROR] CENTRIFUGO_CONFIG apunta a una ruta inexistente:
        echo         %CENTRIFUGO_CONFIG%
        echo.
        pause
        exit /b 1
    )
    set "CONFIG=%CENTRIFUGO_CONFIG%"
    goto :run
)

if not exist "%ENV_FILE%" (
    echo [ERROR] No se encontro el .env del proyecto:
    echo         %ENV_FILE%
    echo.
    pause
    exit /b 1
)

set "APP_BASE="
set "API_KEY="
set "CENT_URL="

for /f "usebackq tokens=1,* delims==" %%A in (`findstr /b /c:"APP_URL_BASE=" "%ENV_FILE%"`) do set "APP_BASE=%%B"
for /f "usebackq tokens=1,* delims==" %%A in (`findstr /b /c:"CENTRIFUGO_API_KEY=" "%ENV_FILE%"`) do set "API_KEY=%%B"
for /f "usebackq tokens=1,* delims==" %%A in (`findstr /b /c:"CENTRIFUGO_URL=" "%ENV_FILE%"`) do set "CENT_URL=%%B"

REM Quitar comillas por si vienen entrecomilladas en el .env
if defined APP_BASE set "APP_BASE=%APP_BASE:"=%"
if defined API_KEY set "API_KEY=%API_KEY:"=%"
if defined CENT_URL set "CENT_URL=%CENT_URL:"=%"

if not defined APP_BASE (
    echo [ERROR] Falta APP_URL_BASE en el .env.
    echo         Se usa para autorizar el origen de las conexiones WebSocket.
    echo.
    pause
    exit /b 1
)

if not defined API_KEY (
    echo [ERROR] Falta CENTRIFUGO_API_KEY en el .env.
    echo         Es la clave con la que Laravel publica eventos por HTTP.
    echo.
    pause
    exit /b 1
)

REM Puerto: tercer campo de http://host:puerto. Sin puerto explicito, 8000.
set "PORT=8000"
if defined CENT_URL (
    for /f "tokens=3 delims=:" %%A in ("%CENT_URL%") do set "PORT=%%A"
)
set "PORT=%PORT:/=%"
if not defined PORT set "PORT=8000"

if not exist "%WORK_DIR%" mkdir "%WORK_DIR%"
set "CONFIG=%WORK_DIR%\config.json"

REM Se regenera en cada arranque: el .env es la unica fuente de verdad. Para una
REM config propia usa CENTRIFUGO_CONFIG en lugar de editar este archivo.
> "%CONFIG%" echo {
>>"%CONFIG%" echo   "http_server": { "address": "127.0.0.1", "port": %PORT% },
>>"%CONFIG%" echo   "client": {
>>"%CONFIG%" echo     "allow_anonymous_connect_without_token": true,
>>"%CONFIG%" echo     "allowed_origins": [
>>"%CONFIG%" echo       "https://%APP_BASE%",
>>"%CONFIG%" echo       "https://*.%APP_BASE%",
>>"%CONFIG%" echo       "http://%APP_BASE%",
>>"%CONFIG%" echo       "http://*.%APP_BASE%"
>>"%CONFIG%" echo     ]
>>"%CONFIG%" echo   },
>>"%CONFIG%" echo   "http_api": { "key": "%API_KEY%" },
>>"%CONFIG%" echo   "channel": {
>>"%CONFIG%" echo     "namespaces": [
>>"%CONFIG%" echo       {
>>"%CONFIG%" echo         "name": "restaurant",
>>"%CONFIG%" echo         "allow_subscribe_for_client": true,
>>"%CONFIG%" echo         "allow_subscribe_for_anonymous": true
>>"%CONFIG%" echo       }
>>"%CONFIG%" echo     ]
>>"%CONFIG%" echo   }
>>"%CONFIG%" echo }

:run

REM El cd es obligatorio: Centrifugo carga el .env del directorio actual, y si se
REM lanza parado en la raiz del proyecto se traga el .env de Laravel.
cd /d "%WORK_DIR%"

echo [INFO] Binario: %BIN%
echo [INFO] Config:  %CONFIG%
echo [INFO] Interno: http://127.0.0.1:%PORT%  ^(Laravel publica aqui^)
echo [INFO] Publico: wss://ws.%APP_BASE%/connection/websocket  ^(via Apache^)
echo.
echo Requiere Apache levantado para el proxy wss.
echo Presiona Ctrl+C para detener.
echo.

"%BIN%" -c "%CONFIG%"

echo.
echo [INFO] Centrifugo se detuvo.
pause >nul
