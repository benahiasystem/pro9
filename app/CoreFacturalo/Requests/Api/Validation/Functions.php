<?php

namespace App\CoreFacturalo\Requests\Api\Validation;

use App\Models\Tenant\Document;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Series;
use App\Models\Tenant\Catalogs\UnitType;
use Exception;
use App\Models\Tenant\Configuration;
use App\Services\SeriesCodeGenerator;
use App\Services\SalesCustomerIdentityPolicy;
use App\Support\Venezuela\IdentityDocument;
use App\Support\Venezuela\PersonLocation;
use Carbon\Carbon;

class Functions
{
    public static function establishment($inputs) {
        $establishment = Establishment::where('code', $inputs['code'])->first();

        if ($establishment) {
            return $establishment->id;
        }

        throw new Exception("El código ingresado del establecimiento es incorrecto.");
    }

    public static function person($inputs, $type) {
        $identityDocumentTypeId = (string) $inputs['identity_document_type_id'];
        $number = IdentityDocument::normalizeNumber(
            $identityDocumentTypeId,
            $inputs['number'] ?? ''
        );

        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        $location = PersonLocation::resolve((new Person())->getConnection(), $inputs['country_id'], $inputs['district_id'] ?? null);
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

        $person = Person::updateOrCreate([
            'type' => $type,
            'identity_document_type_id' => $identityDocumentTypeId,
            'number' => $number,
        ], [
            'name' => $inputs['name'],
            'trade_name' => $inputs['trade_name'],
            'country_id' => $inputs['country_id'],
            'department_id' => $location['department_id'],
            'province_id' => $location['province_id'],
            'district_id' => $location['district_id'],
            'address' => $inputs['address'],
            'email' => $inputs['email'],
            'telephone' => $inputs['telephone'],
            'address_type_id' => $inputs['address_type_id'],
        ]);

        return $person->id;
    }

    public static function item($inputs)
    {
        // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        $inputs['unit_type_id'] = UnitType::requireActiveCode($inputs['unit_type_id'] ?? null);
        // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        $item = Item::where('internal_id', $inputs['internal_id'])
            ->first();

        if (!$item) {

            $item = new Item();
            $item->internal_id = $inputs['internal_id'];
            $item->description = $inputs['description'];
            $item->name = $inputs['name'];
            $item->second_name = $inputs['second_name'];
            $item->item_type_id = $inputs['item_type_id'];
            $item->item_code = $inputs['item_code'];
            $item->item_code_gs1 = $inputs['item_code_gs1'];
            $item->unit_type_id = $inputs['unit_type_id'];
            $item->currency_type_id = $inputs['currency_type_id'];
            $item->sale_unit_price =  $inputs['unit_price'];
            $item->sale_affectation_igv_type_id = $inputs['affectation_igv_type_id'];
            $item->purchase_affectation_igv_type_id = $inputs['affectation_igv_type_id'];
            $item->stock = 0;
            $item->save();

        }else{

            $update_description = isset($inputs['update_description']) ? $inputs['update_description'] : false;

            if($update_description)
            {
                $item->update([
                    'description' => $inputs['description'],
                ]);
            }

        }

        return $item->id;
    }

    public static function item2($inputs) {

        // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        $inputs['unit_type_id'] = UnitType::requireActiveCode($inputs['unit_type_id'] ?? null);
        // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########

        $item = Item::firstOrCreate([
            'internal_id' => $inputs['internal_id'],
        ], [
            'description' => $inputs['description'],
            'name' => $inputs['name'],
            'second_name' => $inputs['second_name'],
            'item_type_id' => $inputs['item_type_id'],
            'item_code' => $inputs['item_code'],
            'item_code_gs1' => $inputs['item_code_gs1'],
            'unit_type_id' => $inputs['unit_type_id'],
            'currency_type_id' => $inputs['currency_type_id'],
            'sale_unit_price' =>  $inputs['unit_price'],
            'sale_affectation_igv_type_id' => $inputs['affectation_igv_type_id'],
            'purchase_affectation_igv_type_id' => $inputs['affectation_igv_type_id'],
            // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
            // Una orden de salida no crea existencias iniciales.
            'stock' => 0
            // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        ]);
        return $item->id;
    }

    public static function findAffectedDocumentByExternalId($external_id) {
        $document = Document::where('external_id', $external_id)
            ->first();

        if (!$document) throw new Exception("No se encontró el documento con código externo {$external_id}.");

        return $document;
    }

    public static function voidedDocuments($inputs) {
        if (count($inputs['documents']) === 0) {
            throw new Exception("No se enviaron documentos para la anulación.");
        }

        $documents = [];
        foreach ($inputs['documents'] as $row) {
            $document = Document::where('external_id', $row['external_id'])
                ->whereDate('date_of_issue', $inputs['date_of_reference'])
                ->where('group_id', '01')
                ->first();

            if (!$document) throw new Exception("El código externo {$row['external_id']} no fue encontrado o la fecha indica no corresponde al documento.");

            $documents[] = [
                'document_id' => $document->id,
                'description' => $row['description']
            ];
        }

        return $documents;
    }

    public static function validateSeries($inputs) {
        $series = Series::where('number', $inputs['series'])
            ->where('document_type_id', $inputs['document_type_id'])
            ->where('establishment_id', $inputs['establishment_id'])
            ->first();

        if (!$series) {
            throw new Exception("La serie ingresada {$inputs['series']}, es incorrecta.");
        }

        if ((bool) optional(Configuration::first())->isNrus()
            && ! in_array($series->document_type_id, SeriesCodeGenerator::nrusDocumentTypeIds(), true)) {
            throw new Exception("Para empresas NRUS solo están disponibles las series de Nota de venta.");
        }
    }

    public static function DNI($inputs)
    {
    }

    public static function identityDocumentTypeInvoice($inputs)
    {
        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        SalesCustomerIdentityPolicy::assertCustomerAllowed($inputs['customer_id'] ?? null);
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########
    }




    public static function validateRequiredDistrict($district_id)
    {
        if (is_null($district_id)) throw new Exception("El campo ubigeo es obligatorio");

        if (strlen($district_id) !== 6) throw new Exception("El campo ubigeo debe contener 6 dígitos");

        $exist_district = District::select('id')->find($district_id);
        if (!$exist_district) throw new Exception("El código ubigeo es incorrecto");
    }


    /**
     *
     * Validar fecha de emisión en base a los días configurados en el plazo de envío
     *
     * Días contados desde la fecha de emisión
     *
     * @param  array $inputs
     * @return void
     */
    public static function validateDateOfIssue($inputs)
    {

        $configuration = Configuration::select('shipping_time_days', 'restrict_receipt_date')->firstOrFail();

        if($configuration->restrict_receipt_date)
        {
            $today = Carbon::now();
            $date_of_issue = Carbon::parse($inputs['date_of_issue']);
            $difference_days = $configuration->shipping_time_days - $date_of_issue->diffInDays($today);

            if($difference_days <= 0) throw new Exception("La fecha de emisión no puede ser menor a {$configuration->shipping_time_days} día(s).");
        }

    }


}
