Se registraron **98 casos con incidencias** en la suite principal de PHP. Se conservan las incidencias originales y el resultado del contraste aislado cuando existe.

Los mensajes completos, diferencias y trazas están en [errores-pruebas-php.json](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/errores-pruebas-php.json). El resultado completo de las 1.748 pruebas está en [phpunit-completo.xml](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/phpunit-completo.xml).

1. `Tests\Unit\BankPaymentMysqlTest::test_fresh_tenant_migrations_seed_rollback_and_repeat` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [FiscalEmissionSchemaTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/FiscalEmissionSchemaTest.php:253).

2. `Tests\Unit\BankPaymentMysqlTest::test_free_form_invoices_credit_and_debit_match_cash_products_and_local_pdfs` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:517).

3. `Tests\Unit\BankPaymentMysqlTest::test_note_iva_replacement_preserves_real_vouchers_without_fake_cash` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:1058).

4. `Tests\Unit\BankPaymentMysqlTest::test_note_concurrent_retention_corrections_preserve_uncertain_effects` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:1174).

5. `Tests\Unit\BankPaymentMysqlTest::test_iva_retention_rejects_paid_balance_after_prior_transaction_read` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:148).

6. `Tests\Unit\BankPaymentMysqlTest::test_iva_net_forecast_idempotence_hka_and_audited_replacement` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:212).

7. `Tests\Unit\BankPaymentMysqlTest::test_iva_voucher_staged_during_creation_consumes_upload_only_after_commit` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:255).

8. `Tests\Unit\BankPaymentMysqlTest::test_iva_ambiguous_write_removal_conflicts_and_definitive_rejections` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:286).

9. `Tests\Unit\BankPaymentMysqlTest::test_iva_staged_reference_mismatch_permissions_and_expired_support` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:340).

10. `Tests\Unit\BankPaymentMysqlTest::test_iva_two_processes_register_one_active_voucher` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:376).

11. `Tests\Unit\BankPaymentMysqlTest::test_iva_fiscal_ves_is_independent_from_usd_settlement_rate` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:410).

12. `Tests\Unit\BankPaymentMysqlTest::test_free_form_cash_collection_respects_both_retention_forecasts` — fallo de aserción.

    Failed asserting that an array has the key 'payments'.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:11).

13. `Tests\Unit\BankPaymentMysqlTest::test_free_form_received_iva_and_islr_wait_for_verified_control_and_restore_balance` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:88).

14. `Tests\Unit\BankPaymentMysqlTest::test_free_form_control_requires_admin_unique_real_reference_and_rolls_back` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:186).

15. `Tests\Unit\BankPaymentMysqlTest::test_free_form_wrong_control_invalidates_voucher_without_settling` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:231).

16. `Tests\Unit\BankPaymentMysqlTest::test_islr_coexists_with_iva_idempotence_replacement_cash_and_hka` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:260).

17. `Tests\Unit\BankPaymentMysqlTest::test_islr_pending_support_reference_rollback_and_recovery` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:281).

18. `Tests\Unit\BankPaymentMysqlTest::test_islr_different_rates_declared_authority_and_net_capability` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:297).

19. `Tests\Unit\BankPaymentMysqlTest::test_islr_voucher_creation_upload_scope_and_activation_savepoint` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:310).

20. `Tests\Unit\BankPaymentMysqlTest::test_islr_hka_rejection_foreign_conflict_and_adopted_reversal` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:331).

21. `Tests\Unit\BankPaymentMysqlTest::test_islr_retention_rejects_paid_balance_after_prior_transaction_read` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:352).

22. `Tests\Unit\BankPaymentMysqlTest::test_islr_concurrent_registration_collection_and_reversal` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:387).

23. `Tests\Unit\BankPaymentMysqlTest::test_api_invoice_reuses_sale_note_receipt_and_links_source with data set "digital"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:1108).

24. `Tests\Unit\BankPaymentMysqlTest::test_api_invoice_reuses_sale_note_receipt_and_links_source with data set "forma libre"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:1108).

25. `Tests\Unit\BankPaymentMysqlTest::test_simultaneous_sale_note_receipts_keep_current_settlement_state` — fallo de aserción.

    No se encontró el contenido esperado: "OP. GRAVADA".

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:1719).

26. `Tests\Unit\BankPaymentMysqlTest::test_sale_note_receipt_deletion_and_invoice_reuse_are_serialized` — fallo de aserción.

    Failed asserting that 0 is identical to 1.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5476).

27. `Tests\Unit\BankPaymentMysqlTest::test_concurrent_invoices_cannot_reuse_an_unpaid_sale_note` — fallo de aserción.

    Failed asserting that 0 is identical to 1.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5635).

