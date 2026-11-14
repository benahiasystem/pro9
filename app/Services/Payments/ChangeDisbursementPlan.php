<?php

namespace App\Services\Payments;

use App\Support\Exact\Decimal as D;

// ######## INICIO REPARTO EXACTO DE VUELTO ########
/** Validate cash amounts and preserve exact conversions before registering any outflow. */
final class ChangeDisbursementPlan
{
    /** Resolve the selectable methods from the tenant; currency and account are never client authority. */
    public static function forMethods($change, string $sourceCurrency, $rate, array $lines, int $establishmentId, bool $lock = false): array
    {
        $connection = \Illuminate\Support\Facades\DB::connection('tenant');
        if ($lock && !$connection->transactionLevel()) throw new \LogicException('La validación del vuelto requiere una transacción.');
        if (!$connection->getSchemaBuilder()->hasColumn('payment_method_types', 'available_for_change')) {
            Classification::error('change_disbursements', 'La instalación aún no dispone de la configuración de vueltos.');
        }
        if (!$lines || count($lines) > 20) Classification::error('change_disbursements', 'Indique entre uno y veinte desembolsos.');
        $ids = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !isset($line['payment_method_type_id']) || !is_string($line['payment_method_type_id'])) {
                Classification::error('change_disbursements', 'Seleccione un método para cada desembolso.');
            }
            if (isset($line['reference']) && (!is_string($line['reference']) || mb_strlen($line['reference']) > 255)) {
                Classification::error('change_disbursements', 'La referencia del desembolso no es válida.');
            }
            $ids[] = $line['payment_method_type_id'];
        }
        $query = \App\Models\Tenant\PaymentMethodType::query()->whereIn('id', array_unique($ids));
        if ($lock) $query->lockForUpdate();
        $methods = $query->get()->keyBy('id');
        $accountIds = $methods->pluck('bank_account_id')->filter()->unique()->values()->all();
        $accountsQuery = \App\Models\Tenant\BankAccount::query()->whereIn('id', $accountIds);
        if ($lock) $accountsQuery->lockForUpdate();
        $accounts = $accountsQuery->get()->keyBy('id');
        $resolved = [];
        foreach ($lines as $index => $line) {
            $method = $methods->get($line['payment_method_type_id']);
            if (!$method || !$method->is_active || ($method->collection_kind !== 'cash' && !$method->available_for_change) || !in_array($method->collection_kind, ['cash', 'account'], true)) {
                Classification::error('change_disbursements.'.$index.'.payment_method_type_id', 'Seleccione un método activo disponible para vueltos.');
            }
            if (!in_array($method->currency_type_id, ['VES', 'USD'], true)) Classification::error('change_disbursements', 'La moneda del método no está disponible.');
            if ($method->collection_kind === 'account') {
                $account = $accounts->get($method->bank_account_id);
                $branchMatches = $account && $account->establishment_id !== null && (int)$account->establishment_id === $establishmentId;
                if ($account && $account->establishment_id === null) {
                    $branches = \App\Models\Tenant\Establishment::query()->limit(2)->pluck('id');
                    $branchMatches = $branches->count() === 1 && (int)$branches->first() === $establishmentId;
                }
                if (!$account || !$account->status || $account->currency_type_id !== $method->currency_type_id || !$branchMatches) {
                    Classification::error('change_disbursements.'.$index.'.payment_method_type_id', 'La cuenta del método debe estar activa y pertenecer a la sucursal y moneda del comprobante.');
                }
            }
            $resolved[] = ['currency_type_id'=>$method->currency_type_id, 'amount'=>$line['amount'] ?? null];
        }
        MethodCurrency::assertActiveCurrencies($connection, array_values(array_unique(array_column($resolved, 'currency_type_id'))));
        $plan = self::calculate($change, $sourceCurrency, $rate, $resolved);
        foreach ($plan['lines'] as $index => &$line) {
            $method = $methods->get($lines[$index]['payment_method_type_id']);
            $line['payment_method_type_id'] = (string)$method->id;
            $line['payment_destination_id'] = $method->collection_kind === 'cash' ? 'cash' : $method->bank_account_id;
            $line['reference'] = $lines[$index]['reference'] ?? null;
        }
        unset($line);
        return $plan;
    }

    public static function calculate($change, string $sourceCurrency, $rate, array $lines): array
    {
        $target = D::of(D::input($change, 'change', 2));
        if (!$target->isPositive() || !$lines || count($lines) > 20) {
            Classification::error('change_disbursements', 'Indique un vuelto positivo y entre uno y veinte desembolsos.');
        }
        if (!in_array($sourceCurrency, ['VES', 'USD'], true)) {
            Classification::error('currency_type_id', 'La moneda del vuelto debe ser VES o USD.');
        }
        $rate = \App\Services\ExchangeRates\ExchangeRateMath::rate($rate);
        $sum = D::of(0);
        $prepared = [];
        foreach ($lines as $index => $line) {
            if (!is_array($line)) Classification::error('change_disbursements', 'Indique los detalles de cada desembolso.');
            $currency = $line['currency_type_id'] ?? '';
            $amount = D::of(D::input($line['amount'] ?? null, 'change_disbursements.'.$index.'.amount', 2));
            if (!$amount->isPositive()) Classification::error('change_disbursements.'.$index.'.amount', 'El importe debe ser positivo.');
            $converted = D::convert($amount, $currency, $sourceCurrency, $rate);
            $sum = $sum->plus($converted);
            $prepared[] = ['currency_type_id'=>$currency, 'amount'=>D::represent($amount, 2, true),
                'source_equivalent'=>D::represent($converted),
                'calculation_snapshot'=>D::snapshot(['amount'=>$amount, 'source_equivalent'=>$converted])];
        }
        if (!D::of(D::represent($sum, 2))->isEqualTo($target)) {
            Classification::error('change_disbursements', 'Los desembolsos deben cubrir exactamente el vuelto en la moneda recibida.');
        }
        return ['lines'=>$prepared, 'source_currency'=>$sourceCurrency, 'exchange_rate'=>$rate,
            'source_equivalent'=>D::represent($sum),
            'calculation_snapshot'=>D::snapshot(['change'=>$target, 'source_equivalent'=>$sum,
                'settlement_adjustment'=>$sum->minus($target)])];
    }
}
// ######## FIN REPARTO EXACTO DE VUELTO ########
