<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RegularizeItemWarehouseForItems extends Command
{
    /**
     * Maximo de filas por INSERT. MySQL corta en 65.535 placeholders y cada
     * fila usa 5 (item_id, warehouse_id, stock, created_at, updated_at).
     */
    private const INSERT_BATCH = 2000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'regularize:item-warehouse
                            {--chunk=500 : Cantidad de items a procesar por lote}
                            {--dry-run : Solo informa cuantas filas se crearian, sin insertar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea las filas faltantes en item_warehouse (stock 0) para cada combinacion item/almacen.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = (bool)$this->option('dry-run');
        $chunkSize = (int)$this->option('chunk') ?: 500;

        $this->info('----------------------------------------------');
        $this->info('Hora de inicio: ' . date('Y-m-d H:i:s'));

        $warehouseIds = Warehouse::pluck('id');

        if ($warehouseIds->isEmpty()) {
            $this->error('No hay almacenes registrados. Proceso abortado.');

            return self::FAILURE;
        }

        $query = $this->itemsQuery();
        $total = $query->count();

        // $this->info("Almacenes: {$warehouseIds->count()}");
        $this->info("Productos: {$total}");

        if ($dryRun) {
            $this->warn('Modo dry-run: no se insertara nada.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $creadas = 0;

        $query->chunkById($chunkSize, function (Collection $items) use ($warehouseIds, $dryRun, $bar, &$creadas) {
            $creadas += $dryRun
                ? $this->countMissing($items, $warehouseIds)
                : $this->insertMissing($this->buildRows($items, $warehouseIds));

            $bar->advance($items->count());
        });

        $bar->finish();
        $this->newLine(2);

        $this->info($dryRun
            ? "Filas que se crearian: {$creadas}"
            : "Filas creadas: {$creadas}");

        $this->info('Hora de termino: ' . date('Y-m-d H:i:s'));
        $this->info('----------------------------------------------');

        return self::SUCCESS;
    }

    /**
     * Solo productos que manejan stock: sin servicios ni sets, mismo criterio
     * que usa el resto del modulo de inventario.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function itemsQuery()
    {
        return Item::query()
            ->select('id')
            ->where('item_type_id', '01')
            ->whereIsNotService()
            ->whereNotIsSet();
    }

    /**
     * Producto cartesiano items x almacenes, con stock en cero.
     *
     * @param  Collection  $items
     * @param  \Illuminate\Support\Collection  $warehouseIds
     * @return array
     */
    private function buildRows(Collection $items, $warehouseIds)
    {
        $now = now();
        $rows = [];

        foreach ($items->modelKeys() as $itemId) {
            $rows[] = [
                'item_id'      => $itemId,
                'warehouse_id' => 1,
                'stock'        => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        return $rows;
    }

    /**
     * Inserta apoyandose en el unique (item_id, warehouse_id): las filas que ya
     * existen se descartan solas y su stock queda intacto.
     *
     * @param  array  $rows
     * @return int
     */
    private function insertMissing(array $rows)
    {
        $creadas = 0;

        foreach (array_chunk($rows, self::INSERT_BATCH) as $batch) {
            $creadas += ItemWarehouse::insertOrIgnore($batch);
        }

        return $creadas;
    }

    /**
     * Cuenta las combinaciones faltantes sin escribir (para --dry-run).
     *
     * @param  Collection  $items
     * @param  \Illuminate\Support\Collection  $warehouseIds
     * @return int
     */
    private function countMissing(Collection $items, $warehouseIds)
    {
        $existentes = ItemWarehouse::query()
            ->whereIn('item_id', $items->modelKeys())
            ->whereIn('warehouse_id', $warehouseIds)
            ->count();

        return ($items->count() * $warehouseIds->count()) - $existentes;
    }
}
