<?php

namespace Modules\Ecommerce\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Company;
use App\Http\Requests\Tenant\ConfigurationEcommerceRequest;
use App\Http\Resources\Tenant\ConfigurationEcommerceResource;
use Modules\Finance\Helpers\UploadFileHelper;
use Illuminate\Support\Facades\Storage;


class ConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('ecommerce::configuration.index');
    }

    public function record() {
        $configuration = ConfigurationEcommerce::first();
        $record = new ConfigurationEcommerceResource($configuration);
        
        $bank_accounts = \App\Models\Tenant\BankAccount::with('bank', 'currency_type')->get()->map(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->bank->description . ' - ' . $row->currency_type->symbol . ' - ' . $row->number,
            ];
        });

        return [
            'data' => $record,
            'bank_accounts' => $bank_accounts
        ];
    }


    public function store_configuration(ConfigurationEcommerceRequest $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());

        // Guardar campos de páginas personalizadas si existen en el request
        if ($request->has('terms_conditions')) {
            $configuration->terms_conditions = $request->input('terms_conditions');
        }
        if ($request->has('privacy_policy')) {
            $configuration->privacy_policy = $request->input('privacy_policy');
        }
        if ($request->has('about_us')) {
            $configuration->about_us = $request->input('about_us');
        }
        if ($request->has('delivery_no_coverage_message')) {
            $configuration->delivery_no_coverage_message = $request->input('delivery_no_coverage_message');
        }

        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    public function store_configuration_delivery(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->delivery_no_coverage_message = $request->input('delivery_no_coverage_message', '');
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de delivery actualizada'
        ];
    }

    public function store_configuration_culqui(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        
        $preferences = $configuration->preferences ?: [];
        $preferences['ecommerce_bank_account_ids'] = $request->input('ecommerce_bank_account_ids', []);
        
        $preferences['enable_cash'] = $request->input('enable_cash', 0);
        $preferences['cash_title'] = $request->input('cash_title', 'Pago contra entrega');
        $preferences['cash_description'] = $request->input('cash_description', null);
        $preferences['cash_pickup_only'] = $request->input('cash_pickup_only', 0) ? true : false;
        
        // Custom gateway configurations
        $preferences['enable_izipay'] = $request->input('enable_izipay', 0);
        $preferences['title_izipay'] = $request->input('title_izipay', 'Pago con Izipay');
        $preferences['description_izipay'] = $request->input('description_izipay', null);

        $preferences['enable_mp'] = $request->input('enable_mp', 0);
        $preferences['title_mp'] = $request->input('title_mp', 'Mercado Pago');
        $preferences['description_mp'] = $request->input('description_mp', null);

        $preferences['enable_culqi'] = $request->input('enable_culqi', 0);
        $preferences['title_culqi'] = $request->input('title_culqi', 'Pago con Tarjeta');
        $preferences['description_culqi'] = $request->input('description_culqi', null);

        $configuration->fill($request->all());
        $configuration->preferences = $preferences;
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    public function store_configuration_paypal(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Paypal actualizada'
        ];
    }

    public function store_configuration_tag(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Tags actualizada'
        ];
    }

    public function store_configuration_social(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de Redes Sociales actualizada'
        ];
    }

    public function uploadFile(Request $request)
    {
        if ($request->hasFile('file')) {

            $config = ConfigurationEcommerce::first();
            $company = Company::first();

            $type = $request->input('type'); //logo_store

            $file = $request->file('file');

            if (!$file->isValid() || empty($file->getPathname()) || !is_file($file->getPathname())) {
                return [
                    'success' => false,
                    'message' =>  __('app.actions.upload.error'),
                ];
            }

            $ext = $file->getClientOriginalExtension();
            $name = $type.'_'.$company->number.'.'.$ext;

            request()->validate(['file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);

            UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);

            $stream = fopen($file->getPathname(), 'r');
            Storage::put('public/uploads/logos/'.$name, $stream);
            if (is_resource($stream)) fclose($stream);

            $config->logo = $name;

            $config->save();

            return [
                'success' => true,
                'message' => __('app.actions.upload.success'),
                'name' => $name,
                'type' => $type
            ];
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    public function store_configuration_links(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de links personalizados actualizado'
        ];
    }

    public function store_configuration_color(Request $request)
    {

        $id = $request->input('id');
        $color = $request->input('color_ecommerce');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->color_ecommerce = $color;

        // Guardar preferencias (el cast a array maneja automáticamente el json_encode)
        $configuration->preferences = [
            'show_description' => (int) $request->input('show_description', 1),
            'show_stock' => (int) $request->input('show_stock', 0),
            'only_available_products' => (int) $request->input('only_available_products', 0),
            'full_width_banner' => (int) $request->input('full_width_banner', 0),
            'header_theme' => in_array($request->input('header_theme'), ['light', 'dark']) ? $request->input('header_theme') : 'light',
            'products_per_page' => in_array((int) $request->input('products_per_page'), [8, 12, 16, 24, 32, 40]) ? (int) $request->input('products_per_page') : 16,
        ];

        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de color y preferencias actualizadas correctamente'
        ];

    }

    public function getColorEcommerce()
        {
            $config = \App\Models\Tenant\ConfigurationEcommerce::first();
            $color = $config ? $config->color_ecommerce : null;
            return response()->json(['color' => $color]);
        }

}
