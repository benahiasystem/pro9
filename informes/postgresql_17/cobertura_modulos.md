# Cobertura PostgreSQL 17.11 por módulo

El inventario identifica 47 módulos con descriptor instalado y activación vigente. La entrada `Affiliations` figura activa en la configuración y carece de descriptor de módulo.

Los 47 módulos tienen al menos un recorrido verificado en PostgreSQL 17.11 temporal. Las ejecuciones acreditan la limpieza de los recursos y el alcance sin adjuntos de Contabilidad. El rendimiento POS sigue aplazado.

## Recorridos verificados

| Módulo | Operación o caso | Evidencia |
| --- | --- | --- |
| Account | `test_contasis_formats_persisted_exact_values_and_real_other_taxes`. Consulta de la estructura Contasis para una factura persistida. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Accounting | `test_posting_retries_have_a_single_effect` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| ApiPeruDev | `GET /service/exchange/{date}`. Recupera tasa histórica de PostgreSQL. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-1a9d5acea3fc.xml) |
| BusinessTurn | `GET /bussiness_turns/records`. Giro sintético y estado activo. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| CashReport | `test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances`. Apertura, cierre e informes VES/USD. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| ClaimsBook | `GET /claims/tables`. Catálogo de estados. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| CustomField | `GET /configurations/custom-fields/documents`. Filtra campos habilitados. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| Dashboard | `test_daily_points_preserve_the_original_fraction_and_a_separate_raw_total` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Digemid | `GET /digemid`. Renderiza el componente de Farmacia. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| Dispatch | `test_dispatch_invoice_does_not_repeat_global_stock_movement` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Document | `test_actual_invoice_credit_and_debit_pdfs_keep_exact_data_and_fit_active_formats` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| DocumentaryProcedure | `GET /documentary-procedure/offices`. Devuelve oficina con búsqueda sin acentos. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| DocumentFormLayout | `GET /document-form-layout/invoice`. Conserva variante y campos ordenados. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| Ecommerce | `test_public_price_projection_keeps_catalog_and_campaign_fractions_without_exposing_costs` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Expense | `test_bank_commission_purchase_and_expense_do_not_charge_fees_mysql`. Registro de pago a través del controlador de gastos. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| ExtraServices | `test_extra_services_reads_its_public_configuration_from_postgresql`. Lee la configuración central desde el controlador. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/modulos_restantes_pg17_20261118_01.xml) |
| Finance | `test_transfer_validates_both_legs_before_rounding_and_preserves_large_decimal_text` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| FullSuscription | `test_full_subscription_edit_preserves_existing_orders_and_status` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Hotel | `test_hotel_checkin_creates_note_with_all_nights_and_one_cash_movement` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Inventory | `test_warehouse_guide_quantities_keep_fractions_and_reject_invalid_lines_atomically` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Item | `test_lot_reversal_preserves_fractional_presentations`. Persistencia y restauración de un lote. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| ItemFormLayout | `GET /item-form-layout/standard`. Recupera campos fijados. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| LevelAccess | `GET /system-activity-logs/generals/records`. Filtra por IP y recupera usuario y tipo de acceso. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/modulos_orden_factura_pg17_20261118_01.xml) |
| Marketplace | `test_sync_preserves_twelve_decimals_public_contract_and_moderation_on_postgresql` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| MercadoPago | `GET /client-errors/records`. Recupera catálogo público con su tipo de error. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| MobileApp | `test_mobile_capture_keeps_currency_and_rejects_a_conflicting_duplicate`. Registro idempotente de un cobro con moneda. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| MultiUser | `GET /api/multi-users/records`. Lista asociaciones en dos empresas con una central compartida y rechaza el token cruzado. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/modulos_restantes_pg17_20261118_01.xml) |
| Offline | `GET /offline-configurations/record`. Recupera la configuración sintética y exige autenticación. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/modulos_orden_factura_pg17_20261118_01.xml) |
| Order | `test_order_edit_reconciles_inventory_and_rejects_foreign_lines` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| OrderNote | `POST /order-notes/update/state`. Actualiza el estado de un pedido persistido. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/modulos_restantes_pg17_20261118_01.xml) |
| Padron | `test_legacy_delimited_import_is_atomic_and_requires_an_authenticated_admin`. Importación local, autenticación y atomicidad. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Payment | `test_link_pending_uses_the_original_settlement_boundary_instead_of_a_rounded_header` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Pos | `test_invoice_and_credit_freeze_the_server_presentation_fraction_through_edit_and_catalog_removal` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Production | `GET /item-production/records`. Filtra producto de producción. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| Purchase | `test_purchase_settlement_calculates_exact_lines_and_header_instead_of_trusting_input` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| QrApi | `GET /qrapi/configuration`. Lee configuración tenant y disponibilidad de mensajes del plan. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-1a9d5acea3fc.xml) |
| QrChatBuho | `GET /qrchat/configuration`. Recupera configuración local y omite la clave de autenticación. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-1a9d5acea3fc.xml) |
| Report | `test_pos_cash_report_keeps_totals_across_large_pages_and_workbook` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Restaurant | `test_restaurant_cash_links_credit_receipt_once_using_recorded_classification` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Sale | `test_technical_service_lines_keep_exact_inputs_on_create_and_edit`. Alta y edición de un servicio técnico. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Services | `GET /services/exchange/{date}`. Verifica el contrato de la tasa persistida. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-1a9d5acea3fc.xml) |
| Store | `test_quotation_receipt_is_reused_through_sale_note_and_invoice`. Recuperación de cotización y su forma de pago desde Tienda. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
| Suscription | `POST /suscription/client/records`. Busca cliente verificado. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| Template | `TemplatePdf::getBankAccountsForPdf`. Selecciona cuentas visibles y aplica el filtro de sucursal. | [JUnit](../evidencias/postgresql_17_implementacion/resultados/modulos_restantes_pg17_20261118_01.xml) |
| Webhook | `POST /configurations/webhooks/records`. Devuelve el evento y protege el secreto. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-93e6797560ed.xml) |
| WhatsAppApi | `POST /api/whatsapp-cloud/send-message`. Comprueba el rechazo local cuando faltan las credenciales. | [HTTP](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-1a9d5acea3fc.xml) |
| WhatsAppBot | `test_whatsapp_draft_requires_open_cash_and_uses_current_cash_classification` | [JUnit](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-825c5101f1b0-functional.xml) |
