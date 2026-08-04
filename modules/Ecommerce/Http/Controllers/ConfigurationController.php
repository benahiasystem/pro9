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
use Modules\Payment\Models\PaymentConfiguration;


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

        $gateway_availability = PaymentConfiguration::getEcommerceGatewayAvailability();

        return [
            'data' => $record,
            'bank_accounts' => $bank_accounts,
            'gateway_availability' => $gateway_availability,
        ];
    }


    public function store_configuration(ConfigurationEcommerceRequest $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->except(['preferences']));

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

        // Configuración de cotizaciones (tienda virtual)
        if ($request->has('quotation_enabled') || $request->exists('quotation_enabled')) {
            $configuration->quotation_enabled = $request->boolean('quotation_enabled');
        }

        $mode = $request->input('quotation_mode', $configuration->quotation_mode ?: 'quote_and_sell');
        if (! in_array($mode, ['quote_and_sell', 'quote_only'], true)) {
            $mode = 'quote_and_sell';
        }
        if ($request->exists('quotation_mode') || $request->exists('quotation_enabled')) {
            $configuration->quotation_mode = $mode;
        }

        // Ocultar precios solo es válido en "solo cotizar". En híbrido siempre se muestran.
        if ($configuration->quotation_mode === 'quote_and_sell') {
            $configuration->quotation_show_prices = true;
        } elseif ($request->exists('quotation_show_prices')) {
            $configuration->quotation_show_prices = $request->boolean('quotation_show_prices');
        }
        if ($request->exists('quotation_success_message')) {
            $configuration->quotation_success_message = $request->input('quotation_success_message');
        }
        if ($request->exists('quotation_validity_days')) {
            $configuration->quotation_validity_days = max(1, min(90, (int) $request->input('quotation_validity_days')));
        }
        if ($request->exists('quotation_terms')) {
            $configuration->quotation_terms = $request->input('quotation_terms');
        }

        if ($request->has('preferences') && is_array($request->input('preferences'))) {
            $configuration->preferences = $this->mergePreferences(
                $configuration->preferences ?: [],
                $request->input('preferences')
            );
        }

        $configuration->save();

        $modeLabel = $configuration->quotation_mode === 'quote_only'
            ? 'Solo cotizar'
            : 'Cotizar y vender';

        return [
            'success' => true,
            'message' => 'Configuración actualizada ('.$modeLabel.')',
            'quotation_mode' => $configuration->quotation_mode,
            'quotation_show_prices' => (bool) $configuration->quotation_show_prices,
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
        $configuration = ConfigurationEcommerce::findOrFail($id);
        $gateway_availability = PaymentConfiguration::getEcommerceGatewayAvailability();

        $preferences = $configuration->preferences ?: [];

        if ($request->exists('ecommerce_bank_account_ids')) {
            $preferences['ecommerce_bank_account_ids'] = $request->input('ecommerce_bank_account_ids', []);
        }

        if ($request->exists('enable_cash')) {
            $preferences['enable_cash'] = $request->input('enable_cash', 0);
        }
        if ($request->exists('cash_title')) {
            $preferences['cash_title'] = $request->input('cash_title', 'Pago contra entrega');
        }
        if ($request->exists('cash_description')) {
            $preferences['cash_description'] = $request->input('cash_description', null);
        }
        if ($request->exists('cash_pickup_only')) {
            $preferences['cash_pickup_only'] = $request->input('cash_pickup_only', 0) ? true : false;
        }

        if ($request->exists('enable_izipay')) {
            $preferences['enable_izipay'] = ($gateway_availability['izipay'] && (bool) $request->input('enable_izipay', 0)) ? 1 : 0;
        }
        if ($request->exists('title_izipay')) {
            $preferences['title_izipay'] = $request->input('title_izipay', 'Pago con Izipay');
        }
        if ($request->exists('description_izipay')) {
            $preferences['description_izipay'] = $request->input('description_izipay', null);
        }

        if ($request->exists('enable_mp')) {
            $preferences['enable_mp'] = $gateway_availability['mercadopago']
                ? (int) $request->input('enable_mp', 0)
                : 0;
        }
        if ($request->exists('title_mp')) {
            $preferences['title_mp'] = $request->input('title_mp', 'Mercado Pago');
        }
        if ($request->exists('description_mp')) {
            $preferences['description_mp'] = $request->input('description_mp', null);
        }

        if ($request->exists('enable_culqi')) {
            $preferences['enable_culqi'] = ($gateway_availability['culqi'] && (bool) $request->input('enable_culqi', 0)) ? 1 : 0;
        }
        if ($request->exists('title_culqi')) {
            $preferences['title_culqi'] = $request->input('title_culqi', 'Pago con Tarjeta');
        }
        if ($request->exists('description_culqi')) {
            $preferences['description_culqi'] = $request->input('description_culqi', null);
        }

        // Misma lógica que PaymentConfigurationController: desactivar la pasarela en conflicto
        // en lugar de rechazar el guardado con 422.
        if ($request->exists('enable_izipay') || $request->exists('enable_culqi')) {
            PaymentConfiguration::enforceEcommerceIzipayCulqiExclusivity($preferences);
        }

        $configuration->fill($request->only([
            'token_private_culqui',
            'token_public_culqui',
            'script_paypal',
        ]));

        if ($request->exists('enable_yape')) {
            $configuration->enable_yape = $gateway_availability['yape']
                ? (bool) $request->input('enable_yape', 0)
                : false;
        }

        if ($request->exists('enable_transfer')) {
            $configuration->enable_transfer = (bool) $request->input('enable_transfer', 0);
        }

        $configuration->preferences = $preferences;
        $configuration->save();

        return $this->buildPaymentGatewayResponse($configuration);
    }

    /**
     * Fusiona preferencias existentes con las nuevas sin perder claves no enviadas.
     */
    private function mergePreferences(array $current, array $incoming): array
    {
        return array_merge($current, $incoming);
    }

    /**
     * Respuesta estándar para autoguardado de pasarelas de pago.
     */
    private function buildPaymentGatewayResponse(ConfigurationEcommerce $configuration): array
    {
        return [
            'success' => true,
            'message' => 'Configuración actualizada',
            'gateway_availability' => PaymentConfiguration::getEcommerceGatewayAvailability(),
            'data' => (new ConfigurationEcommerceResource($configuration->fresh()))->resolve(),
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

        // Fusionar preferencias de apariencia sin borrar pasarelas de pago u otras claves.
        $configuration->preferences = $this->mergePreferences(
            $configuration->preferences ?: [],
            [
                'show_description' => (int) $request->input('show_description', 1),
                'show_stock' => (int) $request->input('show_stock', 0),
                'only_available_products' => (int) $request->input('only_available_products', 0),
                'full_width_banner' => (int) $request->input('full_width_banner', 0),
                'header_theme' => in_array($request->input('header_theme'), ['light', 'dark']) ? $request->input('header_theme') : 'light',
                'products_per_page' => in_array((int) $request->input('products_per_page'), [8, 12, 16, 24, 32, 40]) ? (int) $request->input('products_per_page') : 16,
            ]
        );

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
