<?php

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/\Apro9_fiscal_concurrency_[a-f0-9]{12}\z/', $input['connection']['database'] ?? '')) {
    throw new RuntimeException('Only temporary fiscal test databases are allowed.');
}
$container = new Illuminate\Container\Container();
Illuminate\Container\Container::setInstance($container);
$manager = new Illuminate\Database\Capsule\Manager($container);
$manager->addConnection($input['connection']);
$db = $manager->getConnection();
$db->getPdo();
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
try {
    $kind = $input['kind'] ?? 'sale';
    if ($kind === 'contingency') {
        $replacement = (new App\Services\Fiscal\FiscalContingencyService($db))->start(1, $input['profile'], $input['payload'], 1, static function () {});
        fwrite(STDOUT, json_encode(['id' => $replacement->id, 'control' => $replacement->control_number], JSON_THROW_ON_ERROR));
        exit(0);
    }
    if (in_array($kind, ['order-stock', 'order-release'], true)) {
        App\Services\Fiscal\FiscalOrderStockReservation::change($db, 25, 1,
            $kind === 'order-stock' ? [['id' => 1, 'cantidad' => 2]] : null, 'status_order_id', 3);
        fwrite(STDOUT, json_encode(['stock_changed' => true], JSON_THROW_ON_ERROR));
        exit(0);
    }
    $reservation = (new App\Services\Fiscal\FiscalCommercialService($db))->register(
        $input['profile'], $input['key'], hash('sha256', $input['payload']), 1, 'presential',
        function (array $identifiers) use ($db, $kind, $input): int {
            $writer = function () use ($db, $kind, $identifiers): int {
                if ($kind === 'order-invoice') $identifiers['external_id'] = 'order-invoice-uuid';
                $id = $db->table('documents')->insertGetId($identifiers);
                foreach (['payment', 'inventory'] as $effect) {
                    $db->table('commercial_effects')->insert(['subject_id' => $id, 'kind' => $effect]);
                }
                if ($kind === 'order-invoice') {
                    $row = $db->table('item_warehouse')->where('id', 1)->lockForUpdate()->first();
                    $db->table('item_warehouse')->where('id', 1)->update(['stock' => bcsub((string) $row->stock, '2', 4)]);
                }
                return (int) $id;
            };
            if ($kind === 'order-invoice') {
                return App\Services\Fiscal\FiscalOrderConversion::register($db,
                    ['id' => 25, 'establishment_id' => 1, 'purchase_fingerprint' => App\Services\Fiscal\FiscalOrderConversion::fingerprint(['total' => 232])],
                    $input['key'], $writer);
            }
            return $writer();
        }
    );
    fwrite(STDOUT, json_encode(['id' => $reservation->id, 'document_id' => $reservation->document_id, 'number' => $reservation->document_number, 'control' => $reservation->control_number], JSON_THROW_ON_ERROR));
} catch (DomainException $e) {
    fwrite(STDOUT, json_encode(['rejected' => true], JSON_THROW_ON_ERROR));
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
