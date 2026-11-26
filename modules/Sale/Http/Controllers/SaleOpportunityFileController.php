<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\SearchItemController;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Item;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\Sale\Models\SaleOpportunityFile;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Helpers\UploadFileHelper;

class SaleOpportunityFileController extends Controller
{

    public function saveFiles($sale_opportunity, $files)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return tenant_storage_transaction(function () use ($sale_opportunity, $files) {

        foreach ($files as $row) {

            $file = isset($row['response']['data']) ? $row['response']['data'] : null;

            if($file){

                $temp_path = $file['temp_path'];

                $file_name_old = $file['filename'];
                $extension = strtolower(pathinfo($file_name_old, PATHINFO_EXTENSION));
                $file_name = Str::slug(pathinfo($file_name_old, PATHINFO_FILENAME)).'-'.$sale_opportunity->id.'.'.$extension;

                // validaciones archivos
                $allowed_file_types_images = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg', 'image/webp'];
                $is_image = UploadFileHelper::getIsImage($temp_path, $allowed_file_types_images);

                $allowed_file_types = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg', 'image/webp', 'application/pdf'];
                UploadFileHelper::checkIfValidFile($file_name, $temp_path, $is_image, 'jpg,jpeg,png,gif,svg,webp,pdf', $allowed_file_types);
                // validaciones archivos
                
                // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                app(\App\Services\Storage\TenantStorage::class)->disk()->put('sale_opportunity_files'.DIRECTORY_SEPARATOR.$file_name, $file_content);
                // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

            }else{

                $file_name = $row['filename'];

            }


            $sale_opportunity->files()->create([
                'filename' => $file_name
            ]);

        }


        });
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function uploadFile(Request $request)
    {

        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,gif,svg,webp,pdf', false);

        if(!$validate_upload['success']){
            return $validate_upload;
        }

        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return $this->upload_file($new_request);
        }
        return [
            'success' => false,
            'message' =>  'No es un archivo',
        ];
    }


    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    function upload_file($request)
    {
        return app(\App\Services\Storage\TenantUploads::class)->create($request['file'],'attachment',false);
    }
    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

    public function download($filename) {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $owner = SaleOpportunityFile::where('filename', $filename)->with('sale_opportunity')->firstOrFail()->sale_opportunity;
        $user = auth()->user();
        abort_unless($owner && $user && ($user->isAdmin() || (int) $owner->establishment_id === (int) $user->establishment_id), 404);
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download('sale_opportunity_files'.DIRECTORY_SEPARATOR.$filename);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    /**
     * @param $id
     *
     * @return array
     */
    public function searchItemById($id)
    {
        $items =  SearchItemController::searchByIdToModal($id);
        return compact('items');

    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function searchItems(Request $request)
    {
        $items = SearchItemController::getNotServiceItemToModal($request);
        return compact('items');
    }
}
