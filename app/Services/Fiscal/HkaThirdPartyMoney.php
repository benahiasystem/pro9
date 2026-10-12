<?php
namespace App\Services\Fiscal;

/** Compatibility entry point; new payloads use the shared domestic adapter once. */
final class HkaThirdPartyMoney
{
    public static function adapt(array $payload, array $snapshot): array
    {
        return HkaDomesticMoney::adapt($payload, $snapshot);
    }
}
