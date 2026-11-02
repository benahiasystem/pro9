<?php

namespace Modules\Suscription\Services;

use App\Http\Controllers\Tenant\SaleNoteController;
use Illuminate\Support\Facades\Storage;

/** Conserva el estado anterior de los PDF hasta que se confirma el lote. */
class SubscriptionSaleNoteController extends SaleNoteController
{
    private array $previousFiles = [];

    public function uploadFile($filename, $file_content, $file_type)
    {
        if ($file_type === 'sale_note') {
            $path = 'sale_note/'.$filename.'.pdf';
            $disk = Storage::disk('tenant');
            if (!array_key_exists($path, $this->previousFiles)) {
                $this->previousFiles[$path] = $disk->exists($path) ? $disk->get($path) : null;
            }
            if (!$disk->put($path, $file_content)) {
                throw new \RuntimeException('No se pudo guardar el PDF de la nota de suscripción.');
            }
            return;
        }
        parent::uploadFile($filename, $file_content, $file_type);
    }

    public function restoreFiles(): void
    {
        $disk = Storage::disk('tenant');
        foreach ($this->previousFiles as $path => $previous) {
            $restored = $previous === null ? $disk->delete($path) : $disk->put($path, $previous);
            if (!$restored) throw new \RuntimeException('No se pudo restaurar el PDF de la nota de suscripción.');
        }
        $this->previousFiles = [];
    }
}
