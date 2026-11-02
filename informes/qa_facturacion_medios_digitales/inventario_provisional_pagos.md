# Inventario provisional de pruebas de clasificación de pagos

Fuente confirmada: chat Depurar tipos de pago iniciales y informe [clasificación de cobros](../clasificacion_medios_pago_caja.md). Este inventario es un punto de partida: falta revisar el historial completo, las pruebas MySQL y los demás productores. Ninguna entrada es un resultado de la nueva corrida.

## tests/Feature/PaymentClassificationTest.php

- `test_loan_form_request_rejects_unclassified_cash_inactive_and_missing_destination` — pendiente de asociar a un caso consolidado.
- `test_income_receipt_reports_received_currency_instead_of_applied_amount` — pendiente de asociar a un caso consolidado.
- `test_new_receipt_rejects_an_inactive_method_or_currency` — pendiente de asociar a un caso consolidado.
- `test_hotel_catalog_exposes_active_classification_and_currency` — pendiente de asociar a un caso consolidado.
- `test_subscription_credit_catalog_tracks_active_classification` — pendiente de asociar a un caso consolidado.
- `test_physical_balances_exclude_electronic_money_and_keep_currencies_separate` — pendiente de asociar a un caso consolidado.
- `test_account_currency_and_closed_cash_reject_the_entire_operation` — pendiente de asociar a un caso consolidado.
- `test_credit_and_unclassified_methods_do_not_create_receipts` — pendiente de asociar a un caso consolidado.
- `test_flags_are_derived_and_unclassified_active_methods_are_rejected` — pendiente de asociar a un caso consolidado.
- `test_new_installation_enforces_one_open_cash_without_activation` — pendiente de asociar a un caso consolidado.
- `test_rational_sums_and_opening_are_counted_once` — pendiente de asociar a un caso consolidado.
- `test_actual_receipt_and_igtf_are_separate_from_documentary_principal` — pendiente de asociar a un caso consolidado.
- `test_reclassifying_an_initial_method_changes_only_future_movements` — pendiente de asociar a un caso consolidado.
- `test_cash_filter_uses_recorded_classification_after_method_is_reclassified` — pendiente de asociar a un caso consolidado.
- `test_automatic_subscription_uses_invoice_for_all_customer_identities` — pendiente de asociar a un caso consolidado.
- `test_payment_link_fallback_uses_configured_classification_without_fixed_id` — pendiente de asociar a un caso consolidado.
- `test_purchase_installments_use_balance_after_recorded_payments` — pendiente de asociar a un caso consolidado.
- `test_purchase_edit_keeps_recorded_payment_after_method_reclassification` — pendiente de asociar a un caso consolidado.
- `test_excel_contains_text_amounts_and_their_exact_numerators_and_denominators` — pendiente de asociar a un caso consolidado.

## tests/Feature/PaymentMethodCreationTest.php

- `test_duplicate_code_is_rejected_without_changing_the_existing_method` — pendiente de asociar a un caso consolidado.
- `test_new_code_can_be_created_and_existing_method_can_be_edited` — pendiente de asociar a un caso consolidado.
- `test_new_methods_are_available_even_if_client_requests_inactive` — pendiente de asociar a un caso consolidado.
- `test_initial_methods_are_editable_without_changing_id_or_hka_and_cannot_be_deleted` — pendiente de asociar a un caso consolidado.
- `test_retired_ids_can_be_reused_and_unclassified_methods_remain_inactive` — pendiente de asociar a un caso consolidado.
- `test_currency_and_igtf_options_are_validated_saved_and_removable` — pendiente de asociar a un caso consolidado.
- `test_only_admin_can_change_availability_or_delete_custom_methods` — pendiente de asociar a un caso consolidado.

## tests/Unit/MethodCurrencyTest.php

- `test_same_currency_can_use_the_document_currency` — pendiente de asociar a un caso consolidado.
- `test_cross_currency_needs_explicit_amount_and_rate` — pendiente de asociar a un caso consolidado.

## tests/Unit/DefaultCashOrderingTest.php

- `test_sqlite_default_cash_uses_binary_unicode_order` — pendiente de asociar a un caso consolidado.

## tests/Unit/ReceiptAmountsTest.php

- `test_converts_received_dollars_to_document_bolivars` — pendiente de asociar a un caso consolidado.
- `test_rejects_mismatched_method_currency` — pendiente de asociar a un caso consolidado.
- `test_converts_received_bolivars_to_document_dollars` — pendiente de asociar a un caso consolidado.
- `test_existing_receipt_rejects_a_change_of_received_currency` — pendiente de asociar a un caso consolidado.

