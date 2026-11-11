<?php
namespace Modules\Accounting\Models;
final class InboxEvent extends OwnedModel {
protected $casts=['payload'=>'array','issues'=>'array','source_revision'=>'integer','stream_sequence'=>'integer'];

}
