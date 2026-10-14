# Órdenes de entrega y HKA DEMO

Fecha de verificación: 13 de octubre de 2026. Tenant: `bbc.localhost`.

## Resultado

Implementado el registro idempotente, la emisión automática después del commit, la consulta y conciliación, el PDF original A4 privado y las relaciones con facturas. Pro9 conserva tipo `09`; HKA recibe `04`, `esGuiaDespacho=1`. Se conservan las tablas, rutas y numeración comerciales. Producción, anulación remota, correo HKA de órdenes y ampliación de la API pública quedan fuera de alcance.

BBC conserva **23 órdenes confirmadas**, con control y PDF válido: 22 escenarios repetibles y una orden creada, corregida, consultada y descargada desde el navegador. Conserva también dos facturas confirmadas VES/USD y **cinco relaciones confirmadas en ambos sentidos**. El manifiesto contiene IDs, claves estables, respuestas saneadas, controles y hashes SHA-256 de los PDF; no contiene credenciales, JWT ni el contexto privado de autenticación.

## Cambios y hallazgos corregidos

| Hallazgo | Corrección |
|---|---|
| Se aceptaban datos y snapshots de transporte enviados por el cliente. | Resolver sucursal, cliente, direcciones, productos, conductor, vehículo y transportista desde registros autorizados del tenant antes de escribir. Validar peso positivo, fechas, motivos, bienes/unidades activas y cantidades de hasta cuatro decimales. |
| Doble clic o repetición podían producir otra orden. | UUID de creación y hash comercial; la misma solicitud devuelve la misma orden y una clave con datos distintos devuelve 409. Bloqueo de empresa y numeración dentro de la transacción. |
| Inventario usaba el almacén del usuario o repetía movimientos al reemplazar líneas. | Usar el almacén de la sucursal de la orden, restaurar líneas anteriores una vez y aplicar las nuevas dentro de la misma transacción. Preservar el descuento de stock congelado al editar el mismo motivo. |
| Lotes/seriales podían perderse o reutilizarse. | Comprobar pertenencia, almacén, disponibilidad, cantidades enteras para seriales y ausencia de duplicados. Edición y reversión restauran cantidades y estado de seriales. |
| La factura desde múltiples órdenes podía descontar nuevamente cantidades ya trasladadas. | `DeliveryOrderInvoiceInventory` conserva en servidor la cantidad pendiente; sólo esa cantidad genera stock y Kardex. Restaura sólo el movimiento de la factura al revertirla. Reconstruye lotes y seriales desde las órdenes. |
| Conversiones sumaban incorrectamente productos repetidos o usaban otra sucursal. | Sumar cantidades por producto, conservar la sucursal autorizada al precargar la factura y mostrar referencias con el helper de ocho dígitos, sin guion inicial en series vacías. La conversión individual también conserva la sucursal de origen. |
| El formulario ofrecía códigos de peso ausentes del catálogo local. | Seleccionar `KG`/`TON`; conservar sus equivalencias HKA `KGM`/`TNE` para bienes cuando correspondan. Añadir descripción del servicio para reparación y catálogos condicionales HKA. |
| El selector enviaba un precio oculto aunque la valorización estuviera desactivada. | Ignorar ese precio cuando `enabled_price_items_dispatch=false`. Para guías valorizadas VES congelar cálculos canónicos de IVA por línea; no fabricar pagos ni registros de impuestos comerciales. |
| HKA podía dejar la orden incierta o rechazada después del guardado. | Persistir intención y snapshot primero. HTTP después del commit y fuera de bloqueos. Una falla conserva orden e inventario; una incertidumbre requiere consulta antes de reenviar el mismo UUID/serie/número/payload. |
| No había interfaz fiscal ni descarga original autenticada para órdenes. | Estados y acciones en creación/cierre/listado; PDF comercial local separado del original HKA. POST de envío, consulta y conciliación; PDF privado por tenant/operación, con validación de bytes e identidad/control. |
| Se permitía edición/anulación local incompatible con el estado fiscal. | Bloquear órdenes pendientes, inciertas o confirmadas y órdenes ya relacionadas con factura. La asociación fiscal posterior conserva sus líneas. |

Los usuarios deben tener acceso al módulo; los usuarios distintos del administrador quedan sujetos a su sucursal. El envío manual y la conciliación manual requieren administrador. La consulta y el PDF requieren acceso al registro dentro del tenant/sucursal. Tenants existentes sin ampliación de esquema conservan su flujo local; la integración nueva se habilitó en BBC y en el esquema inicial para instalaciones nuevas.

## Esquema y datos

El consolidado incorpora `dispatches.creation_key` nullable único, `creation_hash`, `hka_data` JSON y `dispatch_emissions` con una operación por orden, UUID único, versión, snapshot, payload, respuesta saneada, estado, control y archivo. Las claves foráneas se añaden en `000999` y se retiran en orden inverso. Inventario actual: **346 tablas y 347 migraciones tenant**. Facturas y notas conservan `document_emissions`.

