<?php
namespace Modules\Accounting\Models;
final class Period extends OwnedModel {
protected $casts=['year'=>'integer','month'=>'integer','version'=>'integer','close_snapshot'=>'array'];

}
