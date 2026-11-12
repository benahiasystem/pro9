<?php

namespace Modules\Accounting\Models;

final class ReportArtifact extends OwnedModel
{
    protected $casts = ['bytes' => 'integer'];
}
