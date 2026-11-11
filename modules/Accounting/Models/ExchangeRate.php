<?php
namespace Modules\Accounting\Models;
final class ExchangeRate extends OwnedModel {
protected $casts=['value'=>'decimal:12','effective_date'=>'date:Y-m-d'];

}
