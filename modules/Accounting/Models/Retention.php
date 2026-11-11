<?php
namespace Modules\Accounting\Models;
final class Retention extends OwnedModel {
protected $casts=['base_amount'=>'decimal:8','rate_percent'=>'decimal:8','amount'=>'decimal:8','exchange_rate'=>'decimal:12','event_date'=>'date:Y-m-d'];

}