## tests/Unit/WhatsAppDraftPricingTest.php

- `test_converts_prices_and_discounts_before_rounding_payment` — pendiente de asociar a un caso consolidado.
- `test_rejects_missing_or_nonpositive_rate` — pendiente de asociar a un caso consolidado.
- `test_orchestrator_explains_that_an_old_draft_must_be_confirmed_again` — pendiente de asociar a un caso consolidado.

## tests/js/payment-classification.test.cjs

- `default cash sorts custom IDs by Unicode code point rather than locale` — pendiente de asociar a un caso consolidado.
- `store configuration can select bank destinations without a payment-method field` — pendiente de asociar a un caso consolidado.
- `cash default is active, matches document currency, and does not depend on a seeded ID` — pendiente de asociar a un caso consolidado.
- `payment validation extracts the server message without guessing a payment row` — pendiente de asociar a un caso consolidado.
- `hotel exposes wrapped and indexed payment errors and retains other form errors` — pendiente de asociar a un caso consolidado.
- `physical currency and banking destinations are independent` — pendiente de asociar a un caso consolidado.
- `the method currency determines eligible bank accounts` — pendiente de asociar a un caso consolidado.
- `supplier and loan payments use their own currency even if the receipt method has another currency` — pendiente de asociar a un caso consolidado.
- `expense IDs never use the payment-method catalog` — pendiente de asociar a un caso consolidado.
- `classification applies without an activation flag on destination options` — pendiente de asociar a un caso consolidado.
- `an incompatible default is cleared and a new banking destination remains selectable` — pendiente de asociar a un caso consolidado.
- `document payment rows clear incompatible destinations without modifying recorded receipts` — pendiente de asociar a un caso consolidado.
- `reused receipts retain their historical destination and nature when the catalog changes` — pendiente de asociar a un caso consolidado.
- `order draft rows clear a destination that conflicts with the method currency` — pendiente de asociar a un caso consolidado.


## tests/Support/PaymentClassificationPersistenceCases.php

