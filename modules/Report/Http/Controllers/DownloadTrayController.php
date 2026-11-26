<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant\DownloadTray;
use Modules\Report\Http\Resources\DownloadTrayCollecion;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;


class DownloadTrayController extends Controller
{
    use StorageDocument;

    public function index()
    {
        return view('report::download-tray.index');
    }

    public function columns()
    {
        return [
            'description' => 'Producto',
        ];
    }

    public function records(Request $request)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $records = $this->authorizedTrays()->latest();
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

        return new DownloadTrayCollecion($records->paginate(config('tenant.items_per_page')));
    }

    public function download($id)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $record = $this->authorizedTrays()->where('status','FINISHED')->findOrFail($id);
        return $this->downloadStorage($record->file_name, $record->path);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    private function authorizedTrays()
    {
        $user = auth()->user();
        abort_unless($user instanceof \App\Models\Tenant\User,403);
        return DownloadTray::query()->when(!$user->isAdmin(),fn($query)=>$query->where('user_id',$user->id));
    }
    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

}
