<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\{ApiProblem, Permissions};
use Modules\Accounting\Services\YearClosingService;

final class YearClosingController extends \App\Http\Controllers\Controller
{
    public function __construct(private YearClosingService $service)
    {
    }

    private function version(Request $request): int
    {
        $value = trim((string) $request->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Actualiza la versión del ejercicio o conjunto antes de continuar.', 428);
        }
        return (int) $value;
    }

    public function index(Request $request, string $company, string $year): array
    {
        $v = $request->validate(['result_account_id' => 'nullable|uuid']);
        return ['data' => $this->service->screen((int) $year, $v['result_account_id'] ?? null)];
    }

    public function show(Request $request, string $company, string $id): array
    {
        return ['data' => $this->service->show($id)];
    }

    public function prepare(Request $request, string $company, string $year): array
    {
        $v = $request->validate(['basis_hash' => 'required|string|size:64|regex:/^[a-f0-9]+$/D', 'result_account_id' => 'required|uuid', 'reason' => 'required|string|min:10|max:2000',
            'attestations' => 'required|array:source_reconciliation,cash_bank_reconciliation,fiscal_review', 'attestations.source_reconciliation' => 'required|string|min:10|max:2000', 'attestations.cash_bank_reconciliation' => 'required|string|min:10|max:2000', 'attestations.fiscal_review' => 'required|string|min:10|max:2000',
            'exceptions' => 'present|array:pending_entries,pending_documents,fiscal_observations,manual_fiscal_links', 'exceptions.*' => 'nullable|string|max:2000']);
        return ['data' => $this->service->prepare((int) $year, $this->version($request), $v, $request->user()->id, $request->header('Idempotency-Key'))];
    }

    public function post(Request $request, string $company, string $id): array
    {
        app(Permissions::class)->require($request->user(), 'post');
        $v = $request->validate(['review_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/D']);
        return ['data' => $this->service->post($id, $this->version($request), $v['review_token'], $request->user()->id, $request->header('Idempotency-Key'))];
    }

    public function cancel(Request $request, string $company, string $id): array
    {
        $v = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        return ['data' => $this->service->cancel($id, $this->version($request), $v['reason'], $request->user()->id, $request->header('Idempotency-Key'))];
    }

    public function prepareReopening(Request $request, string $company, string $id): array
    {
        app(Permissions::class)->require($request->user(), 'reverse');
        $v = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        return ['data' => $this->service->prepareReopening($id, $this->version($request), $v['reason'], $request->user()->id, $request->header('Idempotency-Key'))];
    }

    public function reopen(Request $request, string $company, string $id): array
    {
        app(Permissions::class)->require($request->user(), 'post');
        app(Permissions::class)->require($request->user(), 'reverse');
        $v = $request->validate(['review_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/D']);
        return ['data' => $this->service->reopen($id, $this->version($request), $v['review_token'], $request->user()->id, $request->header('Idempotency-Key'))];
    }
}
