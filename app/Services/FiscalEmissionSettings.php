<?php

namespace App\Services;

use App\Models\Tenant\Company;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
final class FiscalEmissionSettings
{
    public const MODES = [
        'fiscal_machine' => 'Máquina fiscal',
        'digital' => 'Medios digitales',
        'free_form' => 'Forma libre',
    ];

    public const ENVIRONMENTS = ['demo' => 'Demo', 'production' => 'Producción'];

    public const FIELDS = [
        'fiscal_emission_mode', 'fiscal_environment', 'fiscal_configuration',
        'fiscal_credentials', 'clear_fiscal_credentials',
    ];

    public const PARAMETERS = [
        'fiscal_machine' => ['model', 'serial', 'port', 'provider'],
        'digital' => ['provider', 'authorization'],
        'free_form' => ['printer', 'control_number', 'range_start', 'range_end'],
    ];

    public static function rules(bool $required = true): array
    {
        $rules = [
            'fiscal_emission_mode' => [$required ? 'required' : 'sometimes', Rule::in(array_keys(self::MODES))],
            'fiscal_environment' => [$required ? 'required' : 'sometimes', Rule::in(array_keys(self::ENVIRONMENTS))],
            'fiscal_configuration' => ['sometimes', 'array'],
            'fiscal_credentials' => ['sometimes', 'nullable', 'string', 'max:8192'],
            'clear_fiscal_credentials' => ['sometimes', 'boolean'],
        ];
        return $rules;
    }

    public static function validate(array $input): array
    {
        $data = Validator::make($input, self::rules())->validate();
        $mode = $data['fiscal_emission_mode'];
        $parameters = $data['fiscal_configuration'] ?? [];
        $allowed = self::PARAMETERS[$mode];
        $rules = ['parameters' => ['array:' . implode(',', $allowed)]];
        foreach ($allowed as $key) {
            $rules['parameters.' . $key] = in_array($key, ['range_start', 'range_end'], true)
                ? ['nullable', 'integer', 'min:0', 'max:2147483647']
                : ['nullable', 'string', 'max:255'];
        }
        $validator = Validator::make(['parameters' => $parameters], $rules);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                $errors[str_replace('parameters', 'fiscal_configuration', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        if (isset($parameters['range_start'], $parameters['range_end']) && $parameters['range_start'] > $parameters['range_end']) {
            throw ValidationException::withMessages(['fiscal_configuration.range_end' => 'El final del rango debe ser mayor o igual al inicio.']);
        }
        if ($mode !== 'digital' && (isset($data['fiscal_credentials']) && $data['fiscal_credentials'] !== '')) {
            throw ValidationException::withMessages(['fiscal_credentials' => 'Las credenciales corresponden a la modalidad Medios digitales.']);
        }
        if (!empty($data['clear_fiscal_credentials']) && (isset($data['fiscal_credentials']) && $data['fiscal_credentials'] !== '')) {
            throw ValidationException::withMessages(['fiscal_credentials' => 'Elija reemplazar o eliminar las credenciales.']);
        }
        return $data;
    }

    public static function hasOperations(Company $company): bool
    {
        if ($company->fiscal_environment_locked) {
            return true;
        }
        $connection = $company->getConnection();
        $schema = $connection->getSchemaBuilder();
        foreach (config('fiscal_emission.operation_tables', []) as $table) {
            if ($schema->hasTable($table) && $connection->table($table)->exists()) {
                return true;
            }
        }
        return false;
    }

    public static function publicData(Company $company): array
    {
        return [
            'fiscal_emission_mode' => $company->fiscal_emission_mode,
            'fiscal_environment' => $company->fiscal_environment,
            'fiscal_configuration' => (object) ($company->fiscal_configuration ?? []),
            'fiscal_credentials_configured' => !empty($company->getRawOriginal('fiscal_credentials')),
            'fiscal_environment_locked' => self::hasOperations($company),
            'fiscal_integration_status' => 'not_integrated',
        ];
    }

    public static function update(Company $company, array $input, string $actorType, int $actorId, bool $initial = false): Company
    {
        $data = self::validate($input);
        return $company->getConnection()->transaction(function () use ($company, $data, $actorType, $actorId, $initial) {
            $company = $company->newQuery()->lockForUpdate()->findOrFail($company->id);
            $locked = self::hasOperations($company);
            if ($data['fiscal_environment'] !== $company->fiscal_environment && $locked) {
                throw ValidationException::withMessages(['fiscal_environment' => 'Este tenant tiene operaciones. Cree otro tenant limpio para cambiar de ambiente.']);
            }
            $modeChanged = $company->fiscal_emission_mode !== $data['fiscal_emission_mode'];
            $company->fiscal_environment = $data['fiscal_environment'];
            $company->fiscal_emission_mode = $data['fiscal_emission_mode'];
            $company->fiscal_environment_locked = $locked;
            if ($modeChanged || array_key_exists('fiscal_configuration', $data)) {
                $company->fiscal_configuration = $data['fiscal_configuration'] ?? [];
            }
            if ($modeChanged || !empty($data['clear_fiscal_credentials'])) {
                $company->fiscal_credentials = null;
            }
            if (isset($data['fiscal_credentials']) && $data['fiscal_credentials'] !== '') {
                $company->fiscal_credentials = $data['fiscal_credentials'];
            }
            $changed = array_values(array_unique(array_merge(
                $initial ? ['fiscal_emission_mode', 'fiscal_environment'] : [],
                array_keys($company->getDirty())
            )));
            $company->save();
            if ($changed) {
                $company->getConnection()->table('fiscal_configuration_audits')->insert([
                    'company_id' => $company->id,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'changed_fields' => json_encode($changed),
                    'fiscal_emission_mode' => $company->fiscal_emission_mode,
                    'fiscal_environment' => $company->fiscal_environment,
                    'created_at' => now(),
                ]);
            }
            return $company;
        });
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
