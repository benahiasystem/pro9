<?php

namespace Modules\Report\Traits;

use App\Models\Tenant\Company;
use App\Traits\JobReportTrait;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;
use Modules\Report\Jobs\ProcessReportSalesConsolidated;

trait ConsolidatedReportTrayTrait
{
    use JobReportTrait;

    protected function consolidatedReportThreshold(): int
    {
        return 500;
    }

    protected function resolveConsolidatedReportWebsiteId(Request $request): int
    {
        $host = $request->getHost();
        $hostname = Hostname::where('fqdn', $host)->first();

        if (empty($hostname)) {
            $company = Company::active();
            $client = \App\Models\System\Client::where('number', $company->number)->first();

            return $client->hostname->website_id;
        }

        return $hostname->website_id;
    }

    protected function dispatchConsolidatedReportToTray(
        Request $request,
        string $format,
        string $exportMode,
        string $reportSource,
        string $typeLabel,
        int $recordCount
    ): ?array {
        if ($recordCount <= $this->consolidatedReportThreshold()) {
            return null;
        }

        $user_id = auth()->id();
        $tray = $this->createDownloadTray($user_id, 'Reporte', $format, $typeLabel);
        $website_id = $this->resolveConsolidatedReportWebsiteId($request);

        ProcessReportSalesConsolidated::dispatch(
            $tray->id,
            $website_id,
            $request->all(),
            $user_id,
            $exportMode,
            $format,
            $reportSource
        );

        return $this->getJobResponse();
    }
}
