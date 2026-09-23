<?php

namespace App\Services\Fiscal;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Api\Validation\DispatchValidation;
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use App\CoreFacturalo\Requests\Inputs\Common\ActionInput;
use App\Models\Tenant\Company;
use App\Services\FiscalNumberingRepository;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalApiDispatchService
{
    /** Materialize customer/items only after reserving the authorized operation, in the same transaction. */
    public static function register(array $input): Facturalo
    {
        $db = Company::active()->getConnection();
        return $db->transaction(function () use ($db, $input) {
            $profile = (int) $input['fiscal_profile_id'];
            $key = $input['operation_key'];
            $hash = $input['fiscal_fingerprint'];
            $channel = $input['fiscal_channel'];
            $group = $input['fiscal_group_id'];
            $reservation = (new FiscalNumberingRepository($db))->reserveForProfile($profile, $key, $hash,
                (int) $input['establishment_id'], $channel, $group);
            $data = $reservation->dispatch_id
                ? ['type' => 'dispatch', 'establishment_id' => $input['establishment_id'], 'actions' => ActionInput::set($input)]
                : DispatchInput::set(DispatchValidation::materialize($input));
            return (new Facturalo())->saveFiscal($data, $profile, $key, $hash, $channel, $group);
        });
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
