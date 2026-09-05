<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos propios de un backup dentro de la bandeja de descargas del central.
 *
 * @property int $id
 * @property int $download_tray_id
 * @property string $scope
 * @property int|null $hostname_id
 * @property string|null $client_name
 * @property string|null $database
 * @property string|null $batch_id
 * @property bool $includes_files
 */
class BackupTrayDetail extends Model
{
    use UsesSystemConnection;

    public const SCOPE_INDIVIDUAL = 'individual';
    public const SCOPE_ALL = 'todos';

    protected $table = 'backup_tray_details';

    protected $fillable = [
        'download_tray_id',
        'scope',
        'hostname_id',
        'client_name',
        'database',
        'batch_id',
        'includes_files',
    ];

    protected $casts = [
        'download_tray_id' => 'int',
        'hostname_id' => 'int',
        'includes_files' => 'bool',
    ];

    public function download_tray()
    {
        return $this->belongsTo(DownloadTray::class);
    }
}
