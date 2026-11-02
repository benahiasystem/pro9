# Aserciones identificadas en pruebas de pagos

Extracción de apoyo para contrastar los casos funcionales con sus fuentes. Las aserciones son código histórico, no resultados de la nueva corrida. Leer el cuerpo completo de cada prueba antes de concluir cobertura.

### `test_loan_form_request_rejects_unclassified_cash_inactive_and_missing_destination`

Fuente: [tests/Feature/PaymentClassificationTest.php:54](../../tests/Feature/PaymentClassificationTest.php#L54).

- L63: `$this->postJson('/test-loan-validation',$input)->assertOk();`
- L64: `$this->postJson('/test-loan-validation',array_replace($input,['payment_method_type_id'=>'04']))->assertStatus(422)->assertJsonStructure(['message'=>['payment_method_type_id']]);`
- L65: `$this->postJson('/test-loan-validation',array_replace($input,['payment_destination_id'=>null,'expense_method_type_id'=>'1']))->assertStatus(422)->assertJsonStructure(['message'=>['payment_destination_id']]);`
- L67: `$this->postJson('/test-loan-validation',$input)->assertStatus(422)->assertJsonStructure(['message'=>['payment_method_type_id']]);`

### `test_income_receipt_reports_received_currency_instead_of_applied_amount`

Fuente: [tests/Feature/PaymentClassificationTest.php:70](../../tests/Feature/PaymentClassificationTest.php#L70).

- L82: `self::assertSame('4000', $payment->payment);`
- L85: `self::assertSame('USD', Movement::row($global)['currency_type_id']);`
- L86: `self::assertSame('10', Movement::row($global)['amount']);`

### `test_new_receipt_rejects_an_inactive_method_or_currency`

Fuente: [tests/Feature/PaymentClassificationTest.php:89](../../tests/Feature/PaymentClassificationTest.php#L89).

- L98: `} catch (ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L104: `} catch (ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`

### `test_hotel_catalog_exposes_active_classification_and_currency`

Fuente: [tests/Feature/PaymentClassificationTest.php:107](../../tests/Feature/PaymentClassificationTest.php#L107).

- L114: `self::assertTrue($methods->get('04')->is_active);`
- L115: `self::assertSame('cash',$methods->get('04')->collection_kind);`
- L116: `self::assertSame('VES',$methods->get('04')->currency_type_id);`
- L117: `self::assertSame('account',$methods->get('01')->collection_kind);`
- L118: `self::assertFalse($methods->has('10'));`

### `test_subscription_credit_catalog_tracks_active_classification`

Fuente: [tests/Feature/PaymentClassificationTest.php:121](../../tests/Feature/PaymentClassificationTest.php#L121).

- L128: `self::assertContains('01',$ids);`
- L129: `self::assertNotContains('09',$ids);`
- L130: `self::assertNotContains('05',$ids);`

### `test_physical_balances_exclude_electronic_money_and_keep_currencies_separate`

Fuente: [tests/Feature/PaymentClassificationTest.php:142](../../tests/Feature/PaymentClassificationTest.php#L142).

- L149: `self::assertSame($cash->id,$electronic->cash_session_id);`
- L150: `self::assertSame('account',$electronic->report_context['method']['collection_kind']);`
- L154: `self::assertSame('120',$balances['VES']['final_balance']);self::assertSame('13',$balances['USD']['final_balance']);`
- L156: `self::assertSame('Pago Móvil',Movement::row($electronic->fresh())['method']['description']);`
- L157: `self::assertSame('50',Movement::row($physical)['amount']);`

### `test_account_currency_and_closed_cash_reject_the_entire_operation`

Fuente: [tests/Feature/PaymentClassificationTest.php:160](../../tests/Feature/PaymentClassificationTest.php#L160).

- L170: `self::assertArrayHasKey('currency_type_id', $expected->errors());`
- L174: `self::assertSame(0,DB::connection('tenant')->table('income_payments')->count());self::assertSame(0,DB::connection('tenant')->table('global_payments')->count());`

### `test_credit_and_unclassified_methods_do_not_create_receipts`

Fuente: [tests/Feature/PaymentClassificationTest.php:177](../../tests/Feature/PaymentClassificationTest.php#L177).

- L182: `self::assertSame(0,DB::connection('tenant')->table('income_payments')->count());`

### `test_flags_are_derived_and_unclassified_active_methods_are_rejected`

Fuente: [tests/Feature/PaymentClassificationTest.php:185](../../tests/Feature/PaymentClassificationTest.php#L185).

- L188: `self::assertFalse($method->fresh()->is_cash);self::assertFalse($method->fresh()->is_credit);`

### `test_new_installation_enforces_one_open_cash_without_activation`

Fuente: [tests/Feature/PaymentClassificationTest.php:193](../../tests/Feature/PaymentClassificationTest.php#L193).

- L195: `self::assertFalse(Schema::connection('tenant')->hasTable('payment_reporting_settings'));`
- L196: `self::assertFalse(Schema::connection('tenant')->hasColumn('cash','report_version'));`
- L199: `catch (ValidationException $error) { self::assertArrayHasKey('cash',$error->errors()); }`
- L201: `self::assertTrue($this->cash()->state);`
- L202: `self::assertFalse(PaymentMethodType::findOrFail('11')->is_cash);`

### `test_rational_sums_and_opening_are_counted_once`

Fuente: [tests/Feature/PaymentClassificationTest.php:205](../../tests/Feature/PaymentClassificationTest.php#L205).

- L209: `$summary=CashLedger::summarize($rows,['VES'=>'100']);self::assertSame('101',$summary['VES']['final_balance']);`

### `test_actual_receipt_and_igtf_are_separate_from_documentary_principal`

Fuente: [tests/Feature/PaymentClassificationTest.php:212](../../tests/Feature/PaymentClassificationTest.php#L212).

- L223: `self::assertSame('1.03',$row['amount']);self::assertSame('1',$row['principal']);self::assertSame('0.03',$row['tax']);`
- L224: `$payment->receipt_parent_id=1;self::assertNull(Movement::row($global));`
- L225: `$payment->receipt_parent_id=null;$payment->reversed_at='2026-10-26';self::assertNull(Movement::row($global));`

### `test_reclassifying_an_initial_method_changes_only_future_movements`

Fuente: [tests/Feature/PaymentClassificationTest.php:228](../../tests/Feature/PaymentClassificationTest.php#L228).

- L234: `self::assertSame('account',Movement::row($global->fresh())['method']['collection_kind']);`
- L235: `self::assertSame('0',CashLedger::summarize(CashLedger::rows($cash),[])['VES']['income']);`

### `test_cash_filter_uses_recorded_classification_after_method_is_reclassified`

Fuente: [tests/Feature/PaymentClassificationTest.php:240](../../tests/Feature/PaymentClassificationTest.php#L240).

- L249: `self::assertContains($oldCash->payment_id, $ids);`
- L250: `self::assertNotContains($oldBank->payment_id, $ids);`
- L251: `self::assertSame('cash', Movement::row($oldCash->fresh())['method']['collection_kind']);`
- L252: `self::assertSame('account', Movement::row($oldBank->fresh())['method']['collection_kind']);`
- L253: `self::assertSame('cash', Classification::recordedKind($oldCash->payment));`
- L254: `self::assertSame('account', Classification::recordedKind($oldBank->payment));`
- L257: `self::assertNull(Classification::recordedKind($unknown));`

### `test_automatic_subscription_uses_invoice_for_all_customer_identities`

Fuente: [tests/Feature/PaymentClassificationTest.php:260](../../tests/Feature/PaymentClassificationTest.php#L260).

- L263: `foreach (['0','1','6','7','E','C','G','R'] as $identity) self::assertSame('01',$producer->getDocumentType($identity));`

### `test_payment_link_fallback_uses_configured_classification_without_fixed_id`

Fuente: [tests/Feature/PaymentClassificationTest.php:266](../../tests/Feature/PaymentClassificationTest.php#L266).

- L274: `self::assertSame('04',$consumer->selected());`
- L276: `self::assertSame('13',$consumer->selected());`
- L280: `catch (ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`

### `test_purchase_installments_use_balance_after_recorded_payments`

Fuente: [tests/Feature/PaymentClassificationTest.php:283](../../tests/Feature/PaymentClassificationTest.php#L283).

- L299: `self::assertSame('0.750000000000', $fee->amount);`
- L300: `self::assertSame(['n'=>'3','d'=>'4'], D::fraction(D::read($fee, 'scheduled_exact')));`

### `test_purchase_edit_keeps_recorded_payment_after_method_reclassification`

Fuente: [tests/Feature/PaymentClassificationTest.php:303](../../tests/Feature/PaymentClassificationTest.php#L303).

- L317: `self::assertSame([],$new);`
- L318: `self::assertSame(1,$db->table('purchase_payments')->count());`
- L319: `self::assertSame($context,json_decode($db->table('global_payments')->where('id',1)->value('report_context'),true));`
- L322: `catch (ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L323: `self::assertSame('12',$db->table('purchase_payments')->where('id',1)->value('payment'));`

### `test_excel_contains_text_amounts_and_their_exact_numerators_and_denominators`

Fuente: [tests/Feature/PaymentClassificationTest.php:328](../../tests/Feature/PaymentClassificationTest.php#L328).

- L342: `$fractions=$book->getSheetByName('Fracciones exactas');self::assertNotNull($fractions);`
- L345: `self::assertSame('1',$cells[3]);self::assertSame('3',$cells[4]);$found=true;`
- L347: `self::assertTrue($found);`
- L350: `if ($cell->getValue()==='0.33') {self::assertSame('s',$cell->getDataType());$textAmounts++;}`
- L352: `self::assertGreaterThan(0,$textAmounts);`

### `test_duplicate_code_is_rejected_without_changing_the_existing_method`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:60](../../tests/Feature/PaymentMethodCreationTest.php#L60).

- L67: `->assertStatus(422)`
- L68: `->assertJsonPath('message.id.0', 'El código del método de pago ya existe.');`
- L71: `->assertStatus(422)`
- L72: `->assertJsonPath('id.0', 'El código del método de pago ya existe.');`
- L75: `self::assertCount(1, $rows);`
- L76: `self::assertSame('Original', $rows[0]->description);`

### `test_new_code_can_be_created_and_existing_method_can_be_edited`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:79](../../tests/Feature/PaymentMethodCreationTest.php#L79).

- L82: `->assertOk()->assertJsonPath('success', true);`
- L83: `self::assertTrue(\App\Models\Tenant\PaymentMethodType::active()->where('id', '27')->exists());`
- L85: `->assertOk()->assertJsonPath('success', true);`
- L87: `->assertStatus(422)->assertJsonStructure(['message' => ['id']]);`
- L89: `->assertNotFound();`
- L92: `self::assertCount(1, $rows);`
- L93: `self::assertSame('27', $rows[0]->id);`
- L94: `self::assertSame('Editado', $rows[0]->description);`
- L95: `self::assertSame('99', $rows[0]->hka_code);`

### `test_new_methods_are_available_even_if_client_requests_inactive`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:98](../../tests/Feature/PaymentMethodCreationTest.php#L98).

- L105: `])->assertOk()->assertJsonPath('success', true);`
- L106: `self::assertSame(1, DB::connection('tenant')->table('payment_method_types')->where('id', $id)->value('is_active'));`
- L110: `])->assertStatus(422);`
- L111: `self::assertFalse(DB::connection('tenant')->table('payment_method_types')->where('id', '34')->exists());`
- L115: `])->assertOk();`
- L116: `self::assertSame(0, DB::connection('tenant')->table('payment_method_types')->where('id', '31')->value('is_active'));`

### `test_initial_methods_are_editable_without_changing_id_or_hka_and_cannot_be_deleted`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:118](../../tests/Feature/PaymentMethodCreationTest.php#L118).

- L129: `$records = $this->getJson('/test-payment-methods')->assertOk()->json('data');`
- L130: `self::assertCount(10, $records);`
- L132: `self::assertTrue($record['show_actions']);`
- L133: `self::assertFalse($record['can_delete']);`
- L137: `])->assertOk()->assertJsonPath('success', true);`
- L139: `self::assertSame('USD', $row->currency_type_id);`
- L140: `self::assertSame('USD', $row->cash_currency_type_id);`
- L141: `self::assertSame('08', $row->hka_code);`
- L144: `])->assertOk()->assertJsonPath('success', true);`
- L145: `self::assertSame('account', DB::connection('tenant')->table('payment_method_types')->where('id', '01')->value('collection_kind'));`
- L146: `$this->deleteJson('/test-payment-methods/01')->assertJsonPath('success', false);`

### `test_retired_ids_can_be_reused_and_unclassified_methods_remain_inactive`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:149](../../tests/Feature/PaymentMethodCreationTest.php#L149).

- L153: `->assertOk()->assertJsonPath('success', true);`
- L155: `$this->deleteJson('/test-payment-methods/06')->assertOk()->assertJsonPath('success', true);`
- L157: `->assertOk()->assertJsonPath('success', true);`
- L159: `->assertStatus(422);`
- L161: `->assertStatus(422);`

### `test_currency_and_igtf_options_are_validated_saved_and_removable`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:164](../../tests/Feature/PaymentMethodCreationTest.php#L164).

- L166: `$this->getJson('/test-payment-tables')->assertOk()->assertJsonCount(2, 'currency_types')`
- L167: `->assertJsonPath('taxes.0.id', 'IGTF')->assertJsonPath('taxes.0.percentage', '3.00');`
- L169: `$this->postJson('/test-payment-methods', $base)->assertStatus(422);`
- L170: `$this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'XXX'])->assertStatus(422);`
- L171: `$this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'ZZZ'])->assertStatus(422);`
- L172: `$this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'USD', 'iva_rate_type_id'=>'G'])->assertStatus(422);`
- L173: `$this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'USD', 'iva_rate_type_id'=>'IGTF'])->assertOk();`
- L174: `$this->getJson('/test-payment-methods/30')->assertJsonPath('currency_type_id', 'USD')->assertJsonPath('iva_rate_type_id', 'IGTF');`
- L175: `$this->putJson('/test-payment-methods/30', $base + ['currency_type_id'=>'VES', 'iva_rate_type_id'=>null])->assertOk();`
- L176: `$this->getJson('/test-payment-methods/30')->assertJsonPath('currency_type_id', 'VES')->assertJsonPath('iva_rate_type_id', null);`

### `test_only_admin_can_change_availability_or_delete_custom_methods`

Fuente: [tests/Feature/PaymentMethodCreationTest.php:179](../../tests/Feature/PaymentMethodCreationTest.php#L179).

- L188: `$this->postJson($path,['id'=>'14','is_active'=>false])->assertForbidden();`
- L190: `$this->deleteJson('/test-payment-methods/14')->assertForbidden();`
- L191: `self::assertSame(1, DB::connection('tenant')->table('payment_method_types')->where('id','14')->value('is_active'));`
- L194: `$this->postJson($path,['id'=>'15','is_active'=>true])->assertStatus(422);`
- L195: `$this->postJson($path,['id'=>'14','is_active'=>'invalid'])->assertStatus(422);`
- L197: `self::assertSame(0, DB::connection('tenant')->table('payment_method_types')->where('id','15')->value('is_active'));`
- L198: `$this->postJson('/test-finance-payment-active',['id'=>'14','is_active'=>false])->assertOk();`
- L199: `self::assertSame(0, DB::connection('tenant')->table('payment_method_types')->where('id','14')->value('is_active'));`
- L200: `$this->deleteJson('/test-payment-methods/01')->assertOk()->assertJsonPath('success',false);`
- L201: `$this->deleteJson('/test-payment-methods/14')->assertOk()->assertJsonPath('success',true);`

### `test_same_currency_can_use_the_document_currency`

Fuente: [tests/Unit/MethodCurrencyTest.php:11](../../tests/Unit/MethodCurrencyTest.php#L11).

- L13: `self::assertSame('VES', MethodCurrency::resolve('VES', 'VES', []));`
- L14: `self::assertSame('USD', MethodCurrency::resolve('USD', 'USD', ['currency_type_id' => 'USD']));`

### `test_cross_currency_needs_explicit_amount_and_rate`

Fuente: [tests/Unit/MethodCurrencyTest.php:17](../../tests/Unit/MethodCurrencyTest.php#L17).

- L19: `self::assertSame('USD', MethodCurrency::resolve('USD', 'VES', [`
- L27: `self::assertNotEmpty($e->errors());`

### `test_sqlite_default_cash_uses_binary_unicode_order`

Fuente: [tests/Unit/DefaultCashOrderingTest.php:14](../../tests/Unit/DefaultCashOrderingTest.php#L14).

- L35: `self::assertSame('!', Classification::defaultCashMethod('VES')->id);`
- L37: `self::assertSame("\u{E000}", Classification::defaultCashMethod('VES')->id);`

### `test_converts_received_dollars_to_document_bolivars`

Fuente: [tests/Unit/ReceiptAmountsTest.php:20](../../tests/Unit/ReceiptAmountsTest.php#L20).

- L26: `self::assertSame('USD', $result['currency_type_id']);`
- L27: `self::assertSame('10.00', $result['original_amount']);`
- L28: `self::assertEquals('4000', $result['payment']);`
- L29: `self::assertSame('USD', $result['payment_method_snapshot']['currency_type_id']);`

### `test_rejects_mismatched_method_currency`

Fuente: [tests/Unit/ReceiptAmountsTest.php:32](../../tests/Unit/ReceiptAmountsTest.php#L32).

- Sin aserciones detectadas automáticamente; revisar el cuerpo completo.

### `test_converts_received_bolivars_to_document_dollars`

Fuente: [tests/Unit/ReceiptAmountsTest.php:40](../../tests/Unit/ReceiptAmountsTest.php#L40).

- L45: `self::assertSame('VES', $result['currency_type_id']);`
- L46: `self::assertSame('4000.00', $result['original_amount']);`
- L47: `self::assertEquals('10', $result['payment']);`

### `test_existing_receipt_rejects_a_change_of_received_currency`

Fuente: [tests/Unit/ReceiptAmountsTest.php:50](../../tests/Unit/ReceiptAmountsTest.php#L50).

- L53: `\App\Services\Payments\ExistingReceipts::assertCurrencyFields($record,['currency_type_id'=>'USD','original_amount'=>'10.00','exchange_rate'=>'400.00000000']);`
- L55: `\App\Services\Payments\ExistingReceipts::assertCurrencyFields($record,['currency_type_id'=>'VES']);`

### `test_converts_prices_and_discounts_before_rounding_payment`

Fuente: [tests/Unit/WhatsAppDraftPricingTest.php:11](../../tests/Unit/WhatsAppDraftPricingTest.php#L11).

- L17: `self::assertSame('5.00', $result['total_payable']);`
- L18: `self::assertSame('30', $result['items'][0]['unit_price']);`
- L19: `self::assertSame(['n'=>'3','d'=>'1'], $result['items'][0]['calculation_snapshot']['values']['total']);`
- L20: `self::assertSame('USD', $result['items'][0]['price_input']['currency_type_id']);`
- L22: `self::assertSame('104.40', $discounted['total_payable']);`

### `test_rejects_missing_or_nonpositive_rate`

Fuente: [tests/Unit/WhatsAppDraftPricingTest.php:25](../../tests/Unit/WhatsAppDraftPricingTest.php#L25).

- L37: `self::assertArrayHasKey('exchange_rate_sale', $exception->errors());`

### `test_orchestrator_explains_that_an_old_draft_must_be_confirmed_again`

Fuente: [tests/Unit/WhatsAppDraftPricingTest.php:44](../../tests/Unit/WhatsAppDraftPricingTest.php#L44).

- L62: `self::assertFalse($result['success']);`
- L63: `self::assertStringContainsString('prepararse y confirmarse nuevamente',$result['message']);`

### `default cash sorts custom IDs by Unicode code point rather than locale`

Fuente: [tests/js/payment-classification.test.cjs:15](../../tests/js/payment-classification.test.cjs#L15).

- L17: `assert.equal(exported.defaultCashForCurrency(rows,'VES').id,'!')`
- L18: `assert.equal(exported.defaultCashForCurrency(rows.slice(3),'VES').id,'\uE000')`

### `store configuration can select bank destinations without a payment-method field`

Fuente: [tests/js/payment-classification.test.cjs:21](../../tests/js/payment-classification.test.cjs#L21).

- L24: `assert.deepEqual(compiler.compile(source.template.content).errors,[]);`
- L25: `assert.match(source.template.content,/v-for="option in payment_destinations"/);`
- L26: `assert.doesNotMatch(source.template.content,/classifiedPaymentDestinations\(payment_destinations, form\)/);`

### `cash default is active, matches document currency, and does not depend on a seeded ID`

Fuente: [tests/js/payment-classification.test.cjs:29](../../tests/js/payment-classification.test.cjs#L29).

- L37: `assert.equal(exported.defaultCashForCurrency(methods,'USD').id,'04');`
- L38: `assert.equal(exported.defaultCashForCurrency(methods,'VES').id,'07');`
- L39: `assert.equal(exported.defaultCashForCurrency(methods,null),null);`
- L40: `assert.equal(exported.defaultCashForCurrency(methods,'EUR'),null);`

### `payment validation extracts the server message without guessing a payment row`

Fuente: [tests/js/payment-classification.test.cjs:43](../../tests/js/payment-classification.test.cjs#L43).

- L44: `assert.equal(exported.paymentValidationMessage({currency_type_id:['La moneda cambió']}),'La moneda cambió');`
- L45: `assert.equal(exported.paymentValidationMessage({message:'Error',errors:{'payments.2.payment_destination_id':['Cuenta incompatible']}}),'Cuenta incompatible');`
- L46: `assert.equal(exported.paymentValidationMessage({message:{exchange_rate:['Tasa inválida']}}),'Tasa inválida');`
- L47: `assert.equal(exported.paymentValidationMessage({errors:{source_sale_note_payment_id:['El cobro original ya fue aplicado']}}),'El cobro original ya fue aplicado');`
- L48: `assert.equal(exported.paymentValidationMessage({errors:{source_quotation_payment_id:['Cobro ya aplicado']}}),'Cobro ya aplicado');`
- L49: `assert.equal(exported.paymentValidationMessage({errors:{financial_intermediation:['Indique la intermediación']}}),'Indique la intermediación');`
- L50: `assert.equal(exported.paymentValidationMessage({total:['El total es menor que lo cobrado']}),'El total es menor que lo cobrado');`
- L51: `assert.equal(exported.paymentValidationMessage({customer_id:['Cliente inválido']}),null);`
- L52: `assert.equal(exported.paymentValidationMessage(null),null);`

### `hotel exposes wrapped and indexed payment errors and retains other form errors`

Fuente: [tests/js/payment-classification.test.cjs:55](../../tests/js/payment-classification.test.cjs#L55).

- L59: `assert.equal(messages.pop(),'Moneda inactiva');`
- L60: `assert.equal(view.errors.room[0],'Habitación ocupada');`
- L62: `assert.equal(messages.pop(),'Cuenta incompatible');`
- L63: `assert.equal(view.errors['products.1.rent_payment.payment_destination_id'][0],'Cuenta incompatible');`
- L65: `assert.equal(messages.pop(),'Tasa inválida');`
- L66: `assert.equal(view.errors.exchange_rate[0],'Tasa inválida');`
- L68: `assert.equal(messages.length,0);`
- L69: `assert.equal(view.errors.customer_id[0],'Cliente inválido');`
- L71: `assert.equal(messages.pop(),'Sin conexión');`
- L73: `assert.equal(fallback.length,1);`

### `physical currency and banking destinations are independent`

Fuente: [tests/js/payment-classification.test.cjs:76](../../tests/js/payment-classification.test.cjs#L76).

- L77: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'01'},'VES')),['cash']);`
- L78: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10'},'VES')),[]);`
- L79: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10'},undefined)),['cash']);`
- L80: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10',currency_type_id:'USD'},'VES')),['cash']);`
- L81: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'04'},'VES')),[1]);`
- L82: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'04'},'USD')),[2]);`
- L83: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'09'},'VES')),[]);`

### `the method currency determines eligible bank accounts`

Fuente: [tests/js/payment-classification.test.cjs:85](../../tests/js/payment-classification.test.cjs#L85).

- L87: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,usdMethod,{payment_method_type_id:'13'},'VES')),[2]);`
- L88: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,usdMethod,{payment_method_type_id:'13',currency_type_id:'VES'},'VES')),[]);`
- L89: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,usdMethod,{payment_method_type_id:'13',currency_type_id:'USD'},'VES')),[2]);`

### `supplier and loan payments use their own currency even if the receipt method has another currency`

Fuente: [tests/js/payment-classification.test.cjs:91](../../tests/js/payment-classification.test.cjs#L91).

- L97: `assert.deepEqual(ids(mixin.classifiedPaymentDestinations.call(payment,destinations,row)),[1]);`
- L98: `assert.deepEqual(ids(mixin.classifiedPaymentDestinations.call(receipt,destinations,row)),[]);`
- L100: `assert.deepEqual(ids(mixin.classifiedPaymentDestinations.call(purchase,destinations,{payment_method_type_id:'13'})),[1]);`

### `expense IDs never use the payment-method catalog`

Fuente: [tests/js/payment-classification.test.cjs:102](../../tests/js/payment-classification.test.cjs#L102).

- L103: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{expense_method_type_id:1},'USD',true)),['cash']);`
- L104: `assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{expense_method_type_id:2},'VES',true)),[1]);`

### `classification applies without an activation flag on destination options`

Fuente: [tests/js/payment-classification.test.cjs:106](../../tests/js/payment-classification.test.cjs#L106).

- L108: `assert.deepEqual(ids(exported.classifiedDestinations(legacy,methods,{payment_method_type_id:'04'},'VES')),[1]);`

### `an incompatible default is cleared and a new banking destination remains selectable`

Fuente: [tests/js/payment-classification.test.cjs:110](../../tests/js/payment-classification.test.cjs#L110).

- L116: `assert.equal(row.payment_destination_id,null);assert.equal(row.collection_kind,'account');`
- L117: `assert.equal(mixin.classifiedPaymentDestinationDisabled.call(instance,destinations,row),false);`

### `document payment rows clear incompatible destinations without modifying recorded receipts`

Fuente: [tests/js/payment-classification.test.cjs:120](../../tests/js/payment-classification.test.cjs#L120).

- L129: `assert.equal(fresh.payment_destination_id,null);`
- L130: `assert.equal(recorded.payment_destination_id,1);`
- L133: `assert.equal(fresh.payment_destination_id,null);`

### `reused receipts retain their historical destination and nature when the catalog changes`

Fuente: [tests/js/payment-classification.test.cjs:136](../../tests/js/payment-classification.test.cjs#L136).

- L147: `rows.forEach(row=>{assert.equal(row.payment_destination_id,'cash');assert.equal(row.collection_kind,'cash')});`

### `order draft rows clear a destination that conflicts with the method currency`

Fuente: [tests/js/payment-classification.test.cjs:150](../../tests/js/payment-classification.test.cjs#L150).

- L158: `assert.equal(draft.payment_destination_id,null);`

### `test_fractional_local_unit_invoice_and_mobile_weight_catalog`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:13](../../tests/Support/PaymentClassificationPersistenceCases.php#L13).

- L18: `self::assertSame(['KG','TON'],$weightCatalog['data']['unit_types']->pluck('id')->sort()->values()->all());`
- L21: `self::assertSame(['KG'],$weightCatalog['data']['unit_types']->pluck('id')->all());`
- L35: `self::assertSame('KG',$saved->item->unit_type_id);`
- L36: `self::assertSame('KGM',$saved->item->hka_unit_code);`
- L37: `self::assertSame('0.3',\App\Support\Exact\Decimal::represent($saved->exactValue('quantity')));`
- L38: `self::assertSame('125',\App\Support\Exact\Decimal::represent($saved->exactValue('unit_price')));`
- L39: `self::assertSame('37.5',\App\Support\Exact\Decimal::represent($saved->exactValue('total')));`
- L40: `self::assertSame('37.5',\App\Support\Exact\Decimal::represent($invoice->exactValue('total')));`
- L41: `self::assertSame('37.5',\App\Support\Exact\Decimal::represent($invoice->payments()->firstOrFail()->exactValue('payment')));`
- L42: `self::assertTrue($invoice->exactBalance()->isZero());`

### `test_classic_subscription_generation_failure_rolls_back_the_plan`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:45](../../tests/Support/PaymentClassificationPersistenceCases.php#L45).

- L58: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('cat_period_id',$error->errors()); }`
- L59: `self::assertSame(0,$db->table('user_rel_suscription_plans')->count());`
- L60: `self::assertSame(0,$db->table('sale_notes')->count());`
- L69: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('exchange_rate_sale',$error->errors()); }`
- L70: `self::assertSame(0,$withoutRate->calls);`
- L71: `self::assertSame(0,$db->table('user_rel_suscription_plans')->count());`
- L72: `self::assertSame(0,$db->table('sale_notes')->count());`
- L100: `self::assertSame('Fallo simulado de segunda nota',$error->errors()['sale_notes'][0]);`
- L102: `self::assertSame(2,$noteController->calls);`
- L103: `self::assertStringStartsWith('%PDF-',$noteController->generatedPdf);`
- L104: `self::assertFalse(\Illuminate\Support\Facades\Storage::disk('tenant')->exists($noteController->generatedPath));`
- L105: `self::assertSame(0,$db->table('user_rel_suscription_plans')->count());`
- L106: `self::assertSame(0,$db->table('sale_notes')->count());`
- L111: `catch (\Illuminate\Validation\ValidationException $error) { self::assertSame('Fallo simulado de segunda nota',$error->errors()['sale_notes'][0]); }`
- L112: `self::assertSame('archivo anterior',$disk->get($noteController->generatedPath));`
- L113: `self::assertSame(0,$db->table('sale_notes')->count());`
- L131: `\Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('tenant')->andReturn($failingDisk);`
- L135: `self::assertSame('No se pudo guardar el PDF de la nota de suscripción.',$error->errors()['sale_notes'][0]);`
- L140: `self::assertNotNull($failingDisk->failedPath);`
- L141: `self::assertFalse($disk->exists($failingDisk->failedPath));`
- L142: `self::assertSame(0,$db->table('user_rel_suscription_plans')->count());`
- L143: `self::assertSame(0,$db->table('sale_notes')->count());`
- L148: `self::assertSame(1,$db->table('user_rel_suscription_plans')->count());`
- L149: `self::assertSame(1,$db->table('sale_notes')->count());`
- L150: `self::assertSame('10.00000000',$db->table('sale_notes')->value('exchange_rate_sale'));`
- L151: `self::assertSame(0,$db->table('sale_note_payments')->count());`
- L152: `self::assertSame(0,$db->table('global_payments')->count());`
- L154: `self::assertTrue(\Illuminate\Support\Facades\Storage::disk('tenant')->exists('sale_note/'.$filename.'.pdf'));`
- L155: `self::assertStringStartsWith('%PDF-',\Illuminate\Support\Facades\Storage::disk('tenant')->get('sale_note/'.$filename.'.pdf'));`
- L167: `catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }`
- L168: `self::assertSame(0,$db->table('sale_note_payments')->count());`
- L169: `self::assertSame(0,$db->table('global_payments')->count());`
- L171: `self::assertTrue($paymentController->store($paymentRequest)['success']);`
- L173: `self::assertSame('USD',$payment->currency_type_id);`
- L174: `self::assertSame('23.2',$payment->original_amount);`
- L175: `self::assertSame('232',$payment->payment);`
- L176: `self::assertSame('0',$paymentController->document($payment->sale_note_id)['total_difference']);`
- L178: `self::assertSame('account',\App\Services\Payments\Classification::recordedKind($payment->fresh()));`
- L179: `self::assertSame(1,$db->table('global_payments')->count());`
- L181: `self::assertSame('0',$cashReport['balances_by_currency']['VES']['final_balance']);`
- L182: `self::assertSame('0',$cashReport['balances_by_currency']['USD']['final_balance']);`
- L188: `self::assertSame($noteIds,$db->table('user_rel_suscription_plans')->where('id',$planId)->value('sale_notes'));`
- L189: `self::assertSame(1,$db->table('sale_notes')->count());`
- L190: `self::assertSame(1,$db->table('sale_note_payments')->count());`
- L191: `self::assertSame(1,$db->table('global_payments')->count());`
- L192: `self::assertSame('account',\App\Services\Payments\Classification::recordedKind($payment->fresh()));`
- L212: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L223: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L225: `self::assertTrue($response['success'] ?? false, json_encode($response));`
- L231: `self::assertSame(1,$db->table('sale_notes')->where('user_rel_suscription_plan_id',$racePlan->id)->count());`
- L232: `self::assertSame(1,$db->table('sale_note_payments')->count());`
- L233: `self::assertSame(1,$db->table('global_payments')->count());`
- L239: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('series_id',$error->errors()); }`
- L240: `self::assertSame(2,$db->table('user_rel_suscription_plans')->count());`
- L241: `self::assertSame(2,$db->table('sale_notes')->count());`
- L242: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_api_invoice_from_restaurant_checks_method_currency_and_bank_atomically`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:244](../../tests/Support/PaymentClassificationPersistenceCases.php#L244).

- L266: `catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }`
- L267: `self::assertSame(0,$db->table('documents')->count());`
- L268: `self::assertSame(0,$db->table('global_payments')->count());`
- L273: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L276: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`
- L280: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L284: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }`
- L286: `self::assertSame(0,$db->table('documents')->count());`
- L287: `self::assertSame(0,$db->table('global_payments')->count());`
- L289: `self::assertTrue($result['success']);`
- L292: `self::assertSame('USD',$payment->currency_type_id);`
- L293: `self::assertSame('23.2',$payment->original_amount);`
- L294: `self::assertSame('232',$payment->payment);`
- L295: `self::assertSame('account',Movement::row($payment->global_payment)['method']['collection_kind']);`
- L297: `self::assertSame('account',Movement::row($payment->global_payment->fresh())['method']['collection_kind']);`
- L298: `self::assertSame('USD',Movement::row($payment->global_payment->fresh())['currency_type_id']);`
- L306: `self::assertTrue($cashResult['success']);`
- L308: `self::assertSame('cash',Movement::row($cashPayment->global_payment)['method']['collection_kind']);`
- L309: `self::assertSame('VES',Movement::row($cashPayment->global_payment)['currency_type_id']);`
- L312: `self::assertSame('232',$cashBalances['VES']['final_balance']);`
- L313: `self::assertSame('0',$cashBalances['USD']['final_balance']);`
- L317: `catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }`
- L318: `self::assertSame(2,$db->table('documents')->count());`
- L319: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_api_sale_note_rejects_retired_currency_and_rolls_back_imported_customer`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:321](../../tests/Support/PaymentClassificationPersistenceCases.php#L321).

- L335: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L336: `self::assertFalse($db->table('persons')->where('number','87654321')->exists());`
- L337: `self::assertSame(0,$db->table('sale_notes')->count());`
- L341: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L342: `self::assertFalse($db->table('persons')->where('number','87654321')->exists());`
- L346: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`
- L347: `self::assertFalse($db->table('persons')->where('number','87654321')->exists());`
- L348: `self::assertSame(0,$db->table('sale_notes')->count());`
- L349: `self::assertSame(0,$db->table('global_payments')->count());`
- L358: `catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }`
- L359: `self::assertFalse($db->table('persons')->where('number','87654321')->exists());`
- L360: `self::assertSame(0,$db->table('sale_notes')->count());`
- L365: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L368: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`
- L372: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L376: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }`
- L378: `self::assertFalse($db->table('persons')->where('number','87654321')->exists());`
- L379: `self::assertSame(0,$db->table('sale_notes')->count());`
- L381: `self::assertTrue($result['success']);`
- L384: `self::assertSame('USD',$receipt->currency_type_id);`
- L385: `self::assertSame('23.2',$receipt->original_amount);`
- L386: `self::assertSame('232',$receipt->payment);`
- L387: `self::assertSame('account',Movement::row($receipt->global_payment)['method']['collection_kind']);`
- L388: `self::assertSame(1,$db->table('persons')->where('number','87654321')->count());`
- L390: `self::assertSame('account',Movement::row($receipt->global_payment->fresh())['method']['collection_kind']);`
- L391: `self::assertSame('USD',Movement::row($receipt->global_payment->fresh())['currency_type_id']);`
- L398: `self::assertTrue($cashResult['success']);`
- L400: `self::assertSame('cash',Movement::row($cashReceipt->global_payment)['method']['collection_kind']);`
- L401: `self::assertSame('VES',Movement::row($cashReceipt->global_payment)['currency_type_id']);`
- L403: `self::assertSame('232',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L404: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);`
- L413: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L414: `self::assertFalse($nextCalled);`
- L415: `self::assertFalse($db->table('persons')->where('number','87654322')->exists());`
- L416: `self::assertSame(0,$db->table('documents')->count());`

### `test_api_invoice_reuses_sale_note_receipt_and_links_source`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:419](../../tests/Support/PaymentClassificationPersistenceCases.php#L419).

- L452: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L453: `self::assertSame(0,$db->table('documents')->count());`
- L454: `self::assertNull($note->fresh()->document_id);`
- L456: `self::assertTrue($result['success']);`
- L459: `self::assertSame($document->id,$note->fresh()->document_id);`
- L460: `self::assertSame((string)$source->id,$payment->payment_method_snapshot['receipt_origin']['id']);`
- L461: `self::assertSame('USD',$payment->currency_type_id);`
- L462: `self::assertSame('5',\App\Support\Exact\Decimal::represent($payment->original_amount));`
- L463: `self::assertSame('50',\App\Support\Exact\Decimal::represent($payment->payment));`
- L464: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_restaurant_cash_links_credit_receipt_once_using_recorded_classification`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:468](../../tests/Support/PaymentClassificationPersistenceCases.php#L468).

- L488: `self::assertTrue($controller->cash_document($request)->getData(true)['success']);`
- L489: `self::assertTrue($controller->cash_document($request)->getData(true)['success']);`
- L491: `self::assertTrue($apiController->cash_document($request)->getData(true)['success']);`
- L492: `self::assertTrue($apiController->cash_document($request)->getData(true)['success']);`
- L493: `self::assertSame(1,$db->table('cash_document_credits')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());`
- L494: `self::assertSame(1,$db->table('cash_documents')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());`
- L495: `self::assertSame(1,$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->count());`
- L498: `self::assertTrue($apiController->cash_document($request)->getData(true)['success']);`
- L499: `self::assertSame(1,$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->count());`
- L500: `self::assertSame((int)$link,(int)$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->value('cash_document_id'));`
- L519: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L530: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L532: `self::assertTrue($response['success'] ?? false);`
- L538: `self::assertSame(1,$db->table('cash_document_credits')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());`
- L539: `self::assertSame(1,$db->table('cash_documents')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());`
- L540: `self::assertSame(1,$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->count());`
- L542: `self::assertSame('account',Movement::row($payment->global_payment->fresh())['method']['collection_kind']);`
- L543: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);`
- L544: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L546: `self::assertFalse((bool)$cash->fresh()->state);`

### `test_full_subscription_edit_preserves_existing_orders_and_status`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:548](../../tests/Support/PaymentClassificationPersistenceCases.php#L548).

- L563: `self::assertSame(1, $db->table('suscription_orders')->where('suscription_id', $id)->count());`
- L571: `self::assertSame(1, $db->table('suscription_orders')->where('suscription_id', $id)->count());`
- L573: `self::assertSame('paused', $subscription->subscription_status);`
- L574: `self::assertSame(2, (int) $subscription->orders_created);`
- L576: `self::assertSame('pausada', $model->calculateStatus());`
- L579: `self::assertSame('cancelada', $model->calculateStatus());`
- L589: `self::assertStringContainsString('Fallo de prueba', $error->getMessage());`
- L593: `self::assertSame(1, $db->table('user_rel_suscription_plans')->count());`
- L594: `self::assertSame(1, $db->table('suscription_orders')->count());`
- L604: `self::assertSame(2,(int)$stats['total_orders']);`
- L605: `self::assertSame(1,(int)$stats['total_approved']);`
- L606: `self::assertSame(1,(int)$stats['total_pending']);`
- L607: `self::assertNull($stats['total_collected']);`
- L608: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_automatic_subscription_invoice_has_no_unverified_cash_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:611](../../tests/Support/PaymentClassificationPersistenceCases.php#L611).

- L628: `self::assertSame('01',$data['document_type_id']);`
- L629: `self::assertSame('02',$data['payment_condition_id']);`
- L630: `self::assertSame([],$data['payments']);`
- L664: `self::assertTrue($response['success'],$response['message']);`
- L666: `self::assertNotNull($document);`
- L667: `self::assertSame('FULL_SUSCRIPTION',$document->source_module);`
- L668: `self::assertFalse($document->emission->response['auto_send_requested']);`
- L669: `self::assertArrayNotHasKey(\App\Services\Fiscal\HkaCreditInvoice::KEY,$document->emission->response);`
- L670: `self::assertStringContainsString('requiere validación',\App\Services\Fiscal\HkaCreditInvoice::reason($document));`
- L671: `self::assertSame(0,$httpCalls);`
- L673: `self::assertNotSame('confirmed',$emission['status']);`
- L674: `self::assertSame(0,$httpCalls);`
- L675: `self::assertFalse(\App\Services\Fiscal\HkaPdf::view($document->fresh())['a4']['available']);`
- L676: `self::assertTrue($controller->generate($order->id)->getData(true)['success']);`
- L677: `self::assertSame(1,$db->table('documents')->count());`
- L681: `self::assertFalse($controller->generate($failedOrder->id)->getData(true)['success']);`
- L682: `self::assertNull($failedOrder->fresh()->documentable_id);`
- L683: `self::assertSame(1,$db->table('documents')->count());`
- L684: `self::assertSame('02',$document->payment_condition_id);`
- L685: `self::assertSame(0,$db->table('document_payments')->where('document_id',$document->id)->count());`
- L686: `self::assertSame(0,$db->table('global_payments')->count());`
- L687: `self::assertSame('232',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));`
- L699: `} catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }`
- L700: `self::assertSame(0,$db->table('global_payments')->count());`
- L702: `self::assertSame('USD',$payment->currency_type_id);`
- L703: `self::assertSame('23.2',$payment->original_amount);`
- L704: `self::assertSame('0',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document->fresh()])[$document->id]));`
- L706: `self::assertSame('account',Movement::row($movement)['method']['collection_kind']);`
- L707: `self::assertSame('USD',Movement::row($movement)['currency_type_id']);`
- L709: `self::assertSame('account',Movement::row($movement->fresh())['method']['collection_kind']);`
- L710: `self::assertSame('USD',Movement::row($movement->fresh())['currency_type_id']);`
- L719: `self::assertTrue($result['success'],$result['message']);`
- L721: `self::assertSame('USD',$usdDocument->currency_type_id);`
- L722: `self::assertSame('232',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$usdDocument])[$usdDocument->id]));`
- L726: `self::assertFalse($controller->generate($inactiveOrder->id)->getData(true)['success']);`
- L727: `self::assertNull($inactiveOrder->fresh()->documentable_id);`
- L728: `self::assertSame(2,$db->table('documents')->count());`
- L736: `self::assertSame('VES',$vesReceipt->currency_type_id);`
- L737: `self::assertSame('100',$vesReceipt->original_amount);`
- L738: `self::assertSame('222',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$usdDocument->fresh()])[$usdDocument->id]));`

### `test_loan_payment_reclassifies_method_and_checks_destination_with_rollback`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:740](../../tests/Support/PaymentClassificationPersistenceCases.php#L740).

- L758: `self::assertTrue($send('01',$ves->id)['success']);`
- L760: `self::assertSame('account',json_decode($movement->report_context,true)['method']['collection_kind']);`
- L764: `catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }`
- L768: `catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }`
- L771: `self::assertSame('account',$snapshot['method']['collection_kind']);`
- L772: `self::assertSame('VES',$snapshot['currency_type_id']);`
- L773: `self::assertSame(1,$db->table('bank_loan_payments')->count());`
- L774: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_document_payment_workbook_keeps_received_and_applied_currencies_and_balance`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:777](../../tests/Support/PaymentClassificationPersistenceCases.php#L777).

- L803: `self::assertSame('40.00',$record->balance);`
- L804: `self::assertSame('VES',$record->currency_type_id);`
- L805: `self::assertSame('USD',$record->payments[0]['currency_type_id']);`
- L806: `self::assertSame('4',$record->payments[0]['payment']);`
- L807: `self::assertSame('40',$record->payments[0]['applied_payment']);`
- L808: `self::assertSame('10.00000000',$record->payments[0]['exchange_rate']);`
- L809: `self::assertNotSame('Nombre cambiado',$record->payments[0]['payment_method_type_description']);`
- L816: `self::assertCount(2,$sheets);`
- L817: `self::assertSame('Fracciones exactas',$sheets[1]->title());`
- L819: `self::assertStringContainsString('Moneda del documento',$html);`
- L820: `self::assertStringContainsString('Moneda recibida 1',$html);`
- L829: `self::assertContains('Moneda recibida 1',$cells);`
- L830: `self::assertContains('VES',$cells); self::assertContains('USD',$cells);`
- L831: `self::assertContains('40.00',$cells); self::assertContains('4.00',$cells);`
- L832: `self::assertContains('10.00000000',$cells);`
- L834: `self::assertContains([(string)$id,$record->invoice,(string)$payment->id,'rate','VES/USD','10','1'],$fractions);`
- L835: `self::assertContains([(string)$id,$record->invoice,(string)$payment->id,'applied','VES','40','1'],$fractions);`
- L846: `self::assertCount(11,$batchRows);`
- L847: `self::assertLessThanOrEqual(8,$queryCount,'El reporte debe leer ajustes y saldos por lote.');`
- L849: `foreach ($batch as $item) self::assertTrue($balances[$item->id]->isEqualTo($item->exactBalance()));`
- L852: `self::assertSame('80.00',$record->balance);`
- L853: `self::assertCount(0,$record->payments);`

### `test_document_receivable_kpi_uses_current_balance_after_foreign_receipt_reversal`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:858](../../tests/Support/PaymentClassificationPersistenceCases.php#L858).

- L877: `self::assertSame('Bs. 60.00',$controller->kpis($request)['receivable']['total']);`
- L879: `self::assertSame('100',$document->fresh()->balance);`
- L880: `self::assertSame('Bs. 100.00',$controller->kpis($request)['receivable']['total']);`
- L888: `self::assertSame('80',$document->fresh()->balance);`
- L889: `self::assertSame('Bs. 80.00',$controller->kpis($request)['receivable']['total']);`
- L906: `self::assertSame('Bs. 97.30',$summary['total']);`
- L907: `self::assertSame('Bs. 80.00',$summary['overdue']);`
- L908: `self::assertSame('Bs. 12.30',$summary['current']);`
- L911: `self::assertSame('Bs. 17.30',$sellerSummary['total']);`
- L912: `self::assertSame('Bs. 0.00',$sellerSummary['overdue']);`
- L913: `self::assertSame('Bs. 12.30',$sellerSummary['current']);`

### `test_simultaneous_document_receipts_after_prior_read_respect_balance`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:918](../../tests/Support/PaymentClassificationPersistenceCases.php#L918).

- L955: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L967: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L972: `self::assertSame($case === 'same' ? 2 : 1,$accepted);`
- L973: `self::assertSame($case === 'same' ? 0 : 1,$rejected);`
- L974: `if ($case === 'same') self::assertSame($ids[0],$ids[1]);`
- L979: `self::assertSame(1,$db->table('document_payments')->where('document_id',$id)->count());`
- L980: `self::assertSame(['different'=>1,'same'=>2,'different_destination'=>3][$case],$db->table('global_payments')->count());`
- L981: `self::assertTrue((bool)$db->table('documents')->where('id',$id)->value('total_canceled'));`

### `test_simultaneous_sale_note_receipts_keep_current_settlement_state`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:987](../../tests/Support/PaymentClassificationPersistenceCases.php#L987).

- L1018: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1029: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1031: `self::assertTrue($response['success'] ?? false);`
- L1037: `self::assertSame(2,$db->table('sale_note_payments')->where('sale_note_id',$note->id)->count());`
- L1038: `self::assertSame(2,$db->table('global_payments')->count());`
- L1039: `self::assertSame('0',(new \App\Http\Controllers\Tenant\SaleNotePaymentController())->document($note->id)['total_difference']);`
- L1040: `self::assertTrue((bool)$note->fresh()->total_canceled);`

### `test_simultaneous_receipts_do_not_exceed_quotation_or_technical_service_balance`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1045](../../tests/Support/PaymentClassificationPersistenceCases.php#L1045).

- L1078: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1090: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1095: `self::assertSame(1,$accepted,"{$table}: debe guardarse un solo cobro.");`
- L1096: `self::assertSame(1,$rejected,"{$table}: el otro cobro debe exceder el saldo restante.");`
- L1101: `self::assertSame(1,$db->table($paymentTable)->where($field,$id)->count());`
- L1103: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_bank_currency_edit_and_receipt_serialize_without_mismatched_currency`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1108](../../tests/Support/PaymentClassificationPersistenceCases.php#L1108).

- L1134: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1151: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1162: `self::assertContains('currency_type_id',$result($process)['rejected']);`
- L1167: `self::assertSame('USD',$bank->fresh()->currency_type_id);`
- L1168: `self::assertSame(1,$db->table('quotation_payments')->count());`
- L1178: `self::assertContains('payment_destination_id',$result($process)['rejected']);`
- L1183: `self::assertSame('VES',$bank->fresh()->currency_type_id);`
- L1184: `self::assertSame(1,$db->table('quotation_payments')->count());`
- L1185: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_receipt_rechecks_currency_after_concurrent_deactivation`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1190](../../tests/Support/PaymentClassificationPersistenceCases.php#L1190).

- L1218: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1228: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1230: `self::assertContains('currency_type_id',$result['rejected'] ?? []);`
- L1235: `self::assertSame(0,$db->table('quotation_payments')->count());`
- L1236: `self::assertSame(0,$db->table('global_payments')->count());`
- L1243: `self::assertTrue($receipt['success']);`
- L1253: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1263: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1265: `self::assertTrue($result['success']);`
- L1271: `self::assertSame('USD',Movement::row($saved->global_payment)['currency_type_id']);`
- L1272: `self::assertSame('1',Movement::row($saved->global_payment)['amount']);`
- L1273: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_method_activation_rechecks_classification_after_concurrent_edit`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1276](../../tests/Support/PaymentClassificationPersistenceCases.php#L1276).

- L1293: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1303: `self::assertSame(0,$process->wait(),$process->getErrorOutput());`
- L1305: `self::assertContains('collection_kind',$result['rejected'] ?? []);`
- L1311: `self::assertSame('unclassified',$method->collection_kind);`
- L1312: `self::assertSame(0,(int)$method->is_active);`
- L1327: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1336: `self::assertSame(0,$process->wait(),$process->getErrorOutput());`
- L1338: `self::assertContains('collection_kind',$result['rejected'] ?? []);`
- L1344: `self::assertSame('account',$method->collection_kind);`
- L1345: `self::assertSame(1,(int)$method->is_active);`

### `test_receipt_attachment_reuses_concurrently_committed_movement_after_prior_read`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1348](../../tests/Support/PaymentClassificationPersistenceCases.php#L1348).

- L1367: `self::assertSame(0,$db->table('global_payments')->count());`
- L1373: `self::assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());`
- L1374: `self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success']);`
- L1381: `self::assertSame(1,$db->table('global_payments')->count());`
- L1385: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }`
- L1408: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1409: `self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success']);`
- L1414: `self::assertSame(1,$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)->where('payment_id',$secondId)->count());`
- L1425: `self::assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());`
- L1428: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }`
- L1434: `self::assertSame('10',\App\Support\Exact\Decimal::represent($db->table('quotation_payments')->where('id',$thirdId)->value('payment')));`

### `test_sale_note_batch_uses_current_locked_method_and_blocks_reclassification`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1437](../../tests/Support/PaymentClassificationPersistenceCases.php#L1437).

- L1462: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1471: `self::assertSame(0,$process->wait(),$process->getErrorOutput());`
- L1473: `self::assertContains('payment_destination_id',$result['rejected'] ?? []);`
- L1478: `self::assertSame(0,$db->table('sale_note_payments')->count());`
- L1479: `self::assertSame(0,$db->table('global_payments')->count());`
- L1493: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1502: `self::assertSame(0,$process->wait(),$process->getErrorOutput());`
- L1503: `self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success'] ?? false);`
- L1508: `self::assertSame(2,$db->table('global_payments')->count());`
- L1510: `self::assertSame('account',data_get($movement->report_context,'method.collection_kind'));`
- L1511: `self::assertSame('USD',data_get($movement->report_context,'currency_type_id'));`
- L1513: `self::assertSame('cash',$db->table('payment_method_types')->where('id','13')->value('collection_kind'));`

