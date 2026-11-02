---
name: mantener-catalogos-fiscales-venezuela
description: Mantener el inventario histórico y el estado inicial de los catálogos venezolanos de Pro9. Usar al tocar bancos, documentos, identidad, IVA, descuentos/cargos, leyendas, notas, operaciones, pagos, traslados, gastos o tablas fiscales peruanas retiradas.
---

# Mantener catálogos iniciales venezolanos

Antes de cambiar cualquier categoría cubierta por esta skill, leer el inventario normativo [references/catalogos-iniciales.md](references/catalogos-iniciales.md). Ese archivo conserva el estado final y el registro histórico retirado; actualizarlo junto con el código y las pruebas en el mismo cambio.

## Contrato

- Una apertura de caja con saldo positivo usa el efectivo activo de menor ID de cada moneda; validar todas las monedas antes de escribir saldos. El orden de IDs es binario UTF-8 en servidor y por puntos de código Unicode en JavaScript, para que los códigos personalizados se ordenen igual. Con saldo cero no requiere método. No interpretar `01` o `10` como efectivo ni `04` como transferencia: cualquier método puede reclasificarse. La repetición de una apertura existente conserva su snapshot. Ver [clasificación de medios](../../../informes/clasificacion_medios_pago_caja.md).
- El filtro de cuentas por cobrar incluye todos los métodos, aun inactivos o reclasificados, para localizar referencias históricas y personalizadas; no usa IDs fijos ni habilita métodos para nuevos cobros. Verificar `UnpaidPaymentMethodFilterTest`.
- La generación de nota desde pedidos y restaurante y el cierre de Hotel sólo ofrecen medios `cash` o `account` cuyo `is_active` sea explícitamente verdadero; un estado ausente no habilita un cobro. Comprobar `hotel-order-receipt-creation.test.cjs` y mantener la revalidación del servidor.
- En la cuota de crédito de nota de venta, la fecha es editable si el método vigente es `credit` sin días o con cero días; con plazo positivo se propone según ese plazo. No usar el ID `09` para controlar el campo. Verificar métodos iniciales reclasificados y personalizados en `default-receipt-methods.test.cjs`; el formulario se revisó también en bbc sin guardar la nota.
- Al cambiar el método de una cuota de factura o nota, aplicar el plazo sólo a la fila seleccionada y no sustituir fechas pactadas en otras filas. Cero numérico o textual no es un plazo positivo.
- Al conservar cuotas al alternar la condición, distinguir «Seleccione un método de crédito» de la ausencia real de métodos activos; contado omite las cuotas sólo en la solicitud, sin borrar el borrador.
- Para asociar notas a caja, la condición de pago guardada determina si hay crédito. Sólo si una nota antigua carece de ella se consulta `collection_kind` del método, no el indicador derivado `is_credit`; esa alternativa no puede reconstruir una condición histórica que nunca se guardó. Verificar `CreditConditionTest` y la prueba MySQL de registro de crédito en caja.
- Los catálogos de los diálogos de cobro posterior de facturas, notas, cotizaciones y servicios técnicos, ingresos, conversión de servicios técnicos y órdenes de entrega, y pagos de compras usan `getActiveReceiptMethods`: sólo `cash` y `account` activos. Crédito permanece como condición comercial y no como movimiento de dinero. Verificar los controladores con flags heredados contradictorios, clasificación modificada y un ID personalizado. Los pagos de compras conservan sus propias reglas monetarias; las liquidaciones de compras están fuera del alcance.
- Las opciones de destino bancario cargan el catálogo de bancos por lote y mantienen ID, descripción y moneda; sólo incluyen cuentas activas. Probar el número de consultas con `test_payment_destination_catalog_loads_bank_names_in_one_query`.
- Los catálogos de Hotel incluyen `is_active`, naturaleza y moneda para que el frontend pueda proponer efectivo compatible. Los cuatro catálogos de crédito de suscripciones consultan métodos activos con naturaleza `credit`, sin construir exclusiones por IDs. `PaymentClassificationTest` cubre ambos contratos; queda pendiente comprobar las pantallas con assets compilados.
- Las cotizaciones conservan el método de pago guardado al consultar y cargar los formularios: el ID `10` no debe convertirse a `01`. Su naturaleza y moneda se consultan en el catálogo vigente para operaciones nuevas y en el snapshot para movimientos registrados. Las pruebas MySQL, HTTP y la revisión visual están detalladas en [clasificación de medios](../../../informes/clasificacion_medios_pago_caja.md); la auditoría de otros consumidores continúa.
- Los filtros de efectivo y bancario de pagos comerciales consultan la naturaleza congelada en `global_payments.report_context.method`; una reclasificación posterior no altera los resultados. Los registros sin snapshot no se clasifican por un ID supuesto. La prueba `test_cash_filter_uses_recorded_classification_after_method_is_reclassified` cubre ingresos; los demás consumidores y la integración MySQL siguen pendientes de verificación.
- Los filtros compartidos de movimientos y devoluciones en efectivo usan la naturaleza congelada, incluyendo devoluciones con IDs reclasificados. `test_note_refund_preserves_collections_and_reverses_once` verifica en MySQL un reembolso `04` registrado como efectivo, su presencia en caja tras cambiar el catálogo y su reversión. Los demás reportes financieros requieren pruebas separadas.
- Las exportaciones contables EJB y Contasis consultan la clasificación congelada del cobro y la condición documental para crédito, sin inferirlas de `01` o `09`. Si no hay clasificación histórica reconocible, dejan vacía la cuenta o método correspondiente. Una prueba MySQL genera y reabre ambos XLSX con un cobro históricamente bancario y el método reclasificado a efectivo; verifica los campos afectados. La condición de contado del caso se ajusta sólo en memoria y no acredita otros escenarios ni el transporte de descarga.
- La factura automática de FullSuscription se crea a crédito y sin pagos cuando la orden no acredita un cobro y destino verificables; no debe inventar efectivo ni exigir un método para ese cobro inexistente. `test_automatic_subscription_invoice_has_no_unverified_cash_receipt` comprueba la generación y su rollback en MySQL temporal.
- El comprobante del bot selecciona efectivo VES activo por clasificación y exige caja abierta. `test_whatsapp_draft_requires_open_cash_and_uses_current_cash_classification` cubre reclasificación y ausencia de caja o método; las pruebas del orquestador y de concurrencia descritas en el informe verifican guardado e idempotencia con envío simulado, sin contactar WhatsApp ni HKA.
- La cotización de tienda virtual selecciona efectivo VES mediante el catálogo dentro de su transacción y devuelve el error de configuración cuando no existe. La prueba MySQL del controlador rechaza la falta de efectivo sin crear cliente ni cotización y acepta `04` reclasificado como efectivo VES, sin crear movimiento; simula el PDF y carece de configuración para enviar correo. La importación de pedidos conserva un método explícito activo y clasificado por descripción o utiliza efectivo activo en la moneda configurada; no usa `10` como alternativa. Ver el informe para sus límites y la precisión pendiente de los precios de tienda.
- La API de datos de empresa para clientes externos devuelve métodos activos de efectivo o cuenta por clasificación, sin excluir el ID `07`, que puede pertenecer a un método personalizado. Verificar `test_company_api_includes_active_classified_custom_method_with_retired_id`.
- En la API de factura para clientes externos, comprobar también `01` bancario USD y `04` efectivo VES, destinos incompatibles, moneda activa y efecto separado en caja. `test_api_invoice_from_restaurant_checks_method_currency_and_bank_atomically` ejecuta middleware y controlador; no verifica el compilado de Mozo.

