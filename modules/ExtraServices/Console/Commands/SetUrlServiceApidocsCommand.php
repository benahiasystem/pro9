<?php

namespace Modules\ExtraServices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SetUrlServiceApidocsCommand extends Command
{
    protected $signature = 'extraservices:apidocs:urlservice
                            {url : URL del servicio ApiDocs (urlServiceApidocs)}';

    protected $description = 'Crea y ejecuta una migración para actualizar urlServiceApidocs en extra_services';

    public function handle(): int
    {
        $url = trim((string) $this->argument('url'));

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('La URL proporcionada no es válida.');

            return self::FAILURE;
        }

        $timestamp = now()->format('Y_m_d_His');
        $filename = "{$timestamp}_set_url_service_apidocs_on_extra_services_table.php";
        $path = database_path('migrations/' . $filename);
        $urlLiteral = var_export($url, true);

        $contents = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('extra_services')->update([
            'urlServiceApidocs' => {$urlLiteral},
        ]);
    }

    public function down()
    {
        DB::table('extra_services')->update([
            'urlServiceApidocs' => null,
        ]);
    }
};

PHP;

        File::put($path, $contents);
        $this->info("Migration [{$path}] created successfully.");

        $exitCode = Artisan::call('migrate', [
            '--path' => 'database/migrations/' . $filename,
            '--force' => true,
        ]);

        $this->output->write(Artisan::output());

        if ($exitCode !== 0) {
            $this->error('La migración se creó, pero falló al ejecutarse.');

            return self::FAILURE;
        }

        //$this->info("urlServiceApidocs actualizado a: {$url}");

        return self::SUCCESS;
    }
}
