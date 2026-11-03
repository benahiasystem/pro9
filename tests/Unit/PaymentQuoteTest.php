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
        });
        DB::connection('tenant')->table('payment_method_types')->insert([
            ['id'=>'10','collection_kind'=>'cash'], ['id'=>'01','collection_kind'=>'account'],
        ]);
    }

    private function row(string $amount, string $currency = 'USD', string $method = '10'): array
    {
        return ['date_of_payment'=>'2026-09-10', 'payment_method_type_id'=>$method,
            'currency_type_id'=>$currency, 'original_amount'=>$amount, 'payment'=>$amount,
            'exchange_rate'=>'10', 'financial_intermediation'=>'none'];
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
        $row=$this->row('100');$row['financial_intermediation']='financial_institution';
        $quote=$this->quote([$row]);
        self::assertSame('not_applicable',$quote['payments'][0]['igtf_status']);
        self::assertSame('0.00',$quote['payments'][0]['tax_amount']);
        $row['financial_intermediation']=null;
        $quote=PaymentQuote::calculate([$row],'USD','10','100',fn()=>SpeProfiles::profile(['spe'=>false]),true,'3');
        self::assertSame('0.00',$quote['payments'][0]['tax_amount']);
        self::assertStringContainsString('perfil',$quote['payments'][0]['reason']);
    }

    public function test_spe_currency_receipt_requires_the_actual_intermediation(): void
    {
        $row=$this->row('100');$row['financial_intermediation']=null;
        $this->expectException(ValidationException::class);
        $this->quote([$row]);
    }

    public function test_exception_requires_an_admin_and_retains_its_basis(): void
    {
        $this->actingAs(new \App\Models\Tenant\User(['id'=>1,'type'=>'admin']));
        $row=$this->row('100');$row['igtf_status']='exempt';
        $row['exemption_reason']='Excepción documentada';$row['exception_basis']='Fundamento de prueba';
        $quote=$this->quote([$row]);
        self::assertSame('exempt',$quote['payments'][0]['igtf_status']);
        self::assertSame('0.00',$quote['payments'][0]['tax_amount']);
    }

    public function test_non_admin_cannot_authorize_an_exception(): void
    {
        $this->actingAs(new \App\Models\Tenant\User(['id'=>2,'type'=>'seller']));
        $row=$this->row('100');$row['igtf_status']='exempt';
        $row['exemption_reason']='Excepción documentada';$row['exception_basis']='Fundamento de prueba';
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
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
