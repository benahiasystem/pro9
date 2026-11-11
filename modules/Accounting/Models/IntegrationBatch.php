<?php
namespace Modules\Accounting\Models;
final class IntegrationBatch extends OwnedModel {
protected $casts=['payload'=>'array','result'=>'array','received_at'=>'datetime'];

}