### `test_receipt_serializes_with_concurrent_method_reclassification`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1516](../../tests/Support/PaymentClassificationPersistenceCases.php#L1516).

- L1542: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L1555: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1563: `self::assertContains('currency_type_id',$finish($process)['rejected'] ?? []);`
- L1568: `self::assertSame(0,$db->table('quotation_payments')->count());`
- L1569: `self::assertSame(0,$db->table('global_payments')->count());`
- L1578: `self::assertContains('payment_destination_id',$finish($process)['rejected'] ?? []);`
- L1583: `self::assertSame(0,$db->table('quotation_payments')->count());`
- L1584: `self::assertSame(0,$db->table('global_payments')->count());`
- L1595: `self::assertTrue($finish($process)['success'] ?? false);`
- L1601: `self::assertSame('cash',data_get($cashGlobal->report_context,'method.collection_kind'));`
- L1602: `self::assertSame('USD',data_get($cashGlobal->report_context,'currency_type_id'));`
- L1603: `self::assertSame(Cash::class,$cashGlobal->destination_type);`
- L1604: `self::assertSame((int)$cash->id,(int)$cashGlobal->destination_id);`
- L1615: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }`
- L1616: `self::assertSame(0,$db->table('quotation_payments')->count());`
- L1617: `self::assertSame(0,$db->table('global_payments')->count());`
- L1623: `self::assertTrue($result['success']);`
- L1626: `self::assertTrue($finish($process)['success']);`
- L1632: `self::assertSame('USD',Movement::row($saved->global_payment)['currency_type_id']);`
- L1633: `self::assertSame('account',data_get($saved->global_payment->report_context,'method.collection_kind'));`
- L1634: `self::assertSame('cash',$db->table('payment_method_types')->where('id','13')->value('collection_kind'));`
- L1635: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_quotation_and_technical_service_limit_the_exact_applied_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1638](../../tests/Support/PaymentClassificationPersistenceCases.php#L1638).

- L1661: `self::assertTrue($controller->store($requestClass::create('/', 'POST', $data))['success']);`
- L1663: `self::assertSame($total,$document['total']);`
- L1664: `self::assertSame('3996',$document['total_paid']);`
- L1665: `self::assertSame($remaining,$document['total_difference']);`
- L1666: `self::assertSame($remaining,\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($document,'total_difference')));`
- L1672: `self::assertArrayHasKey('payment',$error->errors());`
- L1674: `self::assertSame($remaining,$controller->document($id)['total_difference']);`
- L1676: `self::assertTrue($controller->store($requestClass::create('/', 'POST', $data))['success']);`
- L1677: `self::assertSame($final,$controller->document($id)['total_difference']);`
- L1679: `self::assertSame(4,$db->table('global_payments')->count());`
- L1688: `self::assertTrue((new \App\Http\Controllers\Tenant\QuotationController())->update($edit)['success']);`
- L1690: `self::assertSame('8000',$after['total']);`
- L1691: `self::assertSame('4400',$after['total_paid']);`
- L1692: `self::assertSame('3600',$after['total_difference']);`
- L1699: `self::assertArrayHasKey('total',$error->errors());`
- L1701: `self::assertSame('8000',(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->document($quotationId)['total']);`
- L1702: `self::assertSame(3,$db->table('quotation_payments')->where('quotation_id',$quotationId)->count());`
- L1712: `self::assertArrayHasKey('payment',$error->errors());`
- L1714: `self::assertSame(0,$db->table('quotation_payments')->where('quotation_id',$initialQuoteId)->count());`
- L1715: `self::assertSame(5,$db->table('global_payments')->count());`
- L1727: `self::assertArrayHasKey('payment',$error->errors());`
- L1729: `self::assertSame($before,$db->table('quotations')->count());`
- L1730: `self::assertSame(5,$db->table('global_payments')->count());`
- L1733: `self::assertTrue($created['success']);`
- L1735: `self::assertSame('8000',$createdBalance['total']);`
- L1736: `self::assertSame('8000',$createdBalance['total_paid']);`
- L1737: `self::assertSame('0',$createdBalance['total_difference']);`

### `test_mobile_update_does_not_remove_a_concurrently_recorded_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1741](../../tests/Support/PaymentClassificationPersistenceCases.php#L1741).

- L1765: `self::assertSame(0,$db->table('global_payments')->count());`
- L1770: `self::assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());`
- L1772: `catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { self::assertSame(409,$error->getResponse()->getStatusCode()); }`
- L1778: `self::assertNotNull($payment->fresh());`
- L1779: `self::assertSame((int)$payment->id,(int)$received->fresh()->matched_document_payment_id);`
- L1780: `self::assertSame(1,$db->table('global_payments')->count());`
- L1782: `self::assertSame('account',data_get($movement->report_context,'method.collection_kind'));`
- L1783: `self::assertSame('USD',data_get($movement->report_context,'currency_type_id'));`
- L1784: `self::assertSame('1',Movement::row($movement)['amount']);`

### `test_mobile_capture_keeps_currency_and_rejects_a_conflicting_duplicate`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1787](../../tests/Support/PaymentClassificationPersistenceCases.php#L1787).

- L1801: `self::assertSame('USD',$result['data']['currency_type_id']);`
- L1802: `self::assertSame('99999999999999.99',$result['data']['amount_decimal']);`
- L1803: `self::assertFalse($result['duplicated']);`
- L1805: `self::assertTrue($controller->store($request)['duplicated']);`
- L1811: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }`
- L1817: `catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { self::assertSame(409,$error->getResponse()->getStatusCode()); }`
- L1818: `self::assertSame(1,$db->table('received_payments')->count());`
- L1840: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L1842: `self::assertTrue($response['success']);`
- L1846: `self::assertSame([false,true],$duplicates);`
- L1851: `self::assertSame(2,$db->table('received_payments')->count());`

### `test_contract_keeps_the_original_cross_currency_receipt_on_edit`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1854](../../tests/Support/PaymentClassificationPersistenceCases.php#L1854).

- L1875: `self::assertSame($snapshot['description'],$historical['payment_method_snapshot']['description']);`
- L1876: `self::assertSame('USD · USD',$historical['payment_destination_description']);`
- L1879: `self::assertSame($snapshot['description'],$serializedPayment['payment_method_snapshot']['description']);`
- L1880: `self::assertSame('USD · USD',$serializedPayment['payment_destination_description']);`
- L1886: `self::assertTrue($controller->store($edit)['success']);`
- L1887: `self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);`
- L1888: `self::assertSame('10',Movement::row($payment->fresh()->global_payment)['amount']);`
- L1889: `self::assertSame(1,$db->table('global_payments')->count());`
- L1897: `self::assertArrayHasKey('payments',$error->errors());`
- L1899: `self::assertSame('4000',$payment->fresh()->payment);`
- L1901: `self::assertTrue($controller->store($edit)['success']);`
- L1902: `self::assertSame(0,$db->table('contract_payments')->where('contract_id',$id)->count());`
- L1904: `self::assertArrayHasKey('reversal',$context);`

### `test_default_cash_orders_custom_ids_like_the_frontend`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1907](../../tests/Support/PaymentClassificationPersistenceCases.php#L1907).

- L1914: `self::assertSame('!', \App\Services\Payments\Classification::defaultCashMethod('VES')->id);`
- L1915: `self::assertSame('!', $db->transaction(fn()=>\App\Services\Payments\Classification::defaultCashMethod('VES')->id));`

### `test_cash_opening_keeps_its_own_currency_when_catalog_methods_change`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1918](../../tests/Support/PaymentClassificationPersistenceCases.php#L1918).

- L1935: `self::assertArrayHasKey('payment_method_type_id',$error->errors());`
- L1936: `self::assertStringContainsString('USD',$error->errors()['payment_method_type_id'][0]);`
- L1938: `self::assertSame(0,$db->table('cash_currency_balances')->where('cash_id',$cash->id)->count());`
- L1939: `self::assertSame(0,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());`
- L1940: `self::assertSame(0,$db->table('global_payments')->count());`
- L1949: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L1951: `self::assertSame(0,$db->table('cash_currency_balances')->where('cash_id',$cash->id)->count());`
- L1952: `self::assertSame(0,$db->table('global_payments')->count());`
- L1955: `self::assertSame(['04','13'],$db->table('cash_transactions')->where('cash_id',$cash->id)->orderBy('id')->pluck('payment_method_type_id')->all());`
- L1961: `self::assertSame('100',$report['balances_by_currency']['VES']['beginning_balance']);`
- L1962: `self::assertSame('5',$report['balances_by_currency']['USD']['beginning_balance']);`
- L1963: `self::assertCount(2,$report['rows']);`
- L1964: `self::assertSame(['cash','cash'],array_column(array_column($report['rows'],'method'),'collection_kind'));`
- L1965: `self::assertSame(['04','13'],array_column(array_column($report['rows'],'method'),'id'));`
- L1966: `self::assertSame(2,$db->table('global_payments')->count());`
- L1967: `self::assertTrue(CashLedger::close($cash)['success']);`
- L1971: `self::assertSame(2,$db->table('cash_currency_balances')->where('cash_id',$zero->id)->count());`
- L1972: `self::assertSame(0,$db->table('cash_transactions')->where('cash_id',$zero->id)->count());`

### `test_cash_opening_rechecks_concurrent_method_deactivation`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:1975](../../tests/Support/PaymentClassificationPersistenceCases.php#L1975).

- L1992: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L2002: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L2004: `self::assertContains('payment_method_type_id',$result['rejected'] ?? []);`
- L2009: `self::assertSame(0,$db->table('cash_currency_balances')->where('cash_id',$cash->id)->count());`
- L2010: `self::assertSame(0,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());`
- L2011: `self::assertSame(0,$db->table('global_payments')->count());`
- L2025: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L2035: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L2037: `self::assertTrue($result['success'] ?? false);`
- L2043: `self::assertSame('5',$report['balances_by_currency']['USD']['beginning_balance']);`
- L2044: `self::assertSame('cash',$report['rows'][1]['method']['collection_kind']);`
- L2045: `self::assertSame('USD',$report['rows'][1]['currency_type_id']);`
- L2046: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_hotel_retries_do_not_duplicate_a_received_payment`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2049](../../tests/Support/PaymentClassificationPersistenceCases.php#L2049).

- L2055: `self::assertSame(0,(int)$db->select("SHOW INDEX FROM hotel_rent_orders WHERE Key_name = 'hotel_rent_orders_operation_key_unique'")[0]->Non_unique);`
- L2067: `self::assertSame(200,$controller->addProductsToRoom($request,$rentId)->getStatusCode());`
- L2068: `self::assertSame(200,$controller->addProductsToRoom(\Modules\Hotel\Http\Requests\HotelRentItemRequest::create('/', 'POST', $payload),$rentId)->getStatusCode());`
- L2069: `self::assertSame(1,$db->table('hotel_rent_item_payments')->count());`
- L2070: `self::assertSame(1,$db->table('global_payments')->count());`
- L2076: `self::assertArrayHasKey('operation_key',$expected->errors());`
- L2078: `self::assertSame(1,$db->table('global_payments')->count());`
- L2099: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L2101: `self::assertTrue($response['success']);`
- L2107: `self::assertSame(2,$db->table('hotel_rent_item_payments')->count());`
- L2108: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_hotel_rolls_back_the_new_note_when_a_receipt_cannot_be_linked`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2111](../../tests/Support/PaymentClassificationPersistenceCases.php#L2111).

- L2136: `self::assertArrayHasKey('payment',$expected->errors());`
- L2138: `self::assertSame(0,$db->table('sale_notes')->count());`
- L2139: `self::assertSame(0,$db->table('hotel_rent_orders')->count());`
- L2140: `self::assertSame(0,$db->table('hotel_rent_items')->count());`

### `test_hotel_reuses_its_sale_note_receipt_without_creating_another_movement`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2143](../../tests/Support/PaymentClassificationPersistenceCases.php#L2143).

- L2169: `self::assertSame((int)$source->id,(int)$hotelPayment->source_sale_note_payment_id);`
- L2170: `self::assertSame('USD',$hotelPayment->currency_type_id);`
- L2171: `self::assertSame('5',$hotelPayment->original_amount);`
- L2172: `self::assertSame(1,$db->table('global_payments')->count());`
- L2179: `self::assertArrayHasKey('payment',$expected->errors());`
- L2181: `self::assertSame(1,$db->table('hotel_rent_item_payments')->count());`
- L2183: `catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('payment',$expected->errors()); }`

### `test_hotel_receipt_preserves_received_currency_and_checks_paid_total`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2186](../../tests/Support/PaymentClassificationPersistenceCases.php#L2186).

- L2204: `self::assertSame('4000',$payment->payment);`
- L2205: `self::assertSame('10',$payment->original_amount);`
- L2206: `self::assertSame('USD',$payment->currency_type_id);`
- L2207: `self::assertSame('10',Movement::row($payment->global_payment)['amount']);`
- L2215: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L2217: `self::assertSame('USD',$bank->fresh()->currency_type_id);`
- L2218: `self::assertSame('USD',Movement::row($payment->global_payment)['currency_type_id']);`
- L2221: `self::assertTrue($accountController->store($unusedEdit)['success']);`
- L2222: `self::assertSame('VES',$inactive->fresh()->currency_type_id);`
- L2230: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L2232: `self::assertTrue($accountController->store(\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', [`
- L2234: `self::assertSame('Cuenta actualizada',$bank->fresh()->description);`
- L2240: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L2242: `self::assertSame('USD',$opening->fresh()->currency_type_id);`
- L2246: `self::assertSame('10',$balance['balance']);`
- L2247: `self::assertSame('10',$balance['credits']);`

### `test_quotation_receipt_can_be_applied_to_an_invoice_without_a_second_movement`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2251](../../tests/Support/PaymentClassificationPersistenceCases.php#L2251).

- L2269: `self::assertTrue($controller->store($request)['success']);`
- L2284: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2287: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`
- L2291: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }`
- L2292: `self::assertSame(0,$db->table('documents')->count());`
- L2293: `self::assertSame(1,$db->table('global_payments')->count());`
- L2296: `self::assertSame(\Modules\Sale\Models\QuotationPayment::class,$applied->payment_method_snapshot['receipt_origin']['type']);`
- L2297: `self::assertSame((string)$source->id,$applied->payment_method_snapshot['receipt_origin']['id']);`
- L2298: `self::assertSame($frozen['description'],$applied->payment_method_snapshot['description']);`
- L2299: `self::assertSame('USD',$applied->currency_type_id);`
- L2300: `self::assertSame('5',$applied->original_amount);`
- L2301: `self::assertSame('50',$applied->payment);`
- L2302: `self::assertSame($source->exchange_rate_source,$applied->exchange_rate_source);`
- L2303: `self::assertSame($source->exchange_rate_date->format('Y-m-d'),$applied->exchange_rate_date->format('Y-m-d'));`
- L2304: `self::assertSame(1,$db->table('global_payments')->count());`
- L2306: `self::assertSame('0',$report['payment']);`
- L2307: `self::assertSame('50',$report['applied_payment']);`
- L2308: `self::assertSame($source->global_payment->report_context['destination_label'],$report['destination_description']);`
- L2310: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }`
- L2311: `self::assertSame(1,$db->table('quotation_payments')->where('id',$source->id)->count());`
- L2314: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }`
- L2315: `self::assertSame(1,$db->table('documents')->count());`
- L2316: `self::assertSame(1,$db->table('global_payments')->count());`
- L2319: `self::assertSame((string)$source->id,$replacement->payments()->firstOrFail()->payment_method_snapshot['receipt_origin']['id']);`
- L2320: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_quotation_receipt_is_reused_through_sale_note_and_invoice`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2323](../../tests/Support/PaymentClassificationPersistenceCases.php#L2323).

- L2346: `self::assertSame('10',$row['payment_method_type_id']);`
- L2350: `self::assertSame('02',$quotationData['quotation']['payment_condition_id']);`
- L2351: `self::assertSame('10',$quotationData['quotation']['payments'][0]['payment_method_type_id']);`
- L2352: `self::assertSame('USD',$quotationData['quotation']['payments'][0]['currency_type_id']);`
- L2354: `self::assertTrue($storeData['success']);`
- L2355: `self::assertSame('10',$storeData['data']['payments'][0]['payment_method_type_id']);`
- L2356: `self::assertSame('USD',$storeData['data']['payments'][0]['currency_type_id']);`
- L2357: `self::assertSame('02',$storeData['data']['payment_condition_id']);`
- L2358: `self::assertSame([],$storeData['data']['data_payments_fee']);`
- L2366: `self::assertSame('10',$copy->payment_method_type_id);`
- L2367: `self::assertSame(0,$copy->payments()->count());`
- L2368: `self::assertEmpty((array)$copy->prepayments);`
- L2369: `self::assertTrue($copy->exactValue('total_prepayment')->isZero());`
- L2370: `self::assertSame($originalSnapshot,$source->fresh()->payment_method_snapshot);`
- L2371: `self::assertSame(1,\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->count());`
- L2372: `self::assertSame(1,$db->table('global_payments')->count());`
- L2384: `self::assertFalse($rejected['success'],'La conversión no debe omitir el cobro de la cotización.');`
- L2385: `self::assertStringContainsString('cobro',mb_strtolower($rejected['message']));`
- L2386: `self::assertSame(0,$db->table('sale_notes')->count());`
- L2387: `self::assertSame(0,$db->table('sale_note_payments')->count());`
- L2388: `self::assertSame(1,$db->table('global_payments')->count());`
- L2394: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2395: `self::assertSame(0,$db->table('sale_notes')->count());`
- L2396: `self::assertSame(1,$db->table('global_payments')->count());`
- L2401: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2402: `self::assertSame(0,$db->table('documents')->count());`
- L2403: `self::assertSame(1,$db->table('global_payments')->count());`
- L2405: `self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota desde la cotización.');`
- L2408: `self::assertSame(\Modules\Sale\Models\QuotationPayment::class,$applied->payment_method_snapshot['receipt_origin']['type']);`
- L2409: `self::assertSame('10',$applied->payment_method_type_id);`
- L2410: `self::assertSame('USD',$applied->currency_type_id);`
- L2411: `self::assertSame('5',$applied->original_amount);`
- L2412: `self::assertSame('50',$applied->payment);`
- L2413: `self::assertNull($applied->global_payment);`
- L2414: `self::assertSame((string)$bank->id,$applied->payment_method_snapshot['receipt_origin']['destination_id']);`
- L2416: `self::assertSame((string)$bank->id,(string)$noteRows[0]['payment_destination_id']);`
- L2417: `self::assertSame([],$db->transaction(fn()=>\App\Services\Payments\ExistingReceipts::synchronize($note,$noteRows,'VES')));`
- L2418: `self::assertSame(1,$db->table('global_payments')->count());`
- L2422: `self::assertFalse($duplicate['success']);`
- L2423: `self::assertStringContainsString('cobro',mb_strtolower($duplicate['message']));`
- L2424: `self::assertSame(1,$db->table('sale_notes')->where('quotation_id',$quotationId)->count());`
- L2425: `self::assertSame(1,$db->table('sale_note_payments')->count());`
- L2426: `self::assertSame(1,$db->table('global_payments')->count());`
- L2432: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }`
- L2433: `self::assertSame(1,$db->table('global_payments')->count());`
- L2435: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }`
- L2437: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }`
- L2443: `self::assertNotContains($bank->id,$currentDestinations->pluck('id')->all());`
- L2445: `self::assertSame('USD · USD',$displayRows[0]['payment_destination_description']);`
- L2448: `self::assertSame((string)$bank->id,(string)$input['payments'][0]['payment_destination_id']);`
- L2451: `self::assertSame(\App\Models\Tenant\SaleNotePayment::class,$invoicePayment->payment_method_snapshot['receipt_origin']['type']);`
- L2452: `self::assertSame($source->payment_method_snapshot['description'],$invoicePayment->payment_method_snapshot['description']);`
- L2453: `self::assertSame('10',$invoicePayment->payment_method_type_id);`
- L2454: `self::assertSame('5',$invoicePayment->original_amount);`
- L2455: `self::assertSame('50',$invoicePayment->payment);`
- L2456: `self::assertSame(1,$db->table('global_payments')->count());`
- L2458: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }`
- L2459: `self::assertSame(1,$db->table('sale_note_payments')->count());`
- L2463: `self::assertSame(0, \App\Models\Tenant\DocumentTax::where('document_payment_id', $invoicePayment->id)->count());`
- L2478: `self::assertSame('50', $subjectPayment->payment);`
- L2479: `self::assertSame('0.15', $subjectPayment->tax_amount);`
- L2482: `self::assertSame('IGTF', $taxNote->note->note_debit_type_id);`
- L2483: `self::assertEquals(1.5, $taxNote->total);`
- L2484: `self::assertEquals(0, $taxNote->payments()->firstOrFail()->cash_received_amount);`
- L2485: `self::assertSame($subjectPayment->id, $fiscal::payment($document, $newReceipt)->id);`
- L2486: `self::assertSame(1, \App\Models\Tenant\DocumentTax::where('document_payment_id', $subjectPayment->id)->count());`
- L2487: `self::assertSame(2, $db->table('global_payments')->count());`
- L2488: `self::assertSame('5.15', Movement::row($subjectPayment->global_payment)['amount']);`
- L2489: `self::assertNull($taxNote->payments()->firstOrFail()->global_payment);`
- L2491: `self::assertSame('11', $taxNote->fresh()->state_type_id);`
- L2492: `self::assertNotNull($subjectPayment->fresh()->reversed_at);`
- L2493: `self::assertSame(2, $db->table('global_payments')->count());`
- L2494: `self::assertNull(Movement::row($subjectPayment->fresh()->global_payment));`
- L2495: `self::assertSame('5', Movement::row($source->fresh()->global_payment)['amount']);`
- L2496: `self::assertSame('USD', $source->fresh()->currency_type_id);`
- L2497: `self::assertSame('account', $source->fresh()->payment_method_snapshot['collection_kind']);`

