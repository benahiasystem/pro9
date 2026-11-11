<?php
namespace Modules\Accounting\Models;
final class Device extends OwnedModel {
protected $casts=['authorized_until'=>'datetime','last_seen_at'=>'datetime','revoked_at'=>'datetime'];

}
