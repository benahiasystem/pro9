# Conexión Offline VendeYa

Este módulo conecta una instalación de escritorio VendeYa con un tenant de
Pro9. Permite trabajar temporalmente sin conexión y sincronizar después, en
orden, aperturas y cierres de caja, facturas, notas de venta y anulaciones.

La integración respeta el contrato venezolano del proyecto:

- admite nuevas Facturas (`01`) y Notas de venta (`80`), no nuevas Boletas;
- usa series dedicadas por máquina para evitar colisiones de correlativos;
- registra comprobantes y anulaciones localmente;
- genera el PDF comercial, pero no XML, firma, CDR ni transmisión fiscal;
- conserva el payload original y una bitácora idempotente para auditoría y
  reintentos.

## Flujo

1. Un administrador consulta `GET /api/sync/enroll-options` y enrola la máquina
   con `POST /api/sync/enroll`, seleccionando series dedicadas libres del mismo
   establecimiento.
2. El token de máquina se entrega una sola vez. Las solicitudes posteriores
   usan `X-Machine-Token`.
3. `GET /api/sync/snapshot` descarga empresa, establecimiento, usuarios,
   series, productos y clientes para el trabajo offline.
4. `POST /api/sync/batch` recibe eventos ordenados por `seq`. Cada
   `external_id` es único: reenviar un evento devuelve su resultado almacenado
   y no duplica la operación.
5. El panel `/sync` permite revocar máquinas, liberar sus series, revisar la
   bitácora, reintentar errores y descartarlos sin borrar la auditoría.

## Tipos de evento

| Tipo | Resultado |
|---|---|
| `cash_open` | Abre o reutiliza la caja activa del usuario |
| `sale` | Valida y registra una Factura con la cadena común de Pro9 y genera PDF |
| `sale_note` | Registra una Nota de venta, la vincula con la caja y genera PDF |
| `void` | Marca localmente como anulada la venta perteneciente a la máquina |
| `cash_close` | Cierra la caja activa del usuario |

`sync:process-voids` permanece disponible para reprocesar eventos `void` que
hubieran quedado `pending` en una versión anterior. La operación no depende de
servicios fiscales externos.

## Seguridad y aislamiento

- El enrolamiento exige autenticación API y usuario administrador.
- Solo pueden asignarse series `01` o `80`, dedicadas, libres y pertenecientes
  al establecimiento elegido.
- El token se guarda como SHA-256 y una máquina revocada deja de autenticar.
- Las rutas del panel usan `auth` y `locked.tenant`; las rutas de sincronización
  usan el contexto tenant y el middleware `auth.machine`.
- Cada venta comprueba que su serie pertenece al grupo de la máquina.

## Estructura principal

```text
modules/Sync/
├── Http/Controllers/SyncController.php
├── Http/Controllers/PanelController.php
├── Http/Middleware/AuthenticateMachine.php
├── Models/OfflineMachine.php
├── Models/SyncEvent.php
├── Services/BatchProcessor.php
├── Services/VoidProcessor.php
├── Console/ProcessVoidsCommand.php
├── Routes/api.php
└── Routes/web.php
```

Las tablas tenant se crean mediante las migraciones canónicas
`2026_09_04_000008` a `2026_09_04_000010`.
