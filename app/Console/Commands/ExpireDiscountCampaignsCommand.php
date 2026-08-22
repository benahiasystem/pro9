<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ecommerce\Models\Tenant\DiscountCampaign;

class ExpireDiscountCampaignsCommand extends Command
{
    protected $signature = 'ecommerce:expire-discount-campaigns';

    protected $description = 'Desactiva las campañas de descuento cuya fecha de vencimiento ya terminó';

    public function handle(): int
    {
        $count = DiscountCampaign::deactivateExpired();
        $this->info("Campañas desactivadas: {$count}");

        return self::SUCCESS;
    }
}
