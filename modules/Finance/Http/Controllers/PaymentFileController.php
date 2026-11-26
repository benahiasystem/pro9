<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Finance\Models\PaymentFile;
use App\Models\Tenant\Cash;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Helpers\UploadFileHelper;

class PaymentFileController extends Controller
{ 

    public function download($filename, $type) { 
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $paymentTypes = [
            'documents' => \App\Models\Tenant\DocumentPayment::class,
            'sale_notes' => \App\Models\Tenant\SaleNotePayment::class,
            'purchases' => \App\Models\Tenant\PurchasePayment::class,
            'quotations' => \Modules\Sale\Models\QuotationPayment::class,
            'expenses' => \Modules\Expense\Models\ExpensePayment::class,
        ];
        abort_unless(isset($paymentTypes[$type]) && basename($filename) === $filename, 404);
        $file = PaymentFile::where('filename', $filename)->where('payment_type', $paymentTypes[$type])->firstOrFail();
        $record = $file->payment?->associated_record_payment;
        $user = auth()->user();
        abort_unless($record && $user && ($user->isAdmin() ||
            ($record->establishment_id && (int) $record->establishment_id === (int) $user->establishment_id)), 404);
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download('payment_files'.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$filename);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function uploadAttached(Request $request)
    {
        // dd($request->all());
        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,gif,svg,webp,pdf', false);
        
        if(!$validate_upload['success']){
            return $validate_upload;
        }

        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
                'index' => $request->input('index'),
            ];

            return $this->upload_attached($new_request);
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }


    function upload_attached($request)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $result = app(\App\Services\Storage\TenantUploads::class)->create($request['file']);
        $result['data']['index'] = (int) ($request['index'] ?? 0);
        return $result;
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }


}
