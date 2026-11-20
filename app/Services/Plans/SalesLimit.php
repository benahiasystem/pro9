<?php

namespace App\Services\Plans;

use App\Models\Tenant\{Configuration, Document};
use App\Support\Exact\{DashboardSales, Decimal as D, ReadSnapshot};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Reporting owns its read; emission validates its own uncommitted header under a tenant lock. */
final class SalesLimit
{
    public static function read($start, $end, bool $includeNotes=false): array
    {
        return ReadSnapshot::run((new Document)->getConnection(),fn()=>self::totals($start,$end,$includeNotes));
    }

    public static function forEmission($start, $end, bool $includeNotes=false): array
    {
        $db=(new Document)->getConnection();
        self::lockWriter($db);
        return self::totals($start,$end,$includeNotes);
    }

    public static function defer($start, $end, bool $includeNotes, $limit): void
    {
        $db=(new Document)->getConnection();
        if (!$db instanceof \App\Database\PostgresConnection || $db->transactionLevel()<1) {
            throw new \LogicException('El límite requiere la conexión PostgreSQL de la transacción de escritura.');
        }
        $limit=D::of($limit);
        $key='sales-limit:'.hash('sha256',json_encode([$start,$end,$includeNotes,D::fraction($limit)],JSON_THROW_ON_ERROR));
        if ($db->hasBeforeCommitValidation($key)) return;
        self::lockWriter($db);
        $db->beforeCommitValidation($key,static function () use ($start,$end,$includeNotes,$limit) {
            $totals=self::totals($start,$end,$includeNotes);
            if (D::readRequired($totals,'total')->isGreaterThan($limit)) {
                throw new \RuntimeException('Ha superado el límite de ventas permitido.');
            }
        });
    }

    private static function lockWriter($db): void
    {
        if ($db->transactionLevel()<1) throw new \LogicException('El límite de emisión requiere la transacción de escritura.');
        if ($db->getDriverName()!=='pgsql' || $db->selectOne('SHOW transaction_isolation')->transaction_isolation!=='read committed') {
            throw new \LogicException('El límite de emisión requiere READ COMMITTED.');
        }
        // The lock survives until the final header is validated and the outer write commits.
        Configuration::withoutEagerLoads()->whereKey(1)->lockForUpdate()->firstOrFail();
    }

    private static function totals($start, $end, bool $includeNotes): array
    {
        $documents=$notes=D::of(0);
        foreach (DashboardSales::documents(null,$start,$end)->lazyById(500) as $document) {
            $documents=$documents->plus(DashboardSales::documentAmount($document))->simplified();
        }
        if ($includeNotes) foreach (DashboardSales::notes(null,$start,$end)->lazyById(500) as $note) {
            $notes=$notes->plus(DashboardSales::inVes($note))->simplified();
        }
        $values=['document_totals'=>$documents,'sale_note_totals'=>$notes,'total'=>$documents->plus($notes)->simplified()];
        return D::values($values)+['calculation_snapshot'=>D::snapshot($values)];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
