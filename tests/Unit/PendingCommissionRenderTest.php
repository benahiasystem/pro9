<?php

namespace Tests\Unit;

use Tests\TestCase;

class PendingCommissionRenderTest extends TestCase
{
    public function test_pdf_and_excel_show_the_out_of_range_total(): void
    {
        $this->app['view']->addNamespace('report', base_path('modules/Report/Resources/views'));
        $company=(object)['name'=>'Prueba','number'=>'J123'];
        $records=[['user_name'=>'Vendedor','currency_rows'=>[['currency_type_id'=>'VES','total_out_of_range'=>'12.34','total_within_range'=>'8.20',
            'total_completed'=>'20.54','commission'=>'0.41','commission_reason'=>null]]]];
        foreach (['report_pdf','report_excel'] as $format) {
            $html=view('report::pending-account-commissions.'.$format,compact('company','records'))->render();
            self::assertStringContainsString('12.34',$html);
            self::assertStringContainsString('20.54',$html);
        }
    }
}
