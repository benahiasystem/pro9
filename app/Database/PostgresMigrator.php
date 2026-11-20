<?php

namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Central models and DDL must share one connection during an installation. */
class PostgresMigrator extends \Illuminate\Database\Migrations\Migrator
{
    public function usingConnection($name, callable $callback)
    {
        $previous = $this->resolver->getDefaultConnection();
        $system = config('tenancy.db.system-connection-name', 'system');
        if ($name === 'central_migrator') config(['tenancy.db.system-connection-name' => $name]);
        $this->setConnection($name);
        try { return $callback(); }
        finally {
            $this->setConnection($previous);
            config(['tenancy.db.system-connection-name' => $system]);
        }
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
