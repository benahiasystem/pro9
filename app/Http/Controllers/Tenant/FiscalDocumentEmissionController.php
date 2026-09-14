<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalEmissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalDocumentEmissionController extends Controller
{
    protected string $subjectTable = 'documents';
    protected string $reservationColumn = 'document_id';
    public function record(Request $request, int $document)
    {
        $db = $this->authorizedConnection($request, $document);
        return ['data' => $this->view($db, $document, $request->user())];
    }

    public function process(Request $request, int $document)
    {
        return $this->mutate($request, $document, 'process');
    }

    public function confirmPrint(Request $request, int $document)
    {
        return $this->mutate($request, $document, 'confirmPrinted');
    }

    public function invalidatePrint(Request $request, int $document)
    {
        return $this->mutate($request, $document, 'invalidatePrint');
    }

    public function contingency(Request $request, int $document): array
    {
        $db = $this->authorizedConnection($request, $document, true);
        $data = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'profile_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:255'],
        ])->validate();
        $original = $db->table('fiscal_number_reservations')->where($this->reservationColumn, $document)->first();
        if (!$original) throw new NotFoundHttpException('El documento no tiene reserva fiscal.');
        $modelClass = $this->subjectTable === 'dispatches' ? \App\Models\Tenant\Dispatch::class : \App\Models\Tenant\Document::class;
        $subject = (new $modelClass())->setConnection($db->getName())->newQuery()->findOrFail($document);
        $type = $this->subjectTable === 'dispatches' ? 'dispatch' : ($subject->document_type_id === '01' ? 'invoice' : ($subject->document_type_id === '07' ? 'credit' : 'debit'));
        try {
            (new \App\Services\Fiscal\FiscalContingencyService($db))->start((int) $original->id,
                (int) $data['profile_id'], $data['reason'], (int) $request->user()->id,
                function () use ($subject, $type) {
                    (new \App\CoreFacturalo\Facturalo())->createPdf($subject, $type, 'a4', 'validate');
                });
            // A file failure may be retried against the same linked physical reservation.
            (new \App\CoreFacturalo\Facturalo())->createPdf($subject, $type, 'a4');
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['fiscal' => $exception->getMessage()]);
        }
        return ['success' => true, 'data' => $this->view($db, $document, $request->user())];
    }

    private function mutate(Request $request, int $document, string $action): array
    {
        $db = $this->authorizedConnection($request, $document, $action === 'invalidatePrint');
        $reservation = $db->table('fiscal_number_reservations')->where($this->reservationColumn, $document)->first();
        if (!$reservation) {
            throw new NotFoundHttpException('El documento no tiene reserva fiscal.');
        }
        $service = new FiscalEmissionService($db);
        try {
            if ($action === 'invalidatePrint') {
                $data = \Illuminate\Support\Facades\Validator::make($request->all(), ['reason' => ['required', 'string', 'max:255']])->validate();
                $service->invalidatePrint((int) $reservation->id, (int) $request->user()->id, $data['reason']);
            } elseif ($action === 'confirmPrinted') {
                $service->confirmPrinted((int) $reservation->id, (int) $request->user()->id);
            } else {
                $service->process((int) $reservation->id);
            }
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['fiscal' => $exception->getMessage()]);
        }
        return ['success' => true, 'data' => $this->view($db, $document, $request->user())];
    }

    protected function connection()
    {
        return Company::active()->getConnection();
    }

    protected function authorizedConnection(Request $request, int $document, bool $adminOnly = false)
    {
        $user = $request->user();
        if (!$user instanceof User || !in_array($user->type, $adminOnly ? ['admin'] : ['admin', 'seller', 'integrator'], true)) {
            throw new AccessDeniedHttpException('No tiene permiso para esta acción fiscal.');
        }
        $db = $this->connection();
        $query = $db->table($this->subjectTable)->where('id', $document)->where('establishment_id', $user->establishment_id);
        if ($user->type !== 'admin') {
            $query->where(function ($scope) use ($user) {
                $scope->where('user_id', $user->id);
                if ($this->subjectTable === 'documents') {
                    $scope->orWhere('seller_id', $user->id);
                }
            });
        }
        if (!$query->exists()) {
            throw new NotFoundHttpException('Documento no disponible para el usuario y sucursal.');
        }
        return $db;
    }

    private function view($db, int $document, User $user): ?array
    {
        $record = $db->table('fiscal_number_reservations')->where($this->reservationColumn, $document)->first();
        if (!$record) {
            return null;
        }
        $originalId = $record->id;
        $record = \App\Services\Fiscal\FiscalReservation::effective($db, $record);
        $snapshot = json_decode($record->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $result = json_decode($record->provider_result ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $canContingency = $user->type === 'admin' && in_array($record->status, ['reserved', 'rejected'], true)
            && in_array($snapshot['mode'], ['digital', 'fiscal_machine'], true);
        $profiles = $canContingency ? $db->table('fiscal_profiles')->where('establishment_id', $user->establishment_id)
            ->where('document_type_id', $snapshot['document_type_id'])->where('channel', 'contingency')
            ->where('mode', 'free_form')->where('active', true)
            ->where('device_group_id', $snapshot['profile']['device_group_id'] ?? null)->get(['id', 'name']) : [];
        return [
            'status' => $record->status, 'mode' => $snapshot['mode'], 'environment' => $snapshot['environment'],
            'series' => $snapshot['series'], 'document_number' => (string) $record->document_number,
            'control_number' => $record->control_number, 'issued_at' => $record->issued_at,
            'simulated' => (bool) ($result['simulated'] ?? false),
            'provider_reference' => $result['provider_reference'] ?? null,
            'invalidation_reason' => $record->invalidation_reason,
            'contingency' => $snapshot['contingency'] ?? null,
            'can_process' => in_array($record->status, ['reserved', 'uncertain', 'processing'], true),
            'can_confirm_print' => $record->status === 'awaiting_print',
            'can_invalidate_print' => $record->status === 'awaiting_print' && $user->type === 'admin',
            'can_start_contingency' => $canContingency, 'contingency_profiles' => $profiles,
            'attempts' => $db->table('fiscal_emission_attempts')->whereIn('reservation_id', [$originalId, $record->id])
                ->orderBy('id')->get(['action', 'status', 'created_at', 'finished_at']),
        ];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
