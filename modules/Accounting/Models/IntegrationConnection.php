<?php
namespace Modules\Accounting\Models;
final class IntegrationConnection extends OwnedModel {
protected $casts=['signing_secret'=>'encrypted','active'=>'boolean','checkpoint'=>'integer','last_received_at'=>'datetime'];
protected $hidden=['token_hash','signing_secret'];
}
