<?php

namespace Modules\Accounting\Support;

use App\Database\PostgresBootstrap;
use Illuminate\Support\Facades\DB;

/** Database-enforced accounting evidence rules for PostgreSQL installations. */
final class PostgresIntegrity
{
    private static function identifier(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/D', $name)) {
            throw new \InvalidArgumentException('Identificador contable inválido.');
        }
        return PostgresBootstrap::identifier(PostgresBootstrap::name($name));
    }

    public static function install(string $table, string $name, string $event, string $body, string $declarations = ''): void
    {
        $connection = DB::connection('tenant');
        if ($connection->getDriverName() !== 'pgsql' || !in_array($event, ['INSERT', 'UPDATE', 'DELETE'], true)) {
            throw new \LogicException('La integridad contable requiere PostgreSQL y un evento explícito.');
        }
        $function = 'public.'.self::identifier($name.'_fn');
        $declare = $declarations === '' ? '' : 'DECLARE '.$declarations."\n";
        $connection->transaction(function () use ($connection, $function, $declare, $body, $name, $event, $table): void {
            $connection->unprepared('CREATE FUNCTION '.$function.'() RETURNS trigger LANGUAGE plpgsql '
                .'SET search_path = pg_catalog, public, pg_temp AS $accounting_guard$' ."\n".$declare."BEGIN\n".$body
                ."\nIF TG_OP = 'DELETE' THEN RETURN OLD; END IF;\nRETURN NEW;\nEND;\n\$accounting_guard$;");
            $connection->statement('CREATE TRIGGER '.self::identifier($name).' BEFORE '.$event.' ON public.'.self::identifier($table)
                .' FOR EACH ROW EXECUTE FUNCTION '.$function.'()');
        });
    }

    public static function reject(string $table, string $name, string $event, string $message, ?string $condition = null): void
    {
        // 45000 is the existing accounting API's business-integrity contract.
        $raise = "RAISE EXCEPTION USING ERRCODE = '45000', MESSAGE = ".DB::connection('tenant')->getPdo()->quote($message).';';
        self::install($table, $name, $event, $condition === null ? $raise : 'IF '.$condition.' THEN '.$raise.' END IF;');
    }

    public static function postedLines(string $name, string $event): void
    {
        $parents = match ($event) {
            'INSERT' => 'NEW.journal_entry_id',
            'DELETE' => 'OLD.journal_entry_id',
            'UPDATE' => 'OLD.journal_entry_id, NEW.journal_entry_id',
            default => throw new \InvalidArgumentException('Evento de partida inválido.'),
        };
        self::install('accounting_journal_lines', $name, $event,
            'FOR entry IN SELECT id, posted_at FROM public.accounting_journal_entries WHERE id IN ('.$parents.') ORDER BY id FOR UPDATE LOOP '
            ."IF entry.posted_at IS NOT NULL THEN RAISE EXCEPTION USING ERRCODE = '45000', MESSAGE = 'Líneas contabilizadas inmutables'; END IF; END LOOP;",
            'entry record;');
    }

    public static function drop(string $table, string $name): void
    {
        $connection = DB::connection('tenant');
        $connection->transaction(function () use ($connection, $table, $name): void {
            $connection->statement('DROP TRIGGER IF EXISTS '.self::identifier($name).' ON public.'.self::identifier($table));
            $connection->statement('DROP FUNCTION IF EXISTS public.'.self::identifier($name.'_fn').'()');
        });
    }
}
