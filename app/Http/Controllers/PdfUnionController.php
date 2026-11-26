<?php

    namespace App\Http\Controllers;

    use Mpdf\Mpdf;
    use Mpdf\MpdfException;
    use setasign\Fpdi\Fpdi;
    use Symfony\Component\HttpFoundation\BinaryFileResponse;

    class PdfUnionController extends Controller
    {
        /**
         * Unifica varios pdf en uno solo.
         *
         * @param Fpdi $fpdi
         * @param Mpdf $pdf
         *
         * @throws MpdfException
         */
        public static function addFpi(Fpdi &$fpdi, Mpdf &$pdf)
        {
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $source = \setasign\Fpdi\PdfParser\StreamReader::createByString($pdf->output('', 'S'));
            $pageCount = $fpdi->setSourceFile($source);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pageId = $fpdi->ImportPage($pageNo);
                $size = $fpdi->getTemplateSize($pageId);
                $fpdi->AddPage($size['orientation'], $size);
                $fpdi->useImportedPage($pageId);
            }
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

        }

        /**
         * @param Fpdi        $pdf
         * @param string|null $file_name
         *
         * @return BinaryFileResponse
         */
        public static function ResponseAsFile(Fpdi $pdf, ?string $file_name = 'pdf')
        {

            $temp = tempnam(sys_get_temp_dir(), $file_name);
            file_put_contents($temp, $pdf->Output('S'));

            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="file.pdf"'
            ];

            return response()->file($temp, $headers)->deleteFileAfterSend(true);
        }

        //
    }