- El catálogo de ambientes fiscales es `fiscal_environments`, exclusivamente `demo` y `production`. Aplicar [mantener-modalidad-emision-fiscal-pro9](../mantener-modalidad-emision-fiscal-pro9/SKILL.md): el esquema nace sin SOAP/PFX y sin tipos de auditoría retirados, por lo que no se crean migraciones incrementales de conversión o limpieza. Esta excepción prevalece sobre las instrucciones generales para instalaciones existentes.

- Tratar los IDs conservados como contratos estables: no reasignar códigos. Para motivos de traslado se conserva `04` y los códigos locales venezolanos son `21`, `22`, `23` y `24`; HKA no publica códigos para este catálogo.
- `cat_identity_document_types` contiene exactamente `0`, `1`, `6`, `7`, `E`, `C`, `G` y `R`, en ese orden y todos con `active = 1`. Mantener sincronizados la fuente central, el seeder y los consumidores sin crear un backfill para tenants existentes.
- Tratar `expense_reasons` como un catálogo inicial nuevo sin históricos: sus IDs contractuales son `1` a `30` y no representan una reclasificación de gastos preexistentes.
- `cat_taxes_types` contiene las cinco familias del inventario; `cat_iva_rate_types.tax_kind` referencia su `id` con integridad restrictiva. Los valores IVA e IGTF y sus alícuotas HKA se conservan. El catálogo es de referencia y no habilita cálculos ni emisión para nuevas familias.
- El estado inicial venezolano es una depuración total de los catálogos enumerados en el inventario. No reintroducir filas retiradas como inactivas.
- No conservar Boletas, series BB/BC/BD ni resúmenes fiscales de Boletas. Aplicar `mantener-facturas-notas-venta-sin-boleta` a creación, consultas, reportes y PDF.
- Usar los tipos del inventario vigente: Factura de venta, notas de crédito/débito, Orden de entrega, Retención, Nota de venta y notas de almacén. No reintroducir códigos retirados sólo para resolver datos antiguos.
- Usar únicamente `10 = Gravado` y `20 = Exento`. No conservar otros IDs para históricos; aplicar `migrar-iva-venezuela` para tasa, cálculos y nombres internos `igv`.
- Los scopes de tipos documentales excluyen IDs retirados: compras ofrece 01/NE76 y el selector general sólo los códigos vigentes de su flujo. El resolutor de reportes admite 01/07/08/80 y no asigna `Document` a un ID desconocido. Probarlo con `CurrentDocumentResolutionTest`.
- Mantener activos los descuentos por ítem `00` y `01` con descripciones IVA. No reproducir estados históricos intermedios que los retiraban.
- Retirar del esquema inicial las nueve tablas declaradas eliminadas en el inventario y adaptar sus consumidores; no basta con dejarlas vacías o inactivas.
- Retirar relaciones y modelos exclusivos de catálogos eliminados. No implementar consultas opcionales que comprueben si una tabla antigua existe.
- Ocultar los paneles de atributos UBL adicionales mediante una capacidad central de Venezuela; no comentar bloques grandes de Vue.