### `test_quotation_conversion_requires_all_mixed_receipts`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2500](../../tests/Support/PaymentClassificationPersistenceCases.php#L2500).

- L2524: `self::assertTrue($payments->store($request)['success']);`
- L2535: `self::assertFalse($rejected['success']);`
- L2536: `self::assertStringContainsString('cobro',mb_strtolower($rejected['message']));`
- L2537: `self::assertSame(0,$db->table('sale_notes')->count());`
- L2538: `self::assertSame(2,$db->table('global_payments')->count());`
- L2541: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2542: `self::assertSame(0,$db->table('documents')->count());`
- L2545: `self::assertTrue($saved['success'],$saved['message'] ?? 'No se pudo aplicar los abonos.');`
- L2546: `self::assertSame(2,$db->table('sale_note_payments')->count());`
- L2547: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_quotation_conversion_rechecks_receipts_after_a_concurrent_payment`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2550](../../tests/Support/PaymentClassificationPersistenceCases.php#L2550).

- L2579: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$worker->getErrorOutput(),$match));`
- L2589: `self::assertSame(0,$worker->wait(),$worker->getErrorOutput());`
- L2591: `self::assertContains('payments',$result['rejected'] ?? []);`
- L2596: `self::assertSame(1,$db->table('quotation_payments')->where('quotation_id',$quotationId)->count());`
- L2597: `self::assertSame(1,$db->table('global_payments')->count());`
- L2598: `self::assertSame(0,$db->table('sale_notes')->count());`
- L2599: `self::assertSame(0,$db->table('documents')->count());`

