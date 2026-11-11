<?php

namespace Tests\Unit;

use App\Services\Fiscal\PaymentQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\SpeProfiles;
use Tests\TestCase;

class PaymentQuoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', ['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'']);
        DB::purge('tenant');
        Schema::connection('tenant')->create('payment_method_types', function ($table) {
            $table->string('id')->primary();
            $table->string('collection_kind');
            // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
            $table->boolean('is_active')->default(true);
            // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########
        });
        DB::connection('tenant')->table('payment_method_types')->insert([
            ['id'=>'10','collection_kind'=>'cash'], ['id'=>'01','collection_kind'=>'account'],
        ]);
    }

    private function row(string $amount, string $currency = 'USD', string $method = '10'): array
    {
        return ['date_of_payment'=>'2026-09-10', 'payment_method_type_id'=>$method,
            'currency_type_id'=>$currency, 'original_amount'=>$amount, 'payment'=>$amount,
            'exchange_rate'=>'10', 'financial_intermediation'=>$method === '01' ? 'financial_institution' : 'none'];
    }

    private function quote(array $rows, string $currency = 'USD', string $due = '100', bool $enabled = true, bool $later = false): array
    {
        return PaymentQuote::calculate($rows, $currency, '10', $due, fn()=>SpeProfiles::profile(), $enabled, '3', $later);
    }

    public function test_tax_uses_the_settled_cents_in_preview(): void
    {
        $quote = $this->quote([$this->row('0.1666')], 'USD', '1');
        self::assertSame('0.17', $quote['payments'][0]['original_amount']);
        self::assertSame('0.01', $quote['payments'][0]['tax_amount']);
        self::assertSame('0.18', $quote['received_by_currency']['USD']);
    }

    public function test_cash_change_is_removed_before_the_tax_is_calculated(): void
    {
        $quote = $this->quote([$this->row('120')]);
        self::assertSame('20.00', $quote['payments'][0]['change']);
        self::assertSame('100.00', $quote['payments'][0]['original_amount']);
        self::assertSame('3.00', $quote['payments'][0]['tax_amount']);
        self::assertSame('103.00', $quote['received_by_currency']['USD']);
        self::assertSame('123.00', $quote['tendered_by_currency']['USD']);
        self::assertSame('0.00', $quote['balance']);
    }

    public function test_cross_currency_cash_settles_subcent_remainder_without_fabricating_change(): void
    {
        $row=$this->row('0.56');
        $row['exchange_rate']='897.53830000';
        $quote=PaymentQuote::calculate([$row], 'VES', '897.53830000', '500', fn()=>SpeProfiles::profile(), true, '3');
        self::assertSame('500.00', $quote['principal_applied']);
        self::assertSame('0.00', $quote['balance']);
        self::assertSame('0.00', $quote['payments'][0]['change']);
        self::assertSame('0.56', $quote['payments'][0]['original_amount']);
        self::assertSame('0.02', $quote['payments'][0]['tax_amount']);
        self::assertSame('0.58', $quote['received_by_currency']['USD']);
        self::assertSame('-2.62', $quote['payments'][0]['settlement_adjustment']);
    }

    public function test_quote_ignores_client_supplied_settlement_adjustment(): void
    {
        $row=$this->row('100');
        $row['_settlement_payment']='1';
        $quote=$this->quote([$row]);
        self::assertSame('100.00',$quote['principal_applied']);
        self::assertSame('0.00',$quote['balance']);
    }

    public function test_dollar_cash_closes_the_nearest_cent_shortfall_and_keeps_the_real_igtf(): void
    {
        $row = $this->row('5.57');
        $row['exchange_rate'] = '897.53830000';
        $quote = PaymentQuote::calculate([$row], 'VES', $row['exchange_rate'], '5000', fn()=>SpeProfiles::profile(), true, '3');
        self::assertSame('5000.00', $quote['principal_applied']);
        self::assertSame('0.00', $quote['balance']);
        self::assertSame('5000', $quote['payments'][0]['payment']);
        self::assertSame('5.57', $quote['payments'][0]['original_amount']);
        self::assertSame('0.17', $quote['payments'][0]['tax_amount']);
        self::assertSame('0.00', $quote['payments'][0]['change']);
        self::assertSame('0.71', $quote['payments'][0]['settlement_adjustment']);
        self::assertSame(['n'=>'711669','d'=>'1000000'], $quote['payments'][0]['settlement_adjustment_exact']);
        self::assertSame(['USD'=>'5.74'], $quote['received_by_currency']);
    }

    public function test_mixed_cash_closes_the_last_dollar_receipt_in_either_order(): void
    {
        $ves = $this->row('1000', 'VES');
        $usd = $this->row('4.46');
        $usd['exchange_rate'] = '897.53830000';
        foreach ([[$ves,$usd],[$usd,$ves]] as $rows) {
            $quote = PaymentQuote::calculate($rows, 'VES', $usd['exchange_rate'], '5000', fn()=>SpeProfiles::profile(), true, '3');
            $receipt = collect($quote['payments'])->firstWhere('currency_type_id', 'USD');
            self::assertSame('5000.00', $quote['principal_applied']);
            self::assertSame('0.00', $quote['balance']);
            self::assertSame('4000', $receipt['payment']);
            self::assertSame('0.13', $receipt['tax_amount']);
            self::assertSame('-3.02', $receipt['settlement_adjustment']);
            self::assertSame(['n'=>'-1510409','d'=>'500000'], $receipt['settlement_adjustment_exact']);
            self::assertSame('4.59', $quote['received_by_currency']['USD']);
            self::assertSame('1000.00', $quote['received_by_currency']['VES']);
        }
    }

    public function test_only_the_last_new_dollar_cash_receipt_closes_the_batch(): void
    {
        $rows = [$this->row('2'), $this->row('3.57')];
        foreach ($rows as &$row) $row['exchange_rate'] = '897.53830000';
        unset($row);
        $quote = PaymentQuote::calculate($rows, 'VES', '897.53830000', '5000', fn()=>SpeProfiles::profile(), true, '3');
        self::assertSame('1795.08', $quote['payments'][0]['payment']);
        self::assertNull($quote['payments'][0]['settlement_adjustment']);
        self::assertSame('3204.92', $quote['payments'][1]['payment']);
        self::assertSame('5000.00', $quote['principal_applied']);
        self::assertSame('0.00', $quote['balance']);
        self::assertSame('5.74', $quote['received_by_currency']['USD']);
    }

    public function test_half_up_ties_close_only_the_rounded_up_received_cent(): void
    {
        foreach (['1.01'=>'0.00','1.00'=>'0.50'] as $received=>$balance) {
            $row = $this->row($received);
            $row['exchange_rate'] = '100';
            $quote = PaymentQuote::calculate([$row], 'VES', '100', '100.50', fn()=>SpeProfiles::profile(), true, '3');
            self::assertSame($balance, $quote['balance']);
        }
    }

    public function test_real_shortfalls_and_non_cash_receipts_are_not_closed_by_a_client_marker(): void
    {
        foreach (['5.56'=>'9.69','2.00'=>'3204.92'] as $amount=>$balance) {
            $row = $this->row($amount);
            $row['exchange_rate'] = '897.53830000';
            $row['_settlement_payment'] = '5000';
            $quote = PaymentQuote::calculate([$row], 'VES', $row['exchange_rate'], '5000', fn()=>SpeProfiles::profile(), true, '3');
            self::assertSame($balance, $quote['balance']);
            self::assertNull($quote['payments'][0]['settlement_adjustment']);
        }
        $row = $this->row('5.57', 'USD', '01');
        $row['exchange_rate'] = '897.53830000';
        $quote = PaymentQuote::calculate([$row], 'VES', $row['exchange_rate'], '5000', fn()=>SpeProfiles::profile(), true, '3');
        self::assertSame('0.71', $quote['balance']);
    }

    public function test_rounding_does_not_rewrite_a_prior_receipt_or_a_later_collection(): void
    {
        $row = $this->row('5.57');
        $row['exchange_rate'] = '897.53830000';
        $prior = $row + ['source_sale_note_payment_id'=>9];
        foreach ([[$prior,false],[$row,true]] as [$input,$later]) {
            $quote = PaymentQuote::calculate([$input], 'VES', $row['exchange_rate'], '5000', fn()=>SpeProfiles::profile(), true, '3', $later);
            self::assertSame('0.71', $quote['balance']);
            self::assertNull($quote['payments'][0]['settlement_adjustment']);
            self::assertSame('4999.29', $quote['payments'][0]['payment']);
        }
    }

    public function test_cross_currency_cash_does_not_hide_nonzero_unrepresentable_change(): void
    {
        $row=$this->row('0.57');
        $row['exchange_rate']='897.53830000';
        $this->expectException(ValidationException::class);
        PaymentQuote::calculate([$row], 'VES', '897.53830000', '500', fn()=>SpeProfiles::profile(), true, '3');
    }

    public function test_mixed_currencies_apply_tax_only_to_the_subject_receipt(): void
    {
        $quote = $this->quote([$this->row('60', 'VES', '01'), $this->row('4')], 'VES', '100');
        self::assertSame('0.00', $quote['payments'][0]['tax_amount']);
        self::assertSame('0.12', $quote['payments'][1]['tax_amount']);
        self::assertSame('1.20', $quote['tax_in_document_currency']);
        self::assertSame(['VES'=>'60.00', 'USD'=>'4.12'], $quote['received_by_currency']);
        self::assertSame('101.20', $quote['total_with_tax']);
    }

    public function test_reused_receipts_do_not_generate_tax_or_new_money(): void
    {
        $row = $this->row('50') + ['source_sale_note_payment_id'=>9];
        $quote = $this->quote([$row, $this->row('50')]);
        self::assertSame('0.00', $quote['payments'][0]['tax_amount']);
        self::assertSame('1.50', $quote['payments'][1]['tax_amount']);
        self::assertSame(['USD'=>'51.50'], $quote['received_by_currency']);
        self::assertSame('0.00', $quote['balance']);
    }

    public function test_subject_payment_without_enabled_configuration_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote([$this->row('100')], 'USD', '100', false);
    }

    public function test_financial_intermediation_and_non_spe_profile_do_not_collect_tax(): void
    {
        $row=$this->row('100', 'USD', '01');$row['financial_intermediation']='financial_institution';
        $quote=$this->quote([$row]);
        self::assertSame('not_applicable',$quote['payments'][0]['igtf_status']);
        self::assertSame('0.00',$quote['payments'][0]['tax_amount']);
        $row['financial_intermediation']=null;
        $quote=PaymentQuote::calculate([$row],'USD','10','100',fn()=>SpeProfiles::profile(['spe'=>false]),true,'3');
        self::assertSame('0.00',$quote['payments'][0]['tax_amount']);
        self::assertStringContainsString('perfil',$quote['payments'][0]['reason']);
    }

    // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
    public function test_omitted_intermediation_is_derived_from_the_method(): void
    {
        foreach (['10'=>'subject', '01'=>'not_applicable'] as $method=>$status) {
            $row=$this->row('100', 'USD', (string)$method);
            unset($row['financial_intermediation']);
            $quote=$this->quote([$row]);
            self::assertSame($status, $quote['payments'][0]['igtf_status']);
            self::assertSame($method === 10 ? '3.00' : '0.00', $quote['payments'][0]['tax_amount']);
        }
    }

    public function test_contradictory_intermediation_cannot_change_the_quote_tax(): void
    {
        $row=$this->row('100');$row['financial_intermediation']='financial_institution';
        $this->expectException(ValidationException::class);
        $this->quote([$row]);
    }
    // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########

    public function test_retired_exception_is_rejected_for_an_admin(): void
    {
        $this->actingAs(new \App\Models\Tenant\User(['id'=>1,'type'=>'admin']));
        $row=$this->row('100');$row['igtf_status']='exempt';
        $row['exemption_reason']='Excepción documentada';$row['exception_basis']='Fundamento de prueba';
        $this->expectException(ValidationException::class);
        $this->quote([$row]);
    }

    public function test_non_admin_cannot_authorize_an_exception(): void
    {
        $this->actingAs(new \App\Models\Tenant\User(['id'=>2,'type'=>'seller']));
        $row=$this->row('100');$row['igtf_status']='exempt';
        $row['exemption_reason']='Excepción documentada';$row['exception_basis']='Fundamento de prueba';
        $this->expectException(ValidationException::class);
        $this->quote([$row]);
    }

    public function test_later_payment_cannot_turn_an_overpayment_into_change(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote([$this->row('101')], 'USD', '100', true, true);
    }

    public function test_quote_route_is_registered_inside_the_authenticated_tenant_group(): void
    {
        $this->app->instance(\Hyn\Tenancy\Contracts\CurrentHostname::class,
            (new \Hyn\Tenancy\Models\Hostname())->forceFill(['fqdn'=>'quote.example.test']));
        \Illuminate\Support\Facades\Route::namespace('App\\Http\\Controllers')->group(base_path('routes/web.php'));
        $route=app('router')->getRoutes()->match(\Illuminate\Http\Request::create('http://quote.example.test/documents/payment-quote','POST'));
        self::assertSame('App\\Http\\Controllers\\Tenant\\DocumentPaymentQuoteController@__invoke',$route->getActionName());
        self::assertContains('auth',$route->gatherMiddleware());
    }
}