## Datos

1. Actualizar `database/seeders/data/tenant_initial_data.php` para tenants nuevos.
2. Mantener la migración de `expense_reasons` limitada a crear su estructura; todas sus filas iniciales deben existir únicamente en `database/seeders/data/tenant_initial_data.php`. No crear una migración incremental para este catálogo mientras no existan tenants históricos.
3. Excepción acordada para detracciones: retirar `cat_payment_method_types` y sus consumidores, campos y datos desde el consolidado, sin migración incremental ni conservación histórica; validar en una base temporal. No eliminar `payment_method_types`. La reconstrucción de tenants existentes requiere destinos explícitos.
4. El proyecto sólo admite instalación nueva para esta adaptación. Actualizar directamente creaciones y semillas, sin conversión incremental.
5. Los `up()` y `down()` crean y eliminan estructura; el seeder carga el estado final una sola vez por identidad.
6. Comprobar en bases temporales que todos los datos iniciales resuelvan sus claves foráneas. No mantener columnas consumidoras de catálogos retirados por compatibilidad histórica.
7. Invalidar cachés o adaptar proveedores de opciones cuando los catálogos no se consulten directamente.

## Presentación

- Usar `%IVA` y `Tipo %IVA` en los consumidores equivalentes, conservando nombres técnicos heredados.
- Actualizar literales visibles activos de Factura de venta y Guía de despacho. Mantener textos técnicos en XML, WSDL, endpoints, validadores externos y formatos peruanos cuando describan contratos reales.
- No editar `public/build`; si cambia Vue o JavaScript, aplicar `frontend-build` e informar que el usuario debe compilar.

## Marcadores y pruebas

- Delimitar cada hunk con `########## INICIO CAMBIO CATÁLOGOS DE NOMBRES` y `######### FIN CAMBIO CATÁLOGOS DE NOMBRES`, usando comentarios válidos.
- Ejecutar `scripts/apply_catalog_contract.php` sólo cuando se necesite reaplicar mecánicamente el inventario al consolidado; revisar siempre su diff.
- Ejecutar `scripts/validate_catalog_contract.php` para comprobar migración limpia, `TenancyDatabaseSeeder`, ausencia de tablas retiradas y conteos venezolanos en una base temporal descartable. La comprobación de `pse_providers` es estructural; no cargar modelos del módulo `PseService`, porque el módulo completo está retirado.
- Probar el inventario completo, las tablas ausentes, los bancos, los 30 motivos de gasto, métodos de pago, los ocho tipos de identidad activos, códigos de traslado, afectaciones `10/20` y paneles UBL.
- Verificar en una base tenant nueva que la migración deje `expense_reasons` vacía antes del seeding y que `TenancyDatabaseSeeder` cargue exactamente los 30 motivos contractuales.
- Ejecutar las pruebas de contrato de catálogos, IVA, ventas sin Boleta, datos tenant y SUNAT/SENIAT, además de `git diff --check` y lint PHP.

## Motivos de notas habilitados (13/10/2026)

Mantener los IDs existentes y selector exclusivo por tipo: NC01 anulación,07 devolución,04 descuento,09 disminución de precios/cálculos; ND02 incremento,01 intereses,03 despacho/fletes/seguros/embalaje. IGTF es exclusivo del flujo especializado, nunca motivo manual ordinario. Mapeos de transacción HKA y capacidades verificadas se documentan en [notas](../../../informes/notas_credito_debito_hka.md); no cambiar códigos ante rechazos1015 ni confundir catálogo local con aceptación fiscal. No se modifican semillas de motivos en esta entrega.

