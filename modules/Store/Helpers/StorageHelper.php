<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Store\Helpers;

use App\Models\Tenant\Company;
use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    protected $root;

    public function setFolder($fiscal_environment, $folder)
    {
        $root = ($fiscal_environment === 'demo') ? 'demo' : 'production';
        $this->root = $root . DIRECTORY_SEPARATOR . $folder;
    }

    /*
     * Payments
     */
    public function uploadPayment($filename, $file_content)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        app(\App\Services\Storage\TenantStorage::class)->disk()->put('payments' . DIRECTORY_SEPARATOR . $filename, $file_content);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function downloadPayment($filename)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download('payments' . DIRECTORY_SEPARATOR . $filename);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    /*
     * Certificate
     */
    public function uploadCertificate($filename, $file_content)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        app(\App\Services\Storage\TenantStorage::class)->disk()->put('certificates' . DIRECTORY_SEPARATOR . $filename, $file_content);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function getCertificate($filename)
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->get('certificates' . DIRECTORY_SEPARATOR . $filename);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    /**
     * Certificate QZ Tray
     */

    public function contentCertificaQzPrivate() 
    {
        $company = Company::active();
        $content_private_certificate = null;
        if (Storage::disk('local')->exists('certificates\\qztray\\'.$company->private_certificate_qztray)) {
            $content_private_certificate = Storage::disk('local')->get('certificates\\qztray\\'.$company->private_certificate_qztray);
        }
        return $content_private_certificate ? $content_private_certificate : null;
    }
    public function contentCertificaQzDigital() 
    {
        $company = Company::active();
        $content_digital_certificate=null;
        if (Storage::disk('local')->exists('certificates\\qztray\\'.$company->digital_certificate_qztray)) {
            $content_digital_certificate = Storage::disk('local')->get('certificates\\qztray\\'.$company->digital_certificate_qztray);
        }

        return $content_digital_certificate ? $content_digital_certificate : null;
    }

    /*
     * Pdf
     */
    public function uploadPdf($filename, $file_content, $format = 'a4')
    {
        $folder = $this->getFolder('pdf_' . $format, $filename . '.pdf');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        app(\App\Services\Storage\TenantStorage::class)->disk()->put($folder, $file_content);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function downloadPdf($filename, $format = 'a4')
    {
        $folder = $this->getFolder('pdf_' . $format, $filename . '.pdf');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function getPdf($filename, $format = 'a4')
    {
        $folder = $this->getFolder('pdf_' . $format, $filename . '.pdf');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->get($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function existPdf($filename, $format = 'a4')
    {
        $folder = $this->getFolder('pdf_' . $format, $filename . '.pdf');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->exists($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function printer($filename, $format = 'a4')
    {
        $temp = tempnam(sys_get_temp_dir(), 'pdf_' . $format);
        file_put_contents($temp, $this->getPdf($filename));

        return response()->file($temp);
    }

    /*
     * Cdr
     */
    public function uploadCdr($filename, $file_content)
    {
        $folder = $this->getFolder('cdr', 'R-' . $filename . '.xml');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        app(\App\Services\Storage\TenantStorage::class)->disk()->put($folder, $file_content);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function downloadCdr($filename)
    {
        $folder = $this->getFolder('cdr', 'R-' . $filename . '.xml');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function getCdr($filename)
    {
        $folder = $this->getFolder('cdr', 'R-' . $filename . '.xml');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->get($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    /*
     * General
     */
    private function getFolder($type, $filename)
    {
        return $this->root . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $filename;
    }


    /*
     * Html
     */
    public function uploadHtml($filename, $file_content)
    {
        $folder = $this->getFolder('html', $filename . '.html');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        app(\App\Services\Storage\TenantStorage::class)->disk()->put($folder, $file_content);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function downloadHtml($filename)
    {
        $folder = $this->getFolder('html', $filename . '.html');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->download($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function getHtml($filename)
    {
        $folder = $this->getFolder('html', $filename . '.html');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->get($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }

    public function existHtml($filename)
    {
        $folder = $this->getFolder('html', $filename . '.html');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        return app(\App\Services\Storage\TenantStorage::class)->disk()->exists($folder);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
