<?php

namespace App\CoreFacturalo\Helpers\Storage;

use App\Services\LocalFiscalDocumentPolicy;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

trait StorageDocument
{
    protected $_folder;
    protected $_filename;

    public function uploadStorage($filename, $file_content, $file_type, $root = null)
    {
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        if (LocalFiscalDocumentPolicy::enabled() && $this->isFiscalXmlFileType($file_type)) {
            return null;
        }
        // ######### FIN CAMBIO SIN XML CDR SUNAT

        $this->setData($filename, $file_type, $root);
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $path = $this->_folder.'/'.$this->_filename;
        if ($file_type === 'cdr_b64') {
            $temporary = tempnam(sys_get_temp_dir(), 'sab-cdr-');
            if ($temporary === false) throw new \RuntimeException('No se pudo preparar el ZIP.');
            try {
                $zip = new ZipArchive();
                if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true
                    || !$zip->addFromString($filename.'.xml', base64_decode($file_content, true)) || !$zip->close()) {
                    throw new \RuntimeException('No se pudo generar el ZIP.');
                }
                $stream = fopen($temporary, 'rb');
                try { app(\App\Services\Storage\TransactionalFiles::class)->put($path, $stream); }
                finally { if (is_resource($stream)) fclose($stream); }
            } finally { if (is_file($temporary)) unlink($temporary); }
            return;
        }
        app(\App\Services\Storage\TransactionalFiles::class)->put($path, $file_content);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function downloadStorage($filename, $file_type, $root = null)
    {
        $this->setData($filename, $file_type, $root);
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download($this->_folder.DIRECTORY_SEPARATOR.$this->_filename,
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            str_ends_with($this->_filename, '.pdf') ? \App\Services\Fiscal\DocumentFileName::visible($this->_filename) : $this->_filename);
    }

    public function getStorage($filename, $file_type, $root = null)
    {
        $this->setData($filename, $file_type, $root);
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TransactionalFiles::class)->read($this->_folder.'/'.$this->_filename);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    private function setData($filename, $file_type, $root)
    {
        $extension = 'xml';
        switch ($file_type) {
            case 'unsigned':
                break;
            case 'signed':
                break;
            case 'pdf':
                $extension = 'pdf';
                break;
            case 'quotation':
                $extension = 'pdf';
                break;
            case 'sale_note':
                $extension = 'pdf';
                break;
            case 'cdr':
                $filename = 'R-'.$filename;
                $extension = 'zip';
                break;
            case 'cdr_xml':
                $filename = 'R-'.$filename;
                $extension = 'xml';
                $file_type = 'cdr';
                break;
            case 'cdr_b64':
                $filename = 'R-'.$filename;
                $extension = 'zip';
                $file_type = 'cdr'; // folder
                break;
            case 'purchase_quotation':
                $extension = 'pdf';
                break;
            case 'purchase_order_attached':
                $extension = '';
                break;
            case 'purchase_order':
                $extension = 'pdf';
                break;
            case 'order_note':
                $extension = 'pdf';
                break;
            case 'sale_opportunity':
                $extension = 'pdf';
                break;
            case 'contract':
                $extension = 'pdf';
                break;
            case 'order_form':
                $extension = 'pdf';
                break;
            case 'purchase':
                $extension = 'pdf';
                break;
            case 'devolution':
                $extension = 'pdf';
                break;
            case 'report_inventory_pdf':
                $extension = 'pdf';
                break;
            case 'download_tray_pdf':
                $extension = 'pdf';
                break;
            case 'download_tray_zip':
                $extension = 'zip';
                break;
            case 'download_tray_xlsx':
                $extension = 'xlsx';
                break;
            case 'income':
                $extension = 'pdf';
                break;
            case 'expense':
                $extension = 'pdf';
                break;
            case 'unpaid':
                $extension = 'pdf';
                break;
        }

        $this->_filename = $filename.'.'.$extension;
        $this->_folder = ($root)?$root.'/'.$file_type:$file_type;
    }


    /**
     *
     * Validar si existe archivo
     *
     * @param  string $filename
     * @param  string $file_type
     * @param  string $root
     * @return bool
     */
    public function existFileInStorage($filename, $file_type, $root = null)
    {
        $this->setData($filename, $file_type, $root);
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->exists($this->_folder.DIRECTORY_SEPARATOR.$this->_filename);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    // ########## INICIO CAMBIO SIN XML CDR SUNAT
    private function isFiscalXmlFileType(string $fileType): bool
    {
        return in_array($fileType, ['unsigned', 'signed', 'cdr', 'cdr_xml', 'cdr_b64'], true);
    }
    // ######### FIN CAMBIO SIN XML CDR SUNAT

}