- `test_fractional_local_unit_invoice_and_mobile_weight_catalog` — pendiente de asociar a un caso consolidado.
- `test_classic_subscription_generation_failure_rolls_back_the_plan` — pendiente de asociar a un caso consolidado.
- `test_api_invoice_from_restaurant_checks_method_currency_and_bank_atomically` — pendiente de asociar a un caso consolidado.
- `test_api_sale_note_rejects_retired_currency_and_rolls_back_imported_customer` — pendiente de asociar a un caso consolidado.
- `test_api_invoice_reuses_sale_note_receipt_and_links_source` — pendiente de asociar a un caso consolidado.
- `test_restaurant_cash_links_credit_receipt_once_using_recorded_classification` — pendiente de asociar a un caso consolidado.
- `test_full_subscription_edit_preserves_existing_orders_and_status` — pendiente de asociar a un caso consolidado.
- `test_automatic_subscription_invoice_has_no_unverified_cash_receipt` — pendiente de asociar a un caso consolidado.
- `test_loan_payment_reclassifies_method_and_checks_destination_with_rollback` — pendiente de asociar a un caso consolidado.
- `test_document_payment_workbook_keeps_received_and_applied_currencies_and_balance` — pendiente de asociar a un caso consolidado.
- `test_document_receivable_kpi_uses_current_balance_after_foreign_receipt_reversal` — pendiente de asociar a un caso consolidado.
- `test_simultaneous_document_receipts_after_prior_read_respect_balance` — pendiente de asociar a un caso consolidado.
- `test_simultaneous_sale_note_receipts_keep_current_settlement_state` — pendiente de asociar a un caso consolidado.
- `test_simultaneous_receipts_do_not_exceed_quotation_or_technical_service_balance` — pendiente de asociar a un caso consolidado.
- `test_bank_currency_edit_and_receipt_serialize_without_mismatched_currency` — pendiente de asociar a un caso consolidado.
- `test_receipt_rechecks_currency_after_concurrent_deactivation` — pendiente de asociar a un caso consolidado.
- `test_method_activation_rechecks_classification_after_concurrent_edit` — pendiente de asociar a un caso consolidado.
- `test_receipt_attachment_reuses_concurrently_committed_movement_after_prior_read` — pendiente de asociar a un caso consolidado.
- `test_sale_note_batch_uses_current_locked_method_and_blocks_reclassification` — pendiente de asociar a un caso consolidado.
- `test_receipt_serializes_with_concurrent_method_reclassification` — pendiente de asociar a un caso consolidado.
- `test_quotation_and_technical_service_limit_the_exact_applied_receipt` — pendiente de asociar a un caso consolidado.
- `test_mobile_update_does_not_remove_a_concurrently_recorded_receipt` — pendiente de asociar a un caso consolidado.
- `test_mobile_capture_keeps_currency_and_rejects_a_conflicting_duplicate` — pendiente de asociar a un caso consolidado.
- `test_contract_keeps_the_original_cross_currency_receipt_on_edit` — pendiente de asociar a un caso consolidado.
- `test_default_cash_orders_custom_ids_like_the_frontend` — pendiente de asociar a un caso consolidado.
- `test_cash_opening_keeps_its_own_currency_when_catalog_methods_change` — pendiente de asociar a un caso consolidado.
- `test_cash_opening_rechecks_concurrent_method_deactivation` — pendiente de asociar a un caso consolidado.
- `test_hotel_retries_do_not_duplicate_a_received_payment` — pendiente de asociar a un caso consolidado.
- `test_hotel_rolls_back_the_new_note_when_a_receipt_cannot_be_linked` — pendiente de asociar a un caso consolidado.
- `test_hotel_reuses_its_sale_note_receipt_without_creating_another_movement` — pendiente de asociar a un caso consolidado.
- `test_hotel_receipt_preserves_received_currency_and_checks_paid_total` — pendiente de asociar a un caso consolidado.
- `test_quotation_receipt_can_be_applied_to_an_invoice_without_a_second_movement` — pendiente de asociar a un caso consolidado.
- `test_quotation_receipt_is_reused_through_sale_note_and_invoice` — pendiente de asociar a un caso consolidado.
- `test_quotation_conversion_requires_all_mixed_receipts` — pendiente de asociar a un caso consolidado.
- `test_quotation_conversion_rechecks_receipts_after_a_concurrent_payment` — pendiente de asociar a un caso consolidado.
- `test_simultaneous_quotation_receipt_applications_allow_only_one_sale_note` — pendiente de asociar a un caso consolidado.
- `test_sale_note_receipt_deletion_and_invoice_reuse_are_serialized` — pendiente de asociar a un caso consolidado.
- `test_multiple_sale_notes_preserve_receipts_and_reject_omissions` — pendiente de asociar a un caso consolidado.
- `test_concurrent_invoices_cannot_reuse_an_unpaid_sale_note` — pendiente de asociar a un caso consolidado.
- `test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income` — pendiente de asociar a un caso consolidado.
- `test_simultaneous_technical_receipt_applications_allow_only_one_sale_note` — pendiente de asociar a un caso consolidado.
- `test_order_draft_payment_creates_money_only_when_generating_the_invoice` — pendiente de asociar a un caso consolidado.
- `test_order_with_price_entered_without_iva` — pendiente de asociar a un caso consolidado.
- `test_technical_document_failure_rolls_back_the_temporary_service_item` — pendiente de asociar a un caso consolidado.
- `test_payment_destination_catalog_loads_bank_names_in_one_query` — pendiente de asociar a un caso consolidado.
- `test_receipt_dialog_catalogs_exclude_credit_and_unclassified_methods` — pendiente de asociar a un caso consolidado.
- `test_technical_service_without_cash_and_repeated_edits_do_not_duplicate_cash_link` — pendiente de asociar a un caso consolidado.
- `test_sale_note_without_open_cash_explains_rejection_and_rolls_back` — pendiente de asociar a un caso consolidado.
- `test_quotation_receipt_keeps_received_dollars_and_applied_bolivars` — pendiente de asociar a un caso consolidado.
- `test_quotation_reclassified_bank_method_checks_currency_and_keeps_snapshot` — pendiente de asociar a un caso consolidado.
- `test_whatsapp_confirmed_usd_price_keeps_rate_and_receipt_in_ves` — pendiente de asociar a un caso consolidado.
- `test_whatsapp_orchestrator_persists_the_confirmed_draft_with_a_mock_sender` — pendiente de asociar a un caso consolidado.
- `test_whatsapp_concurrent_confirmations_reuse_the_same_receipt` — pendiente de asociar a un caso consolidado.
- `test_whatsapp_draft_requires_open_cash_and_uses_current_cash_classification` — pendiente de asociar a un caso consolidado.
- `test_store_import_preflights_all_payment_methods_before_creating_customers` — pendiente de asociar a un caso consolidado.
- `test_storefront_quotation_builder_uses_venezuelan_tax_rate` — pendiente de asociar a un caso consolidado.
- `test_storefront_quotation_rejects_missing_cash_before_creating_guest` — pendiente de asociar a un caso consolidado.
- `test_store_import_generates_credit_and_reclassified_bank_invoices` — pendiente de asociar a un caso consolidado.
- `test_purchase_import_uses_classified_cash_and_creates_one_movement` — pendiente de asociar a un caso consolidado.
- `test_purchase_update_preserves_recorded_payment_after_reclassification_mysql` — pendiente de asociar a un caso consolidado.
- `test_quotation_resource_preserves_dollar_cash_method` — pendiente de asociar a un caso consolidado.
- `test_global_payment_export_reports_failures_and_uses_distinct_files` — pendiente de asociar a un caso consolidado.
- `test_movement_export_records_failure_and_success` — pendiente de asociar a un caso consolidado.
- `test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances` — pendiente de asociar a un caso consolidado.
- `test_transfers_capture_the_currency_of_each_destination_and_reject_stale_cash_currency` — pendiente de asociar a un caso consolidado.
- `test_payment_link_with_banking_method_does_not_create_a_cash_receipt` — pendiente de asociar a un caso consolidado.
- `test_opened_cash_is_returned_by_the_paginated_cash_listing` — pendiente de asociar a un caso consolidado.
- `test_payment_classification_is_ready_from_the_fresh_tenant_schema` — pendiente de asociar a un caso consolidado.
- `test_classified_invoice_receipts_keep_received_currency_and_bank_money_outside_physical_cash` — pendiente de asociar a un caso consolidado.
- `test_company_api_includes_active_classified_custom_method_with_retired_id` — pendiente de asociar a un caso consolidado.
- `test_payment_dialog_opens_when_the_stored_document_rate_is_invalid` — pendiente de asociar a un caso consolidado.
- `test_initial_invoice_rejects_method_currency_before_conversion_and_rolls_back` — pendiente de asociar a un caso consolidado.
- `test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income` — pendiente de asociar a un caso consolidado.
- `test_credit_invoice_installments_rollback_invalid_amount_and_accept_paid_balance` — pendiente de asociar a un caso consolidado.
- `test_usd_invoice_installments_discount_ves_bank_receipt` — pendiente de asociar a un caso consolidado.
- `test_invoice_installments_discount_cross_currency_receipt_and_guarantee_fund` — pendiente de asociar a un caso consolidado.
- `test_credit_sale_note_installments_discount_cross_currency_receipt` — pendiente de asociar a un caso consolidado.
- `test_saved_credit_installments_preserve_cross_currency_receipt_through_conversion_and_pdf` — pendiente de asociar a un caso consolidado.
- `test_credit_invoice_from_sale_note_reuses_cross_currency_receipt_and_keeps_balance` — pendiente de asociar a un caso consolidado.
- `test_sale_note_reuses_a_cross_currency_bank_receipt_without_a_second_movement` — pendiente de asociar a un caso consolidado.
- `test_cash_credit_registration_uses_saved_payment_condition_instead_of_method_id` — pendiente de asociar a un caso consolidado.
- `test_sale_note_cross_currency_cash_change_uses_received_currency` — pendiente de asociar a un caso consolidado.
- `test_sale_note_mixed_currency_change_and_unrepresentable_change` — pendiente de asociar a un caso consolidado.
- `test_classified_invoice_change_only_reduces_cash_and_rejects_electronic_excess` — pendiente de asociar a un caso consolidado.
- `test_classified_cash_report_performance_against_legacy_at_1000_and_10000_receipts` — pendiente de asociar a un caso consolidado.

