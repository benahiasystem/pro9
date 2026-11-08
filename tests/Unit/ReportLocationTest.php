<?php

namespace Tests\Unit;

use App\CoreFacturalo\Helpers\Template\ReportHelper;
use PHPUnit\Framework\TestCase;

class ReportLocationTest extends TestCase
{
    public function test_optional_customer_location_is_safe_and_keeps_venezuelan_hierarchy(): void
    {
        self::assertSame(['district'=>'','department'=>'','province'=>''],ReportHelper::getLocationData((object)[]));
        self::assertSame(['district'=>'','department'=>'','province'=>''],ReportHelper::getLocationData((object)['customer'=>(object)['name'=>'Cliente']]));
        $customer=(object)['district'=>(object)['description'=>'Chacao'],'department'=>(object)['description'=>'Miranda'],
            'province'=>(object)['description'=>'Chacao']];
        self::assertSame(['district'=>'Chacao','department'=>'Miranda','province'=>'Chacao'],ReportHelper::getLocationData((object)['customer'=>$customer]));
    }
}
