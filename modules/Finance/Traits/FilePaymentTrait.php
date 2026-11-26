<?php

namespace Modules\Finance\Traits;

use Carbon\Carbon;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\SaleNotePayment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Finance\Helpers\UploadFileHelper;


trait FilePaymentTrait
{

    public function saveFiles($record, $request, $type)
    {

        $temp_path = $request->temp_path;

        if($temp_path) {

            $file_name_old = $request->filename;
            $file_content = file_get_contents($temp_path);
            $allowed_mimes = 'jpg,jpeg,png,gif,svg,webp,pdf';
            $extension = UploadFileHelper::resolveExtensionFromFile($file_name_old, $temp_path, $allowed_mimes);
            $base_name = pathinfo($file_name_old, PATHINFO_FILENAME);
            $file_name = Str::slug($base_name).'-'.Str::uuid().'.'.$extension;

            // validaciones archivos
            $allowed_file_types_images = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg', 'image/webp'];
            $is_image = UploadFileHelper::getIsImage($temp_path, $allowed_file_types_images);

            $allowed_file_types = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg', 'image/webp', 'application/pdf'];
            UploadFileHelper::checkIfValidFile($file_name, $temp_path, $is_image, 'jpg,jpeg,png,gif,svg,webp,pdf', $allowed_file_types);
            // validaciones archivos


            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            app(\App\Services\Storage\TenantStorage::class)->disk()->put('payment_files'.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$file_name, $file_content);
            $record->payment_file()->create([
                'filename' => $file_name
            ]);

            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE


        }

    }
    

    /**
     * 
     * Guardar archivos de los pagos relacionados a cada documento
     * 
     * Usado en:
     * Facturalo
     * SaleNoteController
     *
     * @param  array $row
     * @param  DocumentPayment|SaleNotePayment $record
     * @param  string $append_folder
     * @return void
     */
    private function saveFilesFromPayments($row, $record, $append_folder)
    {
        $temp_path = $row['temp_path'] ?? false;

        if($temp_path)
        {
            $params_payment_file = [
                'temp_path' => $temp_path,
                'filename' => $row['filename'],
            ];

            $this->saveFiles($record, (object) $params_payment_file, $append_folder);
        }
    }

}
