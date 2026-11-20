<?php
namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class DemoRestoreTemporaryBackupDatabase extends \Illuminate\Console\Command
{
    protected $signature = 'demodb:bktemporary {--activate}';
    protected $description = 'Restaura las empresas demo en bases independientes con paquetes PostgreSQL';
    public function handle(): int
    {
        foreach (\App\Models\System\Client::with('hostname.website')->where('enabled_cron_restore_bkdemo',true)->get() as $client) {
            $code=$this->call('demobk:dbrestore',['database'=>$client->hostname->website->uuid,'restore_dbname'=>$client->restore_dbname_bkdemo,'restore_type'=>$client->restore_type_bkdemo,'--activate'=>(bool)$this->option('activate')]);
            if ($code!==0) return $code;
        }
        return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
