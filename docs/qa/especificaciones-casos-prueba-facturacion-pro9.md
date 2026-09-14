# Especificacion de casos de prueba de facturacion de Pro9

**Version:** 1.0
**Fecha de corte:** 13 de septiembre de 2026
**Audiencia:** QA y equipo tecnico
**Alcance:** operacion comercial de tenants
**Estado:** especificacion lista para ejecucion

## 1. Objetivo

Definir una cobertura exhaustiva, repetible y trazable para los flujos de facturacion de Pro9. La especificacion cubre la creacion, calculo, numeracion, emision local, pago, anulacion, impresion, envio, consulta y reporte de documentos comerciales, junto con los canales que los originan y los modulos de compras y finanzas que los afectan.

El documento debe permitir que QA ejecute los casos sin depender de supuestos de la interfaz y que el equipo tecnico pueda localizar el contrato en rutas, controladores, vistas, tablas y pruebas automatizadas existentes.

## 2. Alcance y limites

### 2.1 Incluido

- Configuracion fiscal del tenant, establecimientos, perfiles por canal, series, numeracion, ambientes demo y produccion.
- Facturas, notas de credito, notas de debito, Notas de venta, ordenes de entrega y sus conversiones.
- Ventas desde documentos, cotizaciones, ordenes, contratos, servicio tecnico, POS, caja, ecommerce, restaurante, hotel, Vende Ya, movil, API y WhatsApp.
- Compras, cotizaciones y ordenes de compra, compras de activos fijos, gastos, prestamos, retenciones y pagos a proveedores.
- Facturacion masiva por plantilla XLSX, validacion previa, reporte de errores, correccion parcial, procesamiento y descarga PDF.
- Cuentas por cobrar y pagar, pagos globales, ingresos, movimientos, transferencias, enlaces de pago, conciliacion y reportes.
- IVA venezolano, VES y USD, identidad y ubicacion de clientes, productos, servicios, unidades, inventario y reservas de pedidos.
- PDF, impresion QZ Tray, correo, consulta publica, exportaciones Excel, bandeja de descargas y webhooks.
- Seguridad, autorizacion por rol, aislamiento tenant, cargas de archivos, idempotencia, concurrencia, rollback y recuperacion.

### 2.2 Fuera de alcance o no soportado

- Facturacion de suscripciones, planes o pagos del propio Pro9.
- Boletas y tipos documentales retirados, incluidas las series BB, BC y BD.
- SOAP, PFX de facturacion, XML, CDR, PSE y aceptacion o rechazo por una autoridad externa.
- Pruebas de cobro real en pasarelas, impresion fiscal real o conectividad real con proveedores.
- Migraciones, backfills o conversion de tenants historicos.

Los flujos no soportados se prueban como rechazos, ausencia de rutas, ausencia de campos o ausencia de artefactos, segun corresponda. No deben aparecer como funcionalidades disponibles.

## Indice

