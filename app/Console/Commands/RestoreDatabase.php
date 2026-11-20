<?php
namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class RestoreDatabase extends \Illuminate\Console\Command
{
    protected $signature = 'database:restoredemo {--activate}';
    protected $description = 'Restaura demos PostgreSQL sin borrar el destino antes de validar';
    public function handle(): int { return $this->call('demodb:bktemporary',['--activate'=>(bool)$this->option('activate')]); }
}
// ######### FIN CAMBIO POSTGRESQL 17
