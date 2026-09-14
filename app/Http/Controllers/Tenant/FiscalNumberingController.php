<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\User;
use App\Services\FiscalEmissionSettings;
use App\Services\FiscalNumberingRepository;
use App\Services\FiscalProfileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalNumberingController extends Controller
{
    protected function connection(Request $request, int $establishment)
    {
        $this->authorizeAdministrator($request);
        $db = Company::firstOrFail()->getConnection();
        abort_unless($db->table('establishments')->where('id', $establishment)->exists(), 404);
        return $db;
    }

    protected function authorizeAdministrator(Request $request): void
    {
        if (!$request->user() instanceof User || $request->user()->type !== 'admin') {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Sólo un administrador tenant puede configurar la numeración fiscal.');
        }
    }

    public function records(Request $request, int $establishment)
    {
        $db = $this->connection($request, $establishment);
        $service = new FiscalProfileService($db);
        $sequences = $db->table('fiscal_sequences')->where(function ($query) use ($establishment) {
            $query->whereNull('establishment_id')->orWhere('establishment_id', $establishment);
        })->orderBy('id')->get()->map(function ($sequence) use ($db) {
            $sequence->last_assigned = $db->table('fiscal_number_reservations')->where('sequence_id', $sequence->id)->max('document_number');
            return $sequence;
        });
        $lots = $db->table('fiscal_control_lots')->where('establishment_id', $establishment)->orderBy('id')->get()->map(function ($lot) {
            $lot->exhausted = $lot->next_ordinal > $lot->end_ordinal;
            foreach (['start', 'end', 'next'] as $prefix) {
                $ordinal = (int) $lot->{$prefix . '_ordinal'};
                $lot->{$prefix . '_control'} = $prefix === 'next' && $lot->exhausted ? null : sprintf('%02d-%08d', intdiv($ordinal, 100000000), $ordinal % 100000000);
            }
            return $lot;
        });
        return ['data' => [
            'sequences' => $sequences, 'lots' => $lots,
            'profiles' => $db->table('fiscal_profiles')->where('establishment_id', $establishment)->orderBy('id')->get()->map(fn ($profile) => $service->publicProfile($profile)),
            'groups' => $db->table('series_device_groups')->where('establishment_id', $establishment)->select('id', 'name')->get(),
            'emitters' => $db->table('users')->where('establishment_id', $establishment)->where('active', true)->whereIn('type', ['admin', 'integrator'])->get(['id', 'name']),
            'channels' => FiscalProfileService::CHANNELS, 'document_types' => FiscalProfileService::TYPES,
            'modes' => FiscalEmissionSettings::MODES,
            'capabilities' => collect(array_keys(FiscalEmissionSettings::MODES))->mapWithKeys(fn ($mode) => [$mode => FiscalProfileService::supportedTypes($mode)]),
            'environment' => $db->table('companies')->value('fiscal_environment'),
        ]];
    }

    public function sequence(Request $request, int $establishment)
    {
        $db = $this->connection($request, $establishment);
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'document_type_id' => ['required', Rule::in(array_keys(FiscalProfileService::TYPES))],
            'series_code' => ['present', 'nullable', 'string', 'max:32', 'regex:/\A[A-Z0-9-]*\z/'],
            'initial_number' => ['required', 'integer', 'min:1', 'max:2147483646'],
            'centralized' => ['required', 'boolean'],
        ]);
        return $this->execute(fn () => $db->transaction(function () use ($data, $db, $request, $establishment) {
            $repository = new FiscalNumberingRepository($db);
            $id = $data['id'] ?? null;
            if ($id) {
                $existing = $db->table('fiscal_sequences')->where('id', $id)->first();
                abort_unless($existing && ($existing->establishment_id === null || (int) $existing->establishment_id === $establishment), 404);
                if ($existing->document_type_id !== $data['document_type_id'] || $existing->series_code !== ($data['series_code'] ?? '') || (bool) $data['centralized'] !== ($existing->establishment_id === null)) {
                    throw new \DomainException('La identidad de la numeración no se modifica; cree otra secuencia.');
                }
                $repository->changeInitialNumber($id, (int) $data['initial_number']);
            } else {
                $id = $repository->createSequence($data['document_type_id'], $data['series_code'] ?? '', (int) $data['initial_number'], $data['centralized'] ? null : $establishment);
            }
            (new FiscalProfileService($db))->audit($request->user()->id, 'sequence.saved', 'sequence', $id, array_keys($data));
            return ['id' => $id];
        }));
    }

    public function lot(Request $request, int $establishment)
    {
        $db = $this->connection($request, $establishment);
        $data = $request->validate([
            'start' => ['required', 'string', 'max:11'], 'end' => ['required', 'string', 'max:11'],
            'printer_name' => ['required', 'string', 'max:255'], 'printer_rif' => ['required', 'string', 'max:32'],
            'authorization' => ['required', 'string', 'max:255'], 'authorization_date' => ['required', 'date_format:Y-m-d'],
            'prepared_at' => ['required', 'date_format:Y-m-d'],
        ]);
        $data['establishment_id'] = $establishment;
        return $this->execute(fn () => $db->transaction(function () use ($data, $db, $request) {
            $id = (new FiscalNumberingRepository($db))->createLot($data);
            (new FiscalProfileService($db))->audit($request->user()->id, 'lot.created', 'lot', $id, array_keys($data));
            return ['id' => $id];
        }));
    }

    public function profile(Request $request, int $establishment)
    {
        $db = $this->connection($request, $establishment);
        return $this->execute(fn () => (new FiscalProfileService($db))->save($establishment, $request->all(), $request->user()->id));
    }

    public function archive(Request $request, int $establishment)
    {
        $db = $this->connection($request, $establishment);
        $data = $request->validate(['entity' => ['required', Rule::in(['profile', 'sequence', 'lot'])], 'id' => ['required', 'integer', 'min:1']]);
        return $this->execute(fn () => $db->transaction(function () use ($data, $db, $request, $establishment) {
            $db->table('companies')->orderBy('id')->lockForUpdate()->first();
            $table = ['profile' => 'fiscal_profiles', 'sequence' => 'fiscal_sequences', 'lot' => 'fiscal_control_lots'][$data['entity']];
            $record = $db->table($table)->where('id', $data['id'])->first();
            abort_unless($record && ($record->establishment_id === null || (int) $record->establishment_id === $establishment), 404);
            $db->table($table)->where('id', $record->id)->update(['active' => false, 'updated_at' => now()]);
            (new FiscalProfileService($db))->audit($request->user()->id, $data['entity'] . '.archived', $data['entity'], $record->id, ['active']);
            return ['id' => $record->id];
        }));
    }

    private function execute(callable $operation): array
    {
        try {
            return ['success' => true, 'data' => $operation(), 'message' => 'Configuración de numeración fiscal guardada.'];
        } catch (\DomainException | \InvalidArgumentException $e) {
            throw ValidationException::withMessages(['configuration' => $e->getMessage()]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw ValidationException::withMessages(['configuration' => 'La numeración ya existe o sus referencias no son válidas. Actualice los datos e intente nuevamente.']);
            }
            throw $e;
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
