<?php

    namespace App\CoreFacturalo\Helpers\Template;

use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
    use App\Models\Tenant\SaleNote;
    use App\Models\Tenant\Dispatch;
    use App\Models\Tenant\Document;
    use App\Models\Tenant\DocumentFee;
    use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\PaymentCondition;
use App\Models\Tenant\SaleNotePayment;
use App\Models\Tenant\Zone;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


    class TemplateHelper
    {


        /**
         * Devuelve la condicion de pago para un Document.
         * Las condiciones son Credito o Contado.
         *
         * @param Document|SaleNote $document
         *
         * @return string|null
         * @example
         *          <?php
         *          $condition =
         *          \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
         *          ?>
         *          {{ $condition  }}
         *
         */
        public static function getDocumentPaymentCondition( $document)
        {
            // Condicion de pago  Crédito / Contado
            if ($document) {
                if ($document->payment_condition) {
                    return $document->payment_condition->name;
                }
                if(get_class($document)==SaleNote::class){
                    // Las notas de venta no tiene condición de pago.
                    if($document->payment_method_type) {
                        return $document->payment_method_type->description;
                    }
                    $payments = $document->payments;
                    if($document->payment_method_type_id && $payments->count() == 0) {
                        return $document->payment_method_type->description;
                    }

                }
            }
            return '-';
            /** @var   PaymentCondition $paymentCondition */
            $paymentCondition = ($document->payment_condition_id === '01') ?
                PaymentCondition::where('id', '10')->first() :
                PaymentCondition::where('id', '09')->first();

            return $paymentCondition->name;
        }


        /**
         * Devuelve un array con los detalles de pago.
         *
         * @param Document|SaleNote $document
         *
         * @return array
         */
        public static function getDetailedPayment( $document, $dateFormat = 'Y-m-d')
        {
            $data = [];
            $payments = $document->payments;


            if ($document->payment_condition_id === '01') {
                $data['PAGOS'] = [];
                /** @var DocumentPayment $row */ //OR
                /** @var SaleNotePayment $row */
                foreach ($payments as $row) {
                    $temp = [
                        'description' => $row->payment_method_type->description,
                        'reference' => $row->reference ? $row->reference . ' - ' : '',
                        'symbol' => $document->currency_type->symbol,
                        'amount' => $row->payment + $row->change,
                    ];

                    $data['PAGOS'][] = $temp;
                }
            } else {
                // Verificar bien si esiste en los pagos si es nulo
                if (is_null($document->payment_condition_id)) {
                    $id = Str::singular($document->getTable()). '_id';
                    $payments = $document->payments()->where($id, $document->id)->get();
                    foreach ($payments as $row) {
                        $temp = [
                            'description' => $row->payment_method_type->description,
                            'reference' => $row->reference ? $row->reference . ' - ' : '',
                            'symbol' => $document->currency_type->symbol,
                            'amount' => $row->payment + $row->change,
                        ];

                        $data['PAGOS'][] = $temp;
                    }
                }
                else if(!empty($document->fee)) {
                    $data['CUOTA'] = [];
                    /**
                     * @var int          $key
                     * @var  DocumentFee $quote
                     */

                    $payment_of_fee = $document->payments;
                    $index = 0;
                    foreach ($document->fee as $key => $quote) {
                        $reference = '';
                        collect($payment_of_fee)->each(function ($item, $index) use ( &$reference) {
                            $reference .= ($index + 1) . ') '. $item->reference . ' - ';
                        });
                        $temp = [
                            'description' => (empty($quote->getStringPaymentMethodType()) ? 'Cuota #' . ($key + 1) : $quote->getStringPaymentMethodType()),
                            'reference' => $reference,
                            'amount' => $quote->amount,
                            'symbol' => $quote->symbol,
                        ];
                        $data['CUOTA'][] = $temp;

                        $index++;
                    }
                } 

            }
            /**
             *  @Deprecated -- Ya no se va a realizar, los pagos se estandarizo con la clase Document
             */

            // if(get_class($document)==SaleNote::class && $payments->count()!= 0){
            //     // Las notas de venta no tiene condicion de pago.

            //     /** @var \App\Models\Tenant\SaleNotePayment $row */
            //     foreach ($payments as $row) {
            //         $temp = [
            //             'date_of_payment' => $row->date_of_payment->format($dateFormat),
            //             'description' => $row->payment_method_type->description,
            //             'reference' => $row->reference ? $row->reference . ' - ' : '',
            //             'symbol' => $document->currency_type->symbol,
            //             'payment' => $row->payment,
            //             'amount' => $row->payment + $row->change,
            //         ];

            //         $data['PAGOS'][] = $temp;
            //         // $payment += (float) $row->payment;
            //     }
            // }


            return $data;
        }

        /**
         * Devuelve las guias de un documento, Primero las guias que esten escritas y luego las guias relacionadas
         *
         * @param Document $document
         *
         * @return array
         * @example
         *         <?php
         * @php
         *     $guias = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getGuides($document);
         * @endphp
         * @if(!empty($guias))
         *     <td class="font-sm" width="100px">
         *     <strong>Guía de Remisión</strong>
         *     </td>
         *     <td class="font-sm" width="8px">:</td>
         *     <td class="font-sm" colspan="4">
         * @foreach ($guias as $guides)
         * @foreach($guides as $index => $item)
         *     {{ $item }}<br>
         * @endforeach
         * @endforeach
         *     </td>
         * @endif
         *     ?>
         */
        public static function getGuides(Document $document)
        {
            $data = [];

            if ($document->guides != null) {
                foreach ($document->guides as $guide) {
                    $type = '';
                    if (isset($guide->document_type_description)) {
                        $type = $guide->document_type_description;
                    } else {
                        if ($guide->document_type_id) {
                            $type = $guide->document_type_id;
                        }
                    }
                    if ( !isset($data[$type])) $data[$type] = [];
                    $data[$type][] = $guide->document_type_description . ": " . $guide->number;
                }
            }

            $type = 'model';
            if ($document->dispatch) {
                /** @var Dispatch $dispatch */
                $dispatch = $document->dispatch;
                if ( !isset($data[$type])) $data[$type] = [];
                $data[$type][] = $dispatch->series . "-" . $dispatch->number;


            }
            return $data;
        }

        /**
         * Devuelve un string html para salto de linea
         *
         * @return string
         */
        public static function breakLine(): string
        {

            return '<div style="page-break-after: always;"></div>';
        }

        public static function setNumber($number, $decimal = 2, $mil = ',', $dec = '.')
        {
            return number_format($number, $decimal, $mil, $dec);
        }

        /**
         * Devuelve la marca desde lo sitems del documento.
         *
         * @param $row
         *
         * @return mixed|string
         */
        public static function  getBrandFormItem($row){
            $brand = '';
            if(!empty($row->item) && !empty($row->item->brand) ){
                if(is_string($row->item->brand)){
                    $brand = $row->item->brand;
                }elseif($row->item->brand->name){
                    $brand = $row->item->brand->name;
                }

            }
            return $brand;
        }

        /**
         * @param int $zone_id
         *
         * @return string|null
         */
        public static function getZoneById($zone_id =0){
            $zone = Zone::find($zone_id);
            if(!empty($zone)){

                return  $zone->getName();
            }
            return '';
        }

        /**
         * Devuelve un string con solo etiquetas <br>
         * @param string $str
         *
         * @return array|string|string[]
         */
        public static function SetHtmlTag($str = ''){
            $str = str_replace("\n","<br>",$str);
            $str = preg_replace('~\$\$[0-9]+~', '', $str);
            $placeholders = [];
            $i = 0;
            $str = preg_replace_callback('~(<br[^>]*>)~', function ($matches) use (&$placeholders, &$i) {
                $key = '$$'.$i++;
                $placeholders[$key] = $matches[0];
                return $key;
            }, $str);
            $str = htmlentities($str);
            foreach ($placeholders as $key => $placeholder) {
                $str = str_replace($key, $placeholder, $str);
            }
            return $str;
        }

        /**
         * Normaliza el nombre personalizado del producto para tickets térmicos.
         * mPDF suele colapsar párrafos/listas HTML en una sola línea en columnas estrechas.
         */
        public static function formatNameProductPdfForTicket(?string $html): string
        {
            if ($html === null || trim($html) === '') {
                return '';
            }

            $text = $html;
            $text = preg_replace('/<\/p>\s*<p[^>]*>/i', '<br/>', $text);
            $text = preg_replace('/<\/div>\s*<div[^>]*>/i', '<br/>', $text);
            $text = preg_replace('/<li[^>]*>/i', '', $text);
            $text = preg_replace('/<\/li>/i', '<br/>', $text);
            $text = preg_replace('/<\/?(?:ul|ol|p|div)[^>]*>/i', '', $text);
            $text = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
            $text = preg_replace('/(<br\s*\/?>\s*)+/i', '<br/>', $text);

            // Ojo: trim() con lista de caracteres borraría letras sueltas (b, r) del
            // nombre del producto. Los <br/> sobrantes se quitan con una expresión.
            $text = trim($text);
            $text = preg_replace('/^(?:<br\s*\/?>)+|(?:<br\s*\/?>)+$/i', '', $text);

            return self::expandGluedProductListLines(trim($text));
        }

        /**
         * Separa ítems en una sola línea: "1 - A-1 - B" → líneas independientes.
         */
        private static function expandGluedProductListLines(string $text): string
        {
            $normalized = preg_replace('/<br\s*\/?>/i', "\n", $text);
            $plain = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lines = [];

            foreach (preg_split('/\r\n|\r|\n/', $plain) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (preg_match_all('/\d+\s*-\s*/', $line) > 1 && preg_match('/-\d+\s*-\s*/', $line)) {
                    foreach (preg_split('/-(?=\d+\s*-\s*)/', $line) as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $lines[] = $part;
                        }
                    }
                } else {
                    $lines[] = $line;
                }
            }

            if (empty($lines)) {
                return '';
            }

            return implode('<br/>', array_map(
                fn ($line) => htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $lines
            ));
        }

        /**
         * Porcentaje de IGV para etiqueta en totales del PDF.
         */
        public static function getDocumentIgvPercentage($document): int
        {
            $percentages = collect($document->items ?? [])
                ->pluck('percentage_igv')
                ->map(function ($percentage) {
                    $p = (float) $percentage;
                    // Algunos registros guardan 0.18 en lugar de 18
                    if ($p > 0 && $p <= 1) {
                        $p *= 100;
                    }

                    return (int) round($p);
                })
                ->filter(fn ($percentage) => $percentage > 0)
                ->unique()
                ->values();

            if ($percentages->count() === 1) {
                return $percentages->first();
            }

            return 18;
        }

        /**
         * @return bool
         */
        public static function canShowNewLineOnObservation(){
            $config = Configuration::first();
            if(empty($config)) $config = new Configuration();
            return (bool)$config->print_new_line_to_observation;


        }

        
        /**
         * 
         * Obtener configuracion de decimales para el precio unitario en pdf
         *
         * @return Configuration
         */
        public static function getConfigurationDecimalQuantity()
        {
            return Configuration::getDataDecimalQuantity();
        }

        public static function getConfigurationInPdf()
        {
            return Configuration::getConfigurationInPdf();
        }

        public static function getConfigurationShowGuaranteeFund()
        {
            return Configuration::getConfigurationShowGuaranteeFund();
        }

        public static function getTypeSoap()
        {
            return Company::getTypeSoap();
        }

        public static function getInformationCompany()
        {
            // Oficina general (default) y Empresa
            $company = Company::getInformationCompany();
            $establishment = Establishment::find(1);
            return [
                'company' => $company,
                'establishment' => $establishment
            ];
        }
        
        public static function sellerPresence($document)
        {
            return in_array($document['transfer_reason_type_id'], ['02', '07', '13']);
        }

        public static function sameAsSender($document)
        {
            return in_array($document['transfer_reason_type_id'], ['02', '07', '04']);
        }

        public static function sellerSupplierPresence($document)
        {
            return in_array($document['transfer_reason_type_id'], ['02', '07']);
        }
        
        /**
         * Verifica si el archivo existe dentro de storage/app/public/uploads y ademas
         * es accesible desde public/storage (el enlace que generan las plantillas pdf
         * con public_path). Si falta el enlace simbolico devuelve false, para que la
         * plantilla omita la imagen en lugar de fallar al leerla.
         *
         * La ruta se recibe relativa a uploads, con o sin el prefijo publico:
         * 'logos/logo.jpg', 'uploads/logos/logo.jpg' o 'storage/uploads/logos/logo.jpg'.
         *
         * @param string|null $path
         *
         * @return bool
         * @example
         *          @if(\App\CoreFacturalo\Helpers\Template\TemplateHelper::existsFileInUploads($logo))
         */
        public static function existsFileInUploads($path): bool
        {
            if (empty($path)) return false;

            $path = ltrim(str_replace('\\', '/', trim($path)), '/');
            $path = ltrim(preg_replace('~^(public/)?storage/~', '', $path), '/');

            if ($path === '') return false;

            if ( !Str::startsWith($path, 'uploads/')) $path = 'uploads/'.$path;

            return Storage::disk('public')->exists($path) && is_file(public_path('storage/'.$path));
        }

        /**
         * Columnas visibles del ticket Plantilla_personalizable (config por sucursal).
         * Usa la config de Plantilla_personalizable (PDF) como fuente principal, ya que
         * el usuario la define desde Configuración > Plantilla PDF. Si no existe, usa
         * Plantilla_personalizable_ticket.
         *
         * @param  int|string $establishmentId
         * @return array{showColumns: array, colspan_total: int}
         */
        public static function getPersonalizableTicketShowColumns($establishmentId): array
        {
            $defaults = [
                'codigo' => true,
                'cantidad' => true,
                'unidad' => true,
                'descripcion' => true,
                'serie' => false,
                'modelo' => false,
                'marca' => false,
                'lote' => false,
                'fecha_vencimiento' => false,
                'precio_unitario' => true,
                'descuento' => false,
                'total' => true,
                'tipo_persona' => false,
                'peso_total' => false,
                'nro_producto' => true,
            ];

            $pdfConfig = \App\Models\Tenant\TemplateColumnsConfig::where('establishment_id', $establishmentId)
                ->where('template_name', 'Plantilla_personalizable')
                ->first();

            $ticketConfig = \App\Models\Tenant\TemplateColumnsConfig::where('establishment_id', $establishmentId)
                ->where('template_name', 'Plantilla_personalizable_ticket')
                ->first();

            $pdfColumns = $pdfConfig ? ($pdfConfig->columns_config ?? []) : [];
            $ticketColumns = $ticketConfig ? ($ticketConfig->columns_config ?? []) : [];

            if (!empty($pdfColumns)) {
                $showColumns = array_merge($defaults, $pdfColumns);
            } elseif (!empty($ticketColumns)) {
                $showColumns = array_merge($defaults, $ticketColumns);
            } else {
                $showColumns = $defaults;
            }

            return [
                'showColumns' => $showColumns,
                'show_codigo' => !empty($showColumns['codigo']),
            ];
        }
    }
