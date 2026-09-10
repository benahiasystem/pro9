<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Bandeja de descargas del central.
 *
 * Es generica a proposito: el modulo se identifica por la columna module y el
 * detalle particular de cada uno vive en su propia tabla. No agregar columnas
 * especificas de un modulo aca.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $module
 * @property string $format
 * @property string|null $type
 * @property string|null $path
 * @property string|null $file_name
 * @property string $disk
 * @property int|null $size
 * @property string $status
 * @property string|null $error_message
 * @property BackupTrayDetail|null $backup_detail
 */
class DownloadTray extends Model
{
    use UsesSystemConnection;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_IN_PROCESS = 'IN_PROCESS';
    public const STATUS_FINISHED = 'FINISHED';
    public const STATUS_FAILED = 'FAILED';

    public const MODULE_BACKUP = 'backup';

    protected $table = 'download_tray';

    protected $fillable = [
        'user_id',
        'module',
        'format',
        'type',
        'path',
        'file_name',
        'disk',
        'size',
        'status',
        'error_message',
        'date_init',
        'date_end',
        'expires_at',
        'payload_request',
    ];

    protected $casts = [
        'user_id' => 'int',
        'size' => 'int',
        'date_init' => 'datetime',
        'date_end' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function backup_detail()
    {
        return $this->hasOne(BackupTrayDetail::class);
    }

    public function scopeModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Registros que siguen en curso, para saber si la vista debe seguir consultando.
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROCESS]);
    }

    public function isDownloadable()
    {
        return $this->status === self::STATUS_FINISHED && $this->path;
    }
}