- [Objetivo](#1-objetivo)
- [Alcance y limites](#2-alcance-y-limites)
- [Contratos funcionales de referencia](#3-contratos-funcionales-de-referencia)
- [Estrategia de ejecucion](#4-estrategia-de-ejecucion)
- [Mapa de modulos y trazabilidad tecnica](#5-mapa-de-modulos-y-trazabilidad-tecnica)
- [Casos de prueba detallados](#6-casos-de-prueba-detallados)
- [Matriz minima de combinaciones](#7-matriz-minima-de-combinaciones)
- [Evidencia requerida](#8-evidencia-requerida)
- [Criterios de aceptacion](#9-criterios-de-aceptacion)
- [Checklist de ejecucion por ciclo](#10-checklist-de-ejecucion-por-ciclo)
- [Fuentes internas](#11-fuentes-internas)

## 3. Contratos funcionales de referencia

| Contrato | Regla verificable |
| --- | --- |
| Documentos de venta | Factura `01`, nota de credito `07`, nota de debito `08` y Nota de venta `80` o alias tecnico `nv` segun el canal. |
| Orden de entrega | Tipo `09`, con numeracion propia y sin presentarla como Factura. |
| Boletas | El tipo `03` y las series `BB`, `BC`, `BD` son rechazados y no aparecen en selectores ni reportes. |
| Emision local | El registro comercial y el PDF no se presentan como aceptacion SENIAT, SUNAT, PSE ni proveedor externo. |
| Modalidad | `fiscal_machine`, `digital` y `free_form`; los parametros corresponden a la modalidad seleccionada. |
| Ambiente | `demo` o `production`; se bloquea despues de la primera operacion y seleccionar produccion no prueba conectividad. |
| IVA | Tasa predeterminada 16 por ciento, afectaciones activas `10 = Gravado` y `20 = Exento`; la tasa configurada prevalece. |
| Moneda | `VES` con simbolo `Bs.` y `USD` con simbolo `$`; `PEN` y `VED` se rechazan. |
| Identidad | Catalogo activo: `0`, `1`, `6`, `7`, `E`, `C`, `G` y `R`. Una identidad inactiva se rechaza en backend. |
| Unidades | `UND` para productos y `SERV` para servicios; `NIU` y `ZZ` heredados se rechazan. |
| Numeracion | Serie, numero de documento, numero de control y registro de maquina son identificadores independientes. No se reciclan numeros comprometidos. |
| Idempotencia | Un reintento con la misma operacion no duplica documento, pago, inventario, reserva ni numero. |
| Inventario | Anulaciones y notas solo revierten el efecto fisico que corresponde al motivo y una sola vez. |
| Privacidad | Credenciales, api tokens, claves privadas y datos sensibles no aparecen en respuestas, logs, PDFs publicos ni reportes no autorizados. |

## 4. Estrategia de ejecucion

### 4.1 Niveles

1. **Contrato y unidad:** reglas de tipos documentales, IVA, moneda, unidades, numeracion, snapshots, normalizadores e idempotencia.
2. **Integracion:** base tenant temporal, persistencia, inventario, caja, pagos, reservas y reportes.
3. **API y web:** rutas autenticadas, permisos, payloads, respuestas HTTP, formularios y errores.
4. **Visual:** PDF, impresion, correo, reportes Excel, estados y presentacion de totales.
5. **Resiliencia:** rollback, reintentos, respuesta incierta, agotamiento de series, concurrencia y recuperacion.

### 4.2 Datos base

| Identificador | Datos preparados |
| --- | --- |
| `TEN-01` | Tenant temporal venezolano en `demo`, modalidad `digital`, dos establecimientos y dos almacenes. |
| `TEN-02` | Segundo tenant temporal para probar aislamiento con IDs locales coincidentes. |
| `USR-ADM` | Administrador tenant activo. Puede configurar empresa, numeracion, perfiles y pagos. |
| `USR-SELL` | Vendedor activo. Puede vender y consultar sus operaciones autorizadas. |
| `USR-INT` | Cuenta `integrator` autorizada para canal digital. |
| `USR-VIS` | Usuario sin permiso de venta, reportes o configuracion fiscal. |
| `CUS-RIF` | Cliente con RIF valido y ubicacion venezolana completa. |
| `CUS-ID` | Cliente con cedula venezolana valida. |
| `CUS-PAS` | Cliente con pasaporte valido y ubicacion omitida. |
| `CUS-OFF` | Cliente con identidad desactivada para validar rechazo de backend. |
| `ITEM-G` | Producto `UND`, afectacion 10, IVA 16 por ciento, stock suficiente. |
| `ITEM-E` | Producto `UND`, afectacion 20, exento. |
| `SERV-01` | Servicio `SERV`, sin movimiento fisico de inventario. |
| `PACK-01` | Pack con componentes y almacenes definidos. |
| `WH-01`, `WH-02` | Almacenes del mismo tenant; `WH-02` se usa para pruebas de reserva. |
| `VES`, `USD` | Monedas activas, con tipo de cambio controlado para la fecha de prueba. |
| `PAY-01` | Efectivo VES, transferencia VES, tarjeta y pago mixto. |
| `PAY-02` | Enlace de pago y pasarela configurada con doble de prueba. |
| `SER-01` | Serie activa con correlativo inicial conocido y lote o rango suficiente. |
| `SER-END` | Serie agotada o con un unico numero disponible. |
| `ORD-01` | Pedido con reserva, almacen explicito y clave de operacion estable. |
| `HOT-01` | Habitacion con tarifa, renta activa, cliente y consumos de producto y servicio. |
| `REST-01` | Mesa, mozo, pedido con modificadores, insumo y caja abierta. |

### 4.3 Roles y resultado

Cada caso registra el resultado de interfaz o API, el efecto persistido, los efectos secundarios y la evidencia. Los estados usados en esta especificacion son:

- `AUTOMATIZADO`: existe una prueba automatizada de referencia; confirmar su resultado en la ejecucion de la suite.
- `MANUAL`: requiere recorrido web, movil, PDF, correo, impresion o criterio visual.
- `INTEGRACION`: requiere base tenant temporal, transaccion, inventario, pagos o doble de proveedor.
- `BRECHA`: el repositorio identifica cobertura incompleta o una limitacion que debe quedar visible.

## 5. Mapa de modulos y trazabilidad tecnica

| Suite | Modulos y flujos | Fuentes principales | Pruebas de referencia |
| --- | --- | --- | --- |
| `FT-CONF` | Empresa, establecimientos, perfiles, series y catalogos fiscales | `routes/web.php`, `app/Http/Controllers/Tenant/FiscalEmissionController.php`, `app/Http/Controllers/Tenant/FiscalNumberingController.php`, `resources/js/views/tenant/establishments/` | `FiscalEmissionSettingsTest`, `FiscalEmissionSchemaTest`, `FiscalProfileServiceTest`, `EstablishmentVenezuelaTaxTest` |
| `FT-DOC` | Documentos, facturas, notas, pagos, anulacion y emision local | `modules/Document/`, `app/Http/Controllers/Tenant/DocumentController.php`, `app/Http/Controllers/Tenant/FiscalDocumentEmissionController.php`, `resources/js/views/tenant/documents/` | `CurrentDocumentResolutionTest`, `CurrentNoteInputTest`, `FiscalDocumentEmissionControllerTest`, `LocalFiscalDocumentPolicyTest` |
| `FT-SALE` | Cotizaciones, ordenes, contratos, servicio tecnico y conversiones | `routes/web.php`, `modules/Sale/`, `resources/js/views/tenant/quotations/` | `QuotationStorefrontCorrelativeTest`, `SaleNotesInvoiceCustomerContractTest`, `SalesDocumentTypePolicyTest` |
| `FT-NOT` | Notas de venta y pagos de Notas de venta | `routes/web.php`, `app/Http/Controllers/Tenant/SaleNoteController.php`, `resources/js/views/tenant/sale_notes/` | `FiscalOrderSalesNoteContextTest`, `SalesWithoutReceiptSourceContractTest`, `InvoiceTotalsPaymentsVisibilityContractTest` |
| `FT-POS` | POS normal, completo, venta rapida, garage y caja | `modules/Pos/`, `app/Http/Controllers/Tenant/PosController.php`, `resources/js/views/tenant/pos/` | `PosCashDenominationsContractTest`, `fiscal-sale.test.cjs`, `InvoiceTotalsPaymentsVisibilityContractTest` |
| `FT-ECOM` | Ecommerce, Mi Tienda, carrito, pedidos, pagos y reservas | `modules/Ecommerce/`, `modules/Order/`, `resources/js/views/tenant/orders/` | `FiscalOrderContextTest`, `FiscalOrderConversionTest`, `FiscalOrderStockReservationTest`, `FiscalOrderStockGuardTest` |
| `FT-REST` | Restaurante, mesas, mozos, pedidos, insumos y caja | `modules/Restaurant/`, `routes/web.php`, `routes/api.php` | `SalesWithoutReceiptSourceContractTest`, `fiscal-order-stock.test.cjs` |
| `FT-HOT` | Habitaciones, rentas, consumos, checkout y reportes hotel | `modules/Hotel/`, `database/migrations/tenant/2026_08_17_000226_create_hotel_rents_table.php` | `HotelRentProductsTemplateContractTest`, `HotelVenezuelanIdentificationContractTest` |
| `FT-API` | API autenticada, movil, Vende Ya y WhatsApp | `routes/api.php`, `modules/MobileApp/`, `modules/WhatsAppApi/`, `modules/WhatsAppBot/` | `FiscalApiDocumentContextTest`, `FiscalWebDocumentContextTest`, `FiscalPaymentDestinationTest`, `VenezuelaPhoneLocalizationTest` |
| `FT-MASS` | Facturacion masiva e importacion de documentos | `resources/js/views/system/massive_invoice/index.vue`, `resources/views/system/massive_invoice/index.blade.php`, `app/Http/Controllers/System/MassiveInvoiceController.php`, `app/Services/MassiveInvoiceService.php` | `LocalFiscalDocumentPolicyTest`, `FiscalEmissionSchemaTest`, `SalesIdentityDocumentEmissionContractTest`, `DetractionRemovalTest` |
| `FT-DISP` | Ordenes de entrega, direcciones, conversiones y emision tipo 09 | `modules/Dispatch/`, `app/Http/Controllers/Tenant/FiscalDispatchEmissionController.php` | `FiscalDispatchConversionTest`, `FiscalDispatchEmissionControllerTest`, `DeliveryOrderNamingContractTest` |
| `FT-PUR` | Compras, proveedores, ordenes, cotizaciones y activos fijos | `modules/Purchase/`, `app/Http/Controllers/Tenant/PurchaseController.php` | `PurchaseDefaultWarehouseSourceContractTest`, `VenezuelaIvaContractTest`, `VenezuelaCurrencyTest` |
| `FT-EXP` | Gastos, prestamos y pagos de gastos | `modules/Expense/`, `routes/web.php` | `DetractionRemovalTest`, `VenezuelaCurrencyTest`, `VenezuelaInitialCatalogContractTest` |
| `FT-FIN` | Pagos, cuentas, ingresos, movimientos, transferencias y enlaces | `modules/Finance/`, `modules/Payment/`, `modules/Account/`, `routes/web.php` | `FiscalPaymentDestinationTest`, `InvoiceTotalsPaymentsVisibilityContractTest`, `MultiUserAccessSecurityTest` |
| `FT-REP` | Reportes comerciales, fiscales locales, PDFs, Excel y bandeja | `modules/Report/`, `resources/views/tenant/reports/`, `app/CoreFacturalo/Templates/pdf/` | `CurrentPdfTemplateContractTest`, `CurrentPdfRenderingTest`, `DashboardTopKpiVisualContractTest` |
| `FT-SEC` | Autorizacion, aislamiento, secretos, archivos y webhooks | middleware, FormRequest, policies, `informes/auditoria_seguridad_pro9.md` | `MultiUserAccessSecurityTest`, `FiscalHttpExceptionResponseTest` |
| `FT-RES` | Concurrencia, rollback, recuperacion e instalacion limpia | `database/migrations/tenant/`, `app/Services/Fiscal/`, `tests/Support/` | `FiscalMySqlConcurrencyTest`, `FiscalOperationFingerprintTest`, `TenantMigrationDataSeederTest` |

La referencia a una prueba existente acredita que el repositorio contiene un punto de verificacion relacionado; no sustituye el caso manual, la prueba HTTP completa ni la revision visual cuando esas capas son necesarias.

### 5.1 Persistencia principal por suite

| Suite | Tablas y recursos principales a verificar |
| --- | --- |
| `FT-CONF` | `companies`, `establishments`, `series`, `series_configurations`, `fiscal_environments`, `fiscal_profiles`, `fiscal_sequences`, `fiscal_control_lots`, `fiscal_number_reservations`, `fiscal_numbering_audits`, `fiscal_emission_attempts`, `fiscal_configuration_audits`, catalogos de identidad, moneda, IVA y unidades. |
| `FT-DOC` | `documents`, `document_items`, `document_payments`, `voided_documents`, `voided`, `retentions`, `retention_documents`, `cash_documents`, `cash_document_payments` y archivos PDF. |
| `FT-SALE` | `quotations`, `order_forms`, `order_notes`, `contracts`, `technical_services`, `technical_service_items`, `technical_service_payments`, oportunidades y comisiones. |
| `FT-NOT` | `sale_notes`, `sale_note_items`, `sale_note_fees`, `sale_note_payments`, `dispatch_sale_notes` y vinculos con `orders`. |
| `FT-POS` | `cash`, `cash_transactions`, `cash_documents`, `cash_document_credits`, `cash_document_payments`, `documents`, `sale_notes` e inventario por almacen. |
| `FT-ECOM` | `orders`, `stock_reservation` en `orders`, `payment_link_payments`, cupones, `documents`, `sale_notes`, `document_payments` y `webhook_deliveries`. |
| `FT-REST` | `restaurant_configurations`, `restaurant_tables`, `restaurant_table_groups`, `restaurant_roles`, `restaurant_notes`, `restaurant_item_order_statuses`, `restaurant_item_supplies`, `restaurant_stock_products` y caja. |
| `FT-HOT` | `hotel_rooms`, `hotel_room_rates`, `hotel_rents`, `hotel_rent_items`, `hotel_rent_item_payments`, `hotel_rent_orders`, `document_hotels` y `documents`. |
| `FT-API` | Recursos compartidos de `persons`, `documents`, `sale_notes`, `orders`, `dispatches`, `payment_link_payments` y `whatsapp_message_logs`. |
| `FT-MASS` | `massive_invoices`, documentos generados, pagos, inventario, archivos de plantilla, reportes de error y campos `estado_emision` y `mensaje_emision`. |
| `FT-DISP` | `dispatches`, `dispatch_items`, `dispatch_addresses`, `dispatch_sale_notes`, `drivers`, `dispatchers`, `transports`, `origin_addresses` y reservas de numeracion. |
| `FT-PUR` | `purchases`, `purchase_items`, `purchase_payments`, `purchase_orders`, `purchase_order_items`, `purchase_quotations`, `purchase_quotation_items`, `purchase_fee`, `purchase_settlements`, `purchase_settlement_items`, `purchase_settlement_payments` y activos. |
| `FT-EXP` | `expenses`, `expense_items`, `expense_payments`, `expense_types`, `expense_reasons`, `expense_method_types`, prestamos y pagos asociados. |
| `FT-FIN` | `payment_method_types`, `payment_configurations`, `payment_links`, `payment_link_payments`, `income`, `income_items`, `income_payments`, `cash_transactions`, cuentas y `accounting_ledger`. |
| `FT-REP` | Consultas sobre documentos, ventas, compras, Notas de venta, rentas, caja, movimientos, cuentas, `download_tray` y archivos PDF/Excel. |
| `FT-SEC` y `FT-RES` | Tablas de todas las suites, auditorias, reservas, intentos de emision, webhooks, indices unicos, FKs y registros de rollback. |

## 6. Casos de prueba detallados

Cada fila contiene el ID, modulo, objetivo, prioridad y tipo, precondiciones y datos, pasos, resultado visible, persistencia y evidencia, y estado o referencia.

### 6.1 Configuracion fiscal y catalogos `FT-CONF`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-CONF-001 | Alta de tenant con modalidad y ambiente. P0 positivo | Alta limpia; `TEN-01`; `digital`, `demo` | Crear tenant desde alta ordinaria, autoregistro y API | Los tres canales exigen modalidad y ambiente validos | `companies` conserva ambos campos y bloqueo falso; capturar respuesta y fila | INTEGRACION; `FiscalEmissionSchemaTest` |
| FT-CONF-002 | Seleccion de modalidad valida. P0 limite | Administrador; tres modalidades | Guardar cada modalidad y sus parametros | Solo se aceptan `fiscal_machine`, `digital` y `free_form` | Se crea auditoria sin secretos; verificar campos por modalidad | AUTOMATIZADO; `FiscalEmissionSettingsTest` |
| FT-CONF-003 | Rechazo de parametros de otra modalidad. P1 negativo | Formulario con modelo de maquina y rango de forma libre mezclados | Enviar payload combinado | Respuesta 422 y ningun campo ajeno queda persistido | Auditoria no contiene parametros rechazados | AUTOMATIZADO; `FiscalEmissionSettingsTest` |
| FT-CONF-004 | Validacion de rango preimpreso. P1 limite | `free_form`; inicio mayor que fin, cero y negativos | Guardar rangos invalidos y luego rango valido | Invalidos rechazan con error por campo; valido guarda | No se crea lote invalido; conservar evidencia HTTP | MANUAL/INTEGRACION |
| FT-CONF-005 | Cifrado, ocultacion y borrado de credenciales. P0 seguridad | Credencial de prueba sin valor real | Guardar, consultar, serializar, actualizar vacio y borrar explicitamente | Nunca aparece en respuesta ni log; vacio conserva; borrado elimina | Comparar base cifrada y ausencia en JSON/logs | AUTOMATIZADO; `FiscalEmissionSettingsTest` |
| FT-CONF-006 | Auditoria de configuracion. P1 persistencia | `USR-ADM`; cambio de modalidad y ambiente | Ejecutar cambios permitidos y consultar auditoria | Actor, modalidad y nombres de campos quedan registrados | Valores secretos nunca se auditan | AUTOMATIZADO; `FiscalEmissionSettingsTest` |
| FT-CONF-007 | Bloqueo del ambiente tras la primera operacion. P0 seguridad | Tenant demo; documento emitible | Emitir una operacion, intentar cambiar ambiente y borrar documento | Cambio rechazado aun despues de eliminar movimientos | `companies` mantiene ambiente bloqueado; capturar 403 o 422 | INTEGRACION; `FiscalEmissionSchemaTest` |
| FT-CONF-008 | Establecimientos y perfiles por canal. P0 integracion | Dos establecimientos y canales presencial/digital | Crear perfiles, asignar series, emitir por cada canal | Cada documento usa el establecimiento y perfil autorizado | Snapshot conserva modalidad, ambiente, perfil y actor | AUTOMATIZADO; `FiscalProfileServiceTest` |
| FT-CONF-009 | Serie, secuencia y lote de control. P0 concurrencia | `SER-01` y `SER-END` | Crear serie, actualizar correlativo, consumir lote y agotar rango | No hay solapamiento; agotamiento rechaza sin numero parcial | Indices unicos y auditoria; conservar consulta antes/despues | AUTOMATIZADO; `FiscalNumberingRepositoryTest`, `FiscalControlNumberTest` |
| FT-CONF-010 | Catalogos iniciales venezolanos. P0 instalacion | Tenant temporal nuevo | Ejecutar migracion y seeding; consultar identidades, IVA, monedas, unidades y documentos | Catalogos exactos, activos y sin filas retiradas | Verificar FK, rollback y segunda instalacion | INTEGRACION; `VenezuelaInitialCatalogContractTest`, `TenantMigrationDataSeederTest` |
| FT-CONF-011 | Cliente con identidad y ubicacion validas. P1 positivo | `CUS-RIF`, `CUS-ID`, `CUS-PAS` | Crear desde formulario, API y documento web | Se conserva tipo, numero, ceros iniciales y ubicacion | Padres parroquia, municipio y estado correctos | AUTOMATIZADO; `VenezuelaPersonLocationTest`, `PersonRequestVenezuelaTest` |
| FT-CONF-012 | Rechazo de identidad inactiva o jerarquia invalida. P0 negativo | `CUS-OFF`; ubicacion de otro pais o inactiva | Emitir con cada combinacion | Rechazo antes de crear cliente o documento | No hay filas parciales ni movimiento comercial | AUTOMATIZADO; `SalesCustomerIdentityPolicyTest` |

### 6.2 Documentos, emision local y notas `FT-DOC`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-DOC-001 | Crear Factura 01. P0 positivo | `TEN-01`, `USR-SELL`, `CUS-RIF`, `ITEM-G`, `SER-01` | Crear factura desde pantalla y API | Factura guardada, total correcto y lista disponible | Documento con snapshot fiscal, serie, numero, modalidad y ambiente | INTEGRACION; `FiscalCommercialServiceTest`, `FiscalDocumentBindingTest` |
| FT-DOC-002 | Validar campos obligatorios y datos de lineas. P0 negativo | Cliente ausente, item inexistente, cantidad cero y precio invalido | Enviar cada variante | 422 antes de persistir o afectar inventario | No hay documento, pago ni Kardex parcial | AUTOMATIZADO; `CurrentNoteInputTest` |
| FT-DOC-003 | Factura gravada con IVA. P0 calculo | Base 100, afectacion 10, tasa 16 | Agregar linea y generar | Subtotal 100, IVA 16, total 116 | `percentage_igv=16`, `total_igv=16` | AUTOMATIZADO; `VenezuelaIvaContractTest` |
| FT-DOC-004 | Factura exenta y mixta. P0 calculo | `ITEM-E`, `ITEM-G`, base conocida | Generar factura con una linea exenta y otra gravada | Exenta no suma IVA; gravada aplica tasa; total consistente | Afectacion por linea y totales persistidos | AUTOMATIZADO; `VenezuelaIvaContractTest`, `fiscal-row.test.cjs` |
| FT-DOC-005 | Moneda VES y USD. P0 calculo | `VES`, `USD`, tipo de cambio controlado | Emitir en ambas monedas y consultar PDF/reporte | Simbolo `Bs.` o `$`; conversion consistente donde corresponde | Moneda, tipo de cambio y valores conservados | AUTOMATIZADO; `VenezuelaCurrencyTest` |
| FT-DOC-006 | Nota de credito por anulacion total. P0 inventario | Factura confirmada con stock; motivo 01 | Crear NC total, rechazar/anular y repetir accion | Documento referenciado; inventario se restituye una sola vez | Relacion a factura y movimiento fisico auditable | AUTOMATIZADO; `FiscalNoteReferenceTest`, `FiscalInventoryPolicyTest` |
| FT-DOC-007 | Nota de credito por devolucion parcial. P0 inventario | Factura con dos unidades; motivo 07 | Crear NC parcial y revisar cantidades | Solo se devuelve cantidad indicada y permitida | Kardex y stock coinciden con la devolucion | AUTOMATIZADO; `FiscalInventoryPolicyTest` |
| FT-DOC-008 | Nota de credito sin efecto fisico. P1 negativo | Motivo 04 o 09; factura con stock | Crear, rechazar y anular nota | Ajuste monetario sin movimiento de inventario | No cambia stock; guardar motivo y auditoria | AUTOMATIZADO; `FiscalInventoryPolicyTest` |
| FT-DOC-009 | Nota de debito. P0 calculo | Factura base; motivo valido | Crear ND y confirmar | Referencia obligatoria y total adicional correcto | No genera devolucion de inventario | AUTOMATIZADO; `FiscalNoteReferenceTest` |
| FT-DOC-010 | Referencia local y externa de notas. P0 seguridad | Factura, Boleta simulada, ID inexistente y otro tenant | Crear notas con cada referencia | Solo una Factura 01 del mismo tenant es aceptada | `data_affected_document` y vinculo local correctos | AUTOMATIZADO; `CurrentLocalDocumentReferenceTest`, `CurrentNoteInputTest` |
| FT-DOC-011 | Emision local, PDF e impresion. P0 visual | Documento listo; plantillas A4, A5 y ticket | Procesar, ver estado fiscal, imprimir y reimprimir | Estado local claro; PDF legible; reimpresion no renumera | Un solo numero y registro de impresion; sin XML/CDR/hash fiscal | AUTOMATIZADO/MANUAL; `LocalFiscalDocumentPolicyTest`, `CurrentPdfRenderingTest` |
| FT-DOC-012 | Contingencia trazable. P0 recuperacion | Documento sin envio o rechazo verificado; administrador | Crear contingencia, validar PDF, confirmar y reintentar | Vinculo con reserva padre; no duplica pago ni inventario | Documento original en contingencia, control efectivo y causa conservados | AUTOMATIZADO; `FiscalContingencyServiceTest`, `fiscal-contingency.test.cjs` |
| FT-DOC-013 | Confirmacion e invalidacion de impresion. P1 estado | Documento procesado; impresion correcta e incorrecta | Confirmar, invalidar y consultar | Estados coherentes; una invalidacion no crea nuevo numero | Auditoria de actor y timestamp; PDF permanece recuperable | AUTOMATIZADO; `FiscalDocumentEmissionControllerTest` |
| FT-DOC-014 | Anulacion comercial local. P0 integracion | Documento registrado y usuario con permiso | Anular por interfaz, API y reintento | Anulacion local confirmada; no se consulta autoridad externa | Stock, caja y pagos revierten segun politica, una sola vez | AUTOMATIZADO; `CurrentLocalDocumentReferenceTest` |
| FT-DOC-015 | Pago asociado a factura. P0 pagos | Factura de total conocido y `PAY-01` | Registrar pago total, parcial, excedido e invalido | Saldo y estado de pago correctos; excedido rechaza | `document_payments`, caja y reportes consistentes | AUTOMATIZADO; `InvoiceTotalsPaymentsVisibilityContractTest` |
| FT-DOC-016 | Correo y consulta publica. P1 visual/seguridad | Documento autorizado; correo de prueba | Enviar PDF, buscar publicamente por datos validos e invalidos | Solo PDF; consulta minima y no permite enumerar documentos | No expone secretos, XML, CDR ni datos de otro tenant | MANUAL; rutas `documents/email`, `consultas`, `document_print_pdf` |

### 6.3 Ventas, cotizaciones y conversiones `FT-SALE` y Notas de venta `FT-NOT`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-SALE-001 | Crear cotizacion con producto y servicio. P1 positivo | `ITEM-G`, `SERV-01`, cliente y precios | Crear, guardar, consultar y descargar | Totales, unidades y cliente correctos | No descuenta stock ni crea documento fiscal | MANUAL; `QuotationStorefrontCorrelativeTest` |
| FT-SALE-002 | Cambiar estado y anular cotizacion. P1 estado | Cotizacion existente | Cambiar estados permitidos y anular | Transiciones validas; estados incompatibles rechazan | Auditoria comercial y PDF mantienen el estado | MANUAL |
| FT-SALE-003 | Convertir cotizacion a orden o documento. P0 integracion | Cotizacion con descuento, IVA y cliente | Generar orden, Nota de venta y Factura | Se conservan lineas, precios, cliente y afectacion; se recalcula total en servidor | Vínculo de origen correcto; inventario solo en el paso que corresponde | INTEGRACION; `SaleNotesInvoiceCustomerContractTest` |
| FT-SALE-004 | Crear orden de formulario y orden de nota. P1 positivo | Usuario vendedor y productos validos | Crear, editar, duplicar y consultar | Datos comerciales completos; duplicado tiene nueva identidad | No duplica pagos ni movimientos de origen | MANUAL |
| FT-SALE-005 | Generar documento desde orden de nota. P0 conversion | Orden aprobada y factura habilitada | Generar Factura y reintentar | Un unico documento vinculado; reintento recupera existente | No se duplica stock, pago o correlativo | INTEGRACION; `FiscalOrderConversionTest` |
| FT-SALE-006 | Contrato y oportunidad comercial. P1 flujo | Cotizacion u oportunidad activa | Generar contrato, cambiar estado y consultar PDF | Contrato conserva cliente y items; no factura hasta accion explicita | Relacion y documento comercial auditable | MANUAL |
| FT-SALE-007 | Servicio tecnico con alias `nv`. P0 compatibilidad | Servicio con producto y servicio | Crear, pagar y generar documento | Usa `nv` donde el contrato del modulo lo exige; no propone Boleta | Pago y detalle tecnico consistentes | MANUAL; `SalesWithoutReceiptSourceContractTest` |
| FT-SALE-008 | Servicio tecnico con Factura. P0 conversion | Servicio finalizado y cliente con RIF | Generar Factura y reintentar | Factura 01 con snapshot de lineas y cliente | Estado de servicio y factura vinculados | MANUAL |
| FT-SALE-009 | Pago de cotizacion y servicio. P1 pagos | Registro pendiente y `PAY-01` | Registrar pago parcial, total y eliminar pago autorizado | Saldo y estado coherentes; eliminacion auditable | Pago no se asocia a otro registro | INTEGRACION |
| FT-SALE-010 | Nota de venta manual. P0 positivo | `CUS-ID`, `ITEM-G`, `SERV-01` | Crear desde web y consultar listado/detalle | Nota de venta `80` o `nv` segun canal, con total correcto | Lineas, cliente, pago e inventario segun contrato | INTEGRACION; `FiscalOrderSalesNoteContextTest` |
| FT-NOT-001 | Pago parcial y total de Nota de venta. P0 pagos | Nota de venta de total conocido | Registrar pagos, consultar saldo e imprimir | Saldo decrece; estado cambia al completar; vuelto no aplica indebidamente | `sale_note_payments` y caja consistentes | MANUAL |
| FT-NOT-002 | Anular Nota de venta una vez. P0 inventario | Nota con producto y servicio | Anular, repetir y editar estado | Primer efecto revierte lo aplicable; repeticiones no duplican | Kardex y auditoria sin doble reversa | AUTOMATIZADO; `FiscalOrderStockGuardTest` |
| FT-NOT-003 | Nota de venta desde pedido automatico. P0 idempotencia | `ORD-01`, reserva activa, emisor digital | Procesar pedido a NV y repetir con misma clave | Recupera NV existente y libera reserva una sola vez | Vínculo de pedido y almacén reservado conservados | AUTOMATIZADO; `FiscalOrderSalesNoteContextTest` |
| FT-NOT-004 | Nota de venta de hotel sin fila separada de IVA. P0 visual | `HOT-01`, documento origen hotel | Finalizar checkout como NV y revisar resumen/PDF | Muestra Subtotal y Total; no muestra fila o importe separado de IVA | Cálculos y datos persistidos no se alteran | AUTOMATIZADO/MANUAL; `HotelRentProductsTemplateContractTest` |
| FT-NOT-005 | Rechazar tipo 03 en venta y Nota. P0 negativo | Payload manipulado con `document_type_id=03` | Enviar desde web, API, POS, ecommerce y restaurante | Rechazo de servidor antes de persistir; UI no ofrece Boleta | No crea serie, documento, pago o stock parcial | AUTOMATIZADO; `SalesDocumentTypePolicyTest` |
| FT-NOT-006 | Cliente por defecto y seleccion de documento. P1 interfaz | Configuracion `default_document_type_80` verdadera y falsa | Abrir venta rapida, cambiar identidad y documento | Default alterna Factura/Nota; nunca infiere Boleta por identidad | Payload usa tipo permitido | MANUAL; `SalesCustomerIdentityPolicyTest` |
| FT-NOT-007 | Documentos de Nota de venta por cliente. P1 consulta | Cliente con varias notas | Filtrar, consultar, descargar y enviar | Solo registros autorizados y totales consistentes | Sin fuga entre tenants | MANUAL |
| FT-NOT-008 | Despacho asociado a Nota de venta. P0 integracion | Nota con items y direcciones validas | Generar orden de entrega y actualizar estado | Despacho conserva cliente, origen, destino y lineas | Vinculo documental único; stock según política | AUTOMATIZADO; `FiscalDispatchConversionTest` |
| FT-NOT-009 | Datos moviles y bot describen tipo real. P1 interfaz | Nota y Factura existentes | Consultar desde movil y WhatsApp | Caption usa Factura o Nota de venta, nunca Boleta | Payload sin campos fiscales retirados | AUTOMATIZADO; `CurrentDocumentResolutionTest` |
| FT-NOT-010 | Duplicar y eliminar relacion de Factura. P1 recuperacion | Nota vinculada y usuario autorizado | Duplicar nota, quitar vinculo y consultar | Duplicado es independiente; quitar vinculo no borra Factura | Auditoria conserva operacion original | MANUAL |

### 6.4 POS y caja `FT-POS`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-POS-001 | Venta POS normal. P0 positivo | Caja abierta, `ITEM-G`, cliente y `SER-01` | Buscar item, agregar cantidad, seleccionar Factura y pagar | Total, documento y pago correctos | Kardex, caja y documento vinculados | MANUAL; `fiscal-sale.test.cjs` |
| FT-POS-002 | Venta POS completo. P1 interfaz | `pos_full`, catalogo y dos items | Cambiar cantidades, descuentos y documento | UI recalcula servidor y no pierde lineas | Persistencia coincide con resumen | MANUAL |
| FT-POS-003 | Venta rapida con Factura. P0 positivo | `pos/fast`, cliente con RIF | Seleccionar item y confirmar pago | Genera Factura 01; no usa identidad para inferir Boleta | Documento, pago y stock una vez | MANUAL; `fiscal-sale.test.cjs` |
| FT-POS-004 | Venta rapida con Nota de venta. P0 positivo | Configuracion default NV y cliente valido | Cambiar tipo a Nota y confirmar | Genera Nota de venta permitida | `sale_notes` y pago consistentes | MANUAL |
| FT-POS-005 | Venta garage. P1 flujo | `pos/garage`, producto y caja | Crear venta, revisar resumen y cerrar | Respeta su contrato de documento, moneda y caja | Reporte de garage coincide con operaciones | MANUAL |
| FT-POS-006 | Validacion de stock, pack y servicio. P0 inventario | `ITEM-G`, `PACK-01`, `SERV-01`, stock insuficiente | Vender cantidad suficiente, insuficiente, pack y servicio | Producto y pack validan stock; servicio no descuenta fisico | Movimientos por componente y almacen correcto | AUTOMATIZADO; `fiscal-order-stock.test.cjs` |
| FT-POS-007 | Apertura y cierre de caja. P0 caja | `USR-SELL`, monto inicial y operaciones | Abrir, vender, registrar ingreso/egreso, cerrar | Solo una caja abierta por actor según contrato; cierre cuadra | Reporte de caja y pagos coincide | MANUAL |
| FT-POS-008 | Pagos mixtos y saldo. P0 pagos | `PAY-01`, total conocido | Pagar con efectivo y transferencia; exceder; eliminar autorizado | Mezcla valida; excedente y metodo inactivo rechazan | Cada pago, destino, caja y saldo correctos | AUTOMATIZADO; `FiscalPaymentDestinationTest` |
| FT-POS-009 | Monto manual, vuelto y denominaciones. P1 interfaz | Efectivo VES; monto mayor al total | Ingresar monto manual, calcular vuelto y consultar accesos rapidos | Vuelto correcto; monto manual disponible; denominaciones no alteran contrato | Pago persistido por monto real | AUTOMATIZADO; `PosCashDenominationsContractTest` |
| FT-POS-010 | Alternancia VES/USD y tipo de cambio. P0 calculo | POS con ambas monedas y tasa | Cambiar moneda antes y despues de agregar item | Simbolo, conversion y total se recalculan sin doble conversion | Documento conserva moneda y tasa aplicada | AUTOMATIZADO; `VenezuelaCurrencyTest` |
| FT-POS-011 | Bloqueo por permiso y caja cerrada. P0 seguridad | `USR-VIS`; caja cerrada | Intentar vender, pagar, imprimir y consultar detalle | 403 o redireccion controlada; no efecto lateral | Sin documento, pago o Kardex | MANUAL |
| FT-POS-012 | Reintento de venta POS. P0 idempotencia | Operacion con clave estable y respuesta incierta simulada | Reenviar la misma venta | Recupera la respuesta sin duplicar | Un documento, un pago, un movimiento | AUTOMATIZADO; `FiscalOperationFingerprintTest` |

### 6.5 Ecommerce, Mi Tienda y pedidos `FT-ECOM`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-ECOM-001 | Catalogo publico y precio autorizado. P0 seguridad | Producto visible y producto oculto | Consultar catalogo, item y carrito | Solo items visibles; precio se obtiene del servidor | No se acepta precio manipulado del cliente | MANUAL; `ProductModuleFlowContractTest` |
| FT-ECOM-002 | Carrito gravado, exento y servicio. P0 calculo | `ITEM-G`, `ITEM-E`, `SERV-01` | Agregar, modificar cantidad y quitar | IVA, subtotal, descuento y total correctos; servicio no usa stock fisico | Pedido conserva lineas y afectaciones | INTEGRACION; `VendeyaDocumentPayloadNormalizerTest` |
| FT-ECOM-003 | Cupon y campaña con concurrencia. P1 seguridad | Cupon activo, expirado y limite uno | Validar, aplicar dos veces y usar en dos pedidos | Solo aplicacion autorizada e idempotente | Uso del cupon y total quedan atomicos | MANUAL/BRECHA |
| FT-ECOM-004 | Guest y cliente autenticado. P0 seguridad | Guest, `CUS-RIF`, cliente de otro pedido | Crear carrito, iniciar sesion, consultar y actualizar | Guest solo accede por capacidad; cliente solo a sus datos | No se puede cambiar `external_id` para acceder a otro pedido | MANUAL |
| FT-ECOM-005 | Direccion y ubicacion venezolana. P1 datos | Direccion completa, omitida, ambigua y de otro pais | Guardar y asociar direccion al pedido | Jerarquia valida; ubicacion omitida permitida si el flujo lo admite | Parroquia, municipio y estado persistidos correctamente | AUTOMATIZADO; `VenezuelaPersonLocationTest` |
| FT-ECOM-006 | Pago en efectivo ecommerce. P1 positivo | Carrito listo y `payment_cash` | Confirmar y consultar pagina de gracias | Pedido registrado; pago comercial segun contrato | Pedido, pago y cliente vinculados | MANUAL |
| FT-ECOM-007 | Pasarela en sandbox o doble. P0 integracion | `PAY-02`, VES y USD | Crear pago, recibir aprobado, rechazado y duplicado | Se valida comercio, importe, moneda, pedido y transaccion | Una transaccion y una conversion documental | INTEGRACION; `FiscalPaymentDestinationTest` |
| FT-ECOM-008 | Pedido con clave de operacion estable. P0 idempotencia | `ORD-01` o pedido nuevo | Enviar dos veces misma solicitud y una con contenido distinto | Igual contenido recupera; contenido distinto rechaza conflicto | No duplica pedido, pago ni documento | AUTOMATIZADO; `FiscalOperationFingerprintTest` |
| FT-ECOM-009 | Conversion automatica a Factura. P0 conversion | Pedido pago, emisor digital configurado | Procesar pedido a Factura y consultar detalle | Canal digital y emisor configurados por servidor | Factura y vinculo de pedido se guardan en una transaccion | AUTOMATIZADO; `FiscalOrderConversionTest` |
| FT-ECOM-010 | Conversion automatica a Nota de venta. P0 conversion | Pedido configurado para NV, reserva activa | Procesar y repetir | Recupera NV existente; no crea Factura adicional | Reserva liberada una vez; almacenes conservados | AUTOMATIZADO; `FiscalOrderSalesNoteContextTest` |
| FT-ECOM-011 | Reserva y liberacion de stock. P0 inventario | `ORD-01`, pack, servicio y stock | Reservar, liberar, cambiar estado y repetir | Cantidades y almacenes se toman del pedido persistido | `stock_reservation` y Kardex sin duplicados | AUTOMATIZADO; `FiscalOrderStockReservationTest` |
| FT-ECOM-012 | Pedido facturado bloquea cambios fisicos. P0 seguridad | Pedido con Factura vinculada | Intentar descontar, liberar y anular desde estado | Accion rechazada antes de cambiar estado | Pedido, factura e inventario permanecen consistentes | AUTOMATIZADO; `FiscalOrderStockGuardTest` |
| FT-ECOM-013 | Fallo despues de reservar o facturar. P0 rollback | Doble que falla despues de escritura intermedia | Provocar error y revisar estado; reintentar | Transaccion revierte reserva, pago, vinculo y factura parcial | No quedan huérfanos ni numeros reutilizados | AUTOMATIZADO; `FiscalOrderConversionTest` |
| FT-ECOM-014 | Mi Tienda sin series de Boleta. P0 regresion | Configuracion nueva y serie de Factura | Abrir configuracion, guardar y procesar pedido | Solo depende de establecimiento y Factura; no consulta campo retirado | Esquema sin `series_document_bt_id` | AUTOMATIZADO; `FiscalEmissionSchemaTest` |

### 6.6 Restaurante `FT-REST`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-REST-001 | Acceso publico de mesa o mozo. P1 seguridad | `REST-01`, enlace valido e invalido | Abrir enlace, autenticarse y consultar mesa | Solo enlace y mesa autorizados; invalido rechaza | No expone pedidos de otra mesa o tenant | MANUAL |
| FT-REST-002 | Crear pedido con modificadores. P0 positivo | Item, grupo de modificadores y mesa | Seleccionar, agregar, editar y enviar pedido | Precio, cantidad y modificadores correctos | Pedido y detalle persistidos; no factura aun | MANUAL |
| FT-REST-003 | Insumos y descuento de stock. P0 inventario | Receta con insumos suficientes e insuficientes | Confirmar pedido y repetir descuento | Descuenta insumos una vez; insuficiencia rechaza | Stock y pedido consistentes | INTEGRACION |
| FT-REST-004 | Roles de mozo y configuracion. P0 seguridad | `USR-ADM`, mozo autorizado y no autorizado | Crear rol, asignar, entrar y emitir | Solo rol autorizado opera su alcance | Cambios de rol auditables y tenant aislado | MANUAL |
| FT-REST-005 | Impresion de orden de cocina. P1 visual | Impresora configurada y no disponible | Enviar orden, sincronizar y reintentar | Orden legible; error no duplica pedido | Registro de impresion y estado claros | MANUAL |
| FT-REST-006 | Caja de restaurante. P0 caja | `REST-01`, caja abierta | Registrar pedido, pago, cierre y reportes | Totales por medio de pago cuadran | Caja, pagos y ventas relacionadas | MANUAL |
| FT-REST-007 | Generar Factura o Nota de venta. P0 conversion | Pedido servido y cliente valido | Seleccionar tipo permitido y confirmar | Genera 01 o 80/nv; tipo 03 es rechazado | Inventario, pagos y documento una vez | AUTOMATIZADO; `SalesDocumentTypePolicyTest` |
| FT-REST-008 | API de caja y pedido restaurante. P1 API | Token valido e invalido | Invocar `cash/restaurant` y documentos | Payload valido procesa; token o tenant incorrecto rechaza | Sin efectos parciales ni datos externos | MANUAL |

### 6.7 Hotel `FT-HOT`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-HOT-001 | Configurar categoria, tarifa, piso y habitacion. P1 positivo | Administrador hotel y datos validos | Crear, editar, desactivar y consultar | Catalogos visibles y consistentes | Relaciones hoteleras correctas | MANUAL |
| FT-HOT-002 | Abrir renta con cliente RIF, cedula y pasaporte. P0 identidad | `CUS-RIF`, `CUS-ID`, `CUS-PAS` | Iniciar renta en habitacion | Identidad y datos del huesped correctos | `hotel_rents` y cliente vinculados | AUTOMATIZADO; `HotelVenezuelanIdentificationContractTest` |
| FT-HOT-003 | Agregar producto y servicio a habitacion. P0 positivo | `HOT-01`, `ITEM-G`, `SERV-01` | Agregar cantidades, consultar y corregir | Producto usa UND y servicio SERV; precios correctos | Consumos ligados a renta; servicio no descuenta stock | AUTOMATIZADO; `HotelRentProductsTemplateContractTest` |
| FT-HOT-004 | Checkout con IVA, descuento y moneda. P0 calculo | Renta con gravado, exento y tasa | Finalizar checkout en VES y USD | Subtotal, IVA y total correctos; moneda y tasa consistentes | Renta finalizada, pagos y documento coherentes | MANUAL |
| FT-HOT-005 | Generar Factura desde renta. P0 conversion | Renta finalizable y cliente con datos | Finalizar como Factura | Factura 01 conserva consumos, identidad y establecimiento | Documento, renta y pago vinculados una vez | MANUAL |
| FT-HOT-006 | Generar Nota de venta de Hotel. P0 visual | Renta finalizable, tipo NV | Finalizar e imprimir resumen | Presenta Subtotal y Total sin fila separada de IVA | Calculo persistido permanece sin cambios | AUTOMATIZADO; `InvoiceTotalsPaymentsVisibilityContractTest` |
| FT-HOT-007 | Extendiendo tiempo y doble checkout. P1 limite | Renta activa y otra finalizada | Extender, finalizar dos veces y reintentar | Solo una finalizacion; estados invalidos rechazan | No duplica consumos, pago ni documento | MANUAL |
| FT-HOT-008 | Reporte de rentas y documentos hotel. P1 reporte | Rango con varias rentas y establecimientos | Filtrar, exportar PDF/Excel y revisar totales | Filtros, moneda, cliente y documento correctos | Reporte coincide con tablas fuente | MANUAL |

### 6.8 API, movil, Vende Ya y WhatsApp `FT-API`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-API-001 | Login y contexto tenant. P0 seguridad | Token valido, invalido y tenant equivocado | Autenticar y llamar endpoint documental | Solo usuario y tenant autorizados; error controlado | No consulta modelos del tenant incorrecto | AUTOMATIZADO; `FiscalApiDocumentContextTest` |
| FT-API-002 | Crear documento por API. P0 positivo | Payload de Factura 01 completo | Enviar, consultar y listar | Respuesta estable con documento y estado local | Persistencia igual a web; snapshot fiscal completo | AUTOMATIZADO; `FiscalApiDocumentContextTest` |
| FT-API-003 | Payload con tipo, moneda o afectacion retirada. P0 negativo | `03`, `PEN`, `VED`, afectacion distinta de 10/20 | Enviar combinaciones invalidas | 422 antes de efectos laterales | No crea cliente, documento, pago ni stock | AUTOMATIZADO; `SalesDocumentTypePolicyTest`, `VenezuelaCurrencyTest` |
| FT-API-004 | Crear cliente por API con ubicacion. P1 datos | Parroquia valida, omitida, ambigua y de otro pais | Crear cliente y documento | Resuelve padres por catalogo; no usa substr ni acepta jerarquia invalida | Ceros iniciales preservados; error no deja fila parcial | AUTOMATIZADO; `VenezuelaPersonLocationTest` |
| FT-API-005 | `operation_key` y alias de clave. P0 idempotencia | Misma clave y mismo payload; misma clave con payload distinto | Reintentar y luego modificar contenido | Mismo resultado para mismo fingerprint; conflicto para contenido distinto | Un documento, pago, inventario y numero | AUTOMATIZADO; `FiscalOperationFingerprintTest` |
| FT-API-006 | Canal y perfil no manipulables. P0 seguridad | Payload intenta forzar presencial, digital, sucursal o emisor | Enviar desde integrator y seller | Servidor resuelve canal por actor y origen real | Snapshot usa perfil autorizado, no payload | AUTOMATIZADO; `FiscalApiDocumentContextTest`, `FiscalWebDocumentContextTest` |
| FT-API-007 | Listados, busqueda y estados. P1 consulta | Documentos de ambos tenants y fechas | Consultar listas, find y estado | Solo registros autorizados; estado local claro | No incluye XML, CDR, api tokens o credenciales | MANUAL |
| FT-API-008 | PDF y correo desde movil. P1 visual | Documento existente y token | Descargar PDF, texto y enviar correo | PDF corresponde al documento; respuesta no expone ruta libre | Acceso por modelo y external id autorizado | MANUAL; `MobileApp/Routes/api.php` |
| FT-API-009 | WhatsApp PDF y telefono venezolano. P1 integracion | Cliente `+58`, documento y doble de envio | Solicitar media/PDF y enviar mensaje | Numero normalizado; mensaje usa tipo real y PDF correcto | Log sin secretos; no se envia a otro tenant | MANUAL; `VenezuelaPhoneLocalizationTest` |
| FT-API-010 | Error HTTP, permisos y rate limit. P0 seguridad | Usuario sin permiso, serie agotada y conflicto | Ejecutar acciones rechazadas | 403, 404, 409, 422 o 429 segun causa; sin debug | `Retry-After` cuando aplica; no hay escritura parcial | AUTOMATIZADO; `FiscalHttpExceptionResponseTest` |

### 6.9 Ordenes de entrega y despacho `FT-DISP`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-DISP-001 | Crear orden de entrega. P0 positivo | Cliente, origen, destino, transportista y lineas | Crear desde menu y desde venta | Orden tipo 09 con datos completos | Numero, serie, direcciones y relaciones correctas | MANUAL; `DeliveryOrderNamingContractTest` |
| FT-DISP-002 | Direcciones y transportista autorizados. P1 seguridad | Direccion propia, otra sucursal y entidad inactiva | Seleccionar y guardar cada variante | Solo opciones permitidas; errores por campo | No guarda relacion ajena | MANUAL |
| FT-DISP-003 | Generar despacho desde Factura o Nota. P0 conversion | Factura/Nota con items y stock | Generar, consultar y reintentar | Se conserva origen, lineas y cliente; reintento recupera | Vinculo unico; no duplica stock | AUTOMATIZADO; `FiscalDispatchConversionTest` |
| FT-DISP-004 | Emision tipo 09 por modalidad. P0 fiscal | Perfil compatible y serie de despacho | Procesar en demo, confirmar e invalidar impresion | Usa numeracion de despacho propia; no se trata como Factura | Registro fiscal local y auditoria | AUTOMATIZADO; `FiscalDispatchEmissionControllerTest` |
| FT-DISP-005 | Agotamiento y concurrencia de numeracion. P0 concurrencia | `SER-END`, dos procesos | Emitir en paralelo | Un solo consumidor obtiene el ultimo control; otro recibe agotamiento | Sin numeros duplicados o reciclados | AUTOMATIZADO; `FiscalMySqlConcurrencyTest` |
| FT-DISP-006 | PDF, impresion y correo de despacho. P1 visual | Orden con datos y formatos | Descargar, imprimir, reimprimir y enviar | Documento legible; sin XML/CDR; QR solo si es comercial vigente | Una sola numeracion y evidencia de impresion | MANUAL; `CurrentPdfTemplateContractTest` |
| FT-DISP-007 | Anular despacho. P1 estado | Despacho registrado, rechazado y anulado | Anular cada estado y repetir | Solo transiciones validas; efectos fisicos una vez | Estado, auditoria y stock coherentes | MANUAL |
| FT-DISP-008 | API y movil de despacho. P1 API | Token y payload validos/invalidos | Crear, consultar, enviar PDF y actualizar estado | Contrato consistente con web; tenant aislado | No acepta contexto de otro pedido por payload | AUTOMATIZADO; `FiscalDispatchEmissionControllerTest` |

### 6.10 Facturacion masiva `FT-MASS`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-MASS-001 | Descargar y validar plantilla XLSX. P0 entrada | Plantilla vigente y archivo alterado | Descargar formato, cambiar encabezados, tipos y columnas; cargar | Solo estructura y columnas contractuales son aceptadas | No procesa filas si la plantilla es invalida; conservar archivo de evidencia | AUTOMATIZADO; `LocalFiscalDocumentPolicyTest` |
| FT-MASS-002 | Importar lote valido de Facturas. P0 positivo | Lote con clientes, items, IVA, VES/USD y series validas | Cargar, prevalidar y procesar | Filas validas generan documentos locales con numeracion unica | Documentos, pagos, inventario y estados guardados por fila | MANUAL/INTEGRACION |
| FT-MASS-003 | Reporte XLSX de errores. P0 negativo | Filas con cliente invalido, item inexistente, unidad NIU, moneda PEN y tipo 03 | Ejecutar validacion y descargar errores | Reporta fila, columna, regla y valor saneado; no procesa fila invalida | No hay efectos parciales de filas rechazadas | AUTOMATIZADO; `SalesIdentityDocumentEmissionContractTest`, `DetractionRemovalTest` |
| FT-MASS-004 | Correccion parcial y revalidacion. P1 recuperacion | Reporte con algunos errores corregidos | Reenviar reporte, conservar errores pendientes y procesar solo validos | Filas corregidas pasan; filas pendientes siguen señaladas | No duplica filas previamente procesadas ni numeros | MANUAL |
| FT-MASS-005 | Procesamiento local y descarga PDF. P0 visual | Lote procesado y documentos generados | Consultar estados, descargar PDF individual y lote | Estado es registrado localmente; PDF legible; no XML/CDR ni aceptacion externa | `estado_emision` y `mensaje_emision`; sin campos SUNAT retirados | AUTOMATIZADO/MANUAL; `LocalFiscalDocumentPolicyTest`, `FiscalEmissionSchemaTest` |
| FT-MASS-006 | Permisos, aislamiento e idempotencia del lote. P0 seguridad | Dos tenants, admin, vendedor y lote repetido | Procesar con roles, tenant cruzado y misma clave | Solo actor autorizado; tenant aislado; reintento no duplica | Lote, documentos, pagos e inventario una sola vez | MANUAL/BRECHA |

### 6.11 Compras `FT-PUR`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-PUR-001 | Crear compra con proveedor e items. P0 positivo | Proveedor valido, `ITEM-G`, `SERV-01`, `WH-01` | Crear y guardar compra | Totales, proveedor, almacen y lineas correctos | Compra, items, stock y pagos segun contrato | MANUAL |
| FT-PUR-002 | Validar proveedor y datos obligatorios. P0 negativo | Proveedor inexistente, inactivo y campos faltantes | Enviar cada variante | Error antes de persistir y recibir stock | No hay compra parcial | MANUAL |
| FT-PUR-003 | IVA de compra gravado, exento y mixto. P0 calculo | Afectaciones 10 y 20; tasa 16 | Crear compra con mezcla | IVA y total por linea correctos | `purchase_has_igv`, `percentage_igv`, `total_igv` correctos | AUTOMATIZADO; `VenezuelaIvaContractTest` |
| FT-PUR-004 | Compra en VES y USD. P0 moneda | Catalogo VES/USD y tipo de cambio | Crear, editar y reportar compras | Simbolo, cambio y totales correctos | Moneda y tasa persistidos; PEN/VED rechazan | AUTOMATIZADO; `VenezuelaCurrencyTest` |
| FT-PUR-005 | Producto, servicio, unidad y presentacion. P1 datos | `ITEM-G` UND, `SERV-01` SERV, unidad retirada | Agregar cada item e importar variante | UND/SERV aceptan; NIU/ZZ/inexistente rechazan | Unidad activa persistida y reportada | AUTOMATIZADO; `VenezuelaUnitTypeContractTest`, `ProductModuleFlowContractTest` |
| FT-PUR-006 | Almacen por compra. P0 inventario | Dos almacenes y seleccion explicita | Crear compra con almacen principal y secundario | Stock se incrementa en almacen seleccionado | No toma primero por defecto cuando hay seleccion valida | AUTOMATIZADO; `PurchaseDefaultWarehouseSourceContractTest` |
| FT-PUR-007 | Cotizacion de compra. P1 flujo | Proveedor e items | Crear, editar, descargar y convertir | Cotizacion no altera stock ni pago hasta convertir | Conversion conserva origen y datos | MANUAL |
| FT-PUR-008 | Orden de compra y recepcion. P0 conversion | Orden aprobada | Crear, anexar, recibir parcialmente y completar | Recepcion parcial y total calculan stock y saldo correctos | Vinculo con orden; no duplica recepcion | MANUAL |
| FT-PUR-009 | Compra desde orden de compra. P0 conversion | Orden con precios y almacen | Generar compra y reintentar | Compra unica con datos de orden persistida | Orden y compra vinculadas; fingerprint estable | MANUAL |
| FT-PUR-010 | Pago de compra parcial y total. P0 pagos | Compra pendiente, `PAY-01` | Registrar, listar, eliminar autorizado y repetir | Saldo, vencimiento y estado correctos | `purchase_payments`, caja y finanzas consistentes | MANUAL |
| FT-PUR-011 | Compra de activo fijo. P1 integracion | Activo y proveedor validos | Crear compra de activo, consultar y anular | Activo y compra relacionados; no se mezcla con inventario vendible | Reportes de activo y compra coinciden | MANUAL |
| FT-PUR-012 | Adjuntar soporte de compra. P1 seguridad | Archivo PDF permitido, archivo ejecutable y tenant ajeno | Cargar, descargar y eliminar permitido | MIME, extension, tamaño y tenant validados | Archivo aislado; nombre generado en servidor; sin ejecucion | MANUAL |
| FT-PUR-013 | Anular compra. P0 estado | Compra con y sin pago, stock recibido | Anular, repetir y consultar | Reversion segun estado; no doble movimiento | Stock, saldo, pago y auditoria coherentes | MANUAL |
| FT-PUR-014 | Compra por API y reporte. P1 API | Token valido y payload controlado | Crear, consultar, imprimir y exportar | Respuesta y reportes consistentes con web | No acepta campos fiscales retirados ni contexto externo | MANUAL |

### 6.12 Gastos y prestamos `FT-EXP`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-EXP-001 | Crear gasto con motivo y metodo. P0 positivo | Catalogos de 30 motivos y metodo activo | Crear, consultar y reportar | Gasto completo y total correcto | Motivo y metodo validos en tenant | MANUAL; `VenezuelaInitialCatalogContractTest` |
| FT-EXP-002 | Rechazar motivo o metodo inactivo. P0 negativo | Catalogo inactivo, desconocido y detraccion heredada | Enviar cada variante | Rechazo antes de guardar; no reintroduce detraccion | Sin filas de catalogo retirado | AUTOMATIZADO; `DetractionRemovalTest` |
| FT-EXP-003 | Gasto VES/USD. P0 moneda | Monedas activas y tasa | Crear gasto en ambas monedas | Etiquetas y conversiones correctas | Moneda, tasa y saldo persistidos | AUTOMATIZADO; `VenezuelaCurrencyTest` |
| FT-EXP-004 | Pago parcial y total de gasto. P0 pagos | Gasto pendiente y `PAY-01` | Registrar, consultar, eliminar autorizado | Saldo y estado correctos; no sobrepago | `expense_payments` y caja consistentes | MANUAL |
| FT-EXP-005 | Prestamo y pago de prestamo. P1 flujo | Prestamo valido y vencimientos | Crear, pagar parcial, cancelar y consultar | Capital, interes y saldo segun reglas configuradas | Movimientos y pagos relacionados | MANUAL |
| FT-EXP-006 | Anular gasto o prestamo. P1 estado | Registro con pagos y sin pagos | Anular y repetir | Transicion valida; no doble impacto financiero | Auditoria y saldos consistentes | MANUAL |
| FT-EXP-007 | PDF, Excel y adjuntos de gasto. P1 visual | Gasto con datos y soporte | Imprimir, exportar, descargar adjunto | Reporte legible; solo formatos permitidos | Sin secretos ni rutas libres | MANUAL |
| FT-EXP-008 | Aislamiento y permisos de gastos. P0 seguridad | `USR-VIS`, `TEN-02`, IDs coincidentes | Consultar, crear, anular y descargar | Solo administrador o rol autorizado dentro de tenant | No hay lectura ni escritura cruzada | MANUAL; `MultiUserAccessSecurityTest` |

### 6.13 Finanzas, pagos y contabilidad `FT-FIN`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-FIN-001 | Catalogo de metodos de pago. P0 configuracion | Administrador y metodo activo/inactivo | Crear, activar, desactivar y consultar | Solo metodos validos aparecen en pagos | Documentos no pierden historial al desactivar | MANUAL |
| FT-FIN-002 | Pago de documento desde modulo Payments. P0 pagos | Factura, NV y compra pendientes | Registrar pago por cada tipo | Pago llega al destino correcto y actualiza saldo | Relaciones polymorficas correctas | AUTOMATIZADO; `FiscalPaymentDestinationTest` |
| FT-FIN-003 | Pago parcial, total y excedido. P0 limite | Documento con total decimal | Aplicar pagos en varias partes y exceder | Saldos sin redondeo indebido; excedente rechaza | Caja y reportes reflejan importes exactos | MANUAL |
| FT-FIN-004 | Pago eliminado o reversado. P1 estado | Pago existente y permisos | Eliminar o revertir desde UI y API | Solo acción autorizada; saldo se recalcula una vez | Auditoria y caja reversadas correctamente | MANUAL |
| FT-FIN-005 | Pagos globales. P0 conciliacion | Varios documentos de cliente | Distribuir pago, cambiar prioridad y confirmar | Distribucion no supera saldos y queda trazable | Todos los documentos y saldo global cuadran | MANUAL |
| FT-FIN-006 | Cuentas por cobrar y vencidos. P0 reporte | Facturas con fechas y saldos | Filtrar no pagados, vencidos y por cliente | Totales y dias correctos | Reporte coincide con pagos y documentos | MANUAL |
| FT-FIN-007 | Cuentas por pagar. P0 reporte | Compras y gastos pendientes | Filtrar por proveedor, fecha y vencimiento | Saldos correctos y sin mezclar ventas | Reporte coincide con compras y pagos | MANUAL |
| FT-FIN-008 | Ingreso manual. P1 flujo | Tipo y motivo de ingreso validos | Crear, pagar, imprimir y anular | Registro afecta caja o cuenta configurada | Movimiento y auditoria correctos | MANUAL |
| FT-FIN-009 | Movimientos, transacciones y caja. P0 reporte | Pagos, ingresos y egresos previos | Consultar filtros, PDF y Excel | Saldos inicial/final y detalle coinciden | Fuentes reconciliadas con caja | MANUAL |
| FT-FIN-010 | Transferencia entre cuentas. P0 integracion | Dos cuentas del mismo tenant | Transferir, repetir y usar cuenta ajena | Solo cuentas autorizadas; doble transferencia rechazada | Debito y credito atomicos | MANUAL |
| FT-FIN-011 | Enlace de pago sin pago. P1 flujo | Documento y enlace configurable | Crear, enviar, abrir y cancelar enlace | Enlace identifica documento y total sin cobrar | Estado de enlace y documento coherentes | MANUAL |
| FT-FIN-012 | Enlace publico y confirmacion de pasarela. P0 seguridad | Token valido, expirado, replay y doble de pasarela | Abrir, confirmar aprobado/rechazado y repetir | Verifica pedido, importe, moneda y transaccion | Un pago y una conversion documental | INTEGRACION; `FiscalPaymentDestinationTest` |
| FT-FIN-013 | Archivo de soporte de pago. P1 seguridad | PDF permitido y archivo activo | Cargar, descargar por tipo y eliminar | Almacenamiento aislado y nombre seguro | No lee `temp_path` libre ni expone ruta interna | MANUAL |
| FT-FIN-014 | Tipo de cambio y reportes financieros. P0 calculo | Movimientos VES/USD y tasa por fecha | Registrar, consultar y exportar | Conversión usa fecha y dirección correctas | Valores origen/destino preservados | MANUAL; `VenezuelaCurrencyTest` |
| FT-FIN-015 | Libro contable y EJB. P1 reporte | Movimientos y documentos contabilizables | Generar libro, exportar y consultar cuenta | Totales trazables a operaciones; no incluye transporte fiscal retirado | Archivo y filtros correctos | MANUAL |
| FT-FIN-016 | Aislamiento de pagos y cuentas. P0 seguridad | `TEN-01`, `TEN-02`, IDs coincidentes | Consultar, pagar y exportar usando IDs cruzados | Rechazo o conjunto vacio controlado | Ningun pago o movimiento cruza tenant | MANUAL; `MultiUserAccessSecurityTest` |

### 6.14 Reportes, PDF, Excel y consulta `FT-REP`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-REP-001 | Reporte de ventas por fecha, cliente y documento. P0 reporte | Facturas, NC, ND y anulados | Filtrar y consultar registros | Filtros y totales correctos; anulados visibles segun contrato | Datos coinciden con `documents` | MANUAL |
| FT-REP-002 | Reporte de Facturas y notas en PDF/Excel. P0 exportacion | Documentos gravados, exentos y mixtos | Exportar formatos y revisar contenido | Encabezados, moneda, IVA, totales y estados correctos | Sin XML/CDR/hash fiscal | AUTOMATIZADO/MANUAL; `CurrentPdfTemplateContractTest` |
| FT-REP-003 | Reporte de Notas de venta. P0 reporte | NV de web, POS, hotel y ecommerce | Filtrar, totalizar, PDF y Excel | Incluye solo NV; Hotel no agrega IVA separado en su resumen | Totales coinciden con `sale_notes` | MANUAL |
| FT-REP-004 | Reporte de compras y gastos. P0 reporte | Compras, activos, gastos y pagos | Filtrar por proveedor, almacen, moneda y fecha | Totales, IVA y saldos correctos | Sin campos retirados ni datos cruzados | MANUAL |
| FT-REP-005 | Reporte de caja y medios de pago. P0 conciliacion | Cajas cerradas y abiertas | Generar A4, ticket, Excel y resumen | Efectivo, transferencia, tarjeta, vuelto y saldo cuadran | Fuente coincide con pagos y caja | MANUAL |
| FT-REP-006 | Reporte de hotel y documentos hotel. P1 reporte | Rentas, consumos y checkout | Filtrar por establecimiento y rango; exportar | Datos de habitacion, cliente, moneda y documento correctos | No filtra por tenant ajeno | MANUAL |
| FT-REP-007 | Reporte de ordenes y reservas. P1 inventario | Pedidos con reserva, conversion y anulación | Consultar estados, almacenes y cantidades | Reserva y liberacion coinciden con persistencia | Sin doble descuento | AUTOMATIZADO; `FiscalOrderStockReservationTest` |
| FT-REP-008 | Reporte de no pagados y por pagar. P0 reporte | Saldos parciales y vencidos | Filtrar, imprimir y exportar | Dias, importes y estados correctos | Cruza solo relaciones esperadas | MANUAL |
| FT-REP-009 | Estado de cuenta y analisis comercial. P1 reporte | Cliente con ventas, pagos y notas | Generar cuenta y analisis | Saldo, ventas netas y pagos trazables | Totales reproducibles | MANUAL |
| FT-REP-010 | Consistencia documental y bandeja de descarga. P0 recuperacion | Documento con PDF y exportacion en cola | Ejecutar consistencia, descargar y repetir | Errores detectables; descarga autorizada y estable | No genera archivos XML/CDR; sin rutas de otro tenant | MANUAL; `TestDocumentsDeletionContractTest` |
| FT-REP-011 | Consulta publica de documento. P0 seguridad | Numero valido, invalido y de otro tenant | Buscar desde formulario y API | Resultado minimo, correcto y no enumerable | No muestra secretos ni documentos ajenos | MANUAL |
| FT-REP-012 | Plantillas PDF vigentes. P0 visual | A4, A5, ticket, marca de agua, NRUS y dispatch | Renderizar cada plantilla con datos base y revisar PNG | Sin clipping, solapamiento, glifos faltantes, Boletas, XML/CDR, ISC o bolsas | PDFs legibles y texto contractual correcto | AUTOMATIZADO/MANUAL; `CurrentPdfRenderingTest`, `CurrentPdfTemplateContractTest` |

### 6.15 Seguridad y autorizacion transversal `FT-SEC`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-SEC-001 | Matriz de roles admin, vendedor, integrator y visitante. P0 seguridad | Usuarios de cada rol | Ejecutar alta, venta, pago, reporte, config y anulacion | Cada accion respeta permiso backend, no solo menu | Rechazo antes de escribir | MANUAL; `MultiUserAccessSecurityTest` |
| FT-SEC-002 | Peticion AJAX no omite autorizacion. P0 seguridad | Accion restringida y header AJAX | Repetir con y sin `X-Requested-With` | Mismo resultado de autorizacion | Sin bypass en rutas/controladores | AUTOMATIZADO; `FiscalHttpExceptionResponseTest` |
| FT-SEC-003 | Aislamiento por tenant y establecimiento. P0 seguridad | `TEN-01`, `TEN-02`, IDs iguales | Cambiar IDs en URL, payload y cookie | Solo contexto de servidor permite acceso | Sin lectura, pago, documento o archivo cruzado | MANUAL |
| FT-SEC-004 | Guest ecommerce frente a usuario tenant. P0 seguridad | Guest con pedido propio y ajeno | Consultar, pagar, descargar y editar | Guest usa capacidad especifica; no usa guard de empleados | Sin enumeracion por ID secuencial | MANUAL |
| FT-SEC-005 | Archivos privados y cargas administrativas. P0 seguridad | PDF valido, PHP, SVG activo, `temp_path` externo | Cargar, leer, descargar y procesar | Permiso, MIME, tamaño, tenant y ruta validos | No ejecucion ni lectura arbitraria | MANUAL |
| FT-SEC-006 | Documentos publicos y enlaces firmados. P0 seguridad | Documento compartido y enlace expirado | Abrir con enlace valido, alterado y vencido | Solo capacidad vigente permite descarga | Respuesta minima y sin datos internos | MANUAL |
| FT-SEC-007 | Tokens y secretos en recursos. P0 seguridad | Usuario, API token y credenciales de proveedor | Consultar recursos, errores, logs y auditoria | No se serializan passwords, hashes, tokens ni claves | Evidencia de redaccion | AUTOMATIZADO; `FiscalEmissionSettingsTest` |
| FT-SEC-008 | Webhook autenticado, replay y duplicado. P0 integracion | Firma valida, invalida y evento repetido | Recibir eventos y repetir | Verifica autenticidad antes de procesar; replay no duplica | Evento y entrega ligados al tenant | MANUAL |
| FT-SEC-009 | Webhook saliente y destino no interno. P1 red | URL publica, loopback, link-local, redirect y DNS cambiante | Crear suscripcion y entregar evento | Destinos internos bloqueados; redirects deshabilitados; timeout | No secretos en logs de entrega | MANUAL |
| FT-SEC-010 | GET sin mutacion. P0 seguridad | Endpoints de consulta y accion | Invocar GET repetidamente y con CSRF ausente | GET no paga, anula, cambia permisos ni vacia tablas | Estado sin variacion indebida | MANUAL |

### 6.16 Concurrencia, rollback y recuperacion `FT-RES`

| ID | Caso, prioridad y tipo | Precondiciones y datos | Pasos | Resultado esperado | Persistencia y evidencia | Estado o referencia |
| --- | --- | --- | --- | --- | --- | --- |
| FT-RES-001 | Dos ventas consumen series en paralelo. P0 concurrencia | Dos operaciones distintas, misma serie | Ejecutar procesos concurrentes | Cada operacion recibe numero distinto o error controlado | Indices y reservas sin solapamiento | AUTOMATIZADO; `FiscalMySqlConcurrencyTest` |
| FT-RES-002 | Reintento con respuesta incierta. P0 recuperacion | Operacion confirmada pero respuesta perdida | Consultar y reenviar misma clave | Conciliacion recupera el resultado; no reemite | Un documento, control, pago e inventario | AUTOMATIZADO; `FiscalOperationFingerprintTest` |
| FT-RES-003 | Reintento con contenido cambiado. P0 seguridad | Misma clave, monto o lineas distintas | Reenviar payload alterado | Conflicto 409 o 422; no modifica original | Huella y auditoria conservadas | AUTOMATIZADO; `FiscalOperationFingerprintTest` |
| FT-RES-004 | Carrera pedido reserva frente a factura. P0 concurrencia | `ORD-01`, dos procesos | Cambiar estado y facturar en paralelo | Orden de bloqueos evita doble descuento o liberacion | Reserva, factura y Kardex consistentes | AUTOMATIZADO; `FiscalOrderStockReservationTest`, `FiscalOrderStockGuardTest` |
| FT-RES-005 | Falla despues de documento y antes de vinculo. P0 rollback | Conversión de pedido con error inducido | Ejecutar y revisar ambas tablas | Transaccion revierte documento y vínculo juntos | No queda factura huérfana ni pedido sin estado correcto | AUTOMATIZADO; `FiscalOrderConversionTest` |
| FT-RES-006 | Falla al generar PDF o confirmar impresion. P1 recuperacion | Documento numerado y renderer con error | Procesar, fallar PDF, reintentar y reimprimir | Numero no se recicla; reintento recupera o informa estado | Intento fiscal y estado de impresion trazables | AUTOMATIZADO; `FiscalPdfDataTest` |
| FT-RES-007 | Rollback de instalacion tenant. P0 esquema | Base temporal vacia | Ejecutar migraciones, seeding, rollback y segunda instalacion | Estructura y datos reproducibles; sin tablas retiradas | FKs, indices y defaults correctos | AUTOMATIZADO; `FiscalEmissionSchemaTest`, `TenantMigrationDataSeederTest` |
| FT-RES-008 | Errores de red, timeout y rate limit. P1 resiliencia | Doble de proveedor o endpoint con fallas | Provocar timeout, 429, 404, 403 y 422; reintentar | Mensajes accionables; no se reintenta operación no idempotente | Sin efectos parciales ni secretos en error | AUTOMATIZADO; `FiscalHttpExceptionResponseTest` |

## 7. Matriz minima de combinaciones

No se ejecuta el producto cartesiano completo. Cada combinacion de esta matriz debe estar representada por un caso de su suite o agregarse como variacion explicita en la ejecucion.

| Dimension | Valores obligatorios |
| --- | --- |
| Documento | Factura 01, NC 07, ND 08, Nota de venta 80/nv, Orden de entrega 09, compra y gasto. |
| Canal | Web, POS, venta rapida, garage, ecommerce, restaurante, hotel, servicio tecnico, API, movil, Vende Ya y WhatsApp. |
| IVA | Gravado 10 al 16 por ciento, exento 20, mezcla, tasa configurada distinta y rechazo de afectacion retirada. |
| Moneda | VES/Bs., USD/$, cambio por fecha, conversion y rechazo de PEN/VED. |
| Cliente | RIF, cedula, pasaporte, identidad inactiva, ubicacion completa, omitida, ambigua y de otro pais. |
| Linea | Producto UND, servicio SERV, pack, descuento, cantidad decimal permitida, cero, negativa, inexistente y stock insuficiente. |
| Numeracion | Serie activa, perfil por establecimiento/canal, lote ultimo, agotado, numero comprometido, reimpresion y concurrencia. |
| Pago | Efectivo, transferencia, tarjeta, mixto, parcial, total, excedido, eliminado, enlace, pasarela aprobada/rechazada y replay. |
| Estado | Borrador, registrado localmente, procesado, impresion confirmada, impresion invalidada, contingencia, anulado y reintento. |
| Actor | Administrador, vendedor, integrator, visitante, cliente ecommerce y guest. |
| Salida | Pantalla, JSON, PDF A4/A5/ticket, impresion, correo, Excel, consulta publica, webhook y logs. |

## 8. Evidencia requerida

Para cada ejecucion, QA debe conservar como minimo:

- ID del caso, fecha, ambiente, tenant y usuario sin incluir secretos.
- Datos usados: documento, cliente, establecimiento, serie, moneda, tasa, items, almacen y pago.
- Capturas o video para UI, POS, restaurante, hotel, ecommerce y estados.
- Request y response saneados para API, con tokens y credenciales anonimizados.
- PDF o imagen del PDF para casos visuales; revisar a 100 por ciento cuando la salida sea documental.
- Registro antes y despues de stock, caja, pagos, reservas, documento, auditoria y reportes.
- Resultado, severidad del defecto, evidencia de rollback y referencia a la prueba automatizada relacionada.

No se deben adjuntar api tokens, passwords, credenciales de proveedores, claves QZ privadas, cookies de sesion ni datos personales reales.

## 9. Criterios de aceptacion

- Los 182 casos tienen ID unico y pertenecen a una suite definida.
- Cada caso especifica objetivo, prioridad, tipo, precondiciones, datos, pasos, resultado visible, persistencia, evidencia y estado o referencia.
- Los 17 grupos de cobertura del mapa estan representados: configuracion, documentos, ventas, Notas de venta, POS, ecommerce, restaurante, hotel, API, facturacion masiva, despacho, compras, gastos, finanzas, reportes, seguridad y resiliencia.
- Cada contrato fiscal critico tiene casos positivos, negativos y de limite.
- Los escenarios de Boletas, series retiradas, SOAP, XML/CDR, PSE y aceptacion externa estan documentados solo como rechazo, ausencia o limite no soportado.
- Factura, NC, ND, Nota de venta y Orden de entrega conservan contratos separados; ningun caso usa la ausencia de Boletas para restringir indebidamente los otros modulos.
- IVA, VES/USD, unidades, identidades, establecimientos, almacenes, pagos, inventario y reservas aparecen en casos de creacion y de error.
- Cada prueba automatizada de referencia queda asociada a uno o mas casos, sin declarar automaticamente que cubre UI, navegador, proveedor real o PDF visual.
- Las brechas actuales quedan marcadas como `BRECHA` y no se presentan como pruebas aprobadas.
- El archivo pasa una revision de encabezados, enlaces internos, IDs duplicados, filas incompletas, placeholders y secretos.
- La implementacion de este documento no modifica APIs, codigo, migraciones, configuracion de runtime ni bundles.

## 10. Checklist de ejecucion por ciclo

1. Crear tenant temporal limpio y cargar datos base.
2. Ejecutar `FT-CONF` y bloquear el ambiente despues de la primera operacion.
3. Ejecutar documentos base y comprobar IVA, moneda, identidad, numeracion y PDF.
4. Ejecutar canales POS, ecommerce, restaurante, hotel, servicio tecnico, API y WhatsApp.
5. Ejecutar compras, gastos, pagos y reportes.
6. Ejecutar autorizacion, aislamiento, cargas, webhooks y casos no soportados.
7. Ejecutar concurrencia, rollback, reintentos y recuperacion.
8. Comparar documentos, inventario, caja, pagos, reservas, reportes y auditoria.
9. Registrar evidencia y actualizar el estado del caso sin modificar retrospectivamente el resultado.
10. Eliminar exclusivamente las bases temporales y conservar el reporte de ejecucion saneado.

## 11. Fuentes internas

- `routes/web.php` y `routes/api.php`.
- `modules/Document`, `Sale`, `Pos`, `Order`, `Ecommerce`, `Restaurant`, `Hotel`, `Dispatch`, `Purchase`, `Expense`, `Finance`, `Payment`, `Account`, `Report`, `MobileApp`, `WhatsAppApi` y `WhatsAppBot`.
- `resources/js/views/system/massive_invoice/` y las rutas de importacion y descarga masiva.
- `app/CoreFacturalo/Templates/pdf/` y `resources/views/tenant/reports/`.
- `database/migrations/tenant/` y `database/seeders/data/tenant_initial_data.php`.
- `tests/Unit/`, `tests/Browser/`, `tests/js/` y `tests/Support/`.
- `informes/adaptacion_modalidad_emision_fiscal.md`, `informes/numeracion_fiscal_venezuela.md` y `informes/auditoria_seguridad_pro9.md`.

La evidencia de codigo y pruebas se interpreta contra el estado real del repositorio en cada ciclo. Una regla documentada no se considera implementada hasta que el caso correspondiente se ejecute y se adjunte evidencia.