## Especificaciones preparadas para integrar al catálogo

Las siguientes especificaciones aún no forman parte del registro Excel. Usar tenant QA aislado, modalidad Medios digitales/DEMO y datos sintéticos. Registrar Aprobó/Reprobó, observado, evidencia, defecto, agente, fecha Caracas e iteración cuando se ejecuten; Bloqueada y No aplica requieren motivo.

### Pago P01 — Semillas y esquema de instalación nueva

**Datos:** base temporal vacía; métodos esperados 01–05 y 09–13. **Pasos:** 1. Instalar esquema inicial y semillas en la base temporal. 2. Consultar `payment_method_types` y su esquema. 3. Repetir las semillas y verificar el resultado. **Esperado:** diez IDs iniciales, sin 06, 07 ni 14–26 y sin columna `charge`; repetición sin duplicados. `unclassified` no se siembra. Los IDs retirados están disponibles para métodos personalizados. **Control:** conteo 10 antes y después de repetir. **Evidencia:** consulta saneada de IDs/columnas y resultados de instalación y repetición.

### Pago P02 — Clasificación y administración de métodos

**Datos:** usuario administrador y usuario sin permiso; método inicial y método personalizado 06, código HKA 99. **Pasos:** 1. Crear 06 clasificado y comprobar código HKA asignado por servidor. 2. Editar naturaleza entre efectivo, cuenta y crédito, y moneda VES/USD. 3. Intentar duplicar ID, eliminar un inicial y activar uno no clasificado; repetir cambios de disponibilidad con usuario sin permiso. **Esperado:** creación/edición autorizadas conservan identidad y HKA; se rechazan duplicado, borrado inicial, activación de `unclassified` y mutación no autorizada. Los indicadores de efectivo/crédito se derivan de `collection_kind`. **Efectos:** ninguna operación rechazada altera catálogo ni movimientos. **Evidencia:** solicitudes saneadas y lecturas antes/después.

