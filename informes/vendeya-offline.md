# VendeYa: ventas persistentes y sincronización offline

La reconstrucción de VendeYa usa una intención identificada por UUID para cada venta. El tenant y el usuario se resuelven mediante el hostname y el Bearer existentes. Los importes, tasas, fecha y vendedor del payload se validan con las reglas actuales de Pro9; un rechazo conserva la intención y no cambia lo cobrado.

## Contrato HTTP

Las cuatro rutas pertenecen al grupo `auth:api` y `locked.tenant` de `routes/api.php`:

| Método y ruta | Resultado |
| --- | --- |
| `GET /api/sellnow/offline-context` | `data.tenant`, `user_id`, `establishment_id`, permisos, `cash`, `available_cash` autorizado y `capabilities.offline_sales` |
| `POST /api/sellnow/sales` | Confirma o recupera una operación usando su UUID |
| `GET /api/sellnow/sales/{uuid}` | Consulta una operación de la misma identidad; 404 para otras identidades |
| `PATCH /api/sellnow/sales/{uuid}/cash` | Cambia expresamente la caja de una intención bloqueada por caja, antes de crear su documento |

El cuerpo del POST es `{client_sale_id, device_id, recorded_at, cash_id, kind, payload}`. Ambos identificadores son UUID. `kind` acepta `document` y `sale_note`; `payload` conserva los contratos actuales de factura y nota, con origen `VENDEYA` impuesto por el servidor.

Las respuestas llevan `data` con `client_sale_id`, `status`, `original_cash_id`, `cash_id`, `recorded_at`, `result`, `error` y `effects`. Los estados son `pending`, `processing`, `needs_attention` y `confirmed`. Un rechazo conocido responde 422 con la operación y sus campos de error; un UUID con contenido distinto responde 409 sin devolver un comprobante que pudiera confundirse con el nuevo contenido.

Una operación `confirmed` acredita persistencia comercial. La confirmación fiscal sigue en `result.data.fiscal_emission`; puede estar pendiente de HKA. Las vistas de emisión, correo y descargas se refrescan en las consultas sin ejecutar peticiones externas.

## Persistencia, caja y efectos

`sellnow_sales` conserva un índice único por UUID, hash canónico del envío, usuario, establecimiento, payload original, caja original y elegida, historial de reasignación, comprobante, resultado y estado de los efectos posteriores. La unicidad y los bloqueos de fila resuelven solicitudes concurrentes con el mismo UUID.

La transacción tenant incluye creación del comprobante, cobros, inventario, enlaces de caja y confirmación del UUID. El total calculado debe coincidir con el total capturado en la representación decimal de transferencia de Pro9 (12 posiciones, HALF_UP; los cálculos internos siguen siendo racionales); una diferencia revierte toda la operación comercial y conserva la intención para revisión. La factura pasa por `InputRequest` y sus transformadores, validadores y resolutores originales. La nota utiliza su mismo controlador y políticas nativas, aplazando únicamente PDF e impresión. Los endpoints anteriores conservan su comportamiento por defecto. En las respuestas nuevas, `print.reason: not_requested` indica que no se pidió impresión automática del servidor y permite al cliente usar BuhoPrinter según su configuración; no significa que las impresoras estén deshabilitadas.

`CashContext` transporta la caja ya autorizada dentro de esa transacción y se restaura en `finally`. Lo usan los destinos de efectivo, los movimientos bancarios y los enlaces de los observadores. Así se evita que un administrador que seleccione otra caja termine registrando el dinero en su propia caja activa. Se conserva `CashLedger::authorize`: un vendedor no puede elegir la caja de otro usuario y el administrador conserva el alcance autorizado por Pro9.

`CASH_CLOSED` y `CASH_UNAVAILABLE` permiten reasignación. El PATCH mantiene el UUID, el hash y el cuerpo original; únicamente cambia la caja persistida y añade actor, fecha y origen/destino al historial. El siguiente POST puede repetir exactamente el envío original. No se reasigna un documento ya creado.

Los efectos `pdf`, `email` y `print` se registran antes del commit comercial. `SaleEffects` adquiere una marca durable por efecto. La generación de PDF admite recuperación; la creación de una orden de impresión y su confirmación se guardan juntas. Una entrega externa cuyo resultado sea incierto no se vuelve a enviar automáticamente. El observador publica en Redis después del commit: una orden revertida no llega a BuhoPrinter. Los PDF HKA A4/A5 se preparan fuera de la transacción y la orden consume únicamente su copia ya almacenada. El ciclo fiscal/HKA mantiene sus identificadores y estados nativos: una respuesta perdida no crea otra factura ni inicia una nueva emisión.

