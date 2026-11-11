<?php
namespace Modules\Accounting\Http\Controllers;
use Illuminate\Http\Request;
use Modules\Accounting\Services\Pro9SourceReader;
use Modules\Accounting\Models\SourceDocument;
final class Pro9Controller extends \App\Http\Controllers\Controller {
 public function __construct(private Pro9SourceReader $reader){}
 public function status():array {return ['data'=>$this->reader->status()];}
 public function index(Request $r,string $company,string $kind):array {$v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','page'=>'nullable|integer|min:1|max:100000']);return $this->reader->listing($kind,$v);}
 public function show(Request $r,string $company,string $kind,int $id):array {return ['data'=>$this->reader->snapshot($kind,$id)];}
 public function receive(Request $r,string $company,string $kind,int $id):array {return $this->reader->receive($kind,$id,$r->user()->id);}
 public function documents():mixed {return SourceDocument::orderByDesc('created_at')->paginate(25);}
}