## Métodos de pago iniciales

- Sembrar únicamente los 10 IDs clasificados `01`–`05` y `09`–`13`; no sembrar `06`, `07` ni `14`–`26` ni crear `payment_method_types.charge`. Los IDs retirados quedan disponibles para métodos personalizados.
- En los formularios de factura, si se crea una fila mientras el catálogo aún carga, indicar la carga y proponer el efectivo compatible cuando llega la respuesta sólo si la fila nueva sigue sin método; no sustituir selecciones ni recibos históricos. Sin efectivo compatible, dejar el método vacío y explicar la situación.
- Todos los métodos se editan desde el listado, incluyendo naturaleza y moneda, conservando ID y código HKA; los iniciales siguen protegidos contra eliminación. Los métodos personalizados sin clasificar permanecen inactivos. Las ediciones no alteran snapshots de movimientos. Aplicar exclusivamente al esquema y datos de instalaciones nuevas; sin conversión de tenants existentes.

- La integración en curso de cobros compara la moneda activa configurada del método con la moneda realmente recibida; las cuentas bancarias deben coincidir con esa moneda. Los reintentos y aplicaciones de cobros existentes se resuelven con su contexto congelado. Ver [moneda de cobros](../migrar-venezuela-moneda/SKILL.md); la asociación informativa IGTF no activa el impuesto.
- Los métodos nuevos se crean disponibles automáticamente en servidor y exigen naturaleza clasificada. Los formularios de creación y edición no muestran la casilla de disponibilidad; esta se cambia desde el listado. Los métodos sin clasificar permanecen inactivos.
- Los selectores de abonos ofrecen métodos activos `cash` y `account`; el crédito se selecciona como condición o cuota, sin borrar abonos. Una cuota y un abono de igual índice deben resolverse por separado. El diálogo múltiple del POS no asigna crédito por ID ni descarta sus filas al aceptar.
- Las consultas de métodos de cobro y crédito se basan en `collection_kind`; `is_cash` e `is_credit` son proyecciones de compatibilidad sincronizadas al guardar y no sustituyen la clasificación.
- Los importadores automáticos deben resolver previamente los métodos de todas las filas importables antes de crear clientes u órdenes. Si necesitan efectivo predeterminado y no existe uno activo en la moneda documental, rechazar el lote antes de escribir.

## Moneda e impuesto del método de pago

- Cada método tiene `currency_type_id` obligatorio con FK al catálogo de monedas; los IDs iniciales 10 y 13 usan USD y los demás VES. La moneda se selecciona entre activas, con VES por defecto al crear. Para efectivo, sincronizar `cash_currency_type_id`.
- `iva_rate_type_id` es opcional y referencia exclusivamente IGTF. Esta asociación es informativa y no habilita ni altera el cálculo de IGTF. Conservar snapshots de cobros y aplicar el esquema sólo a instalaciones nuevas.

## Datos de tarjeta retirados

- No reintroducir la opción «Solicitar datos de tarjeta», `has_card`, `card_brand_id` ni el catálogo de marcas en los pagos comerciales tenant. Conservar los métodos de tarjeta y los datos independientes de pagos de suscripciones del sistema. Aplicar al esquema inicial, sin conversión de tenants existentes.
- La activación de métodos mediante las rutas de ventas y finanzas y la eliminación de métodos personalizados exigen administrador. La ruta financiera reutiliza la validación de ventas; no se puede activar un método `unclassified`, y los métodos iniciales siguen protegidos contra eliminación. Verificar `PaymentMethodCreationTest`.
- La activación bloquea la fila vigente del método dentro de una transacción antes de validar y guardar; una clasificación concurrente a `unclassified` debe impedir la activación posterior. Verificar `test_method_activation_rechecks_classification_after_concurrent_edit` en MySQL.
- La edición también bloquea la fila antes de leerla y aplica los campos enviados sobre el estado actual. Si una activación concurrente se confirma primero, una edición que no desactiva explícitamente el método no puede dejarlo activo y `unclassified`; la prueba concurrente verifica ambos órdenes y la regresión con un cobro.

En el listado de métodos y en el editor, mostrar condición y días de crédito según `collection_kind`; los indicadores derivados `is_credit` e `is_cash` no deciden la presentación. Un método sin clasificar se identifica como tal. `payment-method-form.test.cjs` comprueba flags contradictorios y ambas plantillas.

Las celdas de método en las exportaciones PDF y Excel de pagos globales usan la descripción congelada del movimiento, incluso tras renombrar o reclasificar el catálogo. Verificar la prueba MySQL de caja y reportes, que también reabre el XLSX.
