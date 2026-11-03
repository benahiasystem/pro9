<?php

namespace App\CoreFacturalo\Requests\Inputs\Common;

use App\Models\Tenant\Catalogs\Country;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\AddressType;

class OperationDataInput
{
    public static function set($operation_data)
    {

        $address = $operation_data['address'];
        $address_type = AddressType::find($operation_data['address_type_id']);
        $country = Country::find($operation_data['country_id']);
        
        $district_id = $operation_data['district_id'];
        // Los identificadores venezolanos no contienen los códigos de sus padres.
        $district = District::with('province.department')->findOrFail($district_id);
        $province = $district->province;
        $department = $province->department;

        return [ 
            'address' => $address,
            'country_id' => $country->id,
            'country' => [
                'id' => $country->id,
                'description' => $country->description,
            ],
            'department_id' => $department->id,
            'department' => [
                'id' => $department->id,
                'description' => $department->description,
            ],
            'province_id' => $province->id,
            'province' => [
                'id' => $province->id,
                'description' => $province->description,
            ],
            'district_id' => $district->id,
            'district' => [
                'id' => $district->id,
                'description' => $district->description,
            ],
            'address_type_id' => $address_type->id,
            'address_type' => [
                'id' => $address_type->id,
                'description' => $address_type->description,
            ],
        ];
    }
}