28. `Tests\Unit\BankPaymentMysqlTest::test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income with data set "digital"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5692).

29. `Tests\Unit\BankPaymentMysqlTest::test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income with data set "forma libre"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5692).

30. `Tests\Unit\BankPaymentMysqlTest::test_purchase_update_preserves_recorded_payment_after_reclassification_mysql` — error de ejecución.

    Indique un valor decimal válido.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:9231).

31. `Tests\Unit\BankPaymentMysqlTest::test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income` — fallo de aserción.

    Failed asserting that an array has the key 'source_sale_note_payment_id'.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:10903).

32. `Tests\Unit\BankPaymentMysqlTest::test_saved_credit_installments_preserve_cross_currency_receipt_through_conversion_and_pdf` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:11144).

33. `Tests\Unit\ExactMySqlContinuationTest::test_tender_credit_check_and_reversal_use_the_same_mysql_fractions` — error de ejecución.

    La forma de pago seleccionada no está disponible.

    Prueba: [ExactMySqlContinuationTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/ExactMySqlContinuationTest.php:13).

34. `Tests\Unit\ExactTenderConsumersTest::test_controller_persists_tender_change_and_adjustment_atomically_and_uses_payable_status` — error de ejecución.

    El método no tiene una cuenta bancaria configurada.

    Prueba: [ExactTenderConsumersTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/ExactTenderConsumersTest.php:86).

35. `Tests\Unit\FiscalEmissionSchemaTest::test_fresh_tenant_migrations_seed_rollback_and_repeat` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [FiscalEmissionSchemaTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/FiscalEmissionSchemaTest.php:253).

36. `Tests\Unit\FiscalEmissionSchemaTest::test_delivery_order_reports_keep_fractional_quantities_and_frozen_units` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [DispatchPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/DispatchPersistenceCases.php:173). Contraste aislado: **aprobada**.

37. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_full_credit_restores_fractional_invoice_lots` — error de ejecución.

    Indique un valor decimal válido.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:129). Contraste aislado: **aprobada**.

38. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_note_pdf_formats_show_frozen_lots_and_amounts` — error de ejecución.

    Indique un valor decimal válido.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:235). Contraste aislado: **aprobada**.

39. `Tests\Unit\FiscalEmissionSchemaTest::test_contingency_listing_matches_series_branch_type_and_user_scope` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:363). Contraste aislado: **aprobada**.

40. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_invoices_credit_and_debit_match_cash_products_and_local_pdfs` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:517).

41. `Tests\Unit\FiscalEmissionSchemaTest::test_note_iva_replacement_preserves_real_vouchers_without_fake_cash` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:1058).

42. `Tests\Unit\FiscalEmissionSchemaTest::test_note_concurrent_retention_corrections_preserve_uncertain_effects` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [NotePersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/NotePersistenceCases.php:1174).

43. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_retention_rejects_paid_balance_after_prior_transaction_read` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:148).

44. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_net_forecast_idempotence_hka_and_audited_replacement` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:212).

45. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_voucher_staged_during_creation_consumes_upload_only_after_commit` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:255).

46. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_ambiguous_write_removal_conflicts_and_definitive_rejections` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:286).

47. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_staged_reference_mismatch_permissions_and_expired_support` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:340).

48. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_two_processes_register_one_active_voucher` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:376).

49. `Tests\Unit\FiscalEmissionSchemaTest::test_iva_fiscal_ves_is_independent_from_usd_settlement_rate` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:410).

50. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_cash_collection_respects_both_retention_forecasts` — fallo de aserción.

    Failed asserting that an array has the key 'payments'.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:11).

51. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_received_iva_and_islr_wait_for_verified_control_and_restore_balance` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:88).

52. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_control_requires_admin_unique_real_reference_and_rolls_back` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:186).

53. `Tests\Unit\FiscalEmissionSchemaTest::test_free_form_wrong_control_invalidates_voucher_without_settling` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:231).

54. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_coexists_with_iva_idempotence_replacement_cash_and_hka` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:260).

55. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_pending_support_reference_rollback_and_recovery` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:281).

56. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_different_rates_declared_authority_and_net_capability` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:297).

57. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_voucher_creation_upload_scope_and_activation_savepoint` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:310).

58. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_hka_rejection_foreign_conflict_and_adopted_reversal` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:331).

59. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_retention_rejects_paid_balance_after_prior_transaction_read` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:352).

60. `Tests\Unit\FiscalEmissionSchemaTest::test_islr_concurrent_registration_collection_and_reversal` — error de ejecución.

    La intermediación debe coincidir con la naturaleza del método de pago seleccionado.

    Prueba: [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:387).