En BBC se aplicó exclusivamente la ampliación aditiva autorizada. No se ejecutó reset, reinstalación, reconstrucción, borrado ni sustitución de catálogos. Se añadió:

- Una sucursal con almacén y una dirección de origen adicional, sin reemplazar la dirección existente de la oficina principal.
- Cuatro series `09`, incluidas series vacías y series `PRUEBA-OE-*`. Las identidades enviadas a HKA usan serie vacía y números desde `1600000000` y `1600100000`, evitando las identidades DEMO anteriores. `ConsultaNumeraciones` devolvió HTTP 200/código 201 sin coincidencias; la disponibilidad se comprobó después mediante aceptación real, sin asignar reservas remotas.
- Tres clientes sintéticos J/V/E, seis direcciones adicionales, dos transportistas, dos conductores y dos vehículos.
- Cuatro bienes UND/KG/lote/serial y ocho operaciones explícitas de apertura de existencias, además de registros auxiliares generados por el flujo normal; un lote y tres seriales.
- Una cotización, un pedido, una nota de venta, dos cuentas bancarias sintéticas y dos facturas DEMO confirmadas.

Todos los motivos `04`, `21`, `22`, `23`, `24` conservan `discount_stock=0` en BBC. Los escenarios con descuento habilitado, concurrencia, rollback, modificación de configuración y reversión se ejecutaron en bases temporales. El manifiesto registra también la conciliación de metadatos del lote de la factura sintética USD creada antes de corregir la conversión: se aplicó su cantidad física ya acreditada por Kardex al lote, una sola vez, sin repetir stock ni modificar campos fiscales.

Generador repetible: `scripts/prepare_delivery_order_demo.php`; limitado a BBC digital DEMO, con bloqueo de ejecución y manifiesto persistente. Ejecutar secuencialmente:

```sh
docker compose exec -T php php scripts/prepare_delivery_order_demo.php bbc.localhost prepare
docker compose exec -T php php scripts/prepare_delivery_order_demo.php bbc.localhost emit
docker compose exec -T php php scripts/prepare_delivery_order_demo.php bbc.localhost documents
docker compose exec -T php php scripts/prepare_delivery_order_demo.php bbc.localhost emit
docker compose exec -T php php scripts/prepare_delivery_order_demo.php bbc.localhost audit
```

Conservar el manifiesto: sus claves identifican las órdenes ya creadas. Reejecutar no reproduce operaciones comerciales existentes; consulta las emisiones confirmadas. `emit <caso>` permite revisar sólo un escenario. El modo `documents` reutiliza los documentos existentes y prepara las cinco órdenes derivadas para la siguiente ejecución de `emit`.

## Contrato HKA comprobado

