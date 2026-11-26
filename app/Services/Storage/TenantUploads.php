<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Models\Storage\StorageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Opaque cross-request references; local paths never cross the HTTP boundary. */
final class TenantUploads
{
    private array $files = [];
    private array $claims = [];
    private array $namespaces = [];
    private array $locks = [];
    private ?string $active = null;
    private string $consumptionKey;

    public function __construct() { $this->consumptionKey=(string)Str::uuid(); }

    private function userId(): int
    {
        $user = auth()->user() ?? auth('api')->user();
        abort_unless($user instanceof \App\Models\Tenant\User, 403);
        return (int) $user->id;
    }

    public function create(UploadedFile $file, string $purpose = 'attachment', bool $preview = true): array
    {
        if(!in_array($purpose,['image','attachment'],true))throw new \InvalidArgumentException('Finalidad de carga inválida.');
        $userId = $this->userId();
        if (!$file->isValid()) throw ValidationException::withMessages(['file'=>'No se recibió un archivo válido.']);
        $token = (string) Str::uuid();
        if(!$file->getSize() || $file->getSize()>(int)config('tenant_storage.max_upload_bytes',26214400))throw ValidationException::withMessages(['file'=>'El archivo excede el tamaño permitido.']);
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if($purpose==='image' && !in_array($mime,['image/jpeg','image/png','image/gif','image/webp','image/avif','image/svg+xml'],true))throw ValidationException::withMessages(['file'=>'Seleccione una imagen válida.']);
        $extension = $file->guessExtension() ?: 'bin';
        $path = 'tmp/uploads/'.$token.'/source.'.$extension;
        $disk = app(TenantStorage::class)->disk();
        $disk->putFileAs(dirname($path), $file, basename($path));
        try {
            $row = StorageUpload::create(['id'=>$token,'user_id'=>$userId,'purpose'=>$purpose,'path'=>$path,
                'original_name'=>mb_substr(basename(str_replace('\\','/',$file->getClientOriginalName())),0,255),
                'mime'=>$mime,'size'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath()),
                'expires_at'=>now()->addSeconds(config('tenant_storage.upload_ttl',86400))]);
        } catch (\Throwable $error) { $disk->delete($path); throw $error; }
        $data = ['filename'=>$row->original_name,'upload_id'=>$token,'temp_path'=>$token,'expires_at'=>$row->expires_at->toIso8601String()];
        if ($preview && str_starts_with($mime,'image/')) {
            $data['temp_image'] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        }
        return ['success'=>true,'data'=>$data];
    }

    public function materialize(string $token, string $purpose): string
    {
        $id = $this->userId();
        $uuid = app(TenantStorage::class)->uuid();
        if (isset($this->files[$token])) {
            if($this->namespaces[$token]!==$uuid || (int)$this->claims[$token]->user_id!==$id || $this->claims[$token]->purpose!==$purpose)throw ValidationException::withMessages(['upload_id'=>'La carga corresponde a otra finalidad.']);
            return $this->files[$token];
        }
        if (!Str::isUuid($token)) throw ValidationException::withMessages(['upload_id'=>'La carga debe ser una referencia emitida por SAB.']);
        $lock = Cache::lock('upload:'.$uuid.':'.$token,180);
        if (!$lock->get()) abort(409,'La carga ya se está procesando.');
        $this->locks[] = $lock;
        $row = StorageUpload::whereKey($token)->where('user_id',$id)->first();
        if (!$row || $row->purpose!==$purpose || $row->expires_at->isPast() || $row->consumed_at) {
            throw ValidationException::withMessages(['upload_id'=>'La carga venció, fue utilizada o pertenece a otra solicitud.']);
        }
        $temporary = tempnam(sys_get_temp_dir(),'sab-upload-');
        if ($temporary === false) throw new \RuntimeException('No se pudo preparar la carga.');
        chmod($temporary,0600);
        $this->files[$token] = $temporary;
        $this->claims[$token] = $row;
        $this->namespaces[$token] = $uuid;
        $input = app(TenantStorage::class)->disk()->readStream($row->path);
        $output = fopen($temporary,'wb');
        try {
            if (!is_resource($input) || !$output || stream_copy_to_stream($input,$output) === false) throw new \RuntimeException('No se pudo recuperar la carga.');
        } finally {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
        }
        if (!hash_equals($row->sha256,hash_file('sha256',$temporary))) throw new \RuntimeException('La carga no superó la verificación de integridad.');
        return $temporary;
    }

    public function activateFile(?string $file): void
    {
        $this->active=null;
        if(!$file)return;
        $token=array_search($file,$this->files,true);
        if($token!==false)$this->active=$token;
    }

    /** Called inside the catalog/consumer transaction, for the active source only. */
    public function recordTarget(string $path): void
    {
        if(!$this->active)return;
        if($this->namespaces[$this->active]!==app(TenantStorage::class)->uuid())abort(403);
        $claim=$this->claims[$this->active];
        $imageTarget=str_starts_with($path,'uploads/') && !str_starts_with($path,'uploads/files/');
        if($claim->purpose==='image' && !$imageTarget)return;
        if($claim->purpose==='attachment' && $imageTarget)return;
        if(str_starts_with($path,'tmp/') || str_starts_with($path,'pdf/'))return;
        $row=StorageUpload::whereKey($claim->id)->lockForUpdate()->firstOrFail();
        if($row->expires_at->isPast() || (int)$row->user_id!==$this->userId() || $row->purpose!==$claim->purpose)abort(422,'La carga venció o cambió de propietario.');
        if($row->consumed_at && $row->consumption_key!==$this->consumptionKey)abort(409,'La carga ya fue utilizada.');
        $targets=array_values(array_unique(array_merge($row->targets ?? [],[$path])));
        $row->update(['targets'=>$targets,'consumed_at'=>now(),'consumption_key'=>$this->consumptionKey]);
    }

    public function cleanup(): void
    {
        foreach ($this->files as $file) if (is_file($file)) unlink($file);
        foreach ($this->locks as $lock) {
            try { $lock->release(); } catch (\Throwable $ignored) {}
        }
        $this->files=[];
        $this->claims=[];
        $this->namespaces=[];
        $this->locks=[];
        $this->active=null;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