61. `Tests\Unit\FiscalEmissionSchemaTest::test_api_invoice_reuses_sale_note_receipt_and_links_source with data set "digital"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:1108).

62. `Tests\Unit\FiscalEmissionSchemaTest::test_api_invoice_reuses_sale_note_receipt_and_links_source with data set "forma libre"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:1108).

63. `Tests\Unit\FiscalEmissionSchemaTest::test_simultaneous_sale_note_receipts_keep_current_settlement_state` — fallo de aserción.

    No se encontró el contenido esperado: "OP. GRAVADA".

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:1719).

64. `Tests\Unit\FiscalEmissionSchemaTest::test_invoice_rejects_unavailable_or_foreign_lot_and_preserves_stock` — fallo de aserción.

    Failed asserting that 'indique un valor decimal válido.' contains "lote".

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:3301). Contraste aislado: **aprobada**.

65. `Tests\Unit\FiscalEmissionSchemaTest::test_hotel_occupancy_report_preserves_dates_people_and_recorded_prices` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:3795). Contraste aislado: **aprobada**.

66. `Tests\Unit\FiscalEmissionSchemaTest::test_hotel_guest_report_scopes_users_and_preserves_original_document_amounts` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:3863). Contraste aislado: **aprobada**.

67. `Tests\Unit\FiscalEmissionSchemaTest::test_fixed_asset_purchase_report_matches_fractional_rows_in_all_outputs` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:4021). Contraste aislado: **aprobada**.

68. `Tests\Unit\FiscalEmissionSchemaTest::test_sales_report_listing_uses_one_consistent_read_when_items_change` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:4146). Contraste aislado: **aprobada**.

69. `Tests\Unit\FiscalEmissionSchemaTest::test_document_list_and_totals_share_one_uncached_snapshot` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:4577). Contraste aislado: **aprobada**.

70. `Tests\Unit\FiscalEmissionSchemaTest::test_customer_report_keeps_notes_currencies_and_filters_in_list_and_excel` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:4797). Contraste aislado: **aprobada**.

71. `Tests\Unit\FiscalEmissionSchemaTest::test_sale_note_report_projects_fractional_amounts_in_pages_pdf_and_excel` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:4900). Contraste aislado: **aprobada**.

72. `Tests\Unit\FiscalEmissionSchemaTest::test_quotation_report_uses_exact_snapshot_and_zeroes_voids_in_all_outputs` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:4971). Contraste aislado: **aprobada**.

73. `Tests\Unit\FiscalEmissionSchemaTest::test_sale_note_receipt_deletion_and_invoice_reuse_are_serialized` — fallo de aserción.

    Failed asserting that 0 is identical to 1.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5476).

74. `Tests\Unit\FiscalEmissionSchemaTest::test_concurrent_invoices_cannot_reuse_an_unpaid_sale_note` — fallo de aserción.

    Failed asserting that 0 is identical to 1.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5635).

75. `Tests\Unit\FiscalEmissionSchemaTest::test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income with data set "digital"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5692).

76. `Tests\Unit\FiscalEmissionSchemaTest::test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income with data set "forma libre"` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:5692).

77. `Tests\Unit\FiscalEmissionSchemaTest::test_order_consolidated_allocates_header_and_keeps_exact_usd_prices` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:6774). Contraste aislado: **aprobada**.

78. `Tests\Unit\FiscalEmissionSchemaTest::test_user_commission_uses_exact_profit_and_marks_cost_as_estimated` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:6962). Contraste aislado: **aprobada**.

79. `Tests\Unit\FiscalEmissionSchemaTest::test_purchase_update_preserves_recorded_payment_after_reclassification_mysql` — error de ejecución.

    Indique un valor decimal válido.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:9231). Contraste aislado: **error de ejecución**.

80. `Tests\Unit\FiscalEmissionSchemaTest::test_sales_commission_separates_currencies_exactly_and_never_invents_fixed_currency` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:9361). Contraste aislado: **aprobada**.

81. `Tests\Unit\FiscalEmissionSchemaTest::test_pending_commission_keeps_currency_period_reversals_and_unknown_fixed_amount` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:9499). Contraste aislado: **aprobada**.

82. `Tests\Unit\FiscalEmissionSchemaTest::test_purchase_report_preserves_customer_and_exact_totals_in_pdf_and_excel` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:9621). Contraste aislado: **aprobada**.

83. `Tests\Unit\FiscalEmissionSchemaTest::test_purchase_items_api_reports_document_currency_and_exact_fractions` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:9734). Contraste aislado: **aprobada**.

84. `Tests\Unit\FiscalEmissionSchemaTest::test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:10237). Contraste aislado: **aprobada**.

85. `Tests\Unit\FiscalEmissionSchemaTest::test_opened_cash_is_returned_by_the_paginated_cash_listing` — error de ejecución.

    Target class [request] does not exist.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:10705). Contraste aislado: **aprobada**.

