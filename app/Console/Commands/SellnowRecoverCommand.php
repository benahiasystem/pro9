<?php

namespace App\Console\Commands;

use App\Models\Tenant\{SellnowSale, User};
use App\Services\Sellnow\SaleEffects;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SellnowRecoverCommand extends Command
{
    protected $signature = 'sellnow:recover {--limit=100}';
    protected $description = 'Recupera los efectos pendientes de ventas VendeYa confirmadas sin volver a crearlas.';

    public function handle(SaleEffects $effects): int
    {
        if (!Schema::connection('tenant')->hasTable('sellnow_sales')) return self::SUCCESS;
        $guard = auth()->guard();
        $previous = $guard->user();
        try {
            SellnowSale::where('status','confirmed')->where(function ($query) {
                foreach (['pending','failed','processing'] as $state) $query->orWhere('effects','like','%"status":"'.$state.'"%');
            })->orderBy('updated_at')->orderBy('id')->limit(max(1, min(1000, (int)$this->option('limit'))))->get()
                ->each(function ($sale) use ($effects, $guard) {
                    $user = User::find($sale->user_id);
                    if (!$user) return;
                    $guard->setUser($user);
                    $effects->run($sale);
                });
        } finally {
            if ($previous) $guard->setUser($previous);
            else $guard->forgetUser();
        }
        return self::SUCCESS;
    }
}
