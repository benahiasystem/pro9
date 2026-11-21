<?php
// ######## INICIO API BCV ########
require dirname(__DIR__, 2).'/vendor/autoload.php';
\Illuminate\Database\Connection::resolverFor('pgsql', static fn ($pdo,$database,$prefix,$config) => new \App\Database\PostgresConnection($pdo,$database,$prefix,$config));
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    config([
        'tenancy.db.tenant-division-mode' => 'bypass',
        'database.connections.bcv_test' => $input['connection'],
    ]);
    $website = (new App\Tenancy\Models\Website())->forceFill([
        'id' => 1, 'uuid' => $input['connection']['database'], 'managed_by_database_connection' => 'bcv_test',
    ]);
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-07 10:00:00', 'America/Caracas'));
    $client = new class($input['rate']) extends App\Services\ExchangeRates\BcvClient {
        private string $rate;
        public function __construct($rate) { $this->rate = \App\Services\ExchangeRates\ExchangeRateMath::rate($rate); }
        public function dollarRate(): string {
            echo "READY\n"; flush();
            return $this->rate;
        }
    };
    $service = new App\Services\ExchangeRates\TenantExchangeRateService($client);
    echo json_encode(app(App\Tenancy\Environment::class)->run($website, fn () => $service->exchange('2026-10-07')));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falló el worker BCV: '.get_class($exception).' / '.$exception->getCode());
    exit(1);
}
// ######## FIN API BCV ########