### `test_simultaneous_quotation_receipt_applications_allow_only_one_sale_note`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2602](../../tests/Support/PaymentClassificationPersistenceCases.php#L2602).

- L2638: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L2650: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L2655: `self::assertSame(1,$accepted);`
- L2656: `self::assertSame(1,$rejected);`
- L2661: `self::assertSame(1,$db->table('sale_note_payments')->whereIn('sale_note_id',$noteIds)->count());`
- L2662: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_sale_note_receipt_deletion_and_invoice_reuse_are_serialized`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2665](../../tests/Support/PaymentClassificationPersistenceCases.php#L2665).

- L2687: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2688: `self::assertSame(0,$db->table('documents')->count());`
- L2689: `self::assertSame(1,$db->table('global_payments')->count());`
- L2706: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L2719: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L2724: `self::assertSame(1,$accepted);`
- L2725: `self::assertSame(1,$rejected);`
- L2730: `self::assertSame(1,$db->table('global_payments')->count());`
- L2732: `self::assertTrue(($documents === 1 && $db->table('sale_note_payments')->where('id',$source->id)->exists())`

### `test_multiple_sale_notes_preserve_receipts_and_reject_omissions`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2737](../../tests/Support/PaymentClassificationPersistenceCases.php#L2737).

- L2758: `self::assertCount(2,$preview['payments']);`
- L2759: `self::assertSame(['USD','VES'],array_column($preview['payments'],'currency_type_id'));`
- L2760: `self::assertSame(['10','04'],array_column($preview['payments'],'payment_method_type_id'));`
- L2764: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2765: `self::assertSame(0,$db->table('documents')->count());`
- L2766: `self::assertSame(2,$db->table('global_payments')->count());`
- L2772: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2773: `self::assertSame(0,$db->table('documents')->count());`
- L2774: `self::assertSame(3,$db->table('global_payments')->count());`
- L2779: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_notes_relateds',$error->errors()); }`
- L2780: `self::assertSame(0,$db->table('documents')->count());`
- L2786: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_sale_note_payment_id',$error->errors()); }`
- L2787: `self::assertSame(0,$db->table('documents')->count());`
- L2788: `self::assertSame(0,$db->table('sale_notes')->whereIn('id',$noteIds)->whereNotNull('document_id')->count());`
- L2793: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L2794: `self::assertSame(0,$db->table('documents')->count());`
- L2795: `self::assertSame(3,$db->table('global_payments')->count());`
- L2798: `self::assertCount(3,$applied);`
- L2799: `self::assertSame(['USD','VES','USD'],$applied->pluck('currency_type_id')->all());`
- L2800: `self::assertSame(['5','20','1'],$applied->map(fn($row)=>\App\Support\Exact\Decimal::represent($row->original_amount))->all());`
- L2801: `self::assertSame(3,$db->table('global_payments')->count());`
- L2802: `self::assertSame([$document->id,$document->id],$db->table('sale_notes')->whereIn('id',$noteIds)->orderBy('id')->pluck('document_id')->all());`
- L2804: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_note_id',$error->errors()); }`
- L2805: `self::assertSame($document->id,$db->table('sale_notes')->where('id',$noteIds[0])->value('document_id'));`
- L2806: `self::assertCount(2,json_decode($db->table('documents')->where('id',$document->id)->value('sale_notes_relateds'),true));`
- L2808: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_notes_relateds',$error->errors()); }`
- L2809: `self::assertSame(1,$db->table('documents')->count());`
- L2810: `self::assertSame(3,$db->table('global_payments')->count());`
- L2813: `self::assertSame((string)$input['payments'][0]['id'],$resolved['id']);`
- L2814: `self::assertNotNull($applied[0]->fresh()->reversed_at);`
- L2815: `self::assertSame($document->id,$db->table('sale_notes')->where('id',$noteIds[0])->value('document_id'));`
- L2816: `self::assertSame(3,$db->table('global_payments')->count());`

### `test_concurrent_invoices_cannot_reuse_an_unpaid_sale_note`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2821](../../tests/Support/PaymentClassificationPersistenceCases.php#L2821).

- L2848: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L2860: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L2865: `self::assertSame(1,$accepted);`
- L2866: `self::assertSame(1,$rejected);`
- L2867: `self::assertSame(1,$db->table('documents')->count());`
- L2868: `self::assertSame($db->table('documents')->value('id'),$db->table('sale_notes')->where('id',$note->id)->value('document_id'));`
- L2869: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2877](../../tests/Support/PaymentClassificationPersistenceCases.php#L2877).

- L2898: `self::assertTrue($paymentController->store($request)['success']);`
- L2903: `self::assertSame('2026-10-26',$row['date_of_payment']);`
- L2904: `self::assertSame('USD',$row['currency_type_id']);`
- L2905: `self::assertSame((string)$bank->id,(string)$row['payment_destination_id']);`
- L2906: `self::assertArrayNotHasKey('global_payment',$row);`
- L2916: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }`
- L2917: `self::assertSame(0,$db->table('documents')->count());`
- L2920: `self::assertSame(\Modules\Sale\Models\TechnicalServicePayment::class,$applied->payment_method_snapshot['receipt_origin']['type']);`
- L2921: `self::assertSame($frozen['description'],$applied->payment_method_snapshot['description']);`
- L2922: `self::assertSame('USD',$applied->currency_type_id);`
- L2923: `self::assertSame('5',$applied->original_amount);`
- L2924: `self::assertSame('50',$applied->payment);`
- L2925: `self::assertNull($applied->global_payment);`
- L2926: `self::assertSame(2,$db->table('global_payments')->count());`
- L2928: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }`
- L2931: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }`
- L2932: `self::assertSame(1,$db->table('documents')->count());`
- L2939: `self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota del servicio.');`
- L2942: `self::assertSame(\Modules\Sale\Models\TechnicalServicePayment::class,$notePayment->payment_method_snapshot['receipt_origin']['type']);`
- L2943: `self::assertSame((string)$bank->id,(string)$notePayment->payment_method_snapshot['receipt_origin']['destination_id']);`
- L2944: `self::assertSame('5',$notePayment->original_amount);`
- L2945: `self::assertNull($notePayment->global_payment);`
- L2947: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }`
- L2951: `self::assertSame((string)$notePayment->id,$invoice->payments()->firstOrFail()->payment_method_snapshot['receipt_origin']['id']);`
- L2952: `self::assertSame('5',$invoice->payments()->firstOrFail()->original_amount);`
- L2953: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_simultaneous_technical_receipt_applications_allow_only_one_sale_note`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:2956](../../tests/Support/PaymentClassificationPersistenceCases.php#L2956).

- L2993: `self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));`
- L3005: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L3010: `self::assertSame(1,$accepted);`
- L3011: `self::assertSame(1,$rejected);`
- L3016: `self::assertSame(1,$db->table('sale_note_payments')->whereIn('sale_note_id',$noteIds)->count());`
- L3017: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_order_draft_payment_creates_money_only_when_generating_the_invoice`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3020](../../tests/Support/PaymentClassificationPersistenceCases.php#L3020).

- L3036: `self::assertTrue($result['success']);`
- L3038: `self::assertSame('USD',data_get($order->prepayments,'0.currency_type_id'));`
- L3039: `self::assertSame(0,$db->table('global_payments')->count());`
- L3046: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }`
- L3047: `self::assertSame(0,$db->table('documents')->count());`
- L3048: `self::assertSame(0,$db->table('global_payments')->count());`
- L3051: `self::assertSame('USD',$receipt->currency_type_id);`
- L3052: `self::assertSame('5',$receipt->original_amount);`
- L3053: `self::assertSame('50',$receipt->payment);`
- L3054: `self::assertSame(1,$db->table('global_payments')->count());`
- L3055: `self::assertSame('5',Movement::row($receipt->global_payment)['amount']);`

### `test_order_with_price_entered_without_iva`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3058](../../tests/Support/PaymentClassificationPersistenceCases.php#L3058).

- L3073: `self::assertTrue($result['success']);`
- L3075: `self::assertSame('116',\App\Support\Exact\Decimal::represent($order->exactValue('total')));`
- L3077: `self::assertSame('100',data_get($line->calculation_snapshot,'inputs.price_input.amount'));`
- L3078: `self::assertFalse(data_get($line->calculation_snapshot,'inputs.price_input.includes_iva'));`
- L3083: `self::assertSame('100',data_get($line->calculation_snapshot,'inputs.price_input.amount'));`
- L3084: `self::assertFalse(data_get($line->calculation_snapshot,'inputs.price_input.includes_iva'));`
- L3085: `self::assertSame('116',\App\Support\Exact\Decimal::represent($order->fresh()->exactValue('total')));`

### `test_technical_document_failure_rolls_back_the_temporary_service_item`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3088](../../tests/Support/PaymentClassificationPersistenceCases.php#L3088).

- L3105: `self::assertFalse($response['success']);`
- L3106: `self::assertStringContainsString('No se puede copiar un cobro registrado',$response['message']);`
- L3107: `self::assertSame(0,$db->table('items')->where('internal_id','TECH-ROLLBACK-UNIQUE')->count());`
- L3108: `self::assertSame(0,$db->table('sale_notes')->count());`
- L3109: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_payment_destination_catalog_loads_bank_names_in_one_query`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3112](../../tests/Support/PaymentClassificationPersistenceCases.php#L3112).

- L3127: `self::assertCount(1,$first);`
- L3128: `self::assertCount(2,$both);`
- L3129: `self::assertSame(['VES','USD'],$both->pluck('currency_type_id')->all());`
- L3130: `self::assertSame(1,$firstQueries);`
- L3131: `self::assertSame($firstQueries,$bothQueries,'El catálogo no debe consultar un banco por cada cuenta.');`
- L3132: `self::assertStringContainsString('Cuenta USD',$both->last()['description']);`
- L3134: `self::assertCount(2,$catalog->getPaymentDestinations());`

### `test_receipt_dialog_catalogs_exclude_credit_and_unclassified_methods`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3137](../../tests/Support/PaymentClassificationPersistenceCases.php#L3137).

- L3154: `self::assertContains('01',$methods->pluck('id')->all());`
- L3155: `self::assertContains('04',$methods->pluck('id')->all());`
- L3156: `self::assertContains('06',$methods->pluck('id')->all());`
- L3157: `self::assertNotContains('05',$methods->pluck('id')->all());`
- L3158: `self::assertNotContains('12',$methods->pluck('id')->all());`
- L3159: `self::assertEmpty($methods->filter(fn($method)=>!in_array($method->collection_kind,['cash','account'],true)));`

### `test_technical_service_without_cash_and_repeated_edits_do_not_duplicate_cash_link`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3163](../../tests/Support/PaymentClassificationPersistenceCases.php#L3163).

- L3172: `self::assertTrue($controller->store($request)['success']);`
- L3174: `self::assertSame(0,$db->table('cash_documents')->where('technical_service_id',$id)->count());`
- L3175: `self::assertSame(0,$db->table('global_payments')->count());`
- L3182: `self::assertTrue($controller->store($edit)['success']);`
- L3184: `self::assertSame(1,$db->table('cash_documents')->where('cash_id',$cash->id)->where('technical_service_id',$id)->count());`
- L3185: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_sale_note_without_open_cash_explains_rejection_and_rolls_back`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3188](../../tests/Support/PaymentClassificationPersistenceCases.php#L3188).

- L3196: `self::assertStringContainsString('Abra una caja',$error->errors()['cash'][0]);`
- L3198: `self::assertSame(0,$db->table('sale_notes')->count());`
- L3199: `self::assertSame(0,$db->table('cash_documents')->count());`
- L3200: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_quotation_receipt_keeps_received_dollars_and_applied_bolivars`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3203](../../tests/Support/PaymentClassificationPersistenceCases.php#L3203).

- L3218: `self::assertContains($bank->id,$destinations->pluck('id')->all());`
- L3219: `self::assertNotContains($inactive->id,$destinations->pluck('id')->all());`
- L3225: `self::assertTrue((new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store($request)['success']);`
- L3227: `self::assertSame('4000',$payment->payment);`
- L3228: `self::assertSame('10',$payment->original_amount);`
- L3229: `self::assertSame('USD',$payment->currency_type_id);`
- L3230: `self::assertSame('10',Movement::row($payment->global_payment)['amount']);`
- L3234: `self::assertSame('USD',$movement['currency_type_id']);`
- L3235: `self::assertSame('10.00',$movement['input']);`
- L3236: `self::assertSame('10.00',$movement['balance']);`
- L3239: `self::assertSame('USD',$mobile['currency_type_id']);`
- L3240: `self::assertSame('10',$mobile['amount_decimal']);`
- L3241: `self::assertSame('4000',$mobile['amount_pen_decimal']);`
- L3251: `self::assertTrue($controller->update($edit)['success']);`
- L3252: `self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);`
- L3253: `self::assertSame(1,$db->table('global_payments')->count());`
- L3254: `self::assertSame('USD',$payment->fresh()->currency_type_id);`
- L3262: `self::assertArrayHasKey('original_amount',$error->errors());`
- L3264: `self::assertSame('10',$payment->fresh()->original_amount);`
- L3265: `self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);`
- L3266: `self::assertSame(1,$db->table('global_payments')->count());`
- L3269: `self::assertSame('10',$balance['quotation_payment']);`
- L3270: `self::assertSame('10',$balance['balance']);`
- L3272: `self::assertTrue($controller->update($edit)['success']);`
- L3273: `self::assertSame(0,$db->table('quotation_payments')->where('quotation_id',$quotationId)->count());`
- L3275: `self::assertArrayHasKey('reversal',$context);`
- L3276: `self::assertSame('0',$finance->getBalanceBySingleBankAcount($bank->fresh())['balance']);`

### `test_quotation_reclassified_bank_method_checks_currency_and_keeps_snapshot`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3279](../../tests/Support/PaymentClassificationPersistenceCases.php#L3279).

- L3306: `self::assertNotEmpty($error->errors());`
- L3308: `self::assertSame(0,$db->table('quotation_payments')->count());`
- L3309: `self::assertSame(0,$db->table('global_payments')->count());`
- L3311: `self::assertTrue($controller->store(\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $data+['payment_destination_id'=>$usd->id]))['success']);`
- L3313: `self::assertSame('10',$payment->original_amount);`
- L3314: `self::assertSame('4000',$payment->payment);`
- L3315: `self::assertSame('USD',$payment->currency_type_id);`
- L3316: `self::assertSame('account',Movement::row($payment->global_payment)['method']['collection_kind']);`
- L3317: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['income']);`
- L3320: `self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);`
- L3321: `self::assertSame('account',Movement::row($payment->global_payment->fresh())['method']['collection_kind']);`
- L3322: `self::assertSame('10',Movement::row($payment->global_payment->fresh())['amount']);`

