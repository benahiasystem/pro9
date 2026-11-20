<?php
namespace App\Tenancy;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Persistent workers must release both the connection and its credentials between jobs. */
final class TenantJobContext
{
    private static array $previous=[];
    public static function begin($job): void
    {
        $id=spl_object_id($job);
        if (array_key_exists($id,self::$previous)) throw new \LogicException('El trabajo ya tiene un contexto activo.');
        self::$previous[$id]=app(\App\Tenancy\Environment::class)->tenant();
        try { self::select($job->payload()['website_id'] ?? null); }
        catch (\Throwable $error) { self::finish($job);throw $error; }
    }
    public static function finish($job): void
    {
        $id=spl_object_id($job);
        if (!array_key_exists($id,self::$previous)) return;
        $previous=self::$previous[$id];unset(self::$previous[$id]);self::clear();
        if ($previous) app(\App\Tenancy\Environment::class)->tenant($previous);
    }
    public static function clear(): void
    {
        app(Environment::class)->switchTenant(null);
    }
    public static function select($websiteId): void
    {
        self::clear();
        if ($websiteId === null) return;
        $website=app(\App\Tenancy\Repositories\WebsiteRepository::class)->findById($websiteId);
        if (!$website) throw new \LogicException('La empresa del trabajo ya no existe.');
        TenantReadiness::ensure($website->uuid);
        app(\App\Tenancy\Environment::class)->tenant($website);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