## Operación y despliegue

Aplicar únicamente la migración `database/migrations/tenant/2026_11_12_000001_create_sellnow_sales_table.php` en los tenants que usarán esta versión, después de las pruebas. El contexto publica capacidad offline 0 hasta que exista la tabla. Publicar el backend y la migración antes de activar el cliente reconstruido.

La migración ya fue aplicada por el agente principal exclusivamente al website 26 (`bbc.localhost`, esquema `tenancy_bbc`), después de validar la suite. La comprobación de solo lectura encontró 20 columnas, el índice único del UUID y cero operaciones; se conserva en `informes/evidencias/vendeya-offline/bbc-application-receipt.json`. El comando acotado utilizado fue:

```bash
docker exec pro9-php php artisan tenancy:migrate --website_id=26 \
  --path=database/migrations/tenant/2026_11_12_000001_create_sellnow_sales_table.php \
  --force --no-interaction
```

La tarea existente de Laravel incorpora `tenancy:run sellnow:recover` cada minuto. El comando procesa como máximo 100 operaciones con efectos pendientes por ejecución, con marcas durables entre trabajadores y prioridad al trabajo cuya última actualización es más antigua; un fallo permanente no retiene indefinidamente los primeros 100 puestos. Puede ejecutarse en el contexto de un tenant con `php artisan sellnow:recover --limit=100`. No vuelve a crear documentos.

No borrar filas confirmadas para limpiar la cola del navegador: son la evidencia que evita duplicados tras reinstalaciones o respuestas perdidas. El `down` de la migración elimina esa tabla; un rollback del frontend debe conservarla y desactivar primero los envíos. El código no sustituye los archivos publicados de Mozo o VendeYa.

## Validación

La suite `tests/Unit/SellnowSalePersistenceTest.php` crea bases con el prefijo exclusivo `pro9_fiscal_test_` y las elimina al terminar. Incluye factura/nota nativas, rechazo con rollback, caja cerrada y reasignación, cobros bancarios y de efectivo en una caja elegida por administrador, aislamiento por identidad, solicitudes simultáneas, caída después del commit, recuperación de efectos y reanudación de un trabajador con lease de impresión vencido después de que otro trabajador haya confirmado la misma orden. El caso HTTP arranca el kernel real, comprueba Bearer, rutas, 409/422, reasignación y descarga de PDF.

Ejecutar desde la raíz del repositorio:

```bash
PRO9_FISCAL_MYSQL_TESTS=1 DB_HOST=127.0.0.1 DB_PORT=3306 \
  php vendor/bin/phpunit tests/Unit/SellnowSalePersistenceTest.php
```

Los resultados y límites efectivamente observados se registran en `informes/evidencias/vendeya-offline/`. Estas pruebas no emiten facturas en el tenant BBC ni imprimen en hardware real.

La validación final combinada pasó **13 pruebas y 235 aserciones**, sin errores, fallos ni casos omitidos. Además de los diez casos nuevos, ejecutó los endpoints anteriores de factura en modo digital y forma libre, y el rechazo atómico de una nota con moneda retirada. Incluyó el límite real `--limit=1`, el avance tras un fallo permanente y el solapamiento entre trabajadores cuando vence una marca de impresión. Los archivos definitivos son `final-junit.xml`, `final.log`, `source-verification.json` y `backend-validation.json`; este último registra los comandos y los límites de la evidencia.

Para repetir exactamente la selección final:

```bash
PRO9_FISCAL_MYSQL_TESTS=1 PRO9_FISCAL_TIMING=1 DB_HOST=127.0.0.1 DB_PORT=3306 \
  php vendor/bin/phpunit -c informes/evidencias/vendeya-offline/final-suite.xml \
  --filter 'SellnowSalePersistenceTest|test_api_invoice_from_restaurant_checks_method_currency_and_bank_atomically|test_api_sale_note_rejects_retired_currency_and_rolls_back_imported_customer' \
  --log-junit informes/evidencias/vendeya-offline/final-junit.xml
```

Cada caso elimina su base temporal propia y sus archivos al terminar; el registro de apagado añade limpieza si el proceso finaliza durante una prueba. No quedaron trabajadores PHP de esta suite. El inventario posterior detectó cinco esquemas temporales sin conexiones, cuatro antiguos y uno parcial anterior a la corrida final; no se eliminaron esquemas cuya procedencia no estaba acreditada. No se ejercitaron impresoras físicas, SMTP real ni proveedores fiscales remotos.
