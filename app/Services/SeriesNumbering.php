<?php

namespace App\Services;

use App\Models\Tenant\Series;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Document\Models\SeriesConfiguration;

/** Must run in the transaction that inserts the document. */
final class SeriesNumbering
{
    public static function next(Model $model, string $type, string $code, $number, ?int $establishmentId = null): int
    {
        $connection = $model->getConnection();
        if ($connection->transactionLevel() < 1) throw new \LogicException('La numeración requiere una transacción de guardado.');
        \App\Models\Tenant\Company::query()->lockForUpdate()->firstOrFail();
        $series = self::resolve($type, $code, $establishmentId, true);
        $query = $model->newQueryWithoutRelationships()->where('series', $series->number)
            ->where('fiscal_environment', $model->fiscal_environment ?? \App\Models\Tenant\Company::active()->fiscal_environment);
        if ($model->getTable() !== 'sale_notes') $query->where('document_type_id', $type);
        $max = $query->max('number');
        if ($number === '#' || $number === null || $number === '') {
            $start = SeriesConfiguration::where('series_id', $series->id)->value('number') ?? 1;
            $number = $max === null ? (int) $start : (int) $max + 1;
        }
        if (filter_var($number, FILTER_VALIDATE_INT) === false || (int) $number < 1 || (int) $number > 2147483647) {
            throw ValidationException::withMessages(['number' => 'Indique un número documental entero positivo válido.']);
        }
        if ((clone $query)->where('number', $number)->exists()) {
            throw ValidationException::withMessages(['number' => 'El número del documento ya está registrado para esta serie y ambiente.']);
        }
        $series->in_use = true;
        $series->save();
        return (int) $number;
    }

    public static function resolve(string $type, string $code, ?int $establishmentId = null, bool $lock = false): Series
    {
        $query = Series::where('document_type_id', $type)->where('number', strtoupper($code));
        if ($establishmentId !== null) $query->where('establishment_id', $establishmentId);
        if ($lock) $query->lockForUpdate();
        $series = $query->first();
        if (!$series) throw ValidationException::withMessages(['series' => 'La serie no corresponde al documento y a la sucursal seleccionados.']);
        $user = auth()->user();
        if ($user && $user->type !== 'admin' && (int) $user->establishment_id !== (int) $series->establishment_id) abort(403);
        $resolver = app(SeriesResolver::class);
        $group = $resolver->activeGroupId();
        if (($series->dedicated && (!$group || (int) $series->series_device_group_id !== $group)) || (!$series->dedicated && $group)) {
            throw ValidationException::withMessages(['series' => 'La serie no está autorizada para el grupo de este equipo.']);
        }
        if ($resolver->isNrus() && !in_array($type, SeriesCodeGenerator::nrusDocumentTypeIds(), true)) {
            throw ValidationException::withMessages(['series' => 'La serie no está disponible para esta empresa.']);
        }
        return $series;
    }

    /** Also detects older records whose in_use flag was not set. */
    public static function used(Series $series): bool
    {
        if ($series->in_use) return true;
        $db = $series->getConnection();
        foreach (['documents', 'dispatches', 'sale_notes', 'retentions', 'perceptions', 'purchase_settlements', 'guides', 'inventories_transfer'] as $table) {
            if (!$db->getSchemaBuilder()->hasTable($table)) continue;
            $query = $db->table($table)->where('series', $series->number);
            if ($table === 'sale_notes') {
                if ($series->document_type_id !== '80') continue;
            } else $query->where('document_type_id', $series->document_type_id);
            if ($query->exists()) return true;
        }
        return false;
    }
}
