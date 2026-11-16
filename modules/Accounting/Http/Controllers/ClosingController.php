<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Services\ClosingService;

final class ClosingController extends \App\Http\Controllers\Controller
{
    public function __construct(private ClosingService $service)
    {
    }

    private function version(Request $request): int
    {
        $value = trim((string) $request->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Actualiza la versión del período antes de continuar.', 428);
        }
        return (int) $value;
    }

    public function index(Request $request): array
    {
        $v = $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        return ['data' => $this->service->periods($v['year'])];
    }

    public function preview(Request $request, string $company, string $id): array
    {
        return ['data' => $this->service->preview($id)];
    }

    public function close(Request $request, string $company, string $id): array
    {
        $v = $request->validate(['review_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/D', 'reason' => 'required|string|min:10|max:2000', 'attestations' => 'required|array:source_reconciliation,cash_bank_reconciliation,fiscal_review', 'attestations.source_reconciliation' => 'required|string|min:10|max:2000', 'attestations.cash_bank_reconciliation' => 'required|string|min:10|max:2000', 'attestations.fiscal_review' => 'required|string|min:10|max:2000', 'exceptions' => 'present|array:pending_entries,pending_documents,fiscal_observations,manual_fiscal_links', 'exceptions.*' => 'nullable|string|max:2000']);
        return ['data' => $this->service->close($id, $this->version($request), $v, $request->user()->id, $request->header('Idempotency-Key'))];
    }

    public function reopening(Request $request, string $company, string $id): array
    {
        return ['data' => $this->service->reopening($id)];
    }

    public function reopen(Request $request, string $company, string $id): array
    {
        $v = $request->validate(['review_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/D', 'reason' => 'required|string|min:10|max:2000']);
        return ['data' => $this->service->reopen($id, $this->version($request), $v, $request->user()->id, $request->header('Idempotency-Key'))];
    }

    public function history(Request $request, string $company, string $id): array
    {
        return ['data' => $this->service->history($id)];
    }

    public function snapshot(Request $request, string $company, string $id, string $review): array
    {
        return ['data' => $this->service->snapshot($id, $review)];
    }
}