Referencia: [Swagger HKA DEMO](https://demoemisionv2.thefactoryhka.com.ve/swagger/index.html?urls.primaryName=Imprenta+Digital+VE), contrastado con el esquema versionado local y respuestas reales del proveedor.

- Guías sin valorización: `precioUnitario=0`, `precioItem=0`, cantidades/unidades y transporte; omitir `encabezado.totales`. HKA rechazó la omisión del precio con 203/1043. Los importes positivos incompletos produjeron 1044/1047.
- Guías valorizadas: precio neto, importe, código y tasa de IVA, IVA y total por línea. El sondeo real de una línea de 200 + 32 IVA fue confirmado con control `00-00000703`. El nodo de totales de factura provocaba 1008/1011 y se excluyó de ambas variantes. Se aceptan precios en VES; no convertir automáticamente un catálogo USD sin un tipo de cambio documental.
- Los UUID se conservan internamente; HKA recibe su representación hexadecimal sin guiones. El snapshot conserva motivo, catálogos, direcciones, identificación y transporte autorizados.
- La relación consulta antes de modificar. Sólo la envolvente exacta de ausencia comprobada (HTTP 200, código 404, mensaje `Relación no encontrada`, validación única `Relación no encontrada en nuestra base de datos.`) autoriza el POST posterior. Timeout y respuestas ambiguas conservan estado por conciliar.
- Las órdenes 22/23 incluyen `facturaGuia[]` al emitirse. Las órdenes 10/11/12 se asociaron después mediante `Documentos/Relacionar`. Consultas desde cada factura devolvieron las mismas cinco identidades.

Estados fiscales independientes: sin solicitar, preparado, enviando, confirmado, rechazado y por conciliar. La confirmación no se degrada por una consulta posterior fallida. El original PDF usa `DescargaArchivo`, se valida y se guarda privadamente; un error de descarga no revierte la confirmación.

## Matriz de resultados

| Escenario | Evidencia y resultado |
|---|---|
| Cinco motivos × transporte público/privado | Diez órdenes confirmadas, controles `00-00000684`–`00-00000693`; consulta y PDF válidos. |
| Fraccionario, lote, serial y segunda sucursal | Cuatro órdenes confirmadas, controles `00-00000694`–`00-00000697`. |
| Público sin conductor propio y clientes J/E | Tres órdenes confirmadas, controles `00-00000700`–`00-00000702`. |
| Creación, rechazo definitivo, corrección, consulta y PDF en navegador | Orden 18, mismo número `1600000016`, finalmente confirmada con control `00-00000704`; sin duplicación comercial. |
| Derivaciones de cotización, pedido, nota de venta y factura VES/USD | Cinco órdenes confirmadas, controles `00-00000705`–`00-00000709`. |
| Facturas y relaciones en ambos sentidos | VES `00000355`/control `00-00000698`, USD `00000356`/control `00-00000699`; cinco vínculos confirmados. |
| UUID repetido, colisión, snapshot, después del commit y PDF | Prueba MySQL de persistencia automática aprobada; repetición conserva orden/operación y colisión devuelve 409. |
| Peso inválido, dirección ajena, principal ajeno al tenant y otra sucursal | Rechazados antes de escribir o llamar a HKA; prueba MySQL aprobada. |
| Edición, almacén de sucursal, lotes/seriales y reversión única | Prueba MySQL aprobada, incluyendo serial repetido y cantidad fraccionaria con serial. |
| Conversión múltiple e inventario ya trasladado | Prueba MySQL aprobada; stock sólo por cantidad pendiente, lotes conservados y reversión sólo del movimiento de la factura. |
| Conversión de orden derivada de pedido | Prueba MySQL aprobada: 13 aserciones. La factura reconoce el stock descontado por el origen; cantidades superiores al origen se rechazan. |
| Repetición de una solicitud con lote/serial ya consumido | Prueba MySQL aprobada: 15 aserciones. Conserva la orden y la cantidad del lote, sin validar su propio movimiento como consumo ajeno. |
| Concurrencia con la misma y con diferentes claves | Prueba MySQL aprobada: una orden/efecto para la misma clave, números distintos para claves distintas (15 aserciones). |
| Timeout y consulta posterior de ausencia | Prueba MySQL aprobada; recuperación conserva la operación y requiere consulta antes de reenvío. |
| Instalación, semillas, claves foráneas, rollback y repetición | Prueba MySQL temporal aprobada: 1601 aserciones. |
| Regresión de emisión de facturas después del commit | Prueba MySQL aprobada: 267 aserciones. |
| Regresión de notas, packs, presentaciones, lotes, seriales y rollback | Prueba MySQL aprobada: 25 aserciones. |
| Tenant existente sin ampliación de esquema | Creación local preservada; prueba MySQL de persistencia/compatibilidad aprobada: 25 aserciones. |
| PHP Unit | 760 pruebas, 15706 aserciones, sin fallos; 52 omitidas por sus condiciones de entorno. Los escenarios MySQL indicados se ejecutaron por separado con `PRO9_FISCAL_MYSQL_TESTS=1`. |
| JavaScript | 169 pruebas aprobadas, sin fallos ni omisiones; acciones/doble clic, refresco, plantillas y conversión múltiple con sucursal/referencias. |
| Navegador y compilación | Creación/cierre, corrección de rechazo, listado, filtro por número, paginación, consulta y descarga comprobados. Diálogos de conversión individual y múltiple cargan datos; las conversiones guardadas están verificadas por persistencia y emisiones reales. Vite final aprobado. |

Se necesitaron **cuatro compilaciones** para incorporar hallazgos de navegador y revisión posteriores a la primera; la definitiva concluyó correctamente en 43,96 s. Permanecen las advertencias habituales de Sass y tamaño de chunks. Los avisos preexistentes de QZ/Menu no impidieron los flujos comprobados.

## Límites observados

El PDF original HKA es válido y legible, con control, identificación y transporte. La plantilla DEMO dejó `Origen Traslado` sin imprimir aunque `transporte.origen/destino` están presentes en el payload aceptado. Los originales ya emitidos conservan también los datos del emisor que estaban configurados al emitir; no se reescriben ni se reemplazan por el PDF local. Es una limitación observada del original del proveedor.

Para convertir bienes con lotes/seriales se exige la cantidad completa en una línea por producto y su unidad original. La mezcla de cantidades ya trasladadas y nuevas con trazabilidad se rechaza para impedir movimientos incorrectos. No se habilitaron packs para esta conversión.

## Archivos de entrega

- [Manifiesto completo](evidencias/ordenes_entrega_bbc_manifest.json).
- [Matriz estructurada](evidencias/ordenes_entrega_resultados.json).
- [Listado verificado](evidencias/ordenes_bbc_hka_listado.jpg).
- Sondeos saneados `evidencias/ordenes_hka_probe_2026_10_13_*.json`.
- Pruebas de comportamiento: `tests/Support/DispatchPersistenceCases.php`, `tests/js/dispatch-hka-ui.test.cjs`.

Las evidencias contienen exclusivamente los datos sintéticos necesarios. Los originales PDF permanecen en almacenamiento privado del tenant y se descargan desde las acciones autenticadas de BBC.