### `test_whatsapp_confirmed_usd_price_keeps_rate_and_receipt_in_ves`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3325](../../tests/Support/PaymentClassificationPersistenceCases.php#L3325).

- L3336: `self::assertSame('error',$tool->execute($arguments)['status']);`
- L3337: `self::assertSame(0,$db->table('documents')->count());`
- L3340: `self::assertSame('ready_for_confirmation',$result['status'],json_encode($result));`
- L3341: `self::assertStringContainsString('10.00000000', $result['summary_text']);`
- L3342: `self::assertStringContainsString('Total: Bs. 3.00', $result['summary_text']);`
- L3349: `self::assertSame('10.00000000',$payload['exchange_rate_sale']);`
- L3350: `self::assertSame('3.00',$payload['payments'][0]['payment']);`
- L3351: `self::assertSame('3', \App\Support\Exact\Decimal::represent($payload['items'][0]['item']['sale_unit_price']));`
- L3352: `self::assertSame('demo',$payload['fiscal_environment']);`
- L3355: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('total',$error->errors()); }`
- L3358: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }`
- L3359: `self::assertSame(0,$db->table('documents')->count());`
- L3360: `self::assertSame(0,$db->table('global_payments')->count());`
- L3362: `self::assertSame('3',\App\Support\Exact\Decimal::represent($document->fresh()->exactValue('total')));`
- L3364: `self::assertSame('3',$payment->payment);`
- L3365: `self::assertSame('VES',$payment->currency_type_id);`
- L3366: `self::assertSame('cash',Movement::row($payment->global_payment)['method']['collection_kind']);`
- L3367: `self::assertSame(1,$db->table('global_payments')->count());`
- L3372: `self::assertSame('ready_for_confirmation',$discounted['status'],json_encode($discounted));`
- L3373: `self::assertStringContainsString('Total: Bs. 1.84',$discounted['summary_text']);`
- L3376: `self::assertSame('1.84',\App\Support\Exact\Decimal::represent($discountedDocument->fresh()->exactValue('total')));`
- L3377: `self::assertSame('1.84',$discountedDocument->payments()->firstOrFail()->payment);`
- L3378: `self::assertSame(2,$db->table('global_payments')->count());`

### `test_whatsapp_orchestrator_persists_the_confirmed_draft_with_a_mock_sender`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3381](../../tests/Support/PaymentClassificationPersistenceCases.php#L3381).

- L3398: `self::assertSame('ready_for_confirmation',$draft['status'],json_encode($draft));`
- L3409: `self::assertTrue($result['success'], $result['message'].' '.($logger->error ?? ''));`
- L3410: `self::assertStringContainsString('Total: Bs. 3.00',$result['message']);`
- L3411: `self::assertStringContainsString('no se pudo enviar el PDF',$result['message']);`
- L3417: `self::assertTrue($retry['success'],$retry['message']);`
- L3418: `self::assertSame($originalContext,$originalPayment->global_payment->fresh()->report_context);`
- L3419: `self::assertSame('cash',Movement::row($originalPayment->global_payment->fresh())['method']['collection_kind']);`
- L3420: `self::assertSame('VES',Movement::row($originalPayment->global_payment->fresh())['currency_type_id']);`
- L3423: `self::assertFalse($noCash['success']);`
- L3424: `self::assertStringContainsString('efectivo activo en VES',$noCash['message']);`
- L3427: `self::assertFalse($conflict['success']);`
- L3428: `self::assertStringContainsString('otros datos',$conflict['message']);`
- L3429: `self::assertSame(1,$db->table('documents')->count());`
- L3431: `self::assertSame('3',\App\Support\Exact\Decimal::represent($document->exactValue('total')));`
- L3432: `self::assertSame('3',$document->payments()->firstOrFail()->payment);`
- L3433: `self::assertSame(1,$db->table('global_payments')->count());`
- L3436: `self::assertFalse($reversed['success']);`
- L3437: `self::assertStringContainsString('revertidos',$reversed['message']);`
- L3438: `self::assertSame(1,$db->table('documents')->count());`
- L3439: `self::assertSame(1,$db->table('document_payments')->count());`

### `test_whatsapp_concurrent_confirmations_reuse_the_same_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3442](../../tests/Support/PaymentClassificationPersistenceCases.php#L3442).

