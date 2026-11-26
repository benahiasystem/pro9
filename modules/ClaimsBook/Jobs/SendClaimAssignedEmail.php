<?php

// Modules/ClaimsBook/Jobs/SendClaimAssignedEmail.php

namespace Modules\ClaimsBook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant\Configuration;
use Modules\ClaimsBook\Mail\ClaimAssigned;
use Modules\ClaimsBook\Models\Tenant\Claim;

/**
 * Job para notificar al usuario asignado cuando se crea un nuevo reclamo.
 * Se envía una sola vez al momento del registro inicial.
 */
class SendClaimAssignedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    protected int $claimId;
    protected string $tenantUuid;

    public function __construct(int $claimId)
    {
        $this->tenantUuid=app(\App\Services\Storage\TenantStorage::class)->uuid();
        $this->afterCommit();
        $this->claimId = $claimId;
    }

    /**
     * Envía el correo de notificación al usuario asignado.
     */
    public function handle(): void
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        app(\App\Services\Storage\TenantContext::class)->run($this->tenantUuid,function(){
        // Aplicar configuración SMTP del tenant activo
        Configuration::setConfigSmtpMail();

        $claim = Claim::with('statusClaim', 'assignedUser')->find($this->claimId);

        if (! $claim) {
            Log::warning("SendClaimAssignedEmail: Claim {$this->claimId} not found");
            return;
        }

        $assignedUser = $claim->assignedUser;
        if (! $assignedUser || empty($assignedUser->email)) {
            Log::info("SendClaimAssignedEmail: No assigned user or email for claim {$this->claimId}");
            return;
        }

        $data = [
            'user_name'  => $assignedUser->name,
            'claim' => [
                'id'         => $claim->id,
                'code'       => $claim->code,
                'public_code'=> $claim->public_code,
                'claim_type' => $claim->claim_type,
                'name'       => $claim->name,
                'email'      => $claim->email,
                'phone'      => $claim->phone,
                'detail'     => $claim->detail,
                'created_at' => $claim->created_at?->format('Y-m-d H:i'),
                'due_date'   => $claim->due_date?->format('d/m/Y'),
            ],
            'status' => $claim->statusClaim
                ? ['name' => $claim->statusClaim->description, 'color' => $claim->statusClaim->color ?? '#066FD1']
                : ['name' => null, 'color' => '#066FD1'],
        ];

        $mailable = new ClaimAssigned($data);

        // Adjuntar el PDF de constancia si está disponible
        if ($claim->pdf_path) {
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $disk=app(\App\Services\Storage\TenantStorage::class)->disk();
            if($disk->exists($claim->pdf_path)) {
                $mailable->attachData($disk->get($claim->pdf_path),$claim->public_code.'.pdf',['mime'=>'application/pdf']);
            }
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        }

        Mail::to($assignedUser->email)->send($mailable);

        Log::info("SendClaimAssignedEmail: Email enviado a {$assignedUser->email} para reclamo {$claim->code}");
        });
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }
}
