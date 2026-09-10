<?php

namespace App\Traits\System;

use App\Models\System\DownloadTray;

/**
 * Manejo de la bandeja de descargas del CENTRAL.
 *
 * Es independiente de App\Traits\JobReportTrait, que opera sobre la bandeja de
 * los tenants (App\Models\Tenant\DownloadTray). No mezclar: son conexiones y
 * tablas distintas.
 */
trait DownloadTrayTrait
{
    public function findSystemDownloadTray($id)
    {
        return DownloadTray::find($id);
    }

    /**
     * Marca el inicio real del procesamiento (cuando el worker toma el job,
     * no cuando el usuario apreto el boton).
     */
    public function startSystemDownloadTray(DownloadTray $tray)
    {
        if ($tray->status === DownloadTray::STATUS_IN_PROCESS) return $tray;

        $tray->status = DownloadTray::STATUS_IN_PROCESS;
        $tray->date_init = now();
        $tray->error_message = null;
        $tray->save();

        return $tray;
    }

    public function finishSystemDownloadTray(DownloadTray $tray, $file_name, $path, $size = null, $disk = 'local')
    {
        $tray->status = DownloadTray::STATUS_FINISHED;
        $tray->file_name = $file_name;
        $tray->path = $path;
        $tray->size = $size;
        $tray->disk = $disk;
        $tray->date_end = now();
        $tray->save();

        return $tray;
    }

    /**
     * El mensaje se recorta porque una excepcion de PDO o del proceso puede traer
     * el dump entero del comando en el texto.
     */
    public function failSystemDownloadTray(DownloadTray $tray, $message)
    {
        $tray->status = DownloadTray::STATUS_FAILED;
        $tray->error_message = mb_substr((string) $message, 0, 2000);
        $tray->date_end = now();
        $tray->save();

        return $tray;
    }
}
