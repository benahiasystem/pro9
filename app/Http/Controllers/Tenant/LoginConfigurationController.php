<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Configuration;
use Modules\Finance\Helpers\UploadFileHelper;


class LoginConfigurationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $config = Configuration::first();

        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $login = $config->login;
        if (is_string($login->image ?? null) && str_starts_with($login->image,'uploads/login/')) {
            $login->image = tenant_asset($login->image);
            $config->login = $login;
        }
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return view('tenant.login_page.index', compact('user', 'config'));
    }

    public function uploadBgImage()
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return tenant_storage_transaction(function () {
        request()->validate([
            'image' => 'required|mimes:jpeg,png,jpg,gif,svg,webp|max:2048'
        ]);

        $config = Configuration::first();
        if (request()->hasFile('image') && request()->file('image')->isValid()) {
            $file = request()->file('image');
            $name = (string)\Illuminate\Support\Str::uuid().'.'.$file->guessExtension();

            UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);

            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            app(\App\Services\Storage\TenantStorage::class)->disk()->putFileAs('uploads/login',$file,$name);
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

            $loginConfig = $config->login;
            $loginConfig->type = 'image';
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $old = $loginConfig->image ?? null;
            $loginConfig->image = 'uploads/login/'.$name;
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $config->login = $loginConfig;
            $config->save();
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            if (is_string($old) && str_starts_with($old,'uploads/login/')) {
                app(\App\Services\Storage\TenantStorage::class)->disk()->delete($old);
            }
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        }

        return response()->json([
            'success' => true,
            'message' => 'Información actualizada.',
        ], 200);

        });
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function update()
    {
        $settings = request()->validate([
            'position_form' => 'required|in:left,right',
            'show_background_image' => 'sometimes|boolean',
            'show_logo_in_form' => 'boolean',
            'position_logo' => 'required|in:on-form,top-left,top-right,bottom-left,bottom-right,none',
            'padding_in_form' => 'boolean',
            'show_socials' => 'boolean',
            'facebook' => 'max:200',
            'twitter' => 'max:200',
            'instagram' => 'max:200',
            'linkedin' => 'max:200',
            'tiktok' => 'max:200',
        ]);

        $config = Configuration::first();
        $loginConfig = $config->login;
        foreach($settings as $key => $option) {
            $loginConfig->$key = $option;
        }
        $config->login = $loginConfig;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Información actualizada.',
        ], 200);
    }
}
