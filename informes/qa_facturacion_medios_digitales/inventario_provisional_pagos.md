# Inventario provisional de pruebas de clasificación de pagos

Fuente confirmada: chat Depurar tipos de pago iniciales y informe [clasificación de cobros](../clasificacion_medios_pago_caja.md). El inventario combina referencias del chat, pruebas MySQL y pruebas de interfaz; cada asociación identifica el caso relacionado, pero no sustituye el contraste del detalle y las aserciones de la prueba. Las declaraciones parametrizadas pueden abarcar varios canales. Ninguna entrada es un resultado de la nueva corrida.

## tests/Feature/PaymentClassificationTest.php

- `test_loan_form_request_rejects_unclassified_cash_inactive_and_missing_destination` — relacionado con QA-MPA-013; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 54](../../tests/Feature/PaymentClassificationTest.php#L54).
- `test_income_receipt_reports_received_currency_instead_of_applied_amount` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 70](../../tests/Feature/PaymentClassificationTest.php#L70).
- `test_new_receipt_rejects_an_inactive_method_or_currency` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 89](../../tests/Feature/PaymentClassificationTest.php#L89).
- `test_hotel_catalog_exposes_active_classification_and_currency` — relacionado con QA-MPA-011; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 107](../../tests/Feature/PaymentClassificationTest.php#L107).
- `test_subscription_credit_catalog_tracks_active_classification` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 121](../../tests/Feature/PaymentClassificationTest.php#L121).
- `test_physical_balances_exclude_electronic_money_and_keep_currencies_separate` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 142](../../tests/Feature/PaymentClassificationTest.php#L142).
- `test_account_currency_and_closed_cash_reject_the_entire_operation` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 160](../../tests/Feature/PaymentClassificationTest.php#L160).
- `test_credit_and_unclassified_methods_do_not_create_receipts` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 177](../../tests/Feature/PaymentClassificationTest.php#L177).
- `test_flags_are_derived_and_unclassified_active_methods_are_rejected` — caso relacionado: QA-MPA-002. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 185](../../tests/Feature/PaymentClassificationTest.php#L185).
- `test_new_installation_enforces_one_open_cash_without_activation` — relacionado con QA-MPA-018; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 193](../../tests/Feature/PaymentClassificationTest.php#L193).
- `test_rational_sums_and_opening_are_counted_once` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 205](../../tests/Feature/PaymentClassificationTest.php#L205).
- `test_actual_receipt_and_igtf_are_separate_from_documentary_principal` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 212](../../tests/Feature/PaymentClassificationTest.php#L212).
- `test_reclassifying_an_initial_method_changes_only_future_movements` — caso relacionado: QA-MPA-005. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 228](../../tests/Feature/PaymentClassificationTest.php#L228).
- `test_cash_filter_uses_recorded_classification_after_method_is_reclassified` — caso relacionado: QA-MPA-005. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 240](../../tests/Feature/PaymentClassificationTest.php#L240).
- `test_automatic_subscription_uses_invoice_for_all_customer_identities` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 260](../../tests/Feature/PaymentClassificationTest.php#L260).
- `test_payment_link_fallback_uses_configured_classification_without_fixed_id` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 266](../../tests/Feature/PaymentClassificationTest.php#L266).
- `test_purchase_installments_use_balance_after_recorded_payments` — caso relacionado: QA-MPA-013. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 283](../../tests/Feature/PaymentClassificationTest.php#L283).
- `test_purchase_edit_keeps_recorded_payment_after_method_reclassification` — caso relacionado: QA-MPA-013. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 303](../../tests/Feature/PaymentClassificationTest.php#L303).
- `test_excel_contains_text_amounts_and_their_exact_numerators_and_denominators` — relacionado con QA-MPA-020; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 328](../../tests/Feature/PaymentClassificationTest.php#L328).

## tests/Feature/PaymentMethodCreationTest.php

- `test_duplicate_code_is_rejected_without_changing_the_existing_method` — caso relacionado: QA-MPA-002. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 60](../../tests/Feature/PaymentMethodCreationTest.php#L60).
- `test_new_code_can_be_created_and_existing_method_can_be_edited` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 79](../../tests/Feature/PaymentMethodCreationTest.php#L79).
- `test_new_methods_are_available_even_if_client_requests_inactive` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 98](../../tests/Feature/PaymentMethodCreationTest.php#L98).
- `test_initial_methods_are_editable_without_changing_id_or_hka_and_cannot_be_deleted` — caso relacionado: QA-MPA-002. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 118](../../tests/Feature/PaymentMethodCreationTest.php#L118).
- `test_retired_ids_can_be_reused_and_unclassified_methods_remain_inactive` — caso relacionado: QA-MPA-002. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 149](../../tests/Feature/PaymentMethodCreationTest.php#L149).
- `test_currency_and_igtf_options_are_validated_saved_and_removable` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 164](../../tests/Feature/PaymentMethodCreationTest.php#L164).
- `test_only_admin_can_change_availability_or_delete_custom_methods` — caso relacionado: QA-MPA-002. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 179](../../tests/Feature/PaymentMethodCreationTest.php#L179).

## tests/Unit/MethodCurrencyTest.php

- `test_same_currency_can_use_the_document_currency` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 11](../../tests/Unit/MethodCurrencyTest.php#L11).
- `test_cross_currency_needs_explicit_amount_and_rate` — caso relacionado: QA-MPA-004. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 17](../../tests/Unit/MethodCurrencyTest.php#L17).

## tests/Unit/DefaultCashOrderingTest.php

- `test_sqlite_default_cash_uses_binary_unicode_order` — caso relacionado: QA-MPA-003. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 14](../../tests/Unit/DefaultCashOrderingTest.php#L14).

## tests/Unit/ReceiptAmountsTest.php

- `test_converts_received_dollars_to_document_bolivars` — caso relacionado: QA-MPA-004. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 20](../../tests/Unit/ReceiptAmountsTest.php#L20).
- `test_rejects_mismatched_method_currency` — caso relacionado: QA-MPA-004. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 32](../../tests/Unit/ReceiptAmountsTest.php#L32).
- `test_converts_received_bolivars_to_document_dollars` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 40](../../tests/Unit/ReceiptAmountsTest.php#L40).
- `test_existing_receipt_rejects_a_change_of_received_currency` — relacionado con QA-MPA-005; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 50](../../tests/Unit/ReceiptAmountsTest.php#L50).

## tests/Unit/WhatsAppDraftPricingTest.php

- `test_converts_prices_and_discounts_before_rounding_payment` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 11](../../tests/Unit/WhatsAppDraftPricingTest.php#L11).
- `test_rejects_missing_or_nonpositive_rate` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 25](../../tests/Unit/WhatsAppDraftPricingTest.php#L25).
- `test_orchestrator_explains_that_an_old_draft_must_be_confirmed_again` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 44](../../tests/Unit/WhatsAppDraftPricingTest.php#L44).

## tests/js/payment-classification.test.cjs

- `default cash sorts custom IDs by Unicode code point rather than locale` — caso relacionado: QA-MPA-003. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 15](../../tests/js/payment-classification.test.cjs#L15).
- `store configuration can select bank destinations without a payment-method field` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 21](../../tests/js/payment-classification.test.cjs#L21).
- `cash default is active, matches document currency, and does not depend on a seeded ID` — caso relacionado: QA-MPA-003. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 29](../../tests/js/payment-classification.test.cjs#L29).
- `payment validation extracts the server message without guessing a payment row` — relacionado con QA-MPA-017; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 43](../../tests/js/payment-classification.test.cjs#L43).
- `hotel exposes wrapped and indexed payment errors and retains other form errors` — relacionado con QA-MPA-017; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 55](../../tests/js/payment-classification.test.cjs#L55).
- `physical currency and banking destinations are independent` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 76](../../tests/js/payment-classification.test.cjs#L76).
- `the method currency determines eligible bank accounts` — caso relacionado: QA-MPA-004. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 85](../../tests/js/payment-classification.test.cjs#L85).
- `supplier and loan payments use their own currency even if the receipt method has another currency` — relacionado con QA-MPA-013; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 91](../../tests/js/payment-classification.test.cjs#L91).
- `expense IDs never use the payment-method catalog` — relacionado con QA-MPA-021; contrastar detalle específico antes de afirmar cobertura completa. Fuente: [línea 102](../../tests/js/payment-classification.test.cjs#L102).
- `classification applies without an activation flag on destination options` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 106](../../tests/js/payment-classification.test.cjs#L106).
- `an incompatible default is cleared and a new banking destination remains selectable` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 110](../../tests/js/payment-classification.test.cjs#L110).
- `document payment rows clear incompatible destinations without modifying recorded receipts` — relacionado con QA-MPA-005; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 120](../../tests/js/payment-classification.test.cjs#L120).
- `reused receipts retain their historical destination and nature when the catalog changes` — caso relacionado: QA-MPA-005. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 136](../../tests/js/payment-classification.test.cjs#L136).
- `order draft rows clear a destination that conflicts with the method currency` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 150](../../tests/js/payment-classification.test.cjs#L150).


## tests/Support/PaymentClassificationPersistenceCases.php

- `test_fractional_local_unit_invoice_and_mobile_weight_catalog` — relacionado con QA-MPA-022; contrastar detalle específico antes de afirmar cobertura completa. Fuente: [línea 13](../../tests/Support/PaymentClassificationPersistenceCases.php#L13).
- `test_classic_subscription_generation_failure_rolls_back_the_plan` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 45](../../tests/Support/PaymentClassificationPersistenceCases.php#L45).
- `test_api_invoice_from_restaurant_checks_method_currency_and_bank_atomically` — caso relacionado: QA-MPA-011. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 244](../../tests/Support/PaymentClassificationPersistenceCases.php#L244).
- `test_api_sale_note_rejects_retired_currency_and_rolls_back_imported_customer` — relacionado con QA-MPA-011; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 321](../../tests/Support/PaymentClassificationPersistenceCases.php#L321).
- `test_api_invoice_reuses_sale_note_receipt_and_links_source` — relacionado con QA-MPA-008; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 419](../../tests/Support/PaymentClassificationPersistenceCases.php#L419).
- `test_restaurant_cash_links_credit_receipt_once_using_recorded_classification` — relacionado con QA-MPA-011; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 468](../../tests/Support/PaymentClassificationPersistenceCases.php#L468).
- `test_full_subscription_edit_preserves_existing_orders_and_status` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 548](../../tests/Support/PaymentClassificationPersistenceCases.php#L548).
- `test_automatic_subscription_invoice_has_no_unverified_cash_receipt` — caso relacionado: QA-MPA-012. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 611](../../tests/Support/PaymentClassificationPersistenceCases.php#L611).
- `test_loan_payment_reclassifies_method_and_checks_destination_with_rollback` — caso relacionado: QA-MPA-013. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 740](../../tests/Support/PaymentClassificationPersistenceCases.php#L740).
- `test_document_payment_workbook_keeps_received_and_applied_currencies_and_balance` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 777](../../tests/Support/PaymentClassificationPersistenceCases.php#L777).
- `test_document_receivable_kpi_uses_current_balance_after_foreign_receipt_reversal` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 858](../../tests/Support/PaymentClassificationPersistenceCases.php#L858).
- `test_simultaneous_document_receipts_after_prior_read_respect_balance` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 918](../../tests/Support/PaymentClassificationPersistenceCases.php#L918).
- `test_simultaneous_sale_note_receipts_keep_current_settlement_state` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 987](../../tests/Support/PaymentClassificationPersistenceCases.php#L987).
- `test_simultaneous_receipts_do_not_exceed_quotation_or_technical_service_balance` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1045](../../tests/Support/PaymentClassificationPersistenceCases.php#L1045).
- `test_bank_currency_edit_and_receipt_serialize_without_mismatched_currency` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1108](../../tests/Support/PaymentClassificationPersistenceCases.php#L1108).
- `test_receipt_rechecks_currency_after_concurrent_deactivation` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1190](../../tests/Support/PaymentClassificationPersistenceCases.php#L1190).
- `test_method_activation_rechecks_classification_after_concurrent_edit` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1276](../../tests/Support/PaymentClassificationPersistenceCases.php#L1276).
- `test_receipt_attachment_reuses_concurrently_committed_movement_after_prior_read` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1348](../../tests/Support/PaymentClassificationPersistenceCases.php#L1348).
- `test_sale_note_batch_uses_current_locked_method_and_blocks_reclassification` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1437](../../tests/Support/PaymentClassificationPersistenceCases.php#L1437).
- `test_receipt_serializes_with_concurrent_method_reclassification` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1516](../../tests/Support/PaymentClassificationPersistenceCases.php#L1516).
- `test_quotation_and_technical_service_limit_the_exact_applied_receipt` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1638](../../tests/Support/PaymentClassificationPersistenceCases.php#L1638).
- `test_mobile_update_does_not_remove_a_concurrently_recorded_receipt` — relacionado con QA-MPA-019; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1741](../../tests/Support/PaymentClassificationPersistenceCases.php#L1741).
- `test_mobile_capture_keeps_currency_and_rejects_a_conflicting_duplicate` — relacionado con QA-MPA-019; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1787](../../tests/Support/PaymentClassificationPersistenceCases.php#L1787).
- `test_contract_keeps_the_original_cross_currency_receipt_on_edit` — relacionado con QA-MPA-005; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1854](../../tests/Support/PaymentClassificationPersistenceCases.php#L1854).
- `test_default_cash_orders_custom_ids_like_the_frontend` — caso relacionado: QA-MPA-003. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 1907](../../tests/Support/PaymentClassificationPersistenceCases.php#L1907).
- `test_cash_opening_keeps_its_own_currency_when_catalog_methods_change` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1918](../../tests/Support/PaymentClassificationPersistenceCases.php#L1918).
- `test_cash_opening_rechecks_concurrent_method_deactivation` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 1975](../../tests/Support/PaymentClassificationPersistenceCases.php#L1975).
- `test_hotel_retries_do_not_duplicate_a_received_payment` — caso relacionado: QA-MPA-011. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2049](../../tests/Support/PaymentClassificationPersistenceCases.php#L2049).
- `test_hotel_rolls_back_the_new_note_when_a_receipt_cannot_be_linked` — caso relacionado: QA-MPA-011. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2111](../../tests/Support/PaymentClassificationPersistenceCases.php#L2111).
- `test_hotel_reuses_its_sale_note_receipt_without_creating_another_movement` — caso relacionado: QA-MPA-011. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2143](../../tests/Support/PaymentClassificationPersistenceCases.php#L2143).
- `test_hotel_receipt_preserves_received_currency_and_checks_paid_total` — relacionado con QA-MPA-011; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2186](../../tests/Support/PaymentClassificationPersistenceCases.php#L2186).
- `test_quotation_receipt_can_be_applied_to_an_invoice_without_a_second_movement` — caso relacionado: QA-MPA-008. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2251](../../tests/Support/PaymentClassificationPersistenceCases.php#L2251).
- `test_quotation_receipt_is_reused_through_sale_note_and_invoice` — caso relacionado: QA-MPA-008. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2323](../../tests/Support/PaymentClassificationPersistenceCases.php#L2323).
- `test_quotation_conversion_requires_all_mixed_receipts` — relacionado con QA-MPA-016; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2500](../../tests/Support/PaymentClassificationPersistenceCases.php#L2500).
- `test_quotation_conversion_rechecks_receipts_after_a_concurrent_payment` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2550](../../tests/Support/PaymentClassificationPersistenceCases.php#L2550).
- `test_simultaneous_quotation_receipt_applications_allow_only_one_sale_note` — relacionado con QA-MPA-016; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2602](../../tests/Support/PaymentClassificationPersistenceCases.php#L2602).
- `test_sale_note_receipt_deletion_and_invoice_reuse_are_serialized` — relacionado con QA-MPA-009; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2665](../../tests/Support/PaymentClassificationPersistenceCases.php#L2665).
- `test_multiple_sale_notes_preserve_receipts_and_reject_omissions` — caso relacionado: QA-MPA-016. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2737](../../tests/Support/PaymentClassificationPersistenceCases.php#L2737).
- `test_concurrent_invoices_cannot_reuse_an_unpaid_sale_note` — caso relacionado: QA-MPA-016. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 2821](../../tests/Support/PaymentClassificationPersistenceCases.php#L2821).
- `test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income` — relacionado con QA-MPA-008; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2877](../../tests/Support/PaymentClassificationPersistenceCases.php#L2877).
- `test_simultaneous_technical_receipt_applications_allow_only_one_sale_note` — relacionado con QA-MPA-016; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 2956](../../tests/Support/PaymentClassificationPersistenceCases.php#L2956).
- `test_order_draft_payment_creates_money_only_when_generating_the_invoice` — relacionado con QA-MPA-016; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3020](../../tests/Support/PaymentClassificationPersistenceCases.php#L3020).
- `test_order_with_price_entered_without_iva` — relacionado con QA-MPA-023; contrastar detalle específico antes de afirmar cobertura completa. Fuente: [línea 3058](../../tests/Support/PaymentClassificationPersistenceCases.php#L3058).
- `test_technical_document_failure_rolls_back_the_temporary_service_item` — relacionado con QA-MPA-008; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3088](../../tests/Support/PaymentClassificationPersistenceCases.php#L3088).
- `test_payment_destination_catalog_loads_bank_names_in_one_query` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3112](../../tests/Support/PaymentClassificationPersistenceCases.php#L3112).
- `test_receipt_dialog_catalogs_exclude_credit_and_unclassified_methods` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3137](../../tests/Support/PaymentClassificationPersistenceCases.php#L3137).
- `test_technical_service_without_cash_and_repeated_edits_do_not_duplicate_cash_link` — relacionado con QA-MPA-008; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3163](../../tests/Support/PaymentClassificationPersistenceCases.php#L3163).
- `test_sale_note_without_open_cash_explains_rejection_and_rolls_back` — relacionado con QA-MPA-011; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3188](../../tests/Support/PaymentClassificationPersistenceCases.php#L3188).
- `test_quotation_receipt_keeps_received_dollars_and_applied_bolivars` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3203](../../tests/Support/PaymentClassificationPersistenceCases.php#L3203).
- `test_quotation_reclassified_bank_method_checks_currency_and_keeps_snapshot` — relacionado con QA-MPA-005; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3279](../../tests/Support/PaymentClassificationPersistenceCases.php#L3279).
- `test_whatsapp_confirmed_usd_price_keeps_rate_and_receipt_in_ves` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3325](../../tests/Support/PaymentClassificationPersistenceCases.php#L3325).
- `test_whatsapp_orchestrator_persists_the_confirmed_draft_with_a_mock_sender` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3381](../../tests/Support/PaymentClassificationPersistenceCases.php#L3381).
- `test_whatsapp_concurrent_confirmations_reuse_the_same_receipt` — caso relacionado: QA-MPA-012. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 3442](../../tests/Support/PaymentClassificationPersistenceCases.php#L3442).
- `test_whatsapp_draft_requires_open_cash_and_uses_current_cash_classification` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3501](../../tests/Support/PaymentClassificationPersistenceCases.php#L3501).
- `test_store_import_preflights_all_payment_methods_before_creating_customers` — caso relacionado: QA-MPA-012. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 3526](../../tests/Support/PaymentClassificationPersistenceCases.php#L3526).
- `test_storefront_quotation_builder_uses_venezuelan_tax_rate` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3584](../../tests/Support/PaymentClassificationPersistenceCases.php#L3584).
- `test_storefront_quotation_rejects_missing_cash_before_creating_guest` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3605](../../tests/Support/PaymentClassificationPersistenceCases.php#L3605).
- `test_store_import_generates_credit_and_reclassified_bank_invoices` — relacionado con QA-MPA-012; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3708](../../tests/Support/PaymentClassificationPersistenceCases.php#L3708).
- `test_purchase_import_uses_classified_cash_and_creates_one_movement` — relacionado con QA-MPA-013; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3764](../../tests/Support/PaymentClassificationPersistenceCases.php#L3764).
- `test_purchase_update_preserves_recorded_payment_after_reclassification_mysql` — relacionado con QA-MPA-013; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3787](../../tests/Support/PaymentClassificationPersistenceCases.php#L3787).
- `test_quotation_resource_preserves_dollar_cash_method` — relacionado con QA-MPA-005; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3836](../../tests/Support/PaymentClassificationPersistenceCases.php#L3836).
- `test_global_payment_export_reports_failures_and_uses_distinct_files` — relacionado con QA-MPA-020; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3860](../../tests/Support/PaymentClassificationPersistenceCases.php#L3860).
- `test_movement_export_records_failure_and_success` — relacionado con QA-MPA-020; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 3973](../../tests/Support/PaymentClassificationPersistenceCases.php#L3973).
- `test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4054](../../tests/Support/PaymentClassificationPersistenceCases.php#L4054).
- `test_transfers_capture_the_currency_of_each_destination_and_reject_stale_cash_currency` — caso relacionado: QA-MPA-015. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 4413](../../tests/Support/PaymentClassificationPersistenceCases.php#L4413).
- `test_payment_link_with_banking_method_does_not_create_a_cash_receipt` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4490](../../tests/Support/PaymentClassificationPersistenceCases.php#L4490).
- `test_opened_cash_is_returned_by_the_paginated_cash_listing` — relacionado con QA-MPA-018; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4512](../../tests/Support/PaymentClassificationPersistenceCases.php#L4512).
- `test_payment_classification_is_ready_from_the_fresh_tenant_schema` — caso relacionado: QA-MPA-001. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 4565](../../tests/Support/PaymentClassificationPersistenceCases.php#L4565).
- `test_classified_invoice_receipts_keep_received_currency_and_bank_money_outside_physical_cash` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4578](../../tests/Support/PaymentClassificationPersistenceCases.php#L4578).
- `test_company_api_includes_active_classified_custom_method_with_retired_id` — relacionado con QA-MPA-002; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4638](../../tests/Support/PaymentClassificationPersistenceCases.php#L4638).
- `test_payment_dialog_opens_when_the_stored_document_rate_is_invalid` — relacionado con QA-MPA-017; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4661](../../tests/Support/PaymentClassificationPersistenceCases.php#L4661).
- `test_initial_invoice_rejects_method_currency_before_conversion_and_rolls_back` — relacionado con QA-MPA-004; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4675](../../tests/Support/PaymentClassificationPersistenceCases.php#L4675).
- `test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income` — relacionado con QA-MPA-008; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4701](../../tests/Support/PaymentClassificationPersistenceCases.php#L4701).
- `test_credit_invoice_installments_rollback_invalid_amount_and_accept_paid_balance` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4726](../../tests/Support/PaymentClassificationPersistenceCases.php#L4726).
- `test_usd_invoice_installments_discount_ves_bank_receipt` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4753](../../tests/Support/PaymentClassificationPersistenceCases.php#L4753).
- `test_invoice_installments_discount_cross_currency_receipt_and_guarantee_fund` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4800](../../tests/Support/PaymentClassificationPersistenceCases.php#L4800).
- `test_credit_sale_note_installments_discount_cross_currency_receipt` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4821](../../tests/Support/PaymentClassificationPersistenceCases.php#L4821).
- `test_saved_credit_installments_preserve_cross_currency_receipt_through_conversion_and_pdf` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 4938](../../tests/Support/PaymentClassificationPersistenceCases.php#L4938).
- `test_credit_invoice_from_sale_note_reuses_cross_currency_receipt_and_keeps_balance` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 5030](../../tests/Support/PaymentClassificationPersistenceCases.php#L5030).
- `test_sale_note_reuses_a_cross_currency_bank_receipt_without_a_second_movement` — relacionado con QA-MPA-008; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 5367](../../tests/Support/PaymentClassificationPersistenceCases.php#L5367).
- `test_cash_credit_registration_uses_saved_payment_condition_instead_of_method_id` — relacionado con QA-MPA-014; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 5485](../../tests/Support/PaymentClassificationPersistenceCases.php#L5485).
- `test_sale_note_cross_currency_cash_change_uses_received_currency` — caso relacionado: QA-MPA-006. La cobertura específica de la prueba automatizada debe cotejarse con el caso; esta referencia no acredita ejecución nueva. Fuente: [línea 5512](../../tests/Support/PaymentClassificationPersistenceCases.php#L5512).
- `test_sale_note_mixed_currency_change_and_unrepresentable_change` — relacionado con QA-MPA-006; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 5581](../../tests/Support/PaymentClassificationPersistenceCases.php#L5581).
- `test_classified_invoice_change_only_reduces_cash_and_rejects_electronic_excess` — relacionado con QA-MPA-007; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 5627](../../tests/Support/PaymentClassificationPersistenceCases.php#L5627).
- `test_classified_cash_report_performance_against_legacy_at_1000_and_10000_receipts` — relacionado con QA-MPA-010; contrastar el detalle específico antes de afirmar cobertura completa. Fuente: [línea 5703](../../tests/Support/PaymentClassificationPersistenceCases.php#L5703).

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

### Pago P13 — Egresos de compras y préstamos

**Datos:** compra USD de 100, abono de 30 USD desde una cuenta USD; método de cobro configurado en VES y fixture de préstamo independiente. **Pasos:** 1. Registrar el pago a proveedor y comprobar la moneda propia del egreso y de la cuenta. 2. Editar el método y reabrir la compra. 3. En el préstamo intentar un destino incompatible y consultar el estado posterior. **Esperado:** el egreso conserva sus datos históricos y sigue las reglas de pago a proveedores o préstamos; no se aplica automáticamente la regla de moneda de cobros. Una cuenta incompatible rechaza la operación completa. **Control:** saldo compra 100−30=70 USD; rechazo sin movimiento adicional. **Evidencia:** pagos, moneda, snapshots y transacción antes/después.

### Pago P14 — Cuotas y abonos en distinta moneda

**Datos:** factura a crédito de 500 VES, abono bancario de 4 USD a tasa 50 y dos cuotas iniciales. **Pasos:** 1. Registrar el abono real. 2. Abrir las cuotas y comparar la deuda disponible. 3. Enviar cuotas que sumen 301 VES y después cuotas válidas que sumen 300 VES. 4. Convertir desde nota de venta en un fixture equivalente. **Esperado:** el abono y las cuotas son registros distintos; la suma incorrecta se rechaza sin cambios; la suma válida conserva recibo y saldo, sin un segundo ingreso al convertir. **Control:** 4×50=200 VES; deuda 500−200=300 VES. **Evidencia:** cuotas, recibo original, petición y conteo de movimientos.

### Pago P15 — Transferencias y moneda de destinos

**Datos:** caja VES y cuentas VES/USD activas; transferencia independiente entre destinos de moneda compatible y una solicitud con moneda incompatible. **Pasos:** 1. Registrar la transferencia válida y consultar ambos destinos. 2. Cambiar la moneda de uno de los destinos antes de enviar una segunda solicitud con datos antiguos. 3. Consultar saldos y snapshots. **Esperado:** el movimiento válido identifica moneda y destino de cada lado; el destino incompatible u obsoleto se rechaza antes de crear una transferencia parcial. **Control:** para transferencia de 100 VES, origen −100 y destino +100 VES; segundo intento Δ=0. **Evidencia:** registros de ambos lados, respuesta y saldos.

### Pago P16 — Conversión multiorigen y recibos obligatorios

**Datos:** dos notas de venta del mismo cliente con recibos activos de 30 y 20 VES, total factura 116 VES. **Pasos:** 1. Seleccionar ambas notas y generar factura. 2. Revisar sus recibos y saldo. 3. En una operación independiente intentar omitir uno de los recibos de origen o facturar el mismo origen desde dos procesos. **Esperado:** ambos recibos se vinculan una sola vez; se rechaza la omisión y la competencia concurrente no crea dos facturas válidas del mismo origen. **Control:** abonos 30+20=50 VES; saldo 116−50=66 VES y cero ingresos adicionales por la conversión. **Evidencia:** referencias, claves, movimientos y respuestas concurrentes.

### Pago P17 — Errores de pagos y conservación del formulario

**Datos:** factura QA 116 VES, formulario con dos filas y error de servidor específico de la segunda; repetir en Hotel con errores envueltos y otros errores del formulario. **Pasos:** 1. Enviar con destino incompatible en la segunda fila. 2. Observar el mensaje y revisar ambas filas. 3. Repetir con respuesta de error general y con tasa documental inválida al abrir Pagos. **Esperado:** se muestra el mensaje real y se conserva el resto del formulario; no se asigna el error a otra fila por suposición. El diálogo se abre con tasa inválida y exige corrección antes de aplicar conversión. **Control:** documento y pagos sin cambios tras rechazos. **Evidencia:** pantalla, respuesta saneada y estado persistido.

### Pago P18 — Caja única, apertura y listado

**Datos:** instalación nueva QA, usuario U1 y sucursal S1, sin caja abierta; apertura de 100 VES. **Pasos:** 1. Abrir la caja sin depender de un indicador de activación legado. 2. Intentar abrir otra caja simultáneamente para el mismo usuario. 3. Consultar el listado paginado, cambiar la clasificación del método y revisar el monto de apertura. **Esperado:** se conserva un único turno abierto y aparece en el listado; la apertura mantiene su moneda y monto aunque cambie el catálogo. **Control:** una caja abierta; apertura 100 VES, contada una vez. **Evidencia:** transacciones, listado paginado y registros de apertura.

### Pago P19 — Captura móvil y actualización concurrente

**Datos:** factura de 500 VES, captura móvil de 4 USD a tasa 50 con UUID K; actualización de pantalla ejecutada en paralelo. **Pasos:** 1. Capturar el recibo. 2. Completar una actualización iniciada antes de la captura. 3. Repetir K con datos idénticos y luego con un importe distinto. **Esperado:** la actualización no elimina el cobro concurrente; el replay idéntico mantiene una operación y un movimiento, el cambio incompatible devuelve conflicto. El importe y moneda recibidos se conservan. **Control:** 4×50=200 VES aplicados; saldo 300 VES; un movimiento para K. **Evidencia:** solicitudes, respuestas, snapshot y lecturas finales. La prueba del endpoint no acredita adaptación del cliente externo.

### Pago P20 — Exportación de movimientos: éxito y falla

**Datos:** dos operaciones QA con salidas distintas; un destino de archivo válido y otro con fallo controlado. **Pasos:** 1. Exportar pagos y movimientos de manera independiente. 2. Provocar error de generación de un archivo. 3. Reintentar y abrir los archivos generados. **Esperado:** éxito y falla se comunican de forma diferenciada; no se entrega un archivo inexistente como exitoso ni se sobrescribe la salida de otra operación. XLSX conserva importes exactos y moneda documentada. **Control:** dos operaciones generan rutas distintas; el intento fallido no altera pagos ni caja. **Evidencia:** respuestas, rutas, hashes y contenido del XLSX/PDF.

### Pago P21 — Catálogo de gastos independiente de medios de pago

**Datos:** un tipo de gasto y un medio de pago con IDs distintos; otro medio con ID coincidente por casualidad. **Pasos:** 1. Registrar un gasto utilizando el catálogo de gastos y un destino financiero válido. 2. Cambiar la clasificación del medio de pago sin modificar el tipo de gasto. 3. Consultar el registro y sus relaciones. **Esperado:** el tipo de gasto se resuelve desde su catálogo propio y no cambia por la clasificación del medio; coincidencia de IDs no fusiona catálogos. **Control:** un gasto, un movimiento autorizado y tipo original conservado. **Evidencia:** solicitud saneada y relaciones persistidas.

### Pago P22 — Unidad fraccionaria en factura y catálogo móvil

**Datos:** producto local de peso con unidad activa KG y cantidad 1,2500; precio base 100 VES por KG e IVA 16 %. **Pasos:** 1. Consultar el catálogo de unidades del cliente móvil. 2. Facturar 1,2500 KG mediante flujo disponible. 3. Revisar cantidad, total, inventario y equivalencia fiscal. **Esperado:** se conserva la precisión de cantidad y se ofrece la unidad de peso válida; no se convierte a unidad entera. **Control:** base 125 VES, IVA 20 VES, total 145 VES; stock −1,2500 KG. **Evidencia:** catálogo, solicitud, factura y stock antes/después. Un endpoint probado no acredita actualización de una aplicación externa.

### Pago P23 — Pedido con precio ingresado sin IVA

**Datos:** pedido de un bien, precio base 100 VES, IVA 16 %, configuración de precio sin IVA. **Pasos:** 1. Registrar el pedido con el precio base. 2. Generar factura y consultar sus líneas y totales. 3. Comparar con el pedido y sus pagos. **Esperado:** base e impuesto se calculan una sola vez; el borrador del pedido no genera un ingreso antes de la factura. **Control:** base 100 + IVA 16 = 116 VES; antes de facturar movimientos de ingreso 0. **Evidencia:** pedido, factura, cálculo y movimientos.

Las líneas de aserción localizadas automáticamente para las 135 pruebas inventariadas están en [aserciones de pruebas de pagos](aserciones_pruebas_pagos.md). La extracción sirve como apoyo y puede omitir comprobaciones realizadas por helpers; para verificar cobertura se debe leer también el cuerpo de cada prueba.

## Índice adicional de pruebas de interfaz cotejadas

Las entradas siguientes relacionan cada declaración de prueba revisada con el caso de ejecución más cercano. Las declaraciones parametrizadas generan varias pruebas según el arreglo del archivo; revisar sus canales y controles complementarios de la guía. La relación no acredita ejecución de navegador ni de la nueva corrida.

### payment-edit-preservation

- [`payment submission preserves draft installments and receipts while normalizing the sent condition`](../../tests/js/payment-edit-preservation.test.cjs#L11) → QA-MPA-014.
- [`${path}: failed submission keeps the draft and sends only installments for credit`](../../tests/js/payment-edit-preservation.test.cjs#L27) → QA-MPA-014.
- [`invoice creation and preview normalize a copy without losing draft installments`](../../tests/js/payment-edit-preservation.test.cjs#L47) → QA-MPA-014.
- [`${path}: default destination preserves existing payments and accepts an empty list`](../../tests/js/payment-edit-preservation.test.cjs#L81) → QA-MPA-005.
- [`changing a sale-note date does not change the date of recorded receipts`](../../tests/js/payment-edit-preservation.test.cjs#L97) → QA-MPA-005.
- [`a rejected sale note keeps its draft currency, received amount and rate`](../../tests/js/payment-edit-preservation.test.cjs#L106) → QA-MPA-017.
- [`resetting a sale note after success selects the configured active currency`](../../tests/js/payment-edit-preservation.test.cjs#L133) → QA-MPA-017.

### purchase-payment-validation

- [`purchase payment amounts compare decimal fractions without float errors`](../../tests/js/purchase-payment-validation.test.cjs#L21) → QA-MPA-013.
- [`${file} accepts exact cents and rejects invalid or excessive payments`](../../tests/js/purchase-payment-validation.test.cjs#L30) → QA-MPA-013.
- [`purchase order conversion submits exact cents but blocks excess and invalid input`](../../tests/js/purchase-payment-validation.test.cjs#L41) → QA-MPA-013.
- [`purchase validation displays server detail without losing the draft`](../../tests/js/purchase-payment-validation.test.cjs#L54) → QA-MPA-017.
- [`editing a purchase normalizes persisted indexed discounts and charges before calculating totals`](../../tests/js/purchase-payment-validation.test.cjs#L70) → QA-MPA-013.
- [`editing a purchase keeps saved installments and suggests only the remaining balance`](../../tests/js/purchase-payment-validation.test.cjs#L83) → QA-MPA-013.
- [`purchase generated from a USD credit order retains the condition without creating a payment`](../../tests/js/purchase-payment-validation.test.cjs#L95) → QA-MPA-013.
- [`switching a purchase to credit discards only empty rows and keeps entered payments`](../../tests/js/purchase-payment-validation.test.cjs#L115) → QA-MPA-013.
- [`${file} removes only an untouched automatic cash proposal when selecting credit`](../../tests/js/purchase-payment-validation.test.cjs#L126) → QA-MPA-013.
- [`${file} keeps tracking the automatic proposal after a total recalculation`](../../tests/js/purchase-payment-validation.test.cjs#L136) → QA-MPA-013.
- [`${file} marks the row created automatically by the condition change`](../../tests/js/purchase-payment-validation.test.cjs#L148) → QA-MPA-013.
- [`selecting credit without an active credit method leaves a selectable installment instead of throwing`](../../tests/js/purchase-payment-validation.test.cjs#L162) → QA-MPA-014.

### fiscal-payment-ui

- [`fiscal payment exposes a rate when the document lacks one`](../../tests/js/fiscal-payment-ui.test.cjs#L13) → QA-MPA-017.
- [`legacy payment rows default to no IGTF without adding a currency selector`](../../tests/js/fiscal-payment-ui.test.cjs#L20) → QA-MPA-004.
- [`an explicitly exempt payment retains its classification and stated reason`](../../tests/js/fiscal-payment-ui.test.cjs#L25) → QA-MPA-004.
- [`payment currency conversions preserve eight-decimal rates and separate IGTF`](../../tests/js/fiscal-payment-ui.test.cjs#L30) → QA-MPA-004.
- [`selecting a USD method proposes received dollars without changing the applied bolivars`](../../tests/js/fiscal-payment-ui.test.cjs#L37) → QA-MPA-004.
- [`editing either fiscal amount keeps received and applied currency in sync`](../../tests/js/fiscal-payment-ui.test.cjs#L48) → QA-MPA-004.
- [`a missing exchange rate never invents a one-to-one conversion`](../../tests/js/fiscal-payment-ui.test.cjs#L62) → QA-MPA-004.
- [`a stale proposal cannot overwrite the current collection`](../../tests/js/fiscal-payment-ui.test.cjs#L71) → QA-MPA-004.
- [`Vue consumers compile with fiscal profiles and the shared payment fields`](../../tests/js/fiscal-payment-ui.test.cjs#L81) → QA-MPA-004.
- [`fiscal form displays the same settlement the server saves after rounding received currency`](../../tests/js/fiscal-payment-ui.test.cjs#L90) → QA-MPA-004.
- [`method proposal and reverse conversion use the settled received amount`](../../tests/js/fiscal-payment-ui.test.cjs#L102) → QA-MPA-004.
- [`fiscal form discloses the received amount rounded for settlement`](../../tests/js/fiscal-payment-ui.test.cjs#L114) → QA-MPA-004.
- [`fiscal fields display structured currency validation errors`](../../tests/js/fiscal-payment-ui.test.cjs#L120) → QA-MPA-017.
- [`document payment submission keeps wrapped currency errors on the payment row`](../../tests/js/fiscal-payment-ui.test.cjs#L125) → QA-MPA-017.
- [`same-currency settlement keeps the amount entered and displays the applied cents`](../../tests/js/fiscal-payment-ui.test.cjs#L138) → QA-MPA-004.
- [`same-currency submission sends the received amount before rounding`](../../tests/js/fiscal-payment-ui.test.cjs#L146) → QA-MPA-004.
- [`remounting a fiscal draft keeps its manual rate and recalculates applied settlement`](../../tests/js/fiscal-payment-ui.test.cjs#L160) → QA-MPA-004.

### classic-subscription-plan

- [`classic enrollment copies the selected plan without changing its lines or inventing payments`](../../tests/js/classic-subscription-plan.test.cjs#L23) → QA-MPA-012.
- [`classic enrollment obtains a visible valid rate and rejects an invalid manual rate`](../../tests/js/classic-subscription-plan.test.cjs#L41) → QA-MPA-012.
- [`late exchange rate lookup does not replace a manually entered rate`](../../tests/js/classic-subscription-plan.test.cjs#L58) → QA-MPA-012.
- [`rejected enrollment keeps the draft and displays the server validation`](../../tests/js/classic-subscription-plan.test.cjs#L72) → QA-MPA-017.

### invoice-payment-validation

- [`invoice validation uses exact sums and preserves recorded receipts`](../../tests/js/invoice-payment-validation.test.cjs#L15) → QA-MPA-014.
- [`only new cash can provide change after IDs are reclassified`](../../tests/js/invoice-payment-validation.test.cjs#L28) → QA-MPA-007.
- [`late catalog resolves only still-pending new rows and preserves selections`](../../tests/js/invoice-payment-validation.test.cjs#L38) → QA-MPA-003.
- [`${file} validates its actual payment method through the shared exact helper`](../../tests/js/invoice-payment-validation.test.cjs#L58) → QA-MPA-004.

### subscription-payment-currency

- [`${moduleName} posts the original USD receipt and prevents a double click`](../../tests/js/subscription-payment-currency.test.cjs#L36) → QA-MPA-012.
- [`${moduleName} validates the applied amount derived from received money`](../../tests/js/subscription-payment-currency.test.cjs#L50) → QA-MPA-012.
- [`${moduleName} retains and displays wrapped currency validation errors`](../../tests/js/subscription-payment-currency.test.cjs#L57) → QA-MPA-017.

### cash-opening-errors

- [`${file} displays opening validation errors without clearing the entered balances`](../../tests/js/cash-opening-errors.test.cjs#L10) → QA-MPA-018.

### quotation-conversion-receipts

- [`invoice initialization never overwrites recorded or selected destinations`](../../tests/js/quotation-conversion-receipts.test.cjs#L9) → QA-MPA-005.
- [`quotation credit preserves real receipts and never fabricates a credit payment`](../../tests/js/quotation-conversion-receipts.test.cjs#L43) → QA-MPA-008.
- [`quotation default amount never replaces an entered or recorded receipt`](../../tests/js/quotation-conversion-receipts.test.cjs#L59) → QA-MPA-008.
- [`quotation conversion sends a recorded receipt without changing its original data`](../../tests/js/quotation-conversion-receipts.test.cjs#L83) → QA-MPA-008.
- [`sale note conversion displays the original receipt and sends it unchanged`](../../tests/js/quotation-conversion-receipts.test.cjs#L104) → QA-MPA-008.
- [`credit installments normalize the form condition while preserving fees and receipts`](../../tests/js/quotation-conversion-receipts.test.cjs#L134) → QA-MPA-014.
- [`switching quotation payment conditions preserves edited installments and receipt data`](../../tests/js/quotation-conversion-receipts.test.cjs#L156) → QA-MPA-014.
- [`sale note conversion exposes credit installments and does not add an invisible cash payment`](../../tests/js/quotation-conversion-receipts.test.cjs#L171) → QA-MPA-014.
- [`removing a draft payment cannot erase the quotation receipt used by the guard`](../../tests/js/quotation-conversion-receipts.test.cjs#L184) → QA-MPA-008.
- [`loading a credit quotation preserves its receipt and suggests only the unpaid balance`](../../tests/js/quotation-conversion-receipts.test.cjs#L193) → QA-MPA-014.
- [`recorded quotation receipts cannot be removed while draft payments can`](../../tests/js/quotation-conversion-receipts.test.cjs#L216) → QA-MPA-008.
- [`invoice creation keeps quotation receipts read-only and permits removing drafts`](../../tests/js/quotation-conversion-receipts.test.cjs#L229) → QA-MPA-008.
- [`a quotation without prior receipts submits the new received currency and rate`](../../tests/js/quotation-conversion-receipts.test.cjs#L249) → QA-MPA-004.
- [`credit conversion calculates the installment from the balance after real receipts`](../../tests/js/quotation-conversion-receipts.test.cjs#L265) → QA-MPA-014.
- [`quotation conversion proposes only the remaining receipt and never fabricates credit amounts`](../../tests/js/quotation-conversion-receipts.test.cjs#L281) → QA-MPA-014.
- [`quotation credit installments account for retention and applied receipts without overwriting edited fees`](../../tests/js/quotation-conversion-receipts.test.cjs#L296) → QA-MPA-014.

### invoice-cash-total

- [`invoice totals and the suggested cash payment follow all lines, quantity edits and removals`](../../tests/js/invoice-cash-total.test.cjs#L30) → QA-MPA-003.
- [`discounts, charges, exempt lines and final cent settlement use the same collectible total`](../../tests/js/invoice-cash-total.test.cjs#L45) → QA-MPA-003.
- [`manual amounts including zero remain manual; a new row follows only the outstanding amount`](../../tests/js/invoice-cash-total.test.cjs#L53) → QA-MPA-003.
- [`credit, existing receipts, restored amounts and foreign-currency receipts are preserved`](../../tests/js/invoice-cash-total.test.cjs#L66) → QA-MPA-003.
- [`USD invoices update their suggested payment and explicit edits stop automatic changes even at the same amount`](../../tests/js/invoice-cash-total.test.cjs#L75) → QA-MPA-003.
- [`suggestion tracking is private, updates matching-currency original amount and respects changed inputs`](../../tests/js/invoice-cash-total.test.cjs#L84) → QA-MPA-003.

### payment-method-form

- [`duplicate code displays the server error and keeps the form open`](../../tests/js/payment-method-form.test.cjs#L17) → QA-MPA-002.
- [`editing sends PUT to the selected method`](../../tests/js/payment-method-form.test.cjs#L37) → QA-MPA-002.
- [`payment methods no longer request card details`](../../tests/js/payment-method-form.test.cjs#L53) → QA-MPA-002.
- [`availability checkbox is absent in both creation and editing`](../../tests/js/payment-method-form.test.cjs#L57) → QA-MPA-002.
- [`editing preserves the availability loaded from the server`](../../tests/js/payment-method-form.test.cjs#L75) → QA-MPA-002.
- [`payment method form template compiles`](../../tests/js/payment-method-form.test.cjs#L97) → QA-MPA-002.
- [`catalogue displays condition and credit fields from classification, even with stale flags`](../../tests/js/payment-method-form.test.cjs#L102) → QA-MPA-002.

### subscription-invoice-receipts

- [`${moduleName} initializes a credit note with its installments and previous receipt`](../../tests/js/subscription-invoice-receipts.test.cjs#L21) → QA-MPA-014.
- [`${moduleName} conversion cannot remove a registered or reused receipt`](../../tests/js/subscription-invoice-receipts.test.cjs#L51) → QA-MPA-008.
- [`${moduleName} conversion retains the source rate and receipt details`](../../tests/js/subscription-invoice-receipts.test.cjs#L67) → QA-MPA-008.
- [`${moduleName} keeps receipts and suggests only the unpaid credit balance`](../../tests/js/subscription-invoice-receipts.test.cjs#L86) → QA-MPA-014.
- [`${moduleName} sends receipts for credit and omits installments for cash`](../../tests/js/subscription-invoice-receipts.test.cjs#L111) → QA-MPA-014.
- [`${moduleName} blocks conversion when no valid exchange rate is available`](../../tests/js/subscription-invoice-receipts.test.cjs#L132) → QA-MPA-004.
- [`${moduleName} shows a validation error unrelated to payment and keeps the draft`](../../tests/js/subscription-invoice-receipts.test.cjs#L143) → QA-MPA-017.

### transfer-cash-currency

- [`transfer sends the configured cash currency with the amounts`](../../tests/js/transfer-cash-currency.test.cjs#L17) → QA-MPA-015.

## Índice complementario de canales y moneda

Relación de declaraciones con el caso más cercano; cada expresión parametrizada puede representar varios canales. Las pruebas de órdenes aquí son órdenes de entrega con indicador fiscal 1 cuando se pruebe HKA; no comprenden notas de entrega indicador 0. Consultar la guía para los controles por canal. No se declaran ejecutadas en esta corrida.

### default-receipt-methods

- [`sale note credit installment date follows classification and configured days, not the method ID`](../../tests/js/default-receipt-methods.test.cjs#L7) → QA-MPA-014.
- [`contract header uses current cash method instead of the old fixed USD cash ID`](../../tests/js/default-receipt-methods.test.cjs#L32) → QA-MPA-003.
- [`contract editing reveals saved payments and uses their frozen descriptions`](../../tests/js/default-receipt-methods.test.cjs#L40) → QA-MPA-005.
- [`purchase order conversion keeps credit out of payment rows and validates destination choices`](../../tests/js/default-receipt-methods.test.cjs#L63) → QA-MPA-013.
- [`${filename} prepares a purchase-order method using the document currency`](../../tests/js/default-receipt-methods.test.cjs#L79) → QA-MPA-013.
- [`${filename} proposes cash for the currency of the expense`](../../tests/js/default-receipt-methods.test.cjs#L102) → QA-MPA-013.
- [`loan payment defaults to an active account method and only a matching currency destination`](../../tests/js/default-receipt-methods.test.cjs#L125) → QA-MPA-013.
- [`loan method change keeps compatible accounts regardless of method ID`](../../tests/js/default-receipt-methods.test.cjs#L139) → QA-MPA-013.
- [`${filename} chooses active cash in the document currency and leaves incompatible destinations empty`](../../tests/js/default-receipt-methods.test.cjs#L160) → QA-MPA-003.
- [`${filename} proposes only cash in the document currency`](../../tests/js/default-receipt-methods.test.cjs#L188) → QA-MPA-003.
- [`POS multiple payments accept only classified receipt methods and retain all entered payments`](../../tests/js/default-receipt-methods.test.cjs#L196) → QA-MPA-004.
- [`${filename} preserves actual receipts when switching to credit`](../../tests/js/default-receipt-methods.test.cjs#L212) → QA-MPA-014.
- [`${filename} resolves installments independently from receipt IDs`](../../tests/js/default-receipt-methods.test.cjs#L232) → QA-MPA-014.
- [`${filename} changes only the selected installment date`](../../tests/js/default-receipt-methods.test.cjs#L249) → QA-MPA-014.
- [`${filename} retains entered installments and receipts when switching payment conditions`](../../tests/js/default-receipt-methods.test.cjs#L266) → QA-MPA-014.
- [`${filename} distinguishes an unselected credit method from an empty catalog`](../../tests/js/default-receipt-methods.test.cjs#L282) → QA-MPA-014.
- [`${filename} chooses matching cash instead of first account`](../../tests/js/default-receipt-methods.test.cjs#L296) → QA-MPA-003.
- [`${filename} never invents a fixed credit method`](../../tests/js/default-receipt-methods.test.cjs#L310) → QA-MPA-014.
- [`${filename} keeps entered receipts while suggesting only the remaining amount`](../../tests/js/default-receipt-methods.test.cjs#L323) → QA-MPA-014.
- [`suggested installments deduct applied receipts and keep saved or manually edited installments`](../../tests/js/default-receipt-methods.test.cjs#L340) → QA-MPA-014.
- [`USD credit installments divide the applied VES receipt balance by cents`](../../tests/js/default-receipt-methods.test.cjs#L355) → QA-MPA-014.
- [`${filename} refreshes suggested credit installments when applied amounts change`](../../tests/js/default-receipt-methods.test.cjs#L365) → QA-MPA-014.

### receipt-currency-fields

- [`reused receipts retain their received currency and amount when the catalog or document rate changes`](../../tests/js/receipt-currency-fields.test.cjs#L16) → QA-MPA-005.
- [`a missing documentary rate keeps the rate field available for same-currency payments`](../../tests/js/receipt-currency-fields.test.cjs#L34) → QA-MPA-004.
- [`hotel creates a secure UUID for retrying one operation`](../../tests/js/receipt-currency-fields.test.cjs#L41) → QA-MPA-011.
- [`proposes method currency and converts the received amount`](../../tests/js/receipt-currency-fields.test.cjs#L50) → QA-MPA-004.
- [`invalid rate leaves the received amount unset`](../../tests/js/receipt-currency-fields.test.cjs#L65) → QA-MPA-004.
- [`balance check uses the rounded received amount and its own rate`](../../tests/js/receipt-currency-fields.test.cjs#L75) → QA-MPA-004.
- [`remounting a draft keeps its manually selected rate and received amount`](../../tests/js/receipt-currency-fields.test.cjs#L83) → QA-MPA-004.
- [`shows server currency, received amount and rate errors without HTML interpolation`](../../tests/js/receipt-currency-fields.test.cjs#L93) → QA-MPA-017.
- [`quotation and technical service preserve wrapped validation errors for the currency fields`](../../tests/js/receipt-currency-fields.test.cjs#L103) → QA-MPA-017.
- [`component template compiles`](../../tests/js/receipt-currency-fields.test.cjs#L119) → QA-MPA-017.
- [`displayed applied amount follows rounded received amount after conversion`](../../tests/js/receipt-currency-fields.test.cjs#L128) → QA-MPA-004.
- [`reverse conversion uses cent settlement before applying the exchange rate`](../../tests/js/receipt-currency-fields.test.cjs#L140) → QA-MPA-004.
- [`shared receipt fields disclose rounding of the received amount`](../../tests/js/receipt-currency-fields.test.cjs#L148) → QA-MPA-004.
- [`remounting a shared receipt draft reconciles its applied amount using its manual rate`](../../tests/js/receipt-currency-fields.test.cjs#L154) → QA-MPA-004.

### technical-service-invoice-receipts

- [`technical service conversion preserves recorded and source-only receipts`](../../tests/js/technical-service-invoice-receipts.test.cjs#L19) → QA-MPA-008.
- [`technical service conversion submits an existing receipt without changing its data`](../../tests/js/technical-service-invoice-receipts.test.cjs#L36) → QA-MPA-008.
- [`technical service without prior receipts submits the received currency and source rate`](../../tests/js/technical-service-invoice-receipts.test.cjs#L52) → QA-MPA-004.

### hotel-order-receipt-creation

- [`order receipt selector requires explicit active classification for every method`](../../tests/js/hotel-order-receipt-creation.test.cjs#L35) → QA-MPA-011.
- [`hotel check-in formats exact string totals after selecting a rate`](../../tests/js/hotel-order-receipt-creation.test.cjs#L48) → QA-MPA-011.
- [`hotel check-in preserves the exact rate when preparing the document line`](../../tests/js/hotel-order-receipt-creation.test.cjs#L62) → QA-MPA-011.
- [`hotel checkout sums decimal balances and applies arrears only once on retries`](../../tests/js/hotel-order-receipt-creation.test.cjs#L71) → QA-MPA-011.
- [`hotel payment defaults use document currency and do not assign cash to an account method`](../../tests/js/hotel-order-receipt-creation.test.cjs#L98) → QA-MPA-011.
- [`hotel product payment changes clear incompatible destinations without changing registered products`](../../tests/js/hotel-order-receipt-creation.test.cjs#L115) → QA-MPA-011.
- [`hotel checkout suggests a valid classified method and filters account currencies`](../../tests/js/hotel-order-receipt-creation.test.cjs#L134) → QA-MPA-011.
- [`order sale note submits received amount and rate without replacing them with the document total`](../../tests/js/hotel-order-receipt-creation.test.cjs#L154) → QA-MPA-016.

### order-invoice-receipts

- [`order draft payments retain received currency and rate at conversion`](../../tests/js/order-invoice-receipts.test.cjs#L26) → QA-MPA-016.
- [`destination validation uses the method classification even with credit documents and retired IDs`](../../tests/js/order-invoice-receipts.test.cjs#L38) → QA-MPA-016.
- [`order conversion proposes matching active cash instead of the first account`](../../tests/js/order-invoice-receipts.test.cjs#L48) → QA-MPA-016.
- [`order draft suggests a classified method and does not overwrite earlier amounts`](../../tests/js/order-invoice-receipts.test.cjs#L69) → QA-MPA-016.
- [`order creation displays validation errors returned in the standard envelope`](../../tests/js/order-invoice-receipts.test.cjs#L87) → QA-MPA-017.
- [`order item forms retain the entered price without floating point IVA artifacts`](../../tests/js/order-invoice-receipts.test.cjs#L99) → QA-MPA-023.
- [`reopening either order item form restores its saved price and tax basis`](../../tests/js/order-invoice-receipts.test.cjs#L121) → QA-MPA-023.
- [`order conversion submits the currency and blocks unverified historical receipt IDs`](../../tests/js/order-invoice-receipts.test.cjs#L141) → QA-MPA-016.

### exact-central-payments

- [`central payment records keep their original decimal text for editing`](../../tests/js/exact-central-payments.test.cjs#L13) → QA-MPA-004.
- [`central display rounds from the fraction without changing the editable value`](../../tests/js/exact-central-payments.test.cjs#L19) → QA-MPA-004.
- [`tenant account templates use the same exact amount projection`](../../tests/js/exact-central-payments.test.cjs#L24) → QA-MPA-020.

### hotel-exchange-rate-sale

- [`hotel sale note obtains a valid exchange rate before saving`](../../tests/js/hotel-exchange-rate-sale.test.cjs#L11) → QA-MPA-011.
- [`converting an existing note keeps its valid recorded exchange rate`](../../tests/js/hotel-exchange-rate-sale.test.cjs#L23) → QA-MPA-011.
- [`an invalid exchange response does not change the note or invoice`](../../tests/js/hotel-exchange-rate-sale.test.cjs#L30) → QA-MPA-011.

### dispatch-invoice-receipts

- [`delivery order conversion exposes the configured receipt currency and fiscal fields`](../../tests/js/dispatch-invoice-receipts.test.cjs#L29) → QA-MPA-004.
- [`delivery order retains entered receipts and suggests installments from the outstanding balance`](../../tests/js/dispatch-invoice-receipts.test.cjs#L36) → QA-MPA-014.
- [`adding a receipt preserves earlier manual amounts`](../../tests/js/dispatch-invoice-receipts.test.cjs#L51) → QA-MPA-014.
- [`switching an untouched automatic cash suggestion to credit does not create a receipt`](../../tests/js/dispatch-invoice-receipts.test.cjs#L61) → QA-MPA-014.
- [`editing the suggested receipt preserves it on credit`](../../tests/js/dispatch-invoice-receipts.test.cjs#L76) → QA-MPA-014.
- [`delivery order defaults to a currently classified receipt method`](../../tests/js/dispatch-invoice-receipts.test.cjs#L90) → QA-MPA-003.
- [`delivery order converts item prices using the document rate, not a default rate`](../../tests/js/dispatch-invoice-receipts.test.cjs#L101) → QA-MPA-004.
- [`delivery order keeps source price exact when IVA is added and currency is converted`](../../tests/js/dispatch-invoice-receipts.test.cjs#L117) → QA-MPA-004.
- [`delivery order refuses to submit without a positive document rate`](../../tests/js/dispatch-invoice-receipts.test.cjs#L132) → QA-MPA-004.
- [`delivery order displays non-payment validation errors without discarding receipts`](../../tests/js/dispatch-invoice-receipts.test.cjs#L140) → QA-MPA-017.
- [`delivery order retains the item IVA classification when generating its invoice`](../../tests/js/dispatch-invoice-receipts.test.cjs#L152) → QA-MPA-023.
- [`delivery order submission waits for fiscal readiness and reports receipt errors`](../../tests/js/dispatch-invoice-receipts.test.cjs#L163) → QA-MPA-017.
- [`delivery order sends credit receipts and omits installments only for cash`](../../tests/js/dispatch-invoice-receipts.test.cjs#L185) → QA-MPA-014.

## Índice de regresiones adicionales citadas en el chat

Relación de pruebas disponibles con casos relacionados. Las pruebas parametrizadas pueden cubrir múltiples archivos; el nombre fuente y la capa constan en el enlace. No se consideran ejecutadas en esta corrida.

### classified-destination-validation

- [`${file} requires a destination by classification, including credit documents with real payments`](../../tests/js/classified-destination-validation.test.cjs#L18) → QA-MPA-004.

### dispatch-destinations

- [`customer main address with id zero is selectable and stale destination is cleared`](../../tests/js/dispatch-destinations.test.cjs#L21) → QA-ODE-055.
- [`changing reason removes customer destination and clears empty own destinations`](../../tests/js/dispatch-destinations.test.cjs#L33) → QA-ODE-055.
- [`a late response cannot replace destinations for the newly selected customer`](../../tests/js/dispatch-destinations.test.cjs#L47) → QA-ODE-055.
- [`failed load removes old destinations and releases loading`](../../tests/js/dispatch-destinations.test.cjs#L57) → QA-ODE-055.
- [`delivery order template compiles with loading and missing destination guidance`](../../tests/js/dispatch-destinations.test.cjs#L65) → QA-ODE-055.

### document-report-search

- [`document sales report starts with an invoice type`](../../tests/js/document-report-search.test.cjs#L26) → QA-MPA-020.
- [`sales totals sum exact fractions by currency and exclude rejected credit notes`](../../tests/js/document-report-search.test.cjs#L32) → QA-MPA-020.
- [`a rejected search clears old results, reports the error and releases the button`](../../tests/js/document-report-search.test.cjs#L47) → QA-MPA-020.

### movement-summary

- [`movement summaries keep VES and USD separate and retain exact fractions`](../../tests/js/movement-summary.test.cjs#L30) → QA-MPA-010.
- [`movement pagination stops on failure and does not expose partial results`](../../tests/js/movement-summary.test.cjs#L43) → QA-MPA-010.
- [`movement pagination collects each page before showing results`](../../tests/js/movement-summary.test.cjs#L61) → QA-MPA-010.
- [`sorting dates preserves received timestamps and balances across pages`](../../tests/js/movement-summary.test.cjs#L76) → QA-MPA-010.

### pos-exchange-rate

- [`el borrador conserva su tasa válida y usa la actual solamente si le falta`](../../tests/js/pos-exchange-rate.test.cjs#L22) → QA-MPA-003.
- [`la respuesta tardía no sobrescribe un borrador ni una edición posterior`](../../tests/js/pos-exchange-rate.test.cjs#L29) → QA-MPA-003.
- [`una solicitud anterior no reemplaza la tasa de una solicitud más reciente`](../../tests/js/pos-exchange-rate.test.cjs#L46) → QA-MPA-003.
- [`fallo sin tasa bloquea el cálculo y permite recuperar mediante reintento`](../../tests/js/pos-exchange-rate.test.cjs#L56) → QA-MPA-003.
- [`${file}: bloquea cambio de moneda y apertura de pago sin tasa`](../../tests/js/pos-exchange-rate.test.cjs#L75) → QA-MPA-003.
- [`${file}: restaura borrador ${hasDraftRate ? 'con tasa' : 'sin tasa'} sin esperar una consulta pendiente`](../../tests/js/pos-exchange-rate.test.cjs#L95) → QA-MPA-003.
- [`${file}: permite reintentar aun cuando la tasa sigue pendiente o es inválida`](../../tests/js/pos-exchange-rate.test.cjs#L139) → QA-MPA-003.
- [`${file}: impide modificar cantidad o total antes de obtener tasa`](../../tests/js/pos-exchange-rate.test.cjs#L151) → QA-MPA-003.
- [`Garage resta una unidad sin truncar ni redondear cantidades fraccionarias`](../../tests/js/pos-exchange-rate.test.cjs#L184) → QA-MPA-022.
- [`Garage recalcula la línea en moneda documental después de restar una cantidad fraccionaria`](../../tests/js/pos-exchange-rate.test.cjs#L211) → QA-MPA-022.
- [`POS varía cantidades decimales sin redondearlas a cuatro posiciones`](../../tests/js/pos-exchange-rate.test.cjs#L241) → QA-MPA-022.

### pos-quantity-currency

- [`${file}: ${name} conserva la tasa al cambiar la cantidad en ambas monedas`](../../tests/js/pos-quantity-currency.test.cjs#L21) → QA-MPA-022.
- [`${file}: editar el total conserva el precio ingresado y su moneda documental`](../../tests/js/pos-quantity-currency.test.cjs#L43) → QA-MPA-022.

### sale-note-invoice-submit

- [`sale note conversion submits a ${currency} invoice with its recorded exchange rate`](../../tests/js/sale-note-invoice-submit.test.cjs#L65) → QA-MPA-008.
- [`conversion obtains a missing exchange rate and sends it without replacing it`](../../tests/js/sale-note-invoice-submit.test.cjs#L81) → QA-MPA-008.
- [`invalid exchange rate blocks conversion before saving or marking the note converted`](../../tests/js/sale-note-invoice-submit.test.cjs#L89) → QA-MPA-008.
- [`recorded receipts are shown read-only and newly added receipts expose currency and fiscal fields`](../../tests/js/sale-note-invoice-submit.test.cjs#L100) → QA-MPA-008.
- [`sale note conversion preserves recorded destinations absent from the active catalog`](../../tests/js/sale-note-invoice-submit.test.cjs#L118) → QA-MPA-008.
- [`sale note conversion cannot remove recorded receipts`](../../tests/js/sale-note-invoice-submit.test.cjs#L137) → QA-MPA-008.
- [`sale note conversion preserves receipts when changing to credit and suggests the outstanding balance`](../../tests/js/sale-note-invoice-submit.test.cjs#L152) → QA-MPA-014.
- [`sale note conversion sends credit receipts and keeps installments out of a cash invoice`](../../tests/js/sale-note-invoice-submit.test.cjs#L181) → QA-MPA-014.

## Índice de pruebas unitarias de cobros y reportes

Relación de cada método de prueba disponible con el caso más cercano y sus controles en la guía. La prueba unitaria es una capa separada; no implica que la ruta HTTP o el navegador se hayan ejecutado en esta corrida.

### CashPaymentLinkTest

- [`test_only_original_movements_from_the_current_session_are_linked`](../../tests/Unit/CashPaymentLinkTest.php#L19) → QA-MPA-010.
- [`test_legacy_movements_keep_the_existing_association_behavior`](../../tests/Unit/CashPaymentLinkTest.php#L28) → QA-MPA-010.

### ClassifiedCashWorkbookTest

- [`test_registry_selects_classified_financial_reports_and_product_builder`](../../tests/Unit/ClassifiedCashWorkbookTest.php#L14) → QA-MPA-020.
- [`test_workbook_separates_cash_and_bank_by_currency`](../../tests/Unit/ClassifiedCashWorkbookTest.php#L30) → QA-MPA-020.
- [`test_historical_groups_keep_names_classifications_and_destinations_separate`](../../tests/Unit/ClassifiedCashWorkbookTest.php#L63) → QA-MPA-020.
- [`test_financial_report_renders_the_historical_kind_beside_the_method`](../../tests/Unit/ClassifiedCashWorkbookTest.php#L82) → QA-MPA-020.
- [`test_filtered_cash_reports_group_only_their_visible_movements`](../../tests/Unit/ClassifiedCashWorkbookTest.php#L94) → QA-MPA-020.

### CreditConditionTest

- [`test_saved_condition_controls_classification_even_if_method_was_reconfigured`](../../tests/Unit/CreditConditionTest.php#L13) → QA-MPA-014.
- [`test_legacy_note_without_condition_uses_the_method_classification`](../../tests/Unit/CreditConditionTest.php#L27) → QA-MPA-014.

### DocumentCurrencyTotalsTest

- [`test_totals_stay_separate_and_round_only_after_exact_sum`](../../tests/Unit/DocumentCurrencyTotalsTest.php#L11) → QA-MPA-020.

### DocumentPaymentHistoricalReportTest

- [`test_invalid_historical_rates_remain_unavailable_for_the_dialog`](../../tests/Unit/DocumentPaymentHistoricalReportTest.php#L12) → QA-MPA-017.
- [`test_unavailable_original_amount_and_rate_are_not_reported_as_zero`](../../tests/Unit/DocumentPaymentHistoricalReportTest.php#L19) → QA-MPA-020.

### ExactFinanceCalculationTest

- [`test_cash_boundary_keeps_the_requested_value_and_exposes_the_adjustment`](../../tests/Unit/ExactFinanceCalculationTest.php#L11) → QA-MPA-010.
- [`test_report_totals_add_original_fractions_instead_of_formatted_rows`](../../tests/Unit/ExactFinanceCalculationTest.php#L20) → QA-MPA-020.
- [`test_account_balances_include_igtf_only_in_the_received_currency`](../../tests/Unit/ExactFinanceCalculationTest.php#L30) → QA-MPA-010.

### ExactReceivablesTest

- [`test_credit_expiry_checks_exact_balances_of_only_the_customer_and_requested_source`](../../tests/Unit/ExactReceivablesTest.php#L59) → QA-MPA-014.
- [`test_balances_match_documents_without_fake_cash_or_duplicate_reversals`](../../tests/Unit/ExactReceivablesTest.php#L75) → QA-MPA-010.
- [`test_batches_summary_pagination_currencies_and_thirds_use_the_same_exact_rows`](../../tests/Unit/ExactReceivablesTest.php#L98) → QA-MPA-020.
- [`test_positive_fraction_smaller_than_storage_projection_is_not_filtered_out`](../../tests/Unit/ExactReceivablesTest.php#L120) → QA-MPA-010.
- [`test_seller_scope_and_converted_sale_notes_remain_excluded`](../../tests/Unit/ExactReceivablesTest.php#L132) → QA-MPA-010.
- [`test_real_xlsx_export_has_text_amounts_composite_identity_and_original_fractions`](../../tests/Unit/ExactReceivablesTest.php#L141) → QA-MPA-020.
- [`test_pdf_displays_documentary_total_and_settled_balance_from_the_same_exact_rows`](../../tests/Unit/ExactReceivablesTest.php#L171) → QA-MPA-020.
- [`test_payables_pdf_shows_original_currency_and_historical_equivalents`](../../tests/Unit/ExactReceivablesTest.php#L195) → QA-MPA-020.

### ExactTenderConsumersTest

- [`test_shared_php_javascript_cases_conserve_actual_tender_and_nonnegative_payments`](../../tests/Unit/ExactTenderConsumersTest.php#L63) → QA-MPA-006.
- [`test_controller_persists_tender_change_and_adjustment_atomically_and_uses_payable_status`](../../tests/Unit/ExactTenderConsumersTest.php#L86) → QA-MPA-006.
- [`test_subprojection_debt_is_not_reported_as_paid_and_snapshot_is_reusable`](../../tests/Unit/ExactTenderConsumersTest.php#L111) → QA-MPA-014.
- [`test_failed_tender_does_not_insert_any_payment`](../../tests/Unit/ExactTenderConsumersTest.php#L123) → QA-MPA-007.
- [`test_batch_validates_methods_once_and_balance_get_does_not_write_or_use_stale_paid_flag`](../../tests/Unit/ExactTenderConsumersTest.php#L133) → QA-MPA-009.
- [`test_locked_method_batch_cannot_escape_its_callback_or_transaction`](../../tests/Unit/ExactTenderConsumersTest.php#L152) → QA-MPA-009.
- [`test_failure_after_an_insert_rolls_back_and_does_not_leave_settlement_deferred`](../../tests/Unit/ExactTenderConsumersTest.php#L173) → QA-MPA-009.
- [`test_payment_request_rejects_precision_range_nonpositive_and_binary_inputs`](../../tests/Unit/ExactTenderConsumersTest.php#L189) → QA-MPA-004.

### FiscalApiPaymentTransformTest

- [`test_legacy_api_payments_keep_document_currency_and_amount`](../../tests/Unit/FiscalApiPaymentTransformTest.php#L17) → QA-MPA-004.
- [`test_api_cannot_replace_a_missing_payment_rate_with_the_invoice_rate`](../../tests/Unit/FiscalApiPaymentTransformTest.php#L22) → QA-MPA-004.
- [`test_api_keeps_the_original_amount_rate_and_operation_key`](../../tests/Unit/FiscalApiPaymentTransformTest.php#L27) → QA-MPA-004.
- [`test_api_requires_an_operation_key_for_payments_with_their_own_currency`](../../tests/Unit/FiscalApiPaymentTransformTest.php#L33) → QA-MPA-004.
- [`test_api_preserves_source_receipt_id_and_original_date`](../../tests/Unit/FiscalApiPaymentTransformTest.php#L40) → QA-MPA-008.
- [`test_offline_web_payload_keeps_currency_rate_and_stable_operation_key`](../../tests/Unit/FiscalApiPaymentTransformTest.php#L53) → QA-MPA-004.

### InvoicePaymentPreviewCurrencyTest

- [`test_preview_converts_cash_change_in_the_received_currency`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L41) → QA-MPA-006.
- [`test_preview_rejects_bank_excess_even_when_its_id_was_originally_cash`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L60) → QA-MPA-007.
- [`test_preview_ignores_client_supplied_change`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L67) → QA-MPA-006.
- [`test_invoice_payment_collection_exposes_gross_tender_and_received_change`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L74) → QA-MPA-006.
- [`test_preview_rejects_change_not_representable_in_received_cents`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L90) → QA-MPA-006.
- [`test_sale_note_detail_uses_the_received_change_fraction_and_frozen_method`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L97) → QA-MPA-006.
- [`test_reused_receipts_are_identified_and_do_not_repeat_original_change`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L113) → QA-MPA-008.
- [`test_legacy_invoice_without_original_amount_does_not_invent_a_foreign_receipt`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L133) → QA-MPA-005.
- [`test_ticket_shows_credit_installments_and_frozen_receipt_without_fixed_method`](../../tests/Unit/InvoicePaymentPreviewCurrencyTest.php#L144) → QA-MPA-014.

### MobileMovementSummaryTest

- [`test_separates_received_money_and_keeps_exact_fractions`](../../tests/Unit/MobileMovementSummaryTest.php#L16) → QA-MPA-019.
- [`test_legacy_scalar_totals_exist_only_for_unambiguous_ves`](../../tests/Unit/MobileMovementSummaryTest.php#L29) → QA-MPA-019.

### MovementBalancesTest

- [`test_balances_do_not_mix_received_currencies_and_use_frozen_fractions`](../../tests/Unit/MovementBalancesTest.php#L12) → QA-MPA-010.
- [`test_invalid_currency_is_not_silently_combined`](../../tests/Unit/MovementBalancesTest.php#L26) → QA-MPA-010.
- [`test_presentation_rounds_only_after_the_exact_balance_is_complete`](../../tests/Unit/MovementBalancesTest.php#L32) → QA-MPA-010.

### PaymentWorkbookExportTest

- [`test_real_workbook_keeps_money_as_text_and_preserves_exact_fractions`](../../tests/Unit/PaymentWorkbookExportTest.php#L15) → QA-MPA-020.

### QuotationReceiptPdfTest

- [`test_cross_currency_receipt_displays_saved_amount_rate_and_method`](../../tests/Unit/QuotationReceiptPdfTest.php#L10) → QA-MPA-008.

### ReceiptPresentationTest

- [`test_first_method_omits_reversed_receipts_and_uses_frozen_description`](../../tests/Unit/ReceiptPresentationTest.php#L11) → QA-MPA-005.
- [`test_it_prefers_frozen_method_and_bank_destination`](../../tests/Unit/ReceiptPresentationTest.php#L21) → QA-MPA-005.
- [`test_it_uses_available_historical_fallbacks`](../../tests/Unit/ReceiptPresentationTest.php#L33) → QA-MPA-005.

### ReceivedPaymentCurrencyTest

- [`test_capture_rules_reject_invalid_currency_and_non_decimal_text`](../../tests/Unit/ReceivedPaymentCurrencyTest.php#L13) → QA-MPA-019.
- [`test_claim_keeps_the_captured_amount_in_the_explicit_received_currency`](../../tests/Unit/ReceivedPaymentCurrencyTest.php#L34) → QA-MPA-019.
- [`test_claim_uses_exact_captured_decimal_and_rejects_currency_override`](../../tests/Unit/ReceivedPaymentCurrencyTest.php#L59) → QA-MPA-019.

### TransferAccountAmountTest

- [`test_transfer_input_is_not_converted_to_float`](../../tests/Unit/TransferAccountAmountTest.php#L12) → QA-MPA-015.

### UnpaidPaymentMethodFilterTest

- [`test_filter_includes_custom_and_inactive_historical_methods`](../../tests/Unit/UnpaidPaymentMethodFilterTest.php#L13) → QA-MPA-005.

## Aceptación integrada HTTP

- [Kernel HTTP con sesión, CSRF, conversiones, pagos y MySQL temporal](../../tests/Unit/ExactHttpAcceptanceTest.php#L9) → QA-MPA-004, QA-MPA-005, QA-MPA-008, QA-MPA-011, QA-MPA-013, QA-MPA-016 y QA-MPA-020. La guía separa respuestas de negocio, rechazo por ruta, datos de origen y medición opcional de selección de partidas. El método contiene múltiples aserciones y es un antecedente de código, no evidencia de una ejecución nueva.

## Cruces con órdenes, retenciones y perfiles

- [Catálogo de medios de conversión de orden](../../tests/Support/DispatchPersistenceCases.php#L12) → QA-MPA-002 y QA-NVE-012.
- [Conversión de orden a factura a crédito con abono en otra moneda](../../tests/Support/DispatchPersistenceCases.php#L39) → QA-MPA-004, QA-MPA-014, QA-NVE-012 y QA-ODI. La prueba fuente usa `free_form`; la guía exige verificar por separado el entorno digital/DEMO.
- [Abono en otra moneda, retención IVA, saldo y reversión](../../tests/Support/IvaRetentionPersistenceCases.php#L60) → QA-MPA-014 y QA-RIVA; ver cálculo 232−50−24 y reversión en la guía.
- [Perfil SPE, cobro, retenciones y recuperación](../../tests/Support/SpePersistenceCases.php#L89) → QA-SPE, QA-MPA-004 y QA-INT; la asociación no acredita ejecución actual ni sustituye los casos de cada impuesto.

Dos filtros históricos de ese chat ya no coinciden con nombres de métodos en el árbol actual (`test_order_with_inclusive_price_repeating_tax_base` y `test_saved_installment_condition_preserves_cross_currency_receipt_through_conversion_and_pdf`). No se atribuye su ejecución a la nueva corrida; se conservan como antecedentes de búsqueda, y se verifican el precio/impuesto y la preservación de cuotas mediante los casos y fuentes vigentes.

## Filtros de integración del chat y casos relacionados

Los siguientes filtros se identificaron en comandos del chat y se vinculan a las pruebas vigentes relacionadas. Un filtro o una prueba histórica no demuestra una ejecución nueva; verificar estado y capa al ejecutar la guía.

- [`test_concurrent_payments_igtf_and_retention_applications_are_serialized`](../../tests/Unit/FiscalEmissionSchemaTest.php#L791) → QA-MPA-009 / QA-INT.
- [`test_cross_currency_credit_receipt_and_iva_retention_keep_balance_and_snapshot`](../../tests/Support/IvaRetentionPersistenceCases.php#L60) → QA-MPA-014 / QA-RIVA.
- [`test_dispatch_invoice_catalog_only_offers_active_receipt_methods`](../../tests/Support/DispatchPersistenceCases.php#L12) → QA-MPA-002 / QA-NVE-012.
- [`test_dispatch_invoice_credit_keeps_cross_currency_receipt_and_rejects_wrong_account`](../../tests/Support/DispatchPersistenceCases.php#L39) → QA-MPA-004 / QA-MPA-014 / QA-NVE-012.
- [`test_exact_calculations_survive_mysql_reads_grouping_discounts_and_rollback`](../../tests/Unit/FiscalEmissionSchemaTest.php#L1351) → QA-CAL / QA-MPA-009.
- [`test_fresh_tenant_migrations_seed_rollback_and_repeat`](../../tests/Unit/FiscalEmissionSchemaTest.php#L166) → QA-MPA-001.
- [`test_islr_concurrent_registration_collection_and_reversal`](../../tests/Support/IslrRetentionPersistenceCases.php#L181) → QA-RISLR / QA-INT.
- [`test_islr_retention_rejects_paid_balance_after_prior_transaction_read`](../../tests/Support/IslrRetentionPersistenceCases.php#L146) → QA-RISLR / QA-MPA-009.
- [`test_issuer_igtf_retentions_and_ves_totals_use_persisted_snapshots`](../../tests/Unit/CurrentPdfRenderingTest.php#L130) → QA-SPE / QA-INT / QA-PDF.
- [`test_iva_net_forecast_idempotence_hka_and_audited_replacement`](../../tests/Support/IvaRetentionPersistenceCases.php#L168) → QA-RIVA / QA-INT.
- [`test_iva_retention_rejects_paid_balance_after_prior_transaction_read`](../../tests/Support/IvaRetentionPersistenceCases.php#L104) → QA-RIVA / QA-MPA-009.
- [`test_note_concurrent_retention_corrections_preserve_uncertain_effects`](../../tests/Support/NotePersistenceCases.php#L310) → QA-NRF / QA-NRI.
- [`test_note_debit_is_a_separate_receivable_without_inventory`](../../tests/Support/NotePersistenceCases.php#L83) → QA-NOT / QA-NHD.
- [`test_note_pending_igtf_and_fund_adjustments_have_no_cash_effect`](../../tests/Support/NotePersistenceCases.php#L144) → QA-NRF / QA-NRI.
- [`test_note_refund_preserves_collections_and_reverses_once`](../../tests/Support/NotePersistenceCases.php#L221) → QA-NRF / QA-NRI.
- [`test_spe_real_collection_context_retentions_notes_freeze_and_recovery`](../../tests/Support/SpePersistenceCases.php#L89) → QA-SPE / QA-INT.

El filtro histórico `test_movement_export_marks_failed_when_generation_throws` ya no coincide con un método del árbol actual; revisar el caso vigente de exportación y fallo QA-MPA-020, sin atribuirle automáticamente el resultado histórico.

## Suites de regresión relacionadas citadas en el chat

Estas declaraciones proceden de suites más amplias citadas junto a las pruebas de pagos. Los grupos indicados son puntos de entrada relacionados: cotejar el detalle con el caso concreto antes de marcar cobertura. Las pruebas parametrizadas pueden cubrir varios canales. No constituyen resultados de la nueva corrida.

### document-edit-ui — QA-EDI-001 / QA-EDI-004

- [`editing submits commercial changes without resubmitting collections`](../../tests/js/document-edit-ui.test.cjs#L14).
- [`blocked edits stop before validation, HTTP or cash writes`](../../tests/js/document-edit-ui.test.cjs#L26).
- [`the editor displays existing collections and preserves currency and series controls`](../../tests/js/document-edit-ui.test.cjs#L31).

### exact-calculation — QA-CAL-001 / QA-CAL-017

- [`shared PHP and JavaScript cases produce the same normalized fractions`](../../tests/js/exact-calculation.test.cjs#L18).
- [`commercial totals retain all mixed IVA lines and do not compound global adjustments`](../../tests/js/exact-calculation.test.cjs#L26).
- [`price 500 and grouping survive exact totals and JSON round trips`](../../tests/js/exact-calculation.test.cjs#L40).
- [`warehouse selector fractions feed the first calculation and explicit price edits take precedence`](../../tests/js/exact-calculation.test.cjs#L52).
- [`fractions, large decimals and currency round trips are lossless`](../../tests/js/exact-calculation.test.cjs#L65).
- [`reopening lines restores original inputs for server recalculation`](../../tests/js/exact-calculation.test.cjs#L71).
- [`largest remainders conserve each cents target deterministically`](../../tests/js/exact-calculation.test.cjs#L87).
- [`a fractional global discount is preserved in persisted line snapshots`](../../tests/js/exact-calculation.test.cjs#L96).

### exact-note-conversion — QA-NVE-005 / QA-INT-021

- [`note modal transports the server groups without regrouping products or fetching current catalog prices`](../../tests/js/exact-note-conversion.test.cjs#L30).
- [`ungrouped note conversion restores every historical payment without changing it`](../../tests/js/exact-note-conversion.test.cjs#L61).
- [`a full browser storage leaves no partial currency or rows and does not open an invoice (grouped=${grouped})`](../../tests/js/exact-note-conversion.test.cjs#L80).
- [`${file} retains distinct prices, quantities and fractions from grouped note lines`](../../tests/js/exact-note-conversion.test.cjs#L106).
- [`${file} discards an exchange-rate reply started before note restoration`](../../tests/js/exact-note-conversion.test.cjs#L120).
- [`missing or malformed historical conversion context is not replaced with zero or a current rate`](../../tests/js/exact-note-conversion.test.cjs#L138).
- [`missing or malformed note receipts cannot partially restore a conversion`](../../tests/js/exact-note-conversion.test.cjs#L148).
- [`only the latest unchanged document rate request may apply`](../../tests/js/exact-note-conversion.test.cjs#L159).
- [`the grouped conversion shows a source validation error instead of silently opening another invoice`](../../tests/js/exact-note-conversion.test.cjs#L171).

### exact-tender-consumers — QA-MPA-006 / QA-MPA-007

- [`PHP and JavaScript tender fixtures conserve cash and reject fictitious change`](../../tests/js/exact-tender-consumers.test.cjs#L8).
- [`sale note totals format large decimal text without Number conversion`](../../tests/js/exact-tender-consumers.test.cjs#L26).
- [`payment inputs remove consecutive zero rows while retaining exact requested strings`](../../tests/js/exact-tender-consumers.test.cjs#L33).
- [`change follows classification even when familiar IDs are reassigned`](../../tests/js/exact-tender-consumers.test.cjs#L40).
- [`a prior cash receipt cannot finance change for a new bank collection`](../../tests/js/exact-tender-consumers.test.cjs#L48).
- [`sale note and invoice forms keep reused receipts and use historical classification`](../../tests/js/exact-tender-consumers.test.cjs#L57).
- [`sale note payment dialog uses fractions for residual debt and adjustment visibility`](../../tests/js/exact-tender-consumers.test.cjs#L76).

### export-invoice-ui — QA-EXP-001 / QA-EXP-014

- [`the operation selector receives the catalog loaded by the form in create and edit contexts`](../../tests/js/export-invoice-ui.test.cjs#L14).
- [`a registered invoice or a populated draft retains its sales category`](../../tests/js/export-invoice-ui.test.cjs#L31).
- [`export lookup selects foreign customers and discards an obsolete response`](../../tests/js/export-invoice-ui.test.cjs#L40).
- [`export header discount is counted once and never introduces IVA`](../../tests/js/export-invoice-ui.test.cjs#L48).
- [`an export discount above its base is rejected`](../../tests/js/export-invoice-ui.test.cjs#L54).
- [`export recalculation includes header charges once and updates payment totals after adjustments`](../../tests/js/export-invoice-ui.test.cjs#L58).
- [`loading an existing export preserves its rate, collections and object-serialized header adjustments`](../../tests/js/export-invoice-ui.test.cjs#L85).
- [`all affected forms compile their templates`](../../tests/js/export-invoice-ui.test.cjs#L110).

### fiscal-row — QA-CAL-001 / QA-CAL-004 / QA-CAL-006

- [`taxed line retains base, IVA and total`](../../tests/js/fiscal-row.test.cjs#L26).
- [`exempt quantity has zero IVA`](../../tests/js/fiscal-row.test.cjs#L37).
- [`configured IVA rate overrides the default`](../../tests/js/fiscal-row.test.cjs#L44).
- [`VES and USD conversion retains quantities and tax`](../../tests/js/fiscal-row.test.cjs#L51).
- [`a discount affecting the base reduces IVA proportionally`](../../tests/js/fiscal-row.test.cjs#L56).
- [`unknown affectations are never converted or calculated`](../../tests/js/fiscal-row.test.cjs#L66).
- [`export context calculates goods at zero IVA and preserves currency conversion`](../../tests/js/fiscal-row.test.cjs#L77).

### garage-series-selection — QA-NUM-013 / QA-CAN-005

- [`customer changes and repeated filtering preserve selected invoice series ${id} through payment`](../../tests/js/garage-series-selection.test.cjs#L54).
- [`switching document type replaces an incompatible series and preserves a compatible selection`](../../tests/js/garage-series-selection.test.cjs#L71).
- [`refreshing available series discards a removed selection and blocks payment when none remain`](../../tests/js/garage-series-selection.test.cjs#L84).
- [`payment panel template compiles without generating assets`](../../tests/js/garage-series-selection.test.cjs#L98).

### igtf-settings-ui — QA-IGT-001 / QA-IGT-002

- [`rows and overlapping dialogs share one request, while reopening refreshes the setting`](../../tests/js/igtf-settings-ui.test.cjs#L37).
- [`saving advanced settings wins over a delayed earlier read`](../../tests/js/igtf-settings-ui.test.cjs#L48).
- [`advanced configuration publishes only an acknowledged save to payment forms`](../../tests/js/igtf-settings-ui.test.cjs#L56).
- [`failed settings block new collections, support retry, and leave credit without payments usable`](../../tests/js/igtf-settings-ui.test.cjs#L66).
- [`disabled settings preserve and reject hidden draft tax while leaving historical receipts intact`](../../tests/js/igtf-settings-ui.test.cjs#L76).
- [`the rendered editor hides only IGTF controls, keeping currency and intermediation`](../../tests/js/igtf-settings-ui.test.cjs#L91).
- [`reopening refreshes the proposal even when the enabled switch has not changed`](../../tests/js/igtf-settings-ui.test.cjs#L109).
- [`disabled IGTF does not silently classify a payment as subject; enabled IGTF still uses the server proposal`](../../tests/js/igtf-settings-ui.test.cjs#L119).
- [`a same-currency amount stays current even while its proposal request is pending`](../../tests/js/igtf-settings-ui.test.cjs#L127).
- [`a reused receipt does not request a new fiscal proposal or change its historical amounts`](../../tests/js/igtf-settings-ui.test.cjs#L138).
- [`a pending fiscal proposal cannot change a row that becomes a reused receipt`](../../tests/js/igtf-settings-ui.test.cjs#L158).
- [`a proposal for another document or removed payment cannot change the current payment`](../../tests/js/igtf-settings-ui.test.cjs#L170).
- [`a changed document context invalidates a proposal before Vue flushes its watchers`](../../tests/js/igtf-settings-ui.test.cjs#L180).
- [`payment entry lifecycle refreshes settings and excludes sale notes and credit notes`](../../tests/js/igtf-settings-ui.test.cjs#L189).
- [`preflight prevents concurrent submissions and releases the form after a failed read`](../../tests/js/igtf-settings-ui.test.cjs#L198).
- [`a preflight completing after closing or replacing the checkout cannot submit a different sale`](../../tests/js/igtf-settings-ui.test.cjs#L208).
- [`later collections submit the entered fiscal context and preserve their draft on preflight failure`](../../tests/js/igtf-settings-ui.test.cjs#L219).
- [`historical receipt tax and derived receipts remain visible with IGTF disabled`](../../tests/js/igtf-settings-ui.test.cjs#L233).
- [`all supported entrypoints pass the shared flag and real payment context`](../../tests/js/igtf-settings-ui.test.cjs#L246).

### invoice-sale-note-unification — QA-ITM-017 / QA-CAL-017 / QA-NVE-005

- [`${file} unifies decimal quantities using the document currency and tax rate`](../../tests/js/invoice-sale-note-unification.test.cjs#L27).
- [`${file} keeps amount-derived and non-terminating quantities in separate rows`](../../tests/js/invoice-sale-note-unification.test.cjs#L38).
- [`${file} does not discard a second row with a distinct exact price or adjustment`](../../tests/js/invoice-sale-note-unification.test.cjs#L56).
- [`invoice barcode accumulation preserves the selected currency and actual quantity`](../../tests/js/invoice-sale-note-unification.test.cjs#L68).

### iva-retention-ui — QA-INT-009 / QA-INT-010

- [`reopening POS payments restores entered receipts instead of suggesting the gross total`](../../tests/js/iva-retention-ui.test.cjs#L12).
- [`garage refresh preserves one receipt and multiple receipts when the collection target changes`](../../tests/js/iva-retention-ui.test.cjs#L28).
- [`voucher validation details remain visible for both Web and API error envelopes`](../../tests/js/iva-retention-ui.test.cjs#L39).
- [`adding a method in order invoicing preserves real collections and suggests only the remaining total`](../../tests/js/iva-retention-ui.test.cjs#L47).
- [`all creation and collection entries compile with retentions managed after creation`](../../tests/js/iva-retention-ui.test.cjs#L53).

### pos-hka-email — QA-COR-002

- [`both POS payment panels expose the same dialog and shared HKA behavior`](../../tests/js/pos-hka-email.test.cjs#L21).
- [`POS sends one request with UUID, blocks double clicks and offers explicit resend`](../../tests/js/pos-hka-email.test.cjs#L28).
- [`unconfirmed, pending and uncertain mail never sends; failures preserve the invoice and request`](../../tests/js/pos-hka-email.test.cjs#L35).
- [`POS loads persisted pending attempts and queries tracking without changing fiscal state`](../../tests/js/pos-hka-email.test.cjs#L40).
- [`failure to create an attempt UUID releases the send button and explains the failure`](../../tests/js/pos-hka-email.test.cjs#L45).
- [`request validation errors cannot silently disappear in the POS dialog`](../../tests/js/pos-hka-email.test.cjs#L50).
- [`flat Pro9 AJAX validation maps the recipient error to the visible email field`](../../tests/js/pos-hka-email.test.cjs#L54).
- [`garage initial amount waits for rendering and tolerates an absent input`](../../tests/js/pos-hka-email.test.cjs#L58).
- [`${file} tolerates missing optional configuration after the sale is saved`](../../tests/js/pos-hka-email.test.cjs#L66).
- [`${file} generates a paid invoice and opens options even when HKA is uncertain`](../../tests/js/pos-hka-email.test.cjs#L75).
- [`${file} handles loss of the creation response without another sale`](../../tests/js/pos-hka-email.test.cjs#L87).
- [`POS credit clears suggested cash and keeps partial real collections enabled`](../../tests/js/pos-hka-email.test.cjs#L95).
- [`POS does not treat a reclassified method 09 as credit`](../../tests/js/pos-hka-email.test.cjs#L106).
- [`${file} preserves credit when reopening payments and changing real amounts`](../../tests/js/pos-hka-email.test.cjs#L116).
- [`${file} opens unpaid credit without inventing payments and preserves real partial amounts`](../../tests/js/pos-hka-email.test.cjs#L128).
- [`${file} defaults to the current classification and a compatible destination`](../../tests/js/pos-hka-email.test.cjs#L138).
- [`${file} initializes the default using the document currency`](../../tests/js/pos-hka-email.test.cjs#L148).
- [`${file} waits for the payment catalog before initializing`](../../tests/js/pos-hka-email.test.cjs#L160).
- [`${file} restores credit amounts from actual draft receipts instead of the invoice total`](../../tests/js/pos-hka-email.test.cjs#L170).
- [`${file} updates its balance when a currency component changes the applied receipt`](../../tests/js/pos-hka-email.test.cjs#L181).
- [`POS does not describe bank or unclassified excess as cash change`](../../tests/js/pos-hka-email.test.cjs#L216).
- [`POS previews exact cash change in each received currency in server allocation order`](../../tests/js/pos-hka-email.test.cjs#L230).
- [`POS never previews change from a previously recorded or reused cash receipt`](../../tests/js/pos-hka-email.test.cjs#L246).
- [`Garage restores multiple decimal receipts without floating point drift`](../../tests/js/pos-hka-email.test.cjs#L260).
- [`${file} preserves registered and reused receipts in the payment dialog`](../../tests/js/pos-hka-email.test.cjs#L271).
- [`${panel} restores an origin-only receipt into the multiple-payment dialog`](../../tests/js/pos-hka-email.test.cjs#L294).
- [`${file} protects multiple or recorded receipts while accepting a restored single amount`](../../tests/js/pos-hka-email.test.cjs#L321).

### received-retention-list — QA-INT-009 / QA-RIVA-001 / QA-NRF-024

- [`creation and confirmation screens expose no retention editor, catalogue or proposal requests`](../../tests/js/received-retention-list.test.cjs#L42).
- [`creation sanitization excludes old retentions without changing items, receipts or source data`](../../tests/js/received-retention-list.test.cjs#L56).
- [`POS and Garage restore sanitized drafts and persist them without resetting real receipts`](../../tests/js/received-retention-list.test.cjs#L65).
- [`adding a receipt suggests the exact remaining amount without redistributing previous collections`](../../tests/js/received-retention-list.test.cjs#L82).
- [`invoice and POS multiple-payment consumers preserve real receipts when adding another method`](../../tests/js/received-retention-list.test.cjs#L104).
- [`the invoice add-payment action suggests only the remaining amount rather than the full total`](../../tests/js/received-retention-list.test.cjs#L113).
- [`other creation modules suggest only their outstanding total when another payment is added`](../../tests/js/received-retention-list.test.cjs#L123).
- [`the manager requires a saved invoice and obtains both tax histories and action permissions`](../../tests/js/received-retention-list.test.cjs#L135).
- [`both tax types register against an existing invoice and upload only linked private support`](../../tests/js/received-retention-list.test.cjs#L148).
- [`closing the manager prevents late proposals and uploads from changing its state`](../../tests/js/received-retention-list.test.cjs#L162).
- [`note retention corrections require the list context while other adjustments remain available`](../../tests/js/received-retention-list.test.cjs#L172).

### special-tax-regime-ui — QA-RGE-001 / QA-RGE-030 / QA-RGE-038

- [`regime availability requires a domestic invoice and digital DEMO`](../../tests/js/special-tax-regime-ui.test.cjs#L24).
- [`selection keeps product IVA and money unchanged; credit refuses the regime`](../../tests/js/special-tax-regime-ui.test.cjs#L31).
- [`export and itinerant switching require removing the selected regime first`](../../tests/js/special-tax-regime-ui.test.cjs#L38).
- [`excluded or credit submissions stop before any commercial request`](../../tests/js/special-tax-regime-ui.test.cjs#L44).
- [`editable regime submits only its ID and preserves the existing payments`](../../tests/js/special-tax-regime-ui.test.cjs#L49).
- [`regime switch reveals the field without selecting a regime and clears it when disabled`](../../tests/js/special-tax-regime-ui.test.cjs#L57).
- [`fiscal switches live in additional information while enabled fields remain in the header`](../../tests/js/special-tax-regime-ui.test.cjs#L70).
- [`loading existing fiscal options restores both switches and retains historical options`](../../tests/js/special-tax-regime-ui.test.cjs#L80).

### venezuela-unit-quantity — QA-ITM-017 / QA-MPA-022

- [`continuous quantities use the Venezuelan IDs, not their HKA equivalents`](../../tests/js/venezuela-unit-quantity.test.cjs#L11).
- [`invoice item step respects the local unit`](../../tests/js/venezuela-unit-quantity.test.cjs#L19).
- [`item editor and list present the local unit codes`](../../tests/js/venezuela-unit-quantity.test.cjs#L30).

Las rutas históricas `tests/js/commercial-credit.test.cjs`, `tests/js/credit-condition.test.cjs` y `tests/js/credit-payment-condition.test.cjs` fueron mencionadas en comandos del chat, pero no existen con esos nombres en el árbol actual. No se atribuyen automáticamente sus resultados a las suites actuales; verificar comportamiento mediante los casos de crédito vigentes.
