<?php

namespace Tests\Unit;

use App\Services\Fiscal\{FiscalAmounts, RetiredIgtfException, SpecialTaxpayerPolicy};
use Illuminate\Validation\ValidationException;
use Tests\Support\SpeProfiles;
use Tests\TestCase;

// ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
class IgtfExceptionRetirementTest extends TestCase
{
    public static function retiredInputs(): array
    {
        return [
            'status'=>[['igtf_status'=>'exempt'], 'igtf_status'],
            'complete'=>[['igtf_status'=>'exempt','exemption_reason'=>'Original','exception_basis'=>'Anterior'], 'igtf_status'],
            'reason'=>[['exemption_reason'=>'Anterior'], 'exemption_reason'],
            'basis'=>[['igtf_status'=>'not_applicable','exception_basis'=>'Anterior'], 'exception_basis'],
            'zero is content'=>[['exemption_reason'=>'0'], 'exemption_reason'],
            'no forged reuse'=>[['igtf_status'=>'exempt','id'=>99,'_reused_receipt'=>true], 'igtf_status'],
        ];
    }

    /** @dataProvider retiredInputs */
    public function test_new_payments_and_profiles_reject_retired_input(array $input, string $field): void
    {
        foreach (['admin','seller'] as $role) {
            $this->actingAs(new \App\Models\Tenant\User(['id'=>1,'type'=>$role]));
            foreach ([
                fn()=>SpecialTaxpayerPolicy::payment(SpeProfiles::profile(), $input+['financial_intermediation'=>'none'], 'USD', '2026-09-10'),
                fn()=>FiscalAmounts::payment($input+['payment'=>'100'], 'USD', '10', true, '3'),
                fn()=>(new \App\Http\Controllers\Tenant\FiscalProfileController())->evaluate(\Illuminate\Http\Request::create('/', 'POST', $input)),
            ] as $operation) {
                try { $operation(); self::fail('La excepción retirada fue aceptada.'); }
                catch (ValidationException $error) { self::assertSame(422,$error->status); self::assertArrayHasKey($field,$error->errors()); }
            }
        }
    }

    public function test_empty_retired_fields_do_not_change_automatic_classification(): void
    {
        $input=['exemption_reason'=>null,'exception_basis'=>'  ','financial_intermediation'=>'none'];
        $decision=SpecialTaxpayerPolicy::payment(SpeProfiles::profile(),$input,'USD','2026-09-10');
        self::assertSame(['igtf_status'=>'subject','financial_intermediation'=>'none'],$decision);
        $values=FiscalAmounts::payment($input+['igtf_status'=>'subject','payment'=>'100'],'USD','10',true,'3');
        self::assertSame('3.00',$values['tax_amount']); self::assertArrayNotHasKey('exemption_reason',$values);
    }

    public function test_mobile_capture_bridge_rejects_retired_input_before_creating_a_payment(): void
    {
        $method=new \ReflectionMethod(\Modules\MobileApp\Http\Controllers\Api\ReceivedPaymentController::class,'runDocumentPaymentStore');
        $method->setAccessible(true);
        foreach (self::retiredInputs() as [$input,$field]) {
            $request=\Illuminate\Http\Request::create('/', 'POST', $input);
            try {
                $method->invoke(new \Modules\MobileApp\Http\Controllers\Api\ReceivedPaymentController(),
                    new \Modules\MobileApp\Models\ReceivedPayment(), $request);
                self::fail('La captura móvil aceptó la excepción.');
            } catch (ValidationException $error) { self::assertSame(422,$error->status);self::assertArrayHasKey($field,$error->errors()); }
        }
    }

    public function test_unsaved_models_cannot_enable_historical_compatibility(): void
    {
        $this->expectException(ValidationException::class);
        \App\Services\Fiscal\RecordedPaymentAmounts::context(new \App\Models\Tenant\DocumentPayment(['igtf_status'=>'exempt']),[]);
    }

    public function test_historical_cash_replay_uses_frozen_net_principal_and_tax_after_change(): void
    {
        $snapshot=['values'=>['tendered_original_amount'=>['n'=>'300','d'=>'1'],
            'payment'=>['n'=>'232','d'=>'1'],'tax_amount'=>['n'=>'0','d'=>'1']]];
        $stored=(new \App\Models\Tenant\DocumentPayment())->newFromBuilder(['id'=>12,'igtf_status'=>'exempt',
            'exemption_reason'=>'LEGACY-REASON','currency_type_id'=>'USD','original_amount'=>'232.00','payment'=>'232.00','tax_amount'=>'0.00',
            'calculation_snapshot'=>json_encode($snapshot),'fiscal_context'=>json_encode(['decision'=>['exception_basis'=>'LEGACY-BASIS','authorized_by'=>7]])]);
        $before=$stored->getAttributes();
        $values=\App\Services\Fiscal\RecordedPaymentAmounts::forRetry($stored,
            ['currency_type_id'=>'USD','original_amount'=>'300.00','payment'=>'300.00','exchange_rate'=>'10'], 'USD', '10');
        self::assertTrue(\App\Support\Exact\Decimal::of($values['original_amount'])->isEqualTo('232'));
        self::assertTrue(\App\Support\Exact\Decimal::of($values['payment'])->isEqualTo('232'));
        self::assertTrue(\App\Support\Exact\Decimal::of($values['tax_amount'])->isZero());self::assertSame('exempt',$values['igtf_status']);
        self::assertSame($snapshot,$values['calculation_snapshot']);self::assertSame($before,$stored->getAttributes());
    }
}
// ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