86. `Tests\Unit\FiscalEmissionSchemaTest::test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income` — fallo de aserción.

    Failed asserting that an array has the key 'source_sale_note_payment_id'.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:10903).

87. `Tests\Unit\FiscalEmissionSchemaTest::test_saved_credit_installments_preserve_cross_currency_receipt_through_conversion_and_pdf` — error de ejecución.

    No se pueden verificar los movimientos de inventario de la nota de venta.

    Prueba: [PaymentClassificationPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/PaymentClassificationPersistenceCases.php:11144).

88. `Tests\Unit\HkaIvaRetentionPayloadTest::test_mixed_iva_rates_keep_the_full_taxable_base_and_reject_an_ambiguous_partial_base` — error de ejecución.

    Database connection [tenant] not configured.

    Prueba: [HkaIvaRetentionPayloadTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/HkaIvaRetentionPayloadTest.php:11).

89. `Tests\Unit\HotelRentProductsTemplateContractTest::rent_product_totals_use_a_complete_table_row_without_nested_html_comments_or_iva` — fallo de aserción.

    &lt;strong&gt;{{ this.form.subtotal | toDecimals }}&lt;/strong&gt; Failed asserting that false is not false.

    Prueba: [HotelRentProductsTemplateContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/HotelRentProductsTemplateContractTest.php:10).

90. `Tests\Unit\InvoiceTotalsPaymentsVisibilityContractTest::invoice_creation_keeps_totals_and_payment_methods_visible` — fallo de aserción.

    No se encontró el contenido esperado: "data-testid="invoice-totals-payments"".

    Prueba: [InvoiceTotalsPaymentsVisibilityContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/InvoiceTotalsPaymentsVisibilityContractTest.php:10).

91. `Tests\Unit\LocalFiscalDocumentPolicyTest::test_invoice_email_reads_and_attaches_only_the_pdf` — error de ejecución.

    App\Services\Fiscal\CurrentFreeFormPdf::bytesIfRegistered(): Argument #1 ($document) must be of type App\Models\Tenant\Document, stdClass given, called in /home/benahia/benahia/pro/Pro9/app/Mail/Tenant/DocumentEmail.php on line 34

    Prueba: [LocalFiscalDocumentPolicyTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/LocalFiscalDocumentPolicyTest.php:48).

92. `Tests\Unit\PosCashDenominationsContractTest::main_pos_payment_keeps_manual_cash_entry_without_quick_denominations` — fallo de aserción.

    No se encontró el contenido esperado: "v-text="isMissingAmount ? 'Faltante' : 'Vuelto'"".

    Prueba: [PosCashDenominationsContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/PosCashDenominationsContractTest.php:11).

93. `Tests\Unit\QuotationPdfAmountsTest::test_every_quotation_template_renders_exact_balance_and_rounding` — fallo de aserción.

    Failed asserting that actual size 13 matches expected size 12.

    Prueba: [QuotationPdfAmountsTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/QuotationPdfAmountsTest.php:47).

94. `Tests\Unit\TenantMigrationDataSeederTest::every_application_table_has_one_consolidated_migration` — fallo de aserción.

    Failed asserting that actual size 350 matches expected size 349.

    Prueba: [TenantMigrationDataSeederTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/TenantMigrationDataSeederTest.php:36).

95. `Tests\Unit\TenantMigrationOrderContractTest::test_all_tables_precede_the_final_foreign_keys` — fallo de aserción.

    Failed asserting that two strings are identical.

    Prueba: [TenantMigrationOrderContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/TenantMigrationOrderContractTest.php:10).

96. `Tests\Unit\VenezuelaInitialCatalogContractTest::tenant_payment_methods_match_the_venezuelan_contract` — fallo de aserción.

    Failed asserting that two arrays are identical.

    Prueba: [VenezuelaInitialCatalogContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/VenezuelaInitialCatalogContractTest.php:269).

97. `Tests\Unit\VenezuelaIvaContractTest::current_operation_structure_prepares_and_persists_sixteen_percent_iva` — fallo de aserción.

    No se encontró el contenido esperado: "Localization::taxPercentage()".

    Prueba: [VenezuelaIvaContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/VenezuelaIvaContractTest.php:81).

98. `Tests\Unit\VenezuelaSourceContractTest::document_list_and_purchase_selector_use_catalog_symbols_and_ves` — fallo de aserción.

    No se encontró el contenido esperado: "Localization::currencySymbol($currencyTypeId)".

    Prueba: [VenezuelaSourceContractTest.php](/home/benahia/benahia/pro/Pro9/tests/Unit/VenezuelaSourceContractTest.php:105).