- L3454: `self::assertSame('ready_for_confirmation',$prepared['status'],json_encode($prepared));`
- L3463: `} catch (\RuntimeException $error) { self::assertSame('Fallo simulado antes de confirmar',$error->getMessage()); }`
- L3464: `self::assertSame(0,$db->table('documents')->count());`
- L3465: `self::assertSame(0,$db->table('document_payments')->count());`
- L3466: `self::assertSame(0,$db->table('global_payments')->count());`
- L3483: `self::assertTrue($process->isRunning());`
- L3488: `self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());`
- L3495: `self::assertSame($ids[0],$ids[1]);`
- L3496: `self::assertSame(1,$db->table('documents')->count());`
- L3497: `self::assertSame(1,$db->table('document_payments')->count());`
- L3498: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_whatsapp_draft_requires_open_cash_and_uses_current_cash_classification`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3501](../../tests/Support/PaymentClassificationPersistenceCases.php#L3501).

- L3511: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }`
- L3512: `self::assertSame(0,$db->table('documents')->count());`
- L3516: `self::assertSame('04',$payload['payment_method_type_id']);`
- L3517: `self::assertSame('04',$payload['payments'][0]['payment_method_type_id']);`
- L3518: `self::assertSame('cash',$payload['payments'][0]['payment_destination_id']);`
- L3519: `self::assertSame('VES',$payload['currency_type_id']);`
- L3522: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }`
- L3523: `self::assertSame(0,$db->table('documents')->count());`

### `test_store_import_preflights_all_payment_methods_before_creating_customers`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3526](../../tests/Support/PaymentClassificationPersistenceCases.php#L3526).

- L3542: `self::assertArrayHasKey('payment_method_type_id',$error->errors());`
- L3544: `self::assertSame($before,$db->table('persons')->count());`
- L3545: `self::assertSame(0,$db->table('order_notes')->count());`
- L3555: `self::assertStringContainsString('Boletas',$error->getMessage());`
- L3557: `self::assertSame($before,$db->table('persons')->count());`
- L3558: `self::assertSame(0,$db->table('order_notes')->count());`
- L3567: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L3569: `self::assertSame($before,$db->table('persons')->count());`
- L3570: `self::assertSame(0,$db->table('order_notes')->count());`
- L3578: `self::assertArrayHasKey('payment_destination_id',$error->errors());`
- L3580: `self::assertSame($before,$db->table('persons')->count());`
- L3581: `self::assertSame(0,$db->table('order_notes')->count());`

### `test_storefront_quotation_builder_uses_venezuelan_tax_rate`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3584](../../tests/Support/PaymentClassificationPersistenceCases.php#L3584).

- L3595: `self::assertCount(1,$result['items']);`
- L3596: `self::assertEquals(16,$result['items'][0]['percentage_igv']);`
- L3597: `self::assertEquals(100,$result['items'][0]['total_value']);`
- L3598: `self::assertEquals(16,$result['items'][0]['total_igv']);`
- L3599: `self::assertEquals(116,$result['totals']['total']);`
- L3601: `self::assertEquals(0,$hidden['totals']['total']);`
- L3602: `self::assertEquals(116,$hidden['items'][0]['item']['suggested_unit_price']);`

### `test_storefront_quotation_rejects_missing_cash_before_creating_guest`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3605](../../tests/Support/PaymentClassificationPersistenceCases.php#L3605).

- L3625: `self::assertSame(422,$response->getStatusCode());`
- L3626: `self::assertStringContainsString('efectivo',strtolower($response->getData(true)['message']));`
- L3627: `self::assertSame($before,$db->table('persons')->count());`
- L3628: `self::assertSame(0,$db->table('quotations')->count());`
- L3629: `self::assertSame(0,$db->table('global_payments')->count());`
- L3651: `self::assertSame(422,$missingRate->getStatusCode(),$missingRate->getContent());`
- L3652: `self::assertStringContainsString('tasa',strtolower($missingRate->getData(true)['message']));`
- L3653: `self::assertSame($before,$db->table('persons')->count());`
- L3654: `self::assertSame(0,$db->table('quotations')->count());`
- L3662: `self::assertSame(200,$response->getStatusCode(),$response->getContent());`
- L3664: `self::assertSame('04',$quotation->payment_method_type_id);`
- L3665: `self::assertSame('VES',$quotation->currency_type_id);`
- L3666: `self::assertSame(1,$quotation->items()->count());`
- L3667: `self::assertSame(0,$db->table('global_payments')->count());`
- L3670: `self::assertTrue($line->exactValue('unit_price')->isEqualTo($expected));`
- L3671: `self::assertTrue($quotation->exactValue('total')->isEqualTo($expected));`
- L3672: `self::assertTrue($quotation->exactValue('subtotal')->isEqualTo($expected->dividedBy('1.16')));`
- L3673: `self::assertEquals(['n'=>'1','d'=>'3'],data_get($line->calculation_snapshot,'inputs.storefront_catalog_price.amount'));`
- L3674: `self::assertSame('10.12345678',data_get($line->calculation_snapshot,'inputs.storefront_catalog_price.exchange_rate'));`
- L3681: `self::assertNotNull($line);`
- L3682: `self::assertTrue($line->exactValue('unit_price')->isEqualTo($expected));`
- L3683: `self::assertTrue($quotation->fresh()->exactValue('total')->isEqualTo($expected));`
- L3684: `self::assertEquals(['n'=>'1','d'=>'3'],data_get($line->calculation_snapshot,'inputs.storefront_catalog_price.amount'));`
- L3685: `self::assertSame(0,$db->table('global_payments')->count());`
- L3689: `self::assertTrue($copy->items()->firstOrFail()->exactValue('unit_price')->isEqualTo($expected));`
- L3690: `self::assertTrue($copy->exactValue('total')->isEqualTo($expected));`
- L3691: `self::assertEmpty((array)$copy->prepayments);`
- L3692: `self::assertSame(0,$copy->payments()->count());`
- L3693: `self::assertSame(0,$db->table('global_payments')->count());`
- L3699: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }`
- L3700: `self::assertTrue($line->fresh()->exactValue('unit_price')->isEqualTo($expected));`
- L3704: `self::assertSame('4',\App\Support\Exact\Decimal::represent($line->fresh()->exactValue('unit_price')));`
- L3705: `self::assertNull(data_get($line->fresh()->calculation_snapshot,'inputs.storefront_catalog_price'));`

### `test_store_import_generates_credit_and_reclassified_bank_invoices`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3708](../../tests/Support/PaymentClassificationPersistenceCases.php#L3708).

- L3750: `self::assertSame('02',$credit->payment_condition_id);`
- L3751: `self::assertCount(0,$credit->payments);`
- L3752: `self::assertSame(0,$db->table('global_payments')->count());`
- L3757: `self::assertSame('01',$payment->payment_method_type_id);`
- L3758: `self::assertSame('VES',$payment->currency_type_id);`
- L3759: `self::assertSame('account',$payment->global_payment->report_context['method']['collection_kind']);`
- L3760: `self::assertSame((string)$bank->id,(string)$payment->global_payment->destination_id);`
- L3761: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_purchase_import_uses_classified_cash_and_creates_one_movement`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3764](../../tests/Support/PaymentClassificationPersistenceCases.php#L3764).

- L3775: `self::assertTrue($result['success'],$result['message'] ?? 'Importación fallida');`
- L3777: `self::assertSame('04',$payment->payment_method_type_id);`
- L3778: `self::assertSame('cash',Movement::row($payment->global_payment)['method']['collection_kind']);`
- L3779: `self::assertSame('egress',Movement::row($payment->global_payment)['direction']);`
- L3780: `self::assertSame('12',Movement::row($payment->global_payment)['amount']);`
- L3781: `self::assertSame(1,$db->table('global_payments')->count());`
- L3784: `self::assertFalse($result['success']);self::assertSame(1,$db->table('purchases')->count());self::assertSame(1,$db->table('global_payments')->count());`

### `test_purchase_update_preserves_recorded_payment_after_reclassification_mysql`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3787](../../tests/Support/PaymentClassificationPersistenceCases.php#L3787).

- L3812: `self::assertTrue($controller->update($request)['success']);`
- L3813: `self::assertSame(1,$db->table('purchase_payments')->where('purchase_id',$id)->count());`
- L3814: `self::assertSame($context,$global->fresh()->report_context);`
- L3815: `self::assertSame('12',$payment->fresh()->payment);`
- L3818: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }`
- L3819: `self::assertSame('12',$payment->fresh()->payment);`
- L3820: `self::assertSame($context,$global->fresh()->report_context);`
- L3824: `self::assertTrue($paymentController->destroy($payment->id)['success']);`
- L3827: `self::assertNotNull($payment->fresh());`
- L3828: `self::assertArrayNotHasKey('reversal',$global->fresh()->report_context);`
- L3830: `self::assertTrue($deleted['success']);`
- L3831: `self::assertNull($payment->fresh());`
- L3832: `self::assertArrayHasKey('reversal',$global->fresh()->report_context);`
- L3833: `self::assertNull(Movement::row($global->fresh()));`

### `test_quotation_resource_preserves_dollar_cash_method`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3836](../../tests/Support/PaymentClassificationPersistenceCases.php#L3836).

- L3852: `self::assertSame('10', $result['quotation']['payment_method_type_id']);`
- L3853: `self::assertSame('01', $result['quotation']['payment_condition_id']);`
- L3857: `self::assertSame('02', $result['quotation']['payment_condition_id']);`

### `test_global_payment_export_reports_failures_and_uses_distinct_files`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3860](../../tests/Support/PaymentClassificationPersistenceCases.php#L3860).

- L3874: `\Illuminate\Support\Facades\Log::shouldReceive('info')->zeroOrMoreTimes();`
- L3880: `catch (\Throwable $e) { self::assertInstanceOf(\Illuminate\Database\Eloquent\ModelNotFoundException::class,$e); }`
- L3882: `self::assertSame('IN_PROCESS',$tray->status);`
- L3883: `self::assertNull($tray->date_end);`
- L3886: `catch (\Throwable $e) { self::assertInstanceOf(\Carbon\Exceptions\InvalidFormatException::class,$e); }`
- L3888: `self::assertSame('FAILED',$tray->status);`
- L3889: `self::assertNotNull($tray->date_end);`
- L3890: `self::assertNull($tray->file_name);`
- L3898: `catch (\Throwable $e) { self::assertSame('No se pudo guardar el reporte de pagos.',$e->getMessage()); }`
- L3900: `self::assertSame('FAILED',$tray->status);`
- L3901: `self::assertNull($tray->file_name);`
- L3916: `self::assertSame('FINISHED',$tray->status);`
- L3918: `self::assertFileExists($file);`
- L3920: `self::assertGreaterThan(0,$book->getSheetCount());`
- L3928: `self::assertSame('FINISHED',$other->status);`
- L3929: `self::assertNotSame($tray->file_name,$other->file_name);`
- L3930: `self::assertStringEndsWith('_'.$tray->id,$tray->file_name);`
- L3931: `self::assertStringEndsWith('_'.$other->id,$other->file_name);`
- L3932: `self::assertFileExists($otherFile);`
- L3950: `\Illuminate\Support\Facades\Storage::shouldReceive('disk')->once()->with('tenant')->andReturn($disk);`
- L3954: `catch (\Throwable $e) { self::assertSame('No se pudo guardar el reporte de pagos.',$e->getMessage()); }`
- L3959: `self::assertSame('FAILED',$pdfTray->status);`
- L3960: `self::assertNull($pdfTray->file_name);`
- L3964: `self::assertSame('FINISHED',$pdfTray->status);`
- L3966: `self::assertSame('%PDF-archivo-de-prueba',file_get_contents($pdfFile));`
- L3967: `self::assertStringEndsWith('_'.$pdfTray->id,$pdfTray->file_name);`

### `test_movement_export_records_failure_and_success`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:3973](../../tests/Support/PaymentClassificationPersistenceCases.php#L3973).

- L3987: `\Illuminate\Support\Facades\Log::shouldReceive('debug')->zeroOrMoreTimes();`
- L3995: `self::assertInstanceOf(\Carbon\Exceptions\InvalidFormatException::class,$exception);`
- L3998: `self::assertSame('FAILED',$tray->status);`
- L3999: `self::assertNotNull($tray->date_end);`
- L4000: `self::assertNull($tray->file_name);`
- L4011: `self::assertInstanceOf(\RuntimeException::class,$exception);`
- L4012: `self::assertSame('No se pudo guardar el reporte de movimientos.',$exception->getMessage());`
- L4015: `self::assertSame('FAILED',$tray->status);`
- L4016: `self::assertNull($tray->file_name);`
- L4031: `self::assertSame('FINISHED',$tray->status);`
- L4032: `self::assertNotNull($tray->date_end);`
- L4034: `self::assertFileExists($file);`
- L4036: `self::assertSame('Mov. de ingresos y egresos',$workbook->getActiveSheet()->getTitle());`
- L4044: `self::assertSame('FINISHED',$otherTray->status);`
- L4045: `self::assertNotSame($tray->file_name,$otherTray->file_name);`
- L4046: `self::assertStringEndsWith('_'.$tray->id,$tray->file_name);`
- L4047: `self::assertStringEndsWith('_'.$otherTray->id,$otherTray->file_name);`
- L4048: `self::assertFileExists($file);`
- L4049: `self::assertFileExists($otherFile);`

### `test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4054](../../tests/Support/PaymentClassificationPersistenceCases.php#L4054).

- L4060: `self::assertSame(2,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());`
- L4082: `self::assertSame($cash->id,$global->cash_session_id);`
- L4089: `self::assertSame('cash',$movementRows->first(fn($row)=>$row['method']['id']==='04')['method']['collection_kind']);`
- L4090: `self::assertSame('account',$movementRows->first(fn($row)=>$row['method']['id']==='01')['method']['collection_kind']);`
- L4091: `self::assertSame('120',$report['balances_by_currency']['VES']['final_balance']);`
- L4092: `self::assertSame('13',$report['balances_by_currency']['USD']['final_balance']);`
- L4101: `self::assertSame(['Saldo inicial VES','Saldo inicial USD'],$openingRows->pluck('number_full')->all());`
- L4102: `self::assertSame(['VES','USD'],$openingRows->pluck('currency_type_id')->all());`
- L4107: `self::assertCount(8,$rows);`
- L4108: `self::assertSame('Servicio de prueba',$rows->first(fn($row)=>$row['instance_type']==='income')['items'][0]['description']);`
- L4110: `self::assertSame('Servicio de prueba',$repeated->first(fn($row)=>$row['instance_type']==='income')['items'][0]['description']);`
- L4111: `self::assertSame(['VES','USD','VES','USD','VES','USD','VES','USD'], $rows->pluck('currency_type_id')->all());`
- L4112: `self::assertSame('320.00',$rows->where('currency_type_id','VES')->last()['balance']);`
- L4113: `self::assertSame('33.00',$rows->where('currency_type_id','USD')->last()['balance']);`
- L4116: `self::assertNull($mobileSummary['balance']);`
- L4117: `self::assertSame('320',$mobileSummary['totals_by_currency']['VES']['balance']);`
- L4118: `self::assertSame('33',$mobileSummary['totals_by_currency']['USD']['balance']);`
- L4119: `self::assertSame(0,$mobileSummary['unclassified_count']);`
- L4125: `self::assertTrue($scroll['success']);`
- L4127: `self::assertCount(8,$mobileRows);`
- L4128: `self::assertSame(['USD','VES'],$mobileRows->pluck('currency_type_id')->unique()->values()->all());`
- L4133: `self::assertSame('Partida en arreglo',$arrayWeb[0]['items'][0]['description']);`
- L4134: `self::assertSame('Partida en arreglo',$arrayMobile[0]['items'][0]['description']);`
- L4137: `self::assertSame($report['balances_by_currency'],$closed['balances_by_currency']);`
- L4138: `self::assertSame('120',$cash->fresh()->final_balance);`
- L4143: `self::assertCount((int)$count,$data['classified']);`
- L4144: `self::assertSame($currency,$data['currency_type_id']);`
- L4146: `self::assertSame($physical,\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::sum(array_map(fn($row)=>\App\Support\Exact\Decimal::readRequired($row,'balance'),$cashGroups))));`
- L4147: `self::assertCount(0,$data['records']);`
- L4151: `self::assertSame($report['balances_by_currency'],$payload['classified']['balances_by_currency']);`
- L4153: `self::assertNotEmpty($payload['classified']['groups']);`
- L4154: `foreach ($payload['classified']['groups'] as $group) self::assertSame('cash',$group['method']['collection_kind']);`
- L4155: `foreach ($payload['classified']['rows'] as $row) self::assertSame('cash',$row['method']['collection_kind']);`
- L4158: `foreach ($payload['classified']['groups'] as $group) self::assertSame('0',$group['egress']);`
- L4159: `foreach ($payload['classified']['rows'] as $row) self::assertSame('income',$row['direction']);`
- L4162: `self::assertContains('account',array_column(array_column($payload['classified']['groups'],'method'),'collection_kind'));`
- L4186: `self::assertSame($historicContext,$bankReport->first()->getRawOriginal('report_context'));`
- L4189: `self::assertSame($historicDestination,$globalRow['destination_description']);`
- L4198: `self::assertNotNull($openingCell);`
- L4199: `self::assertSame($expectedOpening,trim($openingCell->textContent));`
- L4206: `self::assertNotNull($bankDestination);`
- L4207: `self::assertSame($historicDestination,trim($bankDestination->textContent));`
- L4208: `self::assertStringNotContainsString('Cuenta renombrada',$bankDestination->textContent);`
- L4209: `self::assertStringContainsString('Cuenta/Caja actual',$bankHtml);`
- L4216: `self::assertNotNull($methodCell);`
- L4217: `self::assertSame($historicName,trim($methodCell->textContent));`
- L4218: `self::assertStringNotContainsString('Catálogo modificado', $methodCell->textContent);`
- L4225: `self::assertStringStartsWith('%PDF-', $globalPdf->output());`
- L4229: `self::assertStringContainsString('Totales VES',$html);`
- L4230: `self::assertStringContainsString('Totales USD',$html);`
- L4231: `self::assertStringContainsString('Bs.320.00',$html);`
- L4232: `self::assertStringContainsString('$33.00',$html);`
- L4237: `self::assertSame($view==='new_report_pdf' ? 13 : 15,$headerCount);`
- L4240: `self::assertSame($headerCount,(int)$cells->item(0)->getAttribute('colspan')+$cells->length-1);`
- L4252: `self::assertContains($historicName,$globalCells);`
- L4253: `self::assertContains($historicDestination,$globalCells);`
- L4254: `self::assertContains('Saldo inicial VES',$globalCells);`
- L4255: `self::assertContains('Saldo inicial USD',$globalCells);`
- L4256: `self::assertNotContains('Cuenta renombrada',$globalCells);`
- L4257: `self::assertNotContains('Catálogo modificado',$globalCells);`
- L4276: `self::assertContains($expected,$cells);`
- L4286: `self::assertNotNull($headerRow);`
- L4287: `self::assertSame((string)$rows->first()['id'],(string)$sheet->getCell('A'.($headerRow+1))->getValue());`
- L4289: `self::assertSame($label,$sheet->getCell($column.$headerRow)->getValue());`
- L4292: `self::assertArrayHasKey($currency,$totalRows);`
- L4300: `self::assertSame($symbol.\App\Support\Exact\Decimal::format($amount,2,'.',''),$sheet->getCell($column.$totalRows[$currency])->getValue());`
- L4304: `self::assertNotNull($fractionSheet);`
- L4307: `self::assertCount($expectedRows->count()+1,$fractionRows);`
- L4308: `self::assertNotContains('historico-sin-importe',array_column($fractionRows,0));`
- L4311: `self::assertSame([(string)$row['id'],'amount',\App\Support\Exact\Decimal::represent($fraction),$fraction['n'],$fraction['d']],$fractionRows[$index+1]);`
- L4323: `self::assertGreaterThan(1,$all->count());`
- L4326: `self::assertSame($sortedIds,$ids);`
- L4331: `self::assertSame($ids,$exportIds);`
- L4337: `self::assertSame(\App\Support\Exact\Decimal::format($running->applyExact($movement),2,'.',''),$row['balance']);`
- L4341: `self::assertSame($all[1]['id'],$later[0]['id']);`
- L4342: `self::assertSame($all[1]['balance'],$later[0]['balance']);`
- L4346: `self::assertStringContainsString('120.00',$html);self::assertStringContainsString('13.00',$html);`
- L4360: `self::assertSame(['USD','USD'],$transferRows->pluck('currency_type_id')->all());`
- L4361: `self::assertSame(['input','output'],$transferRows->pluck('type_movement')->all());`
- L4362: `self::assertSame(['7.00','0.00'],$transferRows->pluck('balance')->all());`
- L4365: `self::assertSame(['input','output'],$mobileTransfers->pluck('type_movement')->all());`
- L4366: `self::assertSame(['7','7'],$mobileTransfers->pluck('amount_decimal')->all());`
- L4367: `self::assertSame([null,null],$mobileTransfers->pluck('amount_pen')->all());`
- L4373: `self::assertCount(1,$after);`
- L4374: `self::assertSame('-7.00',$after[0]['balance']);`
- L4380: `self::assertNull($unknown[0]['currency_type_id']);`
- L4381: `self::assertSame('-',$unknown[0]['balance']);`
- L4383: `self::assertStringContainsString('Sin moneda',$unknownHtml);`
- L4384: `self::assertStringContainsString('no se incluyen en los totales',$unknownHtml);`
- L4388: `self::assertCount(1,$legacy);`
- L4389: `self::assertNull($legacy[0]['currency_type_id']);`
- L4390: `self::assertNull($legacy[0]['payments']);`
- L4391: `self::assertSame('-',$legacy[0]['balance']);`
- L4394: `self::assertCount(1,$mobileLegacy);`
- L4395: `self::assertNull($mobileLegacy[0]['amount']);`
- L4396: `self::assertNull($mobileLegacy[0]['currency_type_id']);`
- L4398: `self::assertStringContainsString('Sin moneda',$legacyHtml);`
- L4407: `self::assertTrue((new \Modules\Expense\Http\Controllers\ExpensePaymentController())->destroy($expensePaymentId)['success']);`
- L4408: `self::assertNull(ExpensePayment::find($expensePaymentId));`
- L4409: `self::assertArrayHasKey('reversal',$expenseGlobal->fresh()->report_context);`
- L4410: `self::assertNull(Movement::row($expenseGlobal->fresh()));`

