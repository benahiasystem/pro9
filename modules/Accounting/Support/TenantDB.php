<?php
namespace Modules\Accounting\Support;
use Illuminate\Support\Facades\DB;
final class TenantDB {
 public static function __callStatic(string $method,array $arguments):mixed {return DB::connection('tenant')->$method(...$arguments);}
}