### Pago P03 — Efectivo predeterminado y carga asíncrona

**Datos:** documento VES, dos métodos efectivos VES activos con IDs personalizados `!` y `^`, otro USD y un VES inactivo. **Pasos:** 1. Abrir el formulario y crear una fila mientras carga el catálogo. 2. Dejarla vacía hasta recibir respuesta; comparar selección del navegador y servidor. 3. Repetir seleccionando manualmente otro método antes de la respuesta. 4. Desactivar los efectivos VES y abrir una fila nueva. **Esperado:** el predeterminado es el primer ID compatible según el orden común por puntos de código; se respeta la elección manual. Sin efectivo VES activo la selección queda vacía con aviso. **Control:** navegador y servidor escogen el mismo ID; no se modifica ningún recibo histórico. **Evidencia:** capturas, catálogo y resultado del selector PHP/JS.

### Pago P04 — Moneda recibida, destino y conversión

**Datos:** factura VES por 500, cobro de 10 USD a tasa 50 VES/USD; banco USD activo y banco VES de la misma sucursal. **Pasos:** 1. Registrar cobro de 10 USD con banco USD y tasa explícita. 2. Consultar saldo y movimiento. 3. En otra factura intentar cuenta VES, método inactivo y tasa ausente, cada uno por separado. **Esperado:** recibido 10 USD, aplicado 500 VES y saldo 0; snapshot conserva moneda, tasa, naturaleza y destino. Cada entrada inválida se rechaza atómicamente, sin cobro ni movimiento. **Control:** 10×50=500; el banco USD aumenta 10 USD y el efectivo físico no cambia. **Evidencia:** UI, petición saneada, lectura financiera y cálculo.

### Pago P05 — Reclasificación y conservación del movimiento

**Datos:** cobro bancario USD 10 ya registrado con UUID K y snapshot; método luego reclasificado a efectivo VES o desactivado. **Pasos:** 1. Cambiar el catálogo. 2. Consultar caja, reportes, PDF y XLSX históricos. 3. Repetir la operación idéntica con K. 4. Intentar un nuevo cobro con el método incompatible. **Esperado:** movimiento anterior conserva su clasificación, descripción, moneda y destino originales; el reintento no crea otro ingreso. La operación nueva respeta clasificación y disponibilidad vigentes. **Control:** un movimiento histórico, ingreso USD 10 una sola vez. **Evidencia:** snapshots, respuestas de reintento y reportes.

### Pago P06 — Vuelto en moneda recibida

**Datos:** documento de 500 VES; tasa 50 VES/USD; efectivo USD nuevo por 12 USD. **Pasos:** 1. Registrar el cobro en caja USD abierta. 2. Revisar vuelto, importe aplicado y saldo. 3. Repetir en un fixture distinto con un abono bancario que exceda el saldo. **Esperado:** el efectivo puede devolver 2 USD y aplicar 500 VES; se rechaza el exceso bancario sin inventar vuelto físico. **Control:** 12×50=600 VES, vuelto 2×50=100 VES; aplicado 500 VES. **Evidencia:** movimientos, captura de pagos y cálculo independiente.

### Pago P07 — Efectivo anterior no financia un segundo vuelto