### `test_transfers_capture_the_currency_of_each_destination_and_reject_stale_cash_currency`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4413](../../tests/Support/PaymentClassificationPersistenceCases.php#L4413).

- L4423: `self::assertSame(['VES','USD'],$legs->pluck('currency_type_id')->all());`
- L4424: `self::assertSame(['VES','USD'],$legs->map(fn($leg)=>$leg->global_payment->report_context['currency_type_id'])->all());`
- L4425: `self::assertSame(['4000','-10'],$legs->pluck('amount')->all());`
- L4426: `self::assertSame('4000',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L4430: `self::assertSame(['USD','VES'],$newLegs->pluck('currency_type_id')->all());`
- L4431: `self::assertSame('3600',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L4438: `self::assertArrayHasKey('cash_currency_type_id',$error->errors());`
- L4441: `self::assertSame(4,$db->table('transfer_account_payments')->count());`
- L4448: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L4450: `self::assertSame(4,$db->table('transfer_account_payments')->count());`
- L4457: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L4466: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L4468: `self::assertSame(4,$db->table('transfer_account_payments')->count());`
- L4472: `self::assertSame('USD',$direct->fresh()->currency_type_id);`
- L4473: `self::assertSame('USD',$direct->global_payment->report_context['currency_type_id']);`
- L4481: `self::assertSame('3600',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($cash,'VES')));`
- L4482: `self::assertSame('2',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($cash,'USD')));`
- L4483: `self::assertSame('0',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($cash)));`
- L4484: `self::assertSame('-8',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($bank)));`
- L4485: `self::assertSame('-8',$controller->getBalanceBySingleBankAcount($bank)['balance']);`
- L4487: `self::assertNotEmpty($db->select("SHOW INDEX FROM transfer_account_payments WHERE Key_name = 'transfer_account_currency_fk'"));`

### `test_payment_link_with_banking_method_does_not_create_a_cash_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4490](../../tests/Support/PaymentClassificationPersistenceCases.php#L4490).

- L4504: `self::assertArrayHasKey('payment_destination_id',$error->errors());`
- L4506: `self::assertSame('pending',$link->fresh()->status);`
- L4507: `self::assertSame('pending',$link->payments()->firstOrFail()->status);`
- L4508: `self::assertSame(0,$db->table('income')->count());`
- L4509: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_opened_cash_is_returned_by_the_paginated_cash_listing`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4512](../../tests/Support/PaymentClassificationPersistenceCases.php#L4512).

- L4526: `self::assertArrayHasKey('payment_method_type_id',$error->errors());`
- L4528: `self::assertSame(0,$db->table('cash')->count());`
- L4529: `self::assertSame(0,$db->table('cash_currency_balances')->count());`
- L4530: `self::assertSame(0,$db->table('global_payments')->count());`
- L4535: `self::assertArrayHasKey('payment_method_type_id',$error->errors());`
- L4537: `self::assertSame(0,$db->table('cash')->count());`
- L4538: `self::assertSame(0,$db->table('global_payments')->count());`
- L4546: `self::assertArrayHasKey('payment_method_type_id',$error->errors());`
- L4548: `self::assertSame(0,$db->table('cash')->count());`
- L4549: `self::assertSame(0,$db->table('cash_currency_balances')->count());`
- L4550: `self::assertSame(0,$db->table('global_payments')->count());`
- L4553: `self::assertTrue($opened['success']);`
- L4556: `self::assertCount(1, $rows);`
- L4558: `self::assertSame($opened['data']['cash_id'], $row['id']);`
- L4559: `self::assertSame('TEST-CAJA', $row['reference_number']);`
- L4560: `self::assertSame('Aperturada', $row['state_description']);`
- L4561: `self::assertSame('100', $row['balances_by_currency']['VES']['beginning_balance']);`
- L4562: `self::assertSame('10', $row['balances_by_currency']['USD']['beginning_balance']);`

### `test_payment_classification_is_ready_from_the_fresh_tenant_schema`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4565](../../tests/Support/PaymentClassificationPersistenceCases.php#L4565).

- L4568: `self::assertFalse($db->getSchemaBuilder()->hasTable('payment_reporting_settings'));`
- L4569: `self::assertFalse($db->getSchemaBuilder()->hasColumn('cash','report_version'));`
- L4570: `self::assertSame('account',$db->table('payment_method_types')->where('id','11')->value('collection_kind'));`
- L4571: `self::assertSame('USD',$db->table('payment_method_types')->where('id','10')->value('cash_currency_type_id'));`
- L4574: `self::assertSame('100',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L4575: `self::assertSame('10',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);`

### `test_classified_invoice_receipts_keep_received_currency_and_bank_money_outside_physical_cash`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4578](../../tests/Support/PaymentClassificationPersistenceCases.php#L4578).

- L4590: `self::assertSame('1',Movement::row($payment->global_payment)['amount']);`
- L4597: `self::assertArrayHasKey('currency_type_id', $expected->errors());`
- L4599: `self::assertSame(1,$db->table('global_payments')->count());`
- L4603: `self::assertSame('USD',$bank->payment_method_snapshot['currency_type_id']);`
- L4605: `self::assertSame($bank->id,\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row)->id);`
- L4606: `self::assertSame(2,$db->table('global_payments')->count());`
- L4608: `self::assertSame('1',$report['balances_by_currency']['USD']['final_balance']);self::assertSame('0',$report['balances_by_currency']['VES']['final_balance']);`
- L4609: `self::assertTrue($document->fresh()->exactBalance()->isZero());`
- L4611: `self::assertCount(2,$groups);self::assertSame(['USD','USD'],array_column($groups,'currency_type_id'));`
- L4615: `self::assertCount(2,$records);`
- L4616: `foreach ($records as $global) self::assertNotNull(Movement::row($global));`
- L4618: `self::assertCount(2,$resource->toArray(new \Illuminate\Http\Request()));`
- L4619: `self::assertSame('2.32',$resource->with(new \Illuminate\Http\Request())['totals_by_version'][0]['total']);`
- L4631: `self::assertCount(2,$historicalGroups);`
- L4632: `self::assertSame(['Cuenta nueva','Cuenta anterior'],$historicalGroups->pluck('destination_label')->all());`
- L4633: `self::assertSame(['Bancario / electrónico','Bancario / electrónico'],$historicalGroups->pluck('method_kind_label')->all());`
- L4634: `self::assertSame(['1','1.32'],$historicalGroups->pluck('income')->all());`

### `test_company_api_includes_active_classified_custom_method_with_retired_id`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4638](../../tests/Support/PaymentClassificationPersistenceCases.php#L4638).

- L4648: `self::assertTrue($methods->has('07'));`
- L4649: `self::assertSame('USD',$methods->get('07')->currency_type_id);`
- L4650: `self::assertFalse($methods->has('14'));`
- L4655: `self::assertTrue(\App\Models\Tenant\PaymentMethodType::notCredit()->where('id','07')->exists());`
- L4656: `self::assertFalse(\App\Models\Tenant\PaymentMethodType::notCredit()->where('id','09')->exists());`
- L4657: `self::assertTrue(\App\Models\Tenant\PaymentMethodType::credit()->where('id','09')->exists());`
- L4658: `self::assertTrue(\App\Models\Tenant\PaymentMethodType::findOrFail('09')->isCredit());`

### `test_payment_dialog_opens_when_the_stored_document_rate_is_invalid`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4661](../../tests/Support/PaymentClassificationPersistenceCases.php#L4661).

- L4670: `self::assertNull($response['exchange_rate_sale']);`
- L4671: `self::assertSame('VES',$response['currency_type_id']);`
- L4672: `self::assertSame('232',$response['total_difference']);`

### `test_initial_invoice_rejects_method_currency_before_conversion_and_rolls_back`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4675](../../tests/Support/PaymentClassificationPersistenceCases.php#L4675).

- L4685: `self::assertArrayHasKey('currency_type_id',$error->errors());`
- L4687: `self::assertSame(0,$db->table('documents')->count());`
- L4688: `self::assertSame(0,$db->table('global_payments')->count());`
- L4695: `self::assertArrayHasKey('payments',$error->errors());`
- L4697: `self::assertSame(0,$db->table('documents')->count());`
- L4698: `self::assertSame(0,$db->table('global_payments')->count());`

### `test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4701](../../tests/Support/PaymentClassificationPersistenceCases.php#L4701).

- L4718: `catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_sale_note_payment_id',$error->errors()); }`
- L4719: `self::assertSame(0,$db->table('documents')->count());`
- L4721: `self::assertSame((string)$source->id,$document->payments->first()->payment_method_snapshot['receipt_origin']['id']);`
- L4722: `self::assertSame(1,$db->table('global_payments')->count());`
- L4723: `self::assertSame('50',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`

### `test_credit_invoice_installments_rollback_invalid_amount_and_accept_paid_balance`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4726](../../tests/Support/PaymentClassificationPersistenceCases.php#L4726).

- L4741: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('fee.amount',$error->errors()); }`
- L4742: `self::assertSame(0,$db->table('documents')->count());`
- L4743: `self::assertSame(0,$db->table('document_payments')->count());`
- L4744: `self::assertSame(0,$db->table('global_payments')->count());`
- L4747: `self::assertSame('0',$document->fee()->firstOrFail()->amount);`
- L4748: `self::assertSame('0',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));`
- L4749: `self::assertSame(1,$db->table('document_payments')->count());`
- L4750: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_usd_invoice_installments_discount_ves_bank_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4753](../../tests/Support/PaymentClassificationPersistenceCases.php#L4753).

- L4774: `self::assertArrayHasKey('payment_destination_id',$error->errors());`
- L4776: `self::assertSame(0,$db->table('documents')->count());`
- L4777: `self::assertSame(0,$db->table('global_payments')->count());`
- L4779: `self::assertSame('226.99',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));`
- L4780: `self::assertSame(['113.5','113.49'],$document->fee()->orderBy('date')->pluck('amount')->all());`
- L4782: `self::assertSame('VES',$payment->currency_type_id);`
- L4783: `self::assertSame('50.1',$payment->original_amount);`
- L4784: `self::assertSame('5.01',$payment->payment);`
- L4785: `self::assertSame('10.00000000',$payment->exchange_rate);`
- L4787: `self::assertSame('VES',$movement['currency_type_id']);`
- L4788: `self::assertSame('50.1',$movement['amount']);`
- L4789: `self::assertSame('account',$movement['method']['collection_kind']);`
- L4793: `self::assertSame('VES',$after['currency_type_id']);`
- L4794: `self::assertSame('account',$after['method']['collection_kind']);`
- L4795: `self::assertSame('50.1',$after['amount']);`
- L4796: `self::assertSame('226.99',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document->fresh()])[$document->id]));`
- L4797: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_invoice_installments_discount_cross_currency_receipt_and_guarantee_fund`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4800](../../tests/Support/PaymentClassificationPersistenceCases.php#L4800).

- L4814: `self::assertSame('172',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));`
- L4815: `self::assertSame('172',$document->fee()->firstOrFail()->amount);`
- L4816: `self::assertSame('10',$document->guarantee_fund()->firstOrFail()->amount);`
- L4817: `self::assertSame('50',$document->payments()->firstOrFail()->payment);`
- L4818: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_credit_sale_note_installments_discount_cross_currency_receipt`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4821](../../tests/Support/PaymentClassificationPersistenceCases.php#L4821).

- L4839: `self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota.');`
- L4841: `self::assertSame(['60','122'],$note->fee()->orderBy('date')->pluck('amount')->all());`
- L4843: `self::assertSame('50',$receipt->payment);`
- L4844: `self::assertSame(1,$db->table('global_payments')->count());`
- L4848: `self::assertTrue($updated['success'],$updated['message'] ?? 'No se pudo editar la nota.');`
- L4849: `self::assertSame(['60','122'],$note->fee()->orderBy('date')->pluck('amount')->all());`
- L4850: `self::assertSame($snapshot,$receipt->fresh()->payment_method_snapshot);`
- L4851: `self::assertSame(1,$db->table('global_payments')->count());`
- L4855: `self::assertSame($snapshot['description'],$reportRows[0]['payment_form']);`
- L4856: `self::assertStringContainsString('Recibido USD 5',$reportRows[0]['payment_method']);`
- L4857: `self::assertStringContainsString('Aplicado VES 50',$reportRows[0]['payment_method']);`
- L4858: `self::assertStringContainsString('Cuota #1',$reportRows[0]['payment_method']);`
- L4859: `self::assertSame(['n'=>'232','d'=>'1'],$reportRows[0]['report_total_fractions']['total']);`
- L4863: `foreach (['total','total_taxed','total_igv','total_charge'] as $field) self::assertSame('0.00',$voidedRows[0][$field]);`
- L4864: `self::assertSame(['n'=>'232','d'=>'1'],$voidedRows[0]['report_total_fractions']['total']);`
- L4866: `self::assertTrue($secondSale['success'],$secondSale['message'] ?? 'No se pudo guardar la segunda nota.');`
- L4874: `self::assertSame($measureNoteRelations([$note->id]),$measureNoteRelations([$note->id,$secondSale['data']['id']]),`
- L4895: `self::assertStringContainsString('Recibido USD 5.00',$html,$template.'/'.$format);`
- L4896: `self::assertStringContainsString('Aplicado VES 50.00',$html,$template.'/'.$format);`
- L4897: `self::assertStringNotContainsString('<h4>COTIZACIÓN</h4>',$html,$template.'/'.$format);`
- L4898: `self::assertStringContainsString('26-11-2026',$html,$template.'/'.$format);`
- L4899: `self::assertStringContainsString('26-12-2026',$html,$template.'/'.$format);`
- L4902: `self::assertStringContainsString('26-11-2026',$installmentHtml,$template.'/'.$format.' crédito con cuotas');`
- L4903: `self::assertStringContainsString('26-12-2026',$installmentHtml,$template.'/'.$format.' crédito con cuotas');`
- L4904: `self::assertStringContainsString('Recibido USD 5.00',$installmentHtml,$template.'/'.$format.' crédito con cuotas');`
- L4907: `self::assertStringNotContainsString('Recibido:</td>',$html);`
- L4908: `self::assertStringNotContainsString('Vuelto:</td>',$html);`
- L4917: `self::assertStringStartsWith('%PDF-',$bytes);`
- L4922: `self::assertStringStartsWith('%PDF-',$productionPdf);`
- L4934: `self::assertStringStartsWith('%PDF-',$multiPdf);`

### `test_saved_credit_installments_preserve_cross_currency_receipt_through_conversion_and_pdf`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:4938](../../tests/Support/PaymentClassificationPersistenceCases.php#L4938).

- L4958: `self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota.');`
- L4960: `self::assertSame('02',$note->payment_condition_id);`
- L4961: `self::assertSame(['60','122'],$note->fee()->orderBy('date')->pluck('amount')->all());`
- L4963: `self::assertSame('USD',$receipt->currency_type_id);`
- L4964: `self::assertSame('5',$receipt->original_amount);`
- L4965: `self::assertSame('50',$receipt->payment);`
- L4972: `self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la factura.');`
- L4974: `self::assertSame('02',$document->payment_condition_id);`
- L4975: `self::assertSame(['60','122'],$document->fee()->orderBy('date')->pluck('amount')->all());`
- L4976: `self::assertSame('182',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));`
- L4977: `self::assertSame((string)$receipt->id,$document->payments()->firstOrFail()->payment_method_snapshot['receipt_origin']['id']);`
- L4978: `self::assertSame(1,$db->table('global_payments')->count());`
- L4980: `self::assertSame('account',Movement::row($receipt->fresh()->global_payment)['method']['collection_kind']);`
- L4998: `foreach (['26-11-2026','26-12-2026','Recibido USD 5.00','Aplicado VES 50.00'] as $text) self::assertStringContainsString($text,$html,$type);`
- L5002: `self::assertStringStartsWith('%PDF-',$bytes);`
- L5005: `self::assertStringStartsWith('%PDF-',$production);`
- L5006: `self::assertSame(1,preg_match_all('/\/Type\s*\/Page\b/',$production),$type.' debe caber en una página de ticket.');`
- L5012: `self::assertCount(1,$itemRecords);`
- L5013: `self::assertTrue($itemRecords->first()->document->relationLoaded('payments'));`
- L5014: `self::assertTrue($itemRecords->first()->document->relationLoaded('fee'));`
- L5024: `'Cuota #2 — VES 122 — Vence 2026-12-26'] as $expected) self::assertStringContainsString($expected,$text);`

### `test_credit_invoice_from_sale_note_reuses_cross_currency_receipt_and_keeps_balance`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5030](../../tests/Support/PaymentClassificationPersistenceCases.php#L5030).

- L5061: `self::assertTrue($result['success']);`
- L5062: `self::assertSame(0,$httpCalls);`
- L5064: `self::assertSame('02',$document->payment_condition_id);`
- L5065: `self::assertSame('182',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));`
- L5066: `self::assertSame('182',$document->fee()->firstOrFail()->amount);`
- L5068: `self::assertSame('USD',$applied->currency_type_id);`
- L5069: `self::assertSame('5',$applied->original_amount);`
- L5070: `self::assertSame('50',$applied->payment);`
- L5071: `self::assertSame((string)$source->id,$applied->payment_method_snapshot['receipt_origin']['id']);`
- L5072: `self::assertSame(1,$db->table('global_payments')->count());`
- L5075: `self::assertSame($source->payment_method_snapshot['description'],$details['PAGOS'][0]['description']);`
- L5076: `self::assertSame('Cobro previo',$details['PAGOS'][0]['kind']);`
- L5077: `self::assertStringContainsString('Cobro previo',$details['PAGOS'][0]['display']);`
- L5078: `self::assertSame('USD',$details['PAGOS'][0]['currency_type_id']);`
- L5079: `self::assertSame('5',$details['PAGOS'][0]['original_amount']);`
- L5080: `self::assertSame('50',$details['PAGOS'][0]['amount']);`
- L5081: `self::assertStringContainsString('Recibido USD 5',$details['PAGOS'][0]['display']);`
- L5082: `self::assertStringContainsString('Aplicado VES 50',$details['PAGOS'][0]['display']);`
- L5083: `self::assertSame('Cuota #1',$details['CUOTA'][0]['description']);`
- L5084: `self::assertSame('182',$details['CUOTA'][0]['amount']);`
- L5085: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L5086: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);`
- L5106: `self::assertStringContainsString('CONDICIÓN: CRÉDITO',$html);`
- L5107: `self::assertStringContainsString('Cuota #1',$html);`
- L5108: `self::assertStringContainsString('Recibido USD 5.00',$html);`
- L5109: `self::assertStringContainsString('Aplicado VES 50.00',$html);`
- L5112: `self::assertStringContainsString('Cuota #1',$installmentHtml,$template.'/'.$format.' crédito con cuotas');`
- L5113: `self::assertStringContainsString('Recibido USD 5.00',$installmentHtml,$template.'/'.$format.' crédito con cuotas');`
- L5119: `self::assertStringStartsWith('%PDF-',$bytes);`
- L5135: `self::assertStringContainsString('Recibido USD 5.00',$html,$view);`
- L5136: `self::assertStringContainsString('Aplicado VES 50.00',$html,$view);`
- L5137: `self::assertStringContainsString('26-11-2026',$html,$view);`
- L5140: `self::assertStringContainsString('26-11-2026',$installmentHtml,$view.' crédito con cuotas');`
- L5141: `self::assertStringContainsString('Recibido USD 5.00',$installmentHtml,$view.' crédito con cuotas');`
- L5144: `self::assertStringNotContainsString('Recibido:</td>',$html,$view);`
- L5145: `self::assertStringNotContainsString('Vuelto:</td>',$html,$view);`
- L5153: `self::assertStringStartsWith('%PDF-',$bytes,$view);`
- L5159: `self::assertStringStartsWith('%PDF-',$productionPdf,$productionTemplate);`
- L5160: `self::assertSame(1,preg_match_all('/\/Type\s*\/Page\b/',$productionPdf),$productionTemplate.' debe mantener esta muestra en una página.');`
- L5177: `self::assertSame($oneQueries,$twoQueries,'El reporte debe cargar pagos y cuotas por lote.');`
- L5183: `self::assertStringContainsString('Recibido USD 5',$screen[0]['payment_method']);`
- L5184: `self::assertSame($applied->payment_method_snapshot['description'],$screen[0]['payment_form']);`
- L5185: `self::assertSame(['n'=>'232','d'=>'1'],$screen[0]['report_total_fractions']['total']);`
- L5188: `self::assertSame($applied->payment_method_snapshot['description'],$apiRows[0]['payments']['PAGOS'][0]['description']);`
- L5189: `self::assertStringContainsString('Recibido USD 5',$apiRows[0]['payments']['PAGOS'][0]['display']);`
- L5190: `self::assertStringContainsString('Aplicado VES 50',$apiRows[0]['payments']['PAGOS'][0]['display']);`
- L5191: `self::assertSame('Cuota #1',$apiRows[0]['payments']['CUOTA'][0]['description']);`
- L5203: `self::assertStringContainsString('Recibido USD 5',$html,$reportView);`
- L5204: `self::assertStringContainsString('Aplicado VES 50',$html,$reportView);`
- L5205: `self::assertStringContainsString($applied->payment_method_snapshot['description'],$html,$reportView);`
- L5210: `self::assertStringStartsWith('%PDF-',$bytes);`
- L5220: `self::assertStringContainsString('Recibido USD 5',$text,$reportView);`
- L5221: `self::assertStringContainsString('Aplicado VES 50',$text,$reportView);`
- L5241: `self::assertSame(1,$totalRows->length,$reportView);`
- L5243: `self::assertSame('232.00',trim($cells->item(1)->textContent),$reportView.' VES');`
- L5244: `self::assertSame('77.77',trim($cells->item(2)->textContent),$reportView.' USD');`
- L5249: `self::assertStringStartsWith('%PDF-',$bytes);`
- L5256: `self::assertStringContainsString('Cobro previo',$mixedHtml,$reportView);`
- L5257: `self::assertStringContainsString('Recibido USD 5',$mixedHtml,$reportView);`
- L5258: `self::assertStringContainsString('Aplicado VES 50',$mixedHtml,$reportView);`
- L5263: `self::assertSame(1,$rows->length,$reportView.' '.$currency);`
- L5265: `self::assertSame($reportView==='report_pdf_standard' ? '17' : '16',$cells->item(0)->getAttribute('colspan'));`
- L5266: `self::assertSame($expected,trim($cells->item(4)->textContent),$reportView.' '.$currency);`
- L5269: `$bytes=$pdf->output(); self::assertStringStartsWith('%PDF-',$bytes);`
- L5286: `self::assertSame(['Totales VES'=>'232','Totales USD'=>'77.77'],$found);`
- L5293: `self::assertStringContainsString('Recibido USD 5',$html);`
- L5294: `self::assertStringContainsString('Aplicado VES 50',$html);`
- L5295: `self::assertStringContainsString('CREDIT-REF-001',$html);`
- L5296: `self::assertStringContainsString('Cuota #1',$html);`
- L5302: `self::assertStringContainsString('Recibido USD 5',$html);`
- L5303: `self::assertStringContainsString('Aplicado VES 50',$html);`
- L5304: `self::assertStringContainsString($applied->payment_method_snapshot['description'],$html);`
- L5316: `self::assertStringContainsString('Recibido USD 5',$text);`
- L5317: `self::assertStringContainsString('Aplicado VES 50',$text);`
- L5324: `self::assertNotNull($detailColumn);`
- L5325: `self::assertGreaterThan(13,$reopened->getActiveSheet()->getColumnDimension($detailColumn)->getWidth());`
- L5340: `self::assertSame('104101',$ejbRows[0]['automatic_payment_account']);`
- L5345: `self::assertSame('104101',(string) $ejbWorkbook->getActiveSheet()->getCell('Z2')->getValue());`
- L5352: `self::assertSame('CON',$contasisRows[0]['payment_condition']);`
- L5353: `self::assertSame('',$contasisRows[0]['payment_method']);`
- L5359: `self::assertSame('CON',$contasisWorkbook->getActiveSheet()->getCell('X1')->getValue());`
- L5360: `self::assertEmpty($contasisWorkbook->getActiveSheet()->getCell('AM1')->getValue());`

### `test_sale_note_reuses_a_cross_currency_bank_receipt_without_a_second_movement`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5367](../../tests/Support/PaymentClassificationPersistenceCases.php#L5367).

- L5386: `self::assertArrayHasKey('payments',$error->errors());`
- L5388: `self::assertSame(0,$db->table('global_payments')->count());`
- L5391: `self::assertSame('50',$source->payment);`
- L5392: `self::assertSame('5',$source->original_amount);`
- L5393: `self::assertSame('USD',$source->currency_type_id);`
- L5394: `self::assertSame('5',Movement::row($source->global_payment)['amount']);`
- L5397: `self::assertSame('USD',$responseRow['currency_type_id']);`
- L5398: `self::assertSame('5',$responseRow['original_amount']);`
- L5399: `self::assertSame('10.00000000',$responseRow['exchange_rate']);`
- L5404: `self::assertSame($frozen['description'],$responseAfterChange['payment_method_snapshot']['description']);`
- L5407: `self::assertSame([],$newRows);`
- L5408: `self::assertSame($frozen,$source->fresh()->payment_method_snapshot);`
- L5409: `self::assertSame('USD',$source->fresh()->currency_type_id);`
- L5415: `self::assertSame('USD',$historic['currency_type_id']);`
- L5416: `self::assertSame('5',$historic['original_amount']);`
- L5417: `self::assertSame(\App\Services\Payments\ReceiptPresentation::destination($source),$historic['payment_destination_description']);`
- L5421: `self::assertTrue($result['success'],$result['message'] ?? 'No se pudo editar la nota de venta.');`
- L5422: `self::assertSame($frozen,$source->fresh()->payment_method_snapshot);`
- L5423: `self::assertSame('USD',$source->fresh()->currency_type_id);`
- L5424: `self::assertSame(1,$db->table('global_payments')->count());`
- L5428: `self::assertSame((string)$source->id,$document->payments->first()->payment_method_snapshot['receipt_origin']['id']);`
- L5429: `self::assertSame(1,$db->table('global_payments')->count());`
- L5431: `self::assertSame('Cobro reutilizado',$reused['kind']);`
- L5432: `self::assertSame('0',$reused['payment']);`
- L5433: `self::assertSame('50',$reused['applied_payment']);`
- L5434: `self::assertSame('USD',$reused['currency_type_id']);`
- L5435: `self::assertSame('VES',$reused['document_currency_type_id']);`
- L5436: `self::assertSame('10.00000000',$reused['exchange_rate']);`
- L5437: `self::assertSame($frozen['description'],$reused['payment_method_type_description']);`
- L5442: `self::assertFalse($db->table('cash_document_payments')->where('document_payment_id', $documentPayment->id)->exists());`
- L5445: `self::assertTrue($resource['receipt_reused']);`
- L5446: `self::assertSame('10.00000000',(string)$resource['exchange_rate']);`
- L5447: `self::assertSame($frozen['description'],$resource['payment_method_type_description']);`
- L5450: `self::assertSame($frozen['description'],$saleResource['payment_method_type_description']);`
- L5451: `self::assertSame('USD · USD',$saleResource['destination_description']);`
- L5459: `self::assertSame('Cobro',$normal['kind']);`
- L5460: `self::assertSame('6.6',$normal['payment']);`
- L5461: `self::assertSame('66',$normal['applied_payment']);`
- L5462: `self::assertSame('Nombre actualizado',$normal['payment_method_type_description']);`
- L5463: `self::assertSame('Vigente',$normal['status']);`
- L5466: `self::assertSame('Revertido',$reversed['status']);`
- L5467: `self::assertSame('0',$reversed['payment']);`
- L5468: `self::assertSame('0',$reversed['applied_payment']);`
- L5474: `self::assertStringContainsString('Moneda del documento',$html);`
- L5475: `self::assertStringContainsString('Tasa Bs./USD',$html);`
- L5476: `self::assertStringContainsString('Cobro reutilizado',$html);`
- L5477: `self::assertStringContainsString('USD',$html);`
- L5478: `self::assertStringContainsString('VES',$html);`
- L5479: `self::assertStringContainsString('Nombre actualizado',$html);`
- L5482: `self::assertStringStartsWith('%PDF-', $pdf->output());`

### `test_cash_credit_registration_uses_saved_payment_condition_instead_of_method_id`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5485](../../tests/Support/PaymentClassificationPersistenceCases.php#L5485).

- L5494: `self::assertFalse($db->table('cash_document_credits')->where('sale_note_id', $cashNote->id)->exists());`
- L5498: `self::assertTrue($db->table('cash_document_credits')->where('sale_note_id', $creditNote->id)->exists());`
- L5508: `self::assertTrue($db->table('cash_document_credits')->where('sale_note_id',$legacyCredit->id)->exists());`
- L5509: `self::assertFalse($db->table('cash_document_credits')->where('sale_note_id',$legacyAccount->id)->exists());`

### `test_sale_note_cross_currency_cash_change_uses_received_currency`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5512](../../tests/Support/PaymentClassificationPersistenceCases.php#L5512).

- L5527: `self::assertSame('232',$source->payment);`
- L5528: `self::assertSame('68',$source->change);`
- L5529: `self::assertSame('23.2',$source->original_amount);`
- L5531: `self::assertStringContainsString('Recibido USD 30',$details['PAGOS'][0]['display']);`
- L5532: `self::assertStringContainsString('Aplicado VES 232',$details['PAGOS'][0]['display']);`
- L5533: `self::assertStringContainsString('Vuelto USD 6.8',$details['PAGOS'][0]['display']);`
- L5534: `self::assertSame('23.2',Movement::row($source->global_payment)['amount']);`
- L5535: `self::assertSame('23.2',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);`
- L5537: `self::assertSame('30',$transport['tendered_original_amount']);`
- L5538: `self::assertSame('6.8',$transport['received_change']);`
- L5540: `self::assertSame('30',$editTransport['tendered_original_amount']);`
- L5541: `self::assertSame('6.8',$editTransport['received_change']);`
- L5543: `self::assertSame('30',$modelTransport['tendered_original_amount']);`
- L5544: `self::assertSame('6.8',$modelTransport['received_change']);`
- L5545: `self::assertNotEmpty($modelTransport['payment_method_snapshot']);`
- L5549: `self::assertNull($legacyTransport['tendered_original_amount']);`
- L5550: `self::assertNull($legacyTransport['received_change']);`
- L5554: `self::assertStringContainsString('Neto recibido USD 23.2',$legacyDetails['PAGOS'][0]['display']);`
- L5555: `self::assertStringNotContainsString('Vuelto USD',$legacyDetails['PAGOS'][0]['display']);`
- L5560: `self::assertStringContainsString('Recibido USD 30.00', $rendered);`
- L5561: `self::assertStringContainsString('Aplicado VES 232.00', $rendered);`
- L5562: `self::assertStringContainsString('Vuelto USD 6.80', $rendered);`
- L5565: `self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));`
- L5576: `self::assertSame((int)$source->id,(int)$hotelItem->payments()->firstOrFail()->source_sale_note_payment_id);`
- L5577: `self::assertSame(1,$db->table('global_payments')->count());`

### `test_sale_note_mixed_currency_change_and_unrepresentable_change`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5581](../../tests/Support/PaymentClassificationPersistenceCases.php#L5581).

- L5601: `self::assertSame(['132','100'],$payments->pluck('payment')->all());`
- L5602: `self::assertSame(['68','0'],$payments->pluck('change')->all());`
- L5603: `self::assertSame(['52800','100'],$payments->pluck('original_amount')->all());`
- L5604: `self::assertSame('52800',Movement::row($payments[0]->global_payment)['amount']);`
- L5605: `self::assertSame('100',Movement::row($payments[1]->global_payment)['amount']);`
- L5606: `self::assertSame('52800',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L5607: `self::assertSame(2,$db->table('global_payments')->count());`
- L5619: `self::assertArrayHasKey('payments',$error->errors());`
- L5621: `self::assertSame(0,$another->payments()->count());`
- L5622: `self::assertSame(2,$db->table('global_payments')->count());`
- L5623: `self::assertSame('52800',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`

### `test_classified_invoice_change_only_reduces_cash_and_rejects_electronic_excess`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5627](../../tests/Support/PaymentClassificationPersistenceCases.php#L5627).

- L5639: `self::assertSame('32',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L5640: `self::assertTrue($document->fresh()->exactBalance()->isZero());`
- L5643: `self::assertSame('200',$bankMovement['amount']);`
- L5644: `self::assertSame('account',$bankMovement['method']['collection_kind']);`
- L5645: `self::assertSame('32',$cashMovement['amount']);`
- L5646: `self::assertSame('cash',$cashMovement['method']['collection_kind']);`
- L5649: `self::assertSame('50',$display['tendered_original_amount']);`
- L5650: `self::assertSame('18',$display['received_change']);`
- L5651: `self::assertSame('50.004',\App\Support\Exact\Decimal::represent($record->exactValue('requested_amount')));`
- L5652: `self::assertSame('-0.004',\App\Support\Exact\Decimal::represent($record->exactValue('cash_adjustment')));`
- L5653: `self::assertSame('18',\App\Support\Exact\Decimal::represent($record->change));`
- L5655: `self::assertNull($bankRecord->change);`
- L5656: `self::assertSame('200',\App\Support\Exact\Decimal::represent($bankRecord->exactValue('requested_amount')));`
- L5660: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('change',$error->errors()); }`
- L5667: `self::assertArrayHasKey('payments',$error->errors());`
- L5669: `self::assertSame($count,$db->table('documents')->count());`
- L5670: `self::assertSame('32',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L5677: `self::assertArrayHasKey('payments',$error->errors());`
- L5681: `self::assertSame('50',$rows[0]['payment']);`
- L5682: `self::assertSame('10',$rows[1]['payment']);`
- L5683: `self::assertSame('10',$rows[1]['change']);`
- L5688: `self::assertNotNull($record->fresh()->reversed_at);`
- L5689: `self::assertSame($beforeSnapshot,$record->fresh()->calculation_snapshot);`
- L5690: `self::assertSame($movementsBefore,$db->table('global_payments')->count());`
- L5691: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`
- L5692: `self::assertSame('32',\App\Support\Exact\Decimal::represent($document->fresh()->exactBalance()));`
- L5698: `} catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('operation_key',$error->errors()); }`
- L5699: `self::assertSame($movementsBefore,$db->table('global_payments')->count());`
- L5700: `self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);`

### `test_classified_cash_report_performance_against_legacy_at_1000_and_10000_receipts`

Fuente: [tests/Support/PaymentClassificationPersistenceCases.php:5703](../../tests/Support/PaymentClassificationPersistenceCases.php#L5703).

- L5726: `self::assertSame($target===1000 ? '10' : '100',$report['balances_by_currency']['VES']['income']);`
- L5730: `self::assertTrue(\App\Support\Exact\Decimal::of($legacy['total_cash_payment_method_type_01'])->isEqualTo($report['balances_by_currency']['VES']['final_balance']));`
- L5733: `self::assertLessThanOrEqual($legacyMs+max($legacyMs*0.2,100),$times[4]);`
