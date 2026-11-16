<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Models\{Account, ThirdParty, CostCenter};

final class ReportLookupController extends \App\Http\Controllers\Controller
{
    public function index(Request $r, string $company, string $kind): array
    {
        $v = $r->validate(['q' => 'nullable|string|max:100', 'id' => 'nullable|uuid', 'page' => 'nullable|integer|min:1|max:100000']);
        $model = ['accounts' => Account::class, 'third-parties' => ThirdParty::class, 'cost-centers' => CostCenter::class][$kind];
        $query = $model::query();
        if ($kind === 'accounts') { $query->where('posting', true); }
        if (!empty($v['id'])) { $query->whereKey($v['id'])->firstOrFail(); $query->whereKey($v['id']); }
        elseif (!empty($v['q'])) {
            $text = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v['q']) . '%';
            $field = $kind === 'third-parties' ? 'tax_id' : 'code';
            $query->where(fn($q) => $q->where('name', 'like', $text)->orWhere($field, 'like', $text));
        }
        $result = $query->orderBy($kind === 'third-parties' ? 'tax_id' : 'code')->orderBy('id')->paginate(25);
        return ['data' => $result->getCollection()->map(fn($item) => ['id' => $item->id, 'label' => ($item->code ?? $item->tax_id) . ' · ' . $item->name, 'name' => $item->name, 'active' => (bool) $item->active]), 'meta' => ['total' => $result->total(), 'page' => $result->currentPage(), 'last_page' => $result->lastPage()]];
    }
}
