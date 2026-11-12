<?php

namespace Modules\Accounting\Models;

final class PeriodReview extends OwnedModel
{
    protected $casts = ['snapshot' => 'array', 'user_id' => 'integer'];
}
