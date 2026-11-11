<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Person, User};

final class FiscalProfileService
{
    public static function at($entity, string $date): array
    {
        if (!$entity->getConnection()->getSchemaBuilder()->hasTable('fiscal_profile_versions')) return [];
        $key = $entity instanceof Company ? 'company_id' : 'person_id';
        $row = $entity->getConnection()->table('fiscal_profile_versions')->where($key, $entity->id)
            ->where('effective_from', '<=', $date)->orderByDesc('effective_from')->orderByDesc('id')->first();
        return $row ? json_decode($row->profile, true) + ['version_id'=>$row->id] : [];
    }

    public static function store($entity, array $input): array
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->type === 'admin', 403);
        $profile = SpecialTaxpayerPolicy::validate($input);
        return $entity->getConnection()->transaction(function () use ($entity, $profile) {
            Company::query()->lockForUpdate()->firstOrFail();
            $entity = $entity->newQuery()->lockForUpdate()->findOrFail($entity->id);
            if ($entity instanceof Person && $entity->type !== 'customers') FiscalAmounts::error('person_id', 'El perfil corresponde a un cliente.');
            if ($entity instanceof Company && !$profile['commercial_activity']) FiscalAmounts::error('commercial_activity', 'Marque «Actividad comercial habilitada» para guardar el perfil de la empresa emisora, si corresponde a su situación real.');
            if ($entity instanceof Company && preg_replace('/[^A-Z0-9]/', '', $profile['rif']) !== preg_replace('/[^A-Z0-9]/', '', strtoupper($entity->number))) FiscalAmounts::error('rif', 'El RIF del perfil no coincide con el registrado en los datos de la empresa. Revise ambos valores y corrija el que no corresponda al RIF real.');
            $key = $entity instanceof Company ? 'company_id' : 'person_id';
            $id = $entity->getConnection()->table('fiscal_profile_versions')->insertGetId([
                $key=>$entity->id, 'profile'=>json_encode($profile, JSON_THROW_ON_ERROR),
                'effective_from'=>$profile['effective_from'], 'actor_id'=>auth()->id(), 'created_at'=>now(),
            ]);
            $entity->forceFill(['fiscal_profile'=>$profile + ['version_id'=>$id]])->save();
            return $profile + ['version_id'=>$id];
        });
    }
}