**Datos:** factura de 500 VES con recibo efectivo previo de 300 VES; nuevo cobro de 250 VES mediante banco. **Pasos:** 1. Reabrir Pagos y confirmar el recibo previo. 2. Intentar guardar el nuevo pago bancario. 3. Consultar caja, banco y saldo. **Esperado:** el exceso bancario de 50 se rechaza; el efectivo histórico no se usa para justificar un vuelto posterior. **Control:** saldo previo 500−300=200 VES; el cobro nuevo solicitado excede 50. Ningún movimiento adicional. **Evidencia:** solicitudes, saldo antes/después y registros de caja/banco.

### Pago P08 — Cotización, nota y factura reutilizan un abono

**Datos:** cotización VES por 500; abono bancario recibido 10 USD a tasa 50, UUID K. **Pasos:** 1. Guardar el abono y capturar su identidad y snapshot. 2. Convertir a nota de venta y luego a factura. 3. Reabrir pagos y comparar movimientos y vínculos. 4. Duplicar la cotización original. **Esperado:** la cadena conserva el abono y su moneda/tasa sin registrar un segundo ingreso; la copia de la cotización nace sin abonos ni anticipos. **Control:** 10×50=500 VES; un solo movimiento financiero para K. **Evidencia:** referencias origen/destino, recibo, snapshots y conteo de movimientos.

### Pago P09 — Concurrencia de cobros y cambios de catálogo

**Datos:** factura con saldo 100 VES; dos solicitudes de 70 VES con claves distintas; método inicialmente activo y clasificado como cuenta. **Pasos:** 1. Sincronizar dos procesos para que lean el saldo antes de guardar. 2. Ejecutarlos concurrentemente en una base temporal. 3. Repetir con un proceso que desactiva o reclasifica el método mientras el otro intenta cobrar. **Esperado:** nunca se cobran más de 100; la solicitud posterior revalida el estado del método bajo bloqueo. Un fallo revierte la operación completa. **Control:** suma de cobros activos ≤100 y saldo =100−suma aplicada. **Evidencia:** respuestas de ambos procesos, orden de transacciones y lecturas finales.

### Pago P10 — Caja, reportes y cierre por moneda

**Datos:** caja propia abierta con apertura 100 VES; ingreso efectivo 50 VES y cobro bancario 10 USD a tasa 50. **Pasos:** 1. Registrar ambos cobros. 2. Consultar movimientos, cierre, PDF y XLSX. 3. Renombrar y reclasificar el método bancario y consultar otra vez. **Esperado:** efectivo físico final 150 VES; el banco muestra 10 USD por separado. Los reportes conservan descripción y clasificación del snapshot histórico y no suman USD al efectivo VES. **Control:** 100+50=150 VES; banco +10 USD; apertura contabilizada una vez. **Evidencia:** movimiento, cierre, PDF/XLSX y comparativa de snapshots.

### Pago P11 — Hotel y restaurante: reintentos y rollback

**Datos:** nota y factura de prueba de 116 VES; abono real 50 VES, UUID K; método activo y destino compatible. **Pasos:** 1. Crear el cobro mediante el productor correspondiente. 2. Repetir K y ejecutar dos reintentos concurrentes. 3. En otra operación usar cuenta incompatible o impedir la vinculación del recibo. **Esperado:** un solo recibo y movimiento para K; el fallo de vinculación revierte la operación comercial completa. Los endpoints de restaurante se prueban independientemente del compilado de Mozo. **Control:** saldo 116−50=66 VES; movimientos para K=1. **Evidencia:** respuestas, conteos y estado antes/después.

### Pago P12 — Productores automáticos sin cobro ficticio

**Datos:** suscripción automática a crédito por 116 VES; importación con dos filas, una sin método efectivo compatible; borrador WhatsApp USD a tasa confirmada 50. **Pasos:** 1. Generar la suscripción. 2. Importar el lote en un entorno aislado. 3. Confirmar el borrador WhatsApp con emisor simulado y repetir su clave. **Esperado:** la suscripción crea crédito sin registrar efectivo no verificado; el importador valida todo el lote antes de crear clientes u órdenes; WhatsApp conserva precios y tasa confirmados y no duplica el cobro. No enviar mensajes ni HKA durante la simulación. **Control:** suscripción: cobros 0 y saldo 116; lote inválido: clientes y órdenes nuevos 0; reintento: movimiento único. **Evidencia:** snapshots, claves y conteos antes/después, con simulación identificada.
