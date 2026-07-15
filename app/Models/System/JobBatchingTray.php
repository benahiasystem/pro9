<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property Collection $payload
 * @property int $job_batch_id
 * @property string $generated_filename
 */
class JobBatchingTray extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'job_batch_id',
        'generated_filename',
        'payload'
    ];

    protected $casts = [
        'payload' => 'object'
    ];


}