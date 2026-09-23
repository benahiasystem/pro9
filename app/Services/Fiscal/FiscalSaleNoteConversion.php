<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNoteConversion
{
    public static function sourceIds(array $input): array
    {
        $single = $input['sale_note_id'] ?? null;
        $related = $input['sale_notes_relateds'] ?? [];
        if (is_string($related)) $related = json_decode($related, true, 512, JSON_THROW_ON_ERROR);
        if ($single && $related) throw new \DomainException('Seleccione conversión individual o agrupada, sin mezclar ambos orígenes.');
        if (!is_array($related)) throw new \DomainException('Las notas de origen no son válidas.');
        $ids = $single ? [$single] : array_map(static fn ($row) => is_array($row) ? ($row['id'] ?? null) : null, $related);
        if (!$ids) throw new \DomainException('Seleccione al menos una nota de venta.');
        foreach ($ids as $id) if (!is_scalar($id) || !preg_match('/\A[1-9][0-9]*\z/', (string) $id)) throw new \DomainException('El identificador de la nota no es válido.');
        $ids = array_map('intval', $ids);
        if (count(array_unique($ids)) !== count($ids)) throw new \DomainException('Una nota de venta no puede incluirse dos veces.');
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public static function assertAvailable(ConnectionInterface $db, array $input, User $actor): array
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La conversión requiere una transacción.');
        $db->table('companies')->orderBy('id')->lockForUpdate()->first();
        $ids = self::sourceIds($input);
        $sources = $db->table('sale_notes')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($sources->count() !== count($ids)) throw new \DomainException('No se encontraron todas las notas de origen.');
        $total = '0.00';
        foreach ($sources as $source) {
            if (!$source || !in_array($actor->type, ['admin', 'seller', 'integrator'], true)
                || (int) $actor->establishment_id !== (int) $source->establishment_id
                || (int) $input['establishment_id'] !== (int) $source->establishment_id
                || ($actor->type !== 'admin' && (int) $actor->id !== (int) $source->user_id)
                || $input['fiscal_environment'] !== $source->fiscal_environment
                || (int) $input['customer_id'] !== (int) $source->customer_id
                || $input['currency_type_id'] !== $source->currency_type_id) {
                throw new \DomainException('La nota de venta no corresponde al usuario o al contenido de la factura.');
            }
            if ($source->document_id || $source->changed || in_array($source->state_type_id, ['09', '11'], true)
                || $db->table('documents')->where('sale_note_id', $source->id)->exists()) {
                throw new \DomainException('La nota de venta ya está convertida, anulada o rechazada.');
            }
            $total = bcadd($total, (string) $source->total, 2);
        }
        if (bccomp((string) $input['total'], $total, 2) !== 0) throw new \DomainException('El total debe corresponder a la suma de las notas de venta.');
        $paid = (string) $db->table('sale_note_payments')->whereIn('sale_note_id', $ids)->sum('payment');
        foreach ($input['payments'] ?? [] as $payment) {
            if (!is_numeric($payment['payment'] ?? null) || bccomp((string) $payment['payment'], '0', 2) <= 0) {
                throw new \DomainException('Cada cobro nuevo debe tener un importe positivo.');
            }
            if (!empty($payment['id']) || !empty($payment['source_sale_note_payment_id'])) {
                throw new \DomainException('Los cobros originales se aplican automáticamente; sólo envíe cobros nuevos.');
            }
            $paid = bcadd($paid, (string) $payment['payment'], 2);
        }
        if (bccomp($paid, $total, 2) > 0) throw new \DomainException('Los cobros nuevos superan el saldo pendiente de la nota de venta.');
        FiscalSaleNoteItemComparison::assertEquivalent(
            $db->table('sale_note_items')->whereIn('sale_note_id', $ids)->orderBy('id')->lockForUpdate()->get(), $input['items']);
        return $ids;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
