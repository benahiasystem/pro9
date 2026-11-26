<?php

namespace App\Database;

use Illuminate\Database\Connection;

// ########## INICIO CAMBIO POSTGRESQL 17
final class PostgresBootstrap
{
    public static function install(Connection $db): void
    {
        if ($db->getDriverName() !== 'pgsql') throw new \LogicException('Esta instalación requiere PostgreSQL 17.');
        if (intdiv((int) $db->selectOne('SHOW server_version_num')->server_version_num, 10000) !== 17) {
            throw new \RuntimeException('La versión requerida es PostgreSQL 17.');
        }
        $db->statement('CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public');
        $schema = $db->selectOne("SELECT n.nspname FROM pg_extension e JOIN pg_namespace n ON n.oid=e.extnamespace WHERE e.extname='unaccent'")->nspname;
        if ($schema !== 'public') throw new \RuntimeException('unaccent debe estar instalado en public para las búsquedas de Sab.');
        $db->statement("CREATE COLLATION IF NOT EXISTS public.sab_ci_ai (provider=icu, locale='und-u-ks-level1', deterministic=false)");
        $db->statement("CREATE COLLATION IF NOT EXISTS public.sab_search (provider=icu, locale='und', deterministic=true)");
        $db->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.sab_touch_reset() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  PERFORM set_config('sab.touch_' || TG_RELID::text || '_' || TG_ARGV[0], '0', true);
  RETURN NULL;
END $$;
CREATE OR REPLACE FUNCTION public.sab_touch_explicit() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  PERFORM set_config('sab.touch_' || TG_RELID::text || '_' || TG_ARGV[0], '1', true);
  RETURN NEW;
END $$;
CREATE OR REPLACE FUNCTION public.sab_touch_updated_at() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
  field_name text := COALESCE(TG_ARGV[0], 'updated_at');
  flag_name text := 'sab.touch_' || TG_RELID::text || '_' || field_name;
  explicitly_set boolean := COALESCE(current_setting(flag_name, true), '0') = '1';
BEGIN
  PERFORM set_config(flag_name, '0', true);
  IF NOT explicitly_set AND NEW::text IS DISTINCT FROM OLD::text
     AND (to_jsonb(NEW)->>field_name) IS NOT DISTINCT FROM (to_jsonb(OLD)->>field_name) THEN
    NEW := jsonb_populate_record(NEW, jsonb_build_object(field_name, date_trunc('second', statement_timestamp() AT TIME ZONE current_setting('TimeZone'))));
  END IF;
  RETURN NEW;
END $$;
SQL);
    }

    public static function installTouchTrigger(Connection $db, string $table, string $column): void
    {
        $target = self::identifier($table); $field = self::identifier($column); $argument = $db->getPdo()->quote($column);
        foreach (['00_reset' => 'BEFORE UPDATE', '10_explicit' => 'BEFORE UPDATE OF '.$field, '20_touch' => 'BEFORE UPDATE'] as $suffix => $event) {
            $name = self::identifier(self::name($table.'_'.$suffix.'_'.$column));
            $function = ['00_reset'=>'sab_touch_reset', '10_explicit'=>'sab_touch_explicit', '20_touch'=>'sab_touch_updated_at'][$suffix];
            $scope = $suffix === '00_reset' ? 'STATEMENT' : 'ROW';
            $db->statement('CREATE TRIGGER '.$name.' '.$event.' ON '.$target.' FOR EACH '.$scope.' EXECUTE FUNCTION public.'.$function.'('.$argument.')');
        }
    }

    public static function identifier(string $name): string
    {
        if (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_-]{0,62}\z/D', $name)) throw new \InvalidArgumentException('Identificador PostgreSQL inválido o mayor de 63 bytes.');
        return '"'.$name.'"';
    }

    public static function name(string $name): string
    {
        return strlen($name) <= 63 ? $name : substr($name, 0, 50).'_'.substr(hash('sha256', $name), 0, 12);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
