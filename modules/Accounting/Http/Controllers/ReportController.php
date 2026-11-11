<?php
namespace Modules\Accounting\Http\Controllers;
use Modules\Accounting\Services\ReportService;
use Illuminate\Http\Request;
final class ReportController extends \App\Http\Controllers\Controller {
 public function show(Request $r,string $company,string $kind,ReportService $service):array {
  $v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','account_id'=>'nullable|uuid','third_party_id'=>'nullable|uuid','side'=>'nullable|in:receivable,payable','per_page'=>'nullable|integer|min:1|max:500','page'=>'nullable|integer|min:1|max:100000']);
  return ['data'=>$service->build($kind,$v)];
 }
}
