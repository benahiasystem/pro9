<?php
use Modules\Template\Helpers\TemplatePdf;

$establishment = $document->establishment;
$customer = $document->customer;
$invoice = $document->invoice;
$document_base = ($document->note) ? $document->note : null;
$itinerant = $document->itinerant;
// dd($itinerant->description);

//$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
$document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
// $accounts = \App\Models\Tenant\BankAccount::where('show_in_documents', true)->get();
$templatePdf = (new TemplatePdf);
$accounts = $templatePdf->getBankAccountsForPdf($document->establishment_id);

if($document_base) {

$affected_document_number = ($document_base->affected_document) ? $document_base->affected_document->series.'-'.str_pad($document_base->affected_document->number, 8, '0', STR_PAD_LEFT) : $document_base->data_affected_document->series.'-'.str_pad($document_base->data_affected_document->number, 8, '0', STR_PAD_LEFT);

} else {

$affected_document_number = null;
}

$payments = $document->payments;


$document->load('reference_guides');

$total_payment = $document->payments->sum('payment');
$balance = ($document->total - $total_payment) - $document->payments->sum('change');

$logo = "storage/uploads/logos/{$company->logo}";
if($establishment->logo) {
$logo = "{$establishment->logo}";
}

$configuration_decimal_quantity= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationDecimalQuantity();
$configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
$configurationEnableGuaranteeFund = App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationShowGuaranteeFund();
$type = App\CoreFacturalo\Helpers\Template\TemplateHelper::getTypeSoap();
$total_pending_payment = $document->total_pending_payment;

?>
<html>

<head>
    
    
</head>

<body>
    <?php if($type->soap_type_id === '01'): ?>
    <table class="full-width">
        <tr>
            <td style="width: 100%;text-align: center">
                <span style="color: red; font-weight: bold; font-size: 1.6rem;">Esta factura es solo de prueba</span>
            </td>
        </tr>
    </table>
    <?php endif; ?>
    <?php if($document->state_type->id == '11'): ?>
    <div class="company_logo_box" style="position: absolute; text-align: center; top:30%;">
        <img
            src="data:<?php echo e(mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png"))); ?>;base64, <?php echo e(base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png")))); ?>"
            alt="anulado" class="" style="opacity: 0.6;">
    </div>
    <?php endif; ?>
    <?php if($document->state_type->id == '09'): ?>
    <div class="company_logo_box" style="position: absolute; text-align: center; top:30%;">
        <img
            src="data:<?php echo e(mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png"))); ?>;base64, <?php echo e(base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png")))); ?>"
            alt="rechazado" class="" style="opacity: 0.6;">
    </div>
    <?php endif; ?>
    <?php if(isset($configuration['is_preview']) && $configuration['is_preview']): ?>
        <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
            <img
                src="data:<?php echo e(mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png"))); ?>;base64, <?php echo e(base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png")))); ?>"
                alt="vista previa" class="" style="opacity: 0.6; width: 50%;">
        </div>
    <?php endif; ?>
    <table class="full-width">
        <tr>
            <?php if($company->logo): ?>
                <td width="20%">
                    <div class="company_logo_box">
                        <img
                            src="data:<?php echo e(mime_content_type(public_path($logo))); ?>;base64, <?php echo e(base64_encode(file_get_contents(public_path($logo)))); ?>"
                            alt="<?php echo e(\App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company)); ?>" class="company_logo" style="max-width: 150px;">
                    </div>
                </td>
                <td width="50%" class="pl-3 text-center">
                    <div>
                        <?php echo $__env->make('pdf.partials.company_document_header_names', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        <h5><?php echo e('RUC '.$company->number); ?></h5>
                        <h6 style="text-transform: uppercase;">
                            <?php echo e(($establishment->address !== '-') ? $establishment->address : ''); ?>

                            <?php echo e(($establishment->district_id !== '-') ? ', '.$establishment->district->description : ''); ?>

                            <?php echo e(($establishment->province_id !== '-') ? ', '.$establishment->province->description : ''); ?>

                            <?php echo e(($establishment->department_id !== '-') ? '- '.$establishment->department->description : ''); ?>

                        </h6>
                        <?php if(isset($establishment->trade_address)): ?>
                            <h6><?php echo e($establishment->trade_address !== '-' ? 'D. Comercial: '.$establishment->trade_address : ''); ?></h6>
                        <?php endif; ?>
                        <h6><?php echo e($establishment->telephone !== '-' ? 'Central telefónica: '.$establishment->telephone : ''); ?></h6>
                        <h6><?php echo e($establishment->email !== '-' ? 'Email: '.$establishment->email : ''); ?></h6>
                        <?php if(isset($establishment->web_address)): ?>
                            <h6><?php echo e($establishment->web_address !== '-' ? 'Web: '.$establishment->web_address : ''); ?></h6>
                        <?php endif; ?>
                        <?php if(isset($establishment->aditional_information)): ?>
                            <h6><?php echo e($establishment->aditional_information !== '-' ? $establishment->aditional_information : ''); ?></h6>
                        <?php endif; ?>
                    </div>
                </td>
                <td width="30%" class="border-box py-4 px-2 text-center">
                    <h3 class="font-bold"><?php echo e('R.U.C. '.$company->number); ?></h3>
                    <h5><?php echo e($document->document_type->description); ?></h5>
                    <h3><?php echo e($document_number); ?></h3>
                </td>
            <?php else: ?>
                <td colspan="2" width="70%" class="pl-1 text-left">
                    <div>
                        <?php echo $__env->make('pdf.partials.company_document_header_names', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        <h5><?php echo e('RUCs '.$company->number); ?></h5>
                        <h6 style="text-transform: uppercase;">
                            <?php echo e(($establishment->address !== '-') ? $establishment->address : ''); ?>

                            <?php echo e(($establishment->district_id !== '-') ? ', '.$establishment->district->description : ''); ?>

                            <?php echo e(($establishment->province_id !== '-') ? ', '.$establishment->province->description : ''); ?>

                            <?php echo e(($establishment->department_id !== '-') ? '- '.$establishment->department->description : ''); ?>

                        </h6>
                        <?php if(isset($establishment->trade_address)): ?>
                            <h6><?php echo e($establishment->trade_address !== '-' ? 'D. Comercial: '.$establishment->trade_address : ''); ?></h6>
                        <?php endif; ?>
                        <h6><?php echo e($establishment->telephone !== '-' ? 'Central telefónica: '.$establishment->telephone : ''); ?></h6>
                        <h6><?php echo e($establishment->email !== '-' ? 'Email: '.$establishment->email : ''); ?></h6>
                        <?php if(isset($establishment->web_address)): ?>
                            <h6><?php echo e($establishment->web_address !== '-' ? 'Web: '.$establishment->web_address : ''); ?></h6>
                        <?php endif; ?>
                        <?php if(isset($establishment->aditional_information)): ?>
                            <h6><?php echo e($establishment->aditional_information !== '-' ? $establishment->aditional_information : ''); ?></h6>
                        <?php endif; ?>
                    </div>
                </td>
                <td width="30%" class="border-box py-4 px-2 text-center">
                    <h3 class="font-bold"><?php echo e('R.U.C. '.$company->number); ?></h3>
                    <h5><?php echo e($document->document_type->description); ?></h5>
                    <h3><?php echo e($document_number); ?></h3>
                </td>
            <?php endif; ?>
        </tr>
    </table>
    <table class="full-width mt-5">
        <tr>
            <td width="120px">FECHA DE EMISIÓN</td>
            <td width="8px">:</td>
            <td><?php echo e($document->date_of_issue->format('Y-m-d')); ?> / <?php echo e($document->time_of_issue); ?></td>

            <?php if($document->detraction): ?>

            <td width="120px">N. CTA DETRACCIONES</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->bank_account); ?></td>
            <?php endif; ?>
        </tr>
        <?php if($invoice): ?>
        <tr>
            <td>FECHA VENC.</td>
            <td width="8px">:</td>
            <td><?php echo e($invoice->date_of_due->format('Y-m-d')); ?></td>
        </tr>
        <?php endif; ?>

        <?php if($document->detraction): ?>
        <td width="140px">B/S SUJETO A DETRACCIÓN</td>
        <td width="8px">:</td>
        <?php $detractionType = app('App\Services\DetractionTypeService'); ?>
        <td width="220px"><?php echo e($document->detraction->detraction_type_id); ?>

            - <?php echo e($detractionType->getDetractionTypeDescription($document->detraction->detraction_type_id )); ?></td>

        <?php endif; ?>
        <tr>
            <td style="vertical-align: top;">CLIENTE</td>
            <td style="vertical-align: top;">:</td>
            <td style="vertical-align: top;">
                <?php echo e($customer->name); ?>

                <?php if($customer->internal_code ?? false): ?>
                <br>
                <small><?php echo e($customer->internal_code ?? ''); ?></small>
                <?php endif; ?>
            </td>

            <?php if($document->detraction): ?>
            <td width="120px">MÉTODO DE PAGO</td>
            <td width="8px">:</td>
            <td width="220px"><?php echo e($detractionType->getPaymentMethodTypeDescription($document->detraction->payment_method_id )); ?></td>
            <?php endif; ?>

        </tr>
        <tr>
            <td><?php echo e($customer->identity_document_type->description); ?></td>
            <td>:</td>
            <td><?php echo e($customer->number); ?></td>

            <?php if($document->detraction): ?>

            <td width="120px">P. DETRACCIÓN</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->percentage); ?>%</td>
            <?php endif; ?>
        </tr>
        <?php
            $addressParts = [];

            if (!empty($customer->address)) {
                $addressParts[] = $customer->address;
            }

            if ($customer->district_id !== '-') {
                $ubigeo = \App\Models\Tenant\Catalogs\District::find($customer->district_id);
            }

            if (isset($customer->district) && !empty($customer->district->description)) {
                $addressParts[] = $customer->district->description;
            } else {
                $addressParts[] = isset($ubigeo) ? $ubigeo->description : '';
            }

            if (isset($customer->province) && !empty($customer->province->description)) {
                $addressParts[] = $customer->province->description;
            } else {
                $addressParts[] = isset($ubigeo) ? $ubigeo->province->description : '';
            }

            if (isset($customer->department) && !empty($customer->department->description)) {
                $addressParts[] = $customer->department->description;
            } else {
                $addressParts[] = isset($ubigeo) ? $ubigeo->province->department->description : '';
            }

            $fullAddress = implode(', ', $addressParts);
        ?>

        <?php if($itinerant): ?>
        <tr>
            <td class="align-top">DIRECCIÓN DE EMiSOR I.</td>
            <td>:</td>
            <td style="text-transform: uppercase;">
                <?php if($itinerant->id == 1): ?>
                    <?php echo e($fullAddress); ?>

                <?php else: ?>
                    <?php echo e($itinerant->address->address); ?>

                <?php endif; ?>
            </td>
        </tr>

        <?php else: ?>
        <tr>
            <td class="align-top">DIRECCIÓN</td>
            <td>:</td>
            <td style="text-transform: uppercase;">
                <?php echo e($fullAddress ?: 'No disponible'); ?>

            </td>
        </tr>

        <?php endif; ?>
        <?php if($customer->address !== ''): ?>
        <tr>

            
            <?php if($document->detraction): ?>
            <td width="120px">MONTO DETRACCIÓN <?php echo e($document->currency_type->id == 'USD' ? 'SOLES' : ''); ?>

            </td>
            <td width="8px">:</td>
            <td> S/ <?php echo e($document->detraction->amount); ?></td>
            <?php endif; ?>
        </tr>
        <tr>
            <td class="align-top">MONEDA</td>
            <td>:</td>
            <td>
                <?php if($document->currency_type_id == 'PEN'): ?>
                Soles
                <?php elseif($document->currency_type_id == 'USD'): ?>
                Dolares
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if($document->consigned_id): ?>
        <?php
            $consigned =App\Models\Tenant\Consigned::where('id',$document->consigned_id)->first();
            $district = App\Models\Tenant\Catalogs\District::where('id', $document->consigned_ubigeo)->first();
            //La dirección del consignado es el número asignado a la dirección
            $consigned_phone = App\Models\Tenant\PersonAddress::where('person_id', $document->customer_id)
                ->where('address', $document->consigned_address)
                ->where('consigned_id', $document->consigned_id)
                ->first();

            if($district){
                $department = $district->province->department;
                $province = $district->province;
            }
        ?>
        <tr>
            <td class="align-top">CONSIGNADO</td>
            <td>:</td>
            <td style="text-transform: uppercase;">
                <?php echo e($consigned->number); ?> -
                <?php echo e($consigned->name); ?> -
                <?php echo e(($consigned_phone->phone)? $consigned_phone->phone : ' '); ?> -
                <?php echo e($document->consigned_address); ?>

                <?php echo e(($district) ? ' , '.$district->description.' , '.$province->description.' , '.$department->description: ' '); ?>

            </td>
        </tr>
    <?php endif; ?>
        <?php if($document->detraction && $document->currency_type->id == 'USD'): ?>
        <tr>
            <td>
            </td>
            <td>
            </td>
            <td>
            </td>
            <td width="120px">MONTO DETRACCIÓN DÓLARES</td>
            <td width="8px">:</td>
            <td><?php echo e($document->currency_type->symbol); ?> <?php echo e(number_format(($document->detraction->amount/$document->exchange_rate_sale), 2)); ?></td>

        </tr>
        <?php endif; ?>


        <?php if($document->reference_data): ?>
        <tr>
            <td width="120px">D. REFERENCIA</td>
            <td width="8px">:</td>
            <td><?php echo e($document->reference_data); ?></td>
        </tr>
        <?php endif; ?>

        <?php if($document->detraction): ?>
        <?php if($document->detraction->pay_constancy): ?>
        <tr>
            <td colspan="3">
            </td>
            <td width="120px">CONSTANCIA DE PAGO</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->pay_constancy); ?></td>
        </tr>
        <?php endif; ?>
        <?php endif; ?>

        <?php if($document->detraction && $invoice->operation_type_id == '1004'): ?>
        <tr>
            <td colspan="4"><strong>DETALLE - SERVICIOS DE TRANSPORTE DE CARGA</strong></td>
        </tr>
        <tr>
            <td class="align-top">Ubigeo origen</td>
            <td>:</td>
            <td><?php echo e($document->detraction->origin_location_id[2]); ?></td>

            <td width="120px">Dirección origen</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->origin_address); ?></td>
        </tr>
        <tr>
            <td class="align-top">Ubigeo destino</td>
            <td>:</td>
            <td><?php echo e($document->detraction->delivery_location_id[2]); ?></td>

            <td width="120px">Dirección destino</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->delivery_address); ?></td>
        </tr>
        <tr>
            <td class="align-top" width="170px">Valor referencial servicio de transporte</td>
            <td>:</td>
            <td><?php echo e($document->detraction->reference_value_service); ?></td>

            <td width="170px">Valor referencia carga efectiva</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->reference_value_effective_load); ?></td>
        </tr>
        <tr>
            <td class="align-top">Valor referencial carga útil</td>
            <td>:</td>
            <td><?php echo e($document->detraction->reference_value_payload); ?></td>

            <td width="120px">Detalle del viaje</td>
            <td width="8px">:</td>
            <td><?php echo e($document->detraction->trip_detail); ?></td>
        </tr>
        <?php endif; ?>
        <?php if($document->plate_number !== null): ?>
        <tr>
            <td>N° Placa</td>
            <td>:</td>
            <td><?php echo e($document->plate_number); ?></td>
        </tr>
        <?php endif; ?>

    </table>


    
    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    


    <?php if($document->isPointSystem()): ?>
    <table class="full-width mt-3">
        <tr>
            <td width="120px">P. ACUMULADOS</td>
            <td width="8px">:</td>
            <td><?php echo e($document->person->accumulated_points); ?></td>

            <td width="140px">PUNTOS POR LA COMPRA</td>
            <td width="8px">:</td>
            <td><?php echo e($document->getPointsBySale()); ?></td>
        </tr>
    </table>
    <?php endif; ?>


    <?php if($document->guides): ?>
    <br />
    <table>
        <?php $__currentLoopData = $document->guides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $guide): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <?php if(isset($guide->document_type_description)): ?>
            <td><?php echo e($guide->document_type_description); ?></td>
            <?php else: ?>
            <td><?php echo e($guide->document_type_id); ?></td>
            <?php endif; ?>
            <td>:</td>
            <td><?php echo e($guide->number); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </table>
    <?php endif; ?>


    <?php if($document->transport): ?>
    <br>
    <strong>Transporte de pasajeros</strong>
    <?php
    $transport = $document->transport;
    $origin_district_id = (array)$transport->origin_district_id;
    $destinatation_district_id = (array)$transport->destinatation_district_id;
    $origin_district = Modules\Order\Services\AddressFullService::getDescription($origin_district_id[2]);
    $destinatation_district = Modules\Order\Services\AddressFullService::getDescription($destinatation_district_id[2]);
    ?>

    <table class="full-width mt-3">
        <tr>
            <td width="120px"><?php echo e($transport->identity_document_type->description); ?></td>
            <td width="8px">:</td>
            <td><?php echo e($transport->number_identity_document); ?></td>
            <td width="120px">NOMBRE</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->passenger_fullname); ?></td>
        </tr>
        <tr>
            <td width="120px">N° ASIENTO</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->seat_number); ?></td>
            <td width="120px">M. PASAJERO</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->passenger_manifest); ?></td>
        </tr>
        <tr>
            <td width="120px">F. INICIO</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->start_date); ?></td>
            <td width="120px">H. INICIO</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->start_time); ?></td>
        </tr>
        <tr>
            <td width="120px">U. ORIGEN</td>
            <td width="8px">:</td>
            <td><?php echo e($origin_district); ?></td>
            <td width="120px">D. ORIGEN</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->origin_address); ?></td>
        </tr>
        <tr>
            <td width="120px">U. DESTINO</td>
            <td width="8px">:</td>
            <td><?php echo e($destinatation_district); ?></td>
            <td width="120px">D. DESTINO</td>
            <td width="8px">:</td>
            <td><?php echo e($transport->destinatation_address); ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <?php if($document->dispatch): ?>
    <br />
    <strong>Guías de remisión</strong>
    <table>
        <tr>
            <td><?php echo e($document->dispatch->number_full); ?></td>
        </tr>
    </table>

    <?php elseif($document->reference_guides): ?>
    <?php if(count($document->reference_guides) > 0): ?>
    <br />
    <strong>Guías de remisión</strong>
    <table>
        <?php $__currentLoopData = $document->reference_guides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $guide): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($guide->series); ?></td>
            <td>-</td>
            <td><?php echo e($guide->number); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>


    <table class="full-width mt-3">
        <?php if($document->prepayments): ?>
        <?php $__currentLoopData = $document->prepayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td width="120px">ANTICIPO</td>
            <td width="8px">:</td>
            <td><?php echo e($p->number); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
        <?php if($document->purchase_order): ?>
        <tr>
            <td width="120px">ORDEN DE COMPRA</td>
            <td width="8px">:</td>
            <td><?php echo e($document->purchase_order); ?></td>
        </tr>
        <?php endif; ?>
        <?php if($document->quotation_id): ?>
        <tr>
            <td width="120px">COTIZACIÓN</td>
            <td width="8px">:</td>
            <td><?php echo e($document->quotation->identifier); ?></td>

            <?php if(isset($document->quotation->delivery_date)): ?>
            <td width="120px">F. ENTREGA</td>
            <td width="8px">:</td>
            <td><?php echo e($document->date_of_issue->addDays($document->quotation->delivery_date)->format('d-m-Y')); ?></td>
            <?php endif; ?>
        </tr>

        <?php endif; ?>
        <?php if(isset($document->quotation->sale_opportunity)): ?>
        <tr>
            <td width="120px">O. VENTA</td>
            <td width="8px">:</td>
            <td><?php echo e($document->quotation->sale_opportunity->number_full); ?></td>
        </tr>
        <?php endif; ?>
        <?php if(!is_null($document_base)): ?>
        <tr>
            <td width="120px">DOC. AFECTADO</td>
            <td width="8px">:</td>
            <td><?php echo e($affected_document_number); ?></td>
        </tr>
        <tr>
            <td>TIPO DE NOTA</td>
            <td>:</td>
            <td><?php echo e(($document_base->note_type === 'credit')?$document_base->note_credit_type->description:$document_base->note_debit_type->description); ?></td>
        </tr>
        <tr>
            <td>DESCRIPCIÓN</td>
            <td>:</td>
            <td><?php echo e($document_base->note_description); ?></td>
        </tr>
        <?php endif; ?>
        <?php if($document->folio): ?>
        <tr>
            <td>FOLIO</td>
            <td>:</td>
            <td><?php echo e($document->folio); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    
    
    
    
    
    
    
    
    
    
    
    
    <?php
    $showBrandColumn = false;
    $showModelColumn = false;

    foreach ($document->items as $row) {
        if (!empty($row->item->model)) {
            $showModelColumn = true;
        }

        if (!empty($row->m_item->brand->name ?? null)) {
            $showBrandColumn = true;
        }

        if ($showModelColumn && $showBrandColumn) break;
    }
    ?>

    <table class="full-width mt-10 mb-10">
        <thead class="">
            <tr class="bg-grey">
                <th class="border-top-bottom text-center py-2" width="8%">COD.</th>
                <th class="border-top-bottom text-center py-2" width="8%">CANT.</th>
                <th class="border-top-bottom text-center py-2" width="8%">UNIDAD</th>
                <th class="border-top-bottom text-left py-2 px-1">DESCRIPCIÓN</th>
                <?php
                    $showSerieColumn = false;
                    $showLoteColumn = false;
                    $showDiscountItem = false;
                    foreach ($document->items as $row) {
                        if ($row->item->lots) {
                            $showSerieColumn = true;
                            break;
                        }
                    }

                    foreach ($document->items as $row) {
                        $dis = optional($row->discounts)->{0};
                        if (is_null($dis)) continue;

                        if (count((array)$dis) > 0) {
                            $showDiscountItem = true;
                            break;
                        }

                    }

                    foreach ($document->items as $row) {
                        if (isset($row->item->IdLoteSelected)) {
                            $showLoteColumn = true;
                            break;
                        }
                    }


                ?>
                <?php if(empty($showSerieColumn)): ?> <?php else: ?> <th class="border-top-bottom text-left py-2 px-1">SERIE</th> <?php endif; ?>
                <?php if($showModelColumn): ?>
                    <th class="border-top-bottom text-left py-2 px-1">MODELO</th>
                <?php endif; ?>
                <?php if($showBrandColumn): ?>
                    <th class="border-top-bottom text-center py-2 px-1">MARCA</th>
                <?php endif; ?>
                <?php if($showLoteColumn): ?> <th class="border-top-bottom text-center py-2 px-1">
                    LOTE
                </th> <?php endif; ?>
                <?php if($showLoteColumn): ?> <th class="border-top-bottom text-center py-2 px-1"> F. VENC. </th> <?php endif; ?>
                <th class="border-top-bottom text-right py-2 col-total">P.UNIT</th>
                <?php if($showDiscountItem): ?>
                    <th class="border-top-bottom text-right pl-4 col-total">P.TOTAL</th>
                <?php endif; ?>
                <th class="border-top-bottom text-right py-2 pr-2" width="8%">DTO.</th>
                <th class="border-top-bottom text-right py-2 col-total">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $colspan_base = 6;
                $colspan_total = $colspan_base;

                if($showSerieColumn) $colspan_total++;
                if($showModelColumn) $colspan_total++;
                if($showBrandColumn) $colspan_total++;
                if ($showDiscountItem) $colspan_total++;
                if($showLoteColumn) {
                    $colspan_total++;
                    $colspan_total++;
                }
            ?>
            <?php $__currentLoopData = $document->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td class="text-center align-top"><?php echo e($row->item->internal_id); ?></td>
                <td class="text-center align-top">
                    <?php if(((int)$row->quantity != $row->quantity)): ?>
                    <?php echo e($row->quantity); ?>

                    <?php else: ?>
                    <?php echo e(number_format($row->quantity, 0)); ?>

                    <?php endif; ?>
                </td>
                <td class="text-center align-top"><?php echo e($row->item->unit_type_id); ?></td>
                <td class="text-left align-top">
                    <?php if($row->name_product_pdf): ?>
                    <?php echo $row->name_product_pdf; ?>

                    <?php else: ?>
                    <?php echo $row->item->description; ?>

                    <?php endif; ?>

                    <?php if($row->total_isc > 0): ?>
                    <br /><span style="font-size: 9px">ISC : <?php echo e($row->total_isc); ?> (<?php echo e($row->percentage_isc); ?>%)</span>
                    <?php endif; ?>

                    <?php if(!empty($row->item->presentation)): ?> <?php echo $row->item->presentation->description; ?> <?php endif; ?>

                    <?php if($row->total_plastic_bag_taxes > 0): ?>
                    <br /><span style="font-size: 9px">ICBPER : <?php echo e($row->total_plastic_bag_taxes); ?></span>
                    <?php endif; ?>

                    <?php if($row->attributes): ?>
                        <?php $__currentLoopData = $row->attributes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            
                            <?php if(!in_array(strtoupper(trim($attr->description)), ['PLACA', 'NRO PLACA', 'NUMERO DE PLACA', 'NÚMERO DE PLACA', 'N° PLACA'])): ?>
                                <br /><span style="font-size: 9px"><?php echo $attr->description; ?> : <?php echo e($attr->value); ?></span>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>

                    <?php if($row->discounts): ?>
                    <?php $__currentLoopData = $row->discounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dtos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <br /><span style="font-size: 9px"><?php echo e($dtos->factor * 100); ?>% <?php echo e($dtos->description); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>

                    <?php if($row->charges): ?>
                    <?php $__currentLoopData = $row->charges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $charge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <br /><span style="font-size: 9px"><?php echo e($document->currency_type->symbol); ?> <?php echo e($charge->amount); ?> (<?php echo e($charge->factor * 100); ?>%) <?php echo e($charge->description); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>

                    <?php if($row->item->is_set == 1): ?>
                    <br>
                    <?php $itemSet = app('App\Services\ItemSetService'); ?>
                    <?php $__currentLoopData = $itemSet->getItemsSet($row->item_id); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php echo e($item); ?><br>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>

                    <?php if($row->item->used_points_for_exchange ?? false): ?>
                    <br>
                    <span
                        style="font-size: 9px">*** Canjeado por <?php echo e($row->item->used_points_for_exchange); ?> puntos ***</span>
                    <?php endif; ?>

                    <?php if($document->has_prepayment): ?>
                    <br>
                    *** Pago Anticipado ***
                    <?php endif; ?>
                </td>

                <?php if(empty($showSerieColumn)): ?> <?php else: ?>
                <td class="text-left align-top">
                    <?php if(isset($row->item->lots)): ?>
                        <?php $__currentLoopData = $row->item->lots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if( isset($lot->has_sale) && $lot->has_sale): ?>
                                <span style="font-size: 9px"><?php echo e($lot->series); ?></span><br>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <?php if($showModelColumn): ?>
                    <td class="text-left align-top"><?php echo e($row->item->model ?? ''); ?></td>
                <?php endif; ?>

                <?php if($showBrandColumn): ?>
                    <td class="text-left align-top">
                        <?php echo e($row->m_item->brand->name ?? ''); ?>

                    </td>
                <?php endif; ?>
                <?php $itemLotGroup = app('App\Services\ItemLotsGroupService'); ?>
                <?php
                    $lot = $itemLotGroup->getLote($row->item->IdLoteSelected);
                    $date_due = $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected);
                ?>

                <?php if($showLoteColumn): ?>
                    <td class="text-center align-top">
                        <?php if($lot): ?>
                            <?php $__currentLoopData = explode('/', $lot); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php if(trim($code) !== ''): ?>
                                    <?php echo e(trim($code)); ?><br>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <?php if($showLoteColumn): ?>
                    <td class="text-center align-top">
                        <?php
                            $cleanedDate = $date_due != ''
                                ? ltrim($date_due, '/')
                                : ($row->relation_item->date_of_due ? $row->relation_item->date_of_due->format('Y-m-d') : '');
                        ?>

                        <?php echo e($cleanedDate); ?>

                    </td>
                <?php endif; ?>
                <?php
                    $unit_price_item = $row->getUnitPrice(($configuration['is_preview']) , $document);
                    $price_total_item = $unit_price_item * $row->quantity;
                ?>
                <?php if($configuration_decimal_quantity->change_decimal_quantity_unit_price_pdf): ?>
                
                <td class="text-right align-top"><?php echo e($row->generalApplyNumberFormat( $unit_price_item, $configuration_decimal_quantity->decimal_quantity_unit_price_pdf)); ?></td>
                <?php else: ?>
                <td class="text-right align-top"><?php echo e(number_format($unit_price_item, 2)); ?></td>
                <?php endif; ?>

                <?php if($showDiscountItem): ?>
                    
                    <td class="text-right align-top pr-2"><?php echo e(number_format( $price_total_item , 2)); ?></td>

                <?php endif; ?>

                <td class="text-right align-top pr-2">
                    <?php if($row->discounts): ?>
                    <?php
                    $total_discount_line = 0;
                    foreach ($row->discounts as $disto) {
                        if ($disto->from_global_distribution) continue;
                        $amount = $disto->discount_type_id == "00" ? $disto->amount_without_rounded * 1.18 : $disto->amount;
                        $total_discount_line = $total_discount_line + $amount;
                    }
                    ?>
                    <?php echo e(number_format($total_discount_line, 2)); ?>

                    <?php else: ?>
                    0
                    <?php endif; ?>
                </td>
                <td class="text-right align-top"><?php echo e(number_format($row->total, 2)); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo e($colspan_total+1); ?>" class="border-bottom"></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <?php if($document->prepayments): ?>
            <?php $__currentLoopData = $document->prepayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td class="text-center align-top"></td>
                <td class="text-center align-top">1</td>
                <td class="text-center align-top">NIU</td>
                <td class="text-left align-top">
                    ANTICIPO: <?php echo e(($p->document_type_id == '02')? 'FACTURA':'BOLETA'); ?> NRO. <?php echo e($p->number); ?>

                </td>
                <td class="text-right align-top">-<?php echo e(number_format($p->total, 2)); ?></td>
                <td class="text-right align-top">0</td>
                <td class="text-right align-top">-<?php echo e(number_format($p->total, 2)); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo e($colspan_total+1); ?>" class="border-bottom"></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>

            <?php if($document->total_exportation > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">OP. EXPORTACIÓN: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_exportation, 2)); ?></td>
            </tr>
            <?php endif; ?>
            <?php if($document->total_free > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">OP. GRATUITAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_free, 2)); ?></td>
            </tr>
            <?php endif; ?>
            <?php if($document->total_unaffected > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">OP. INAFECTAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_unaffected, 2)); ?></td>
            </tr>
            <?php endif; ?>
            <?php if($document->total_exonerated > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">OP. EXONERADAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_exonerated, 2)); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($document->document_type_id === '07'): ?>
            <?php if($document->total_taxed >= 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right pr-2">OP. GRAVADAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right"><?php echo e(number_format($document->total_taxed, 2)); ?></td>
            </tr>
            <?php endif; ?>
            <?php elseif($document->total_taxed > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right pr-2">OP. GRAVADAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right"><?php echo e(number_format($document->total_taxed, 2)); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($document->total_plastic_bag_taxes > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">ICBPER: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_plastic_bag_taxes, 2)); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right pr-2">IGV: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right"><?php echo e(number_format($document->total_igv, 2)); ?></td>
            </tr>

            <?php if($document->total_isc > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">ISC: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_isc, 2)); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($document->total_discount_with_igv > 0 && $document->subtotal > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">SUBTOTAL: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->subtotal, 2)); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($document->total_discount_with_igv > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>"
                    class="text-right font-bold pr-2"><?php echo e((($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')); ?>

                    : <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_discount_with_igv, 2)); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($document->total_charge > 0): ?>
            <?php if($document->charges): ?>
            <?php
            $total_factor = 0;
            foreach($document->charges as $charge) {
            $total_factor = ($total_factor + $charge->factor) * 100;
            }
            ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">CARGOS (<?php echo e($total_factor); ?>

                    %): <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_charge, 2)); ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">CARGOS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_charge, 2)); ?></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>

            <?php if($document->perception): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">IMPORTE TOTAL: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total, 2)); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">PERCEPCIÓN: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->perception->amount, 2)); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo e(ceil(($colspan_total + 1) / 2)); ?>" class="text-left font-bold" style="white-space: nowrap;">Productos: <?php echo e(rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) { return (float) data_get($item, 'quantity', 0); }), 2, '.', ''), '0'), '.')); ?></td>
                <td colspan="<?php echo e(floor(($colspan_total + 1) / 2) - 1); ?>" class="text-right font-bold pr-2">TOTAL A PAGAR: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format(($document->total + $document->perception->amount), 2)); ?></td>
            </tr>
            <?php elseif($document->retention): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2"
                    style="font-size: 16px;">IMPORTE TOTAL: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold" style="font-size: 16px;"><?php echo e(number_format($document->total, 2)); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right pr-2">TOTAL RETENCIÓN (<?php echo e($document->retention->percentage * 100); ?>

                    %): <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right"><?php echo e(number_format($document->retention->amount, 2)); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right pr-2">IMPORTE NETO: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right"><?php echo e(number_format(($document->total - $document->retention->amount), 2)); ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="<?php echo e(ceil(($colspan_total + 1) / 2)); ?>" class="text-left font-bold" style="white-space: nowrap;">Productos: <?php echo e(rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) { return (float) data_get($item, 'quantity', 0); }), 2, '.', ''), '0'), '.')); ?></td>
                <td colspan="<?php echo e(floor(($colspan_total + 1) / 2) - 1); ?>" class="text-right font-bold pr-2">TOTAL A PAGAR: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total, 2)); ?></td>
            </tr>
            <?php endif; ?>

            <?php if(($document->retention || $document->detraction) && $document->total_pending_payment > 0): ?>
            <?php
                $value_ob = $document->detraction ? $document->detraction : $document->retention;
                $total_pending_payment = $document->total_pending_payment - $value_ob->guarantee_fund;
            ?>

            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">M. PENDIENTE: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_pending_payment, 2)); ?></td>
            </tr>
                <?php if($configurationEnableGuaranteeFund->enabled_guarantee_fund): ?>
                    <tr>
                        <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">FONDO DE GARANTIA: <?php echo e($document->currency_type->symbol); ?></td>
                        <td class="text-right font-bold"><?php echo e(number_format($value_ob->guarantee_fund, 2)); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($balance < 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold pr-2">VUELTO: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format(abs($balance),2, ".", "")); ?></td>
            </tr>
            <?php endif; ?>

        </tbody>
    </table>
    <table class="full-width">
        <?php
            $personType = $document->person->person_type;
        ?>
        <tr width="65%">
            <td colspan="<?php echo e($colspan_total); ?>" class="text-left py-1"><strong>N° DE PRODUCTOS</strong>: <?php echo e($document->items->count()); ?></td>
        </tr>
        <?php if( $personType && $personType->enabled_description_person_type): ?>
            <tr width="65%" >
                <td>
                    <strong><?php echo e($personType->description); ?></strong> : <?php echo e($personType->description_person_type); ?>

                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <td width="65%" style="text-align: top; vertical-align: top;">
                <?php $__currentLoopData = array_reverse( (array) $document->legends); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($row->code == "1000"): ?>
                <p style="text-transform: uppercase;">Son: <span
                        class="font-bold"><?php echo e($row->value); ?> <?php echo e($document->currency_type->description); ?></span></p>
                <?php if(count((array) $document->legends)>1): ?>
                <p><span class="font-bold">Leyendas</span></p>
                <?php endif; ?>
                <?php else: ?>
                <p> <?php echo e($row->code); ?>: <?php echo e($row->value); ?> </p>
                <?php endif; ?>

                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <br />
                <?php if($document->detraction): ?>
                <p>
                    <span class="font-bold">
                        Operación sujeta al Sistema de Pago de Obligaciones Tributarias
                    </span>
                </p>
                <br />
                <?php endif; ?>
                <?php if($customer->department_id == 16): ?>
                <br /><br /><br />
                <div>
                    <center>
                        Representación impresa del Comprobante de Pago Electrónico.
                        <br />Esta puede ser consultada en:
                        <br /><b><?php echo url('/buscar'); ?></b>
                        <br /> "Bienes transferidos en la Amazonía
                        <br />para ser consumidos en la misma".
                    </center>
                </div>
                <br />
                <?php endif; ?>
                <?php $__currentLoopData = $document->additional_information; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $information): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($information): ?>
                <?php if($loop->first): ?>
                <strong>Información adicional</strong>
                <?php endif; ?>
                <p><?php if(\App\CoreFacturalo\Helpers\Template\TemplateHelper::canShowNewLineOnObservation()): ?>
                    <?php echo \App\CoreFacturalo\Helpers\Template\TemplateHelper::SetHtmlTag($information); ?>

                    <?php else: ?>
                    <?php echo e($information); ?>

                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <?php if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf): ?>
                <br>
                <?php if(in_array($document->document_type->id,['01','03'])): ?>
                <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p>
                    <span
                        class="font-bold"><?php echo e($account->bank->description); ?></span> <?php echo e($account->currency_type->description); ?>

                    <span class="font-bold">N°:</span> <?php echo e($account->number); ?>

                    <?php if($account->cci): ?>
                    <span class="font-bold">CCI:</span> <?php echo e($account->cci); ?>

                    <?php endif; ?>
                </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
                <?php endif; ?>

                <?php if($document->custom_fields_data && count((array)$document->custom_fields_data) > 0): ?>
                    <br>
                    <table class="full-width">
                        <?php $__currentLoopData = $document->custom_fields_data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field_slug => $field_value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $custom_field = \Modules\CustomField\Models\CustomField::where('slug', $field_slug)->first();
                                if ($custom_field && !$custom_field->show_in_pdf) {
                                    continue;
                                }
                                $field_name = ($custom_field) ? $custom_field->name : str_replace('_', ' ', ucfirst($field_slug));
                            ?>
                            <tr>
                                <td>
                                    <?php echo e($field_name); ?>

                                </td>
                                <td width="8px">:</td>
                                <td>
                                    <?php if(is_array($field_value)): ?>
                                        <?php echo e(implode(', ', $field_value)); ?>

                                    <?php else: ?>
                                        <?php echo e($field_value); ?>

                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </table>
                <?php endif; ?>
            </td>
            <td width="35%" class="text-right">
                <img src="data:image/png;base64, <?php echo e($document->qr); ?>" style="margin-right: -10px;" />
                <p style="font-size: 9px">Código Hash: <?php echo e($document->hash); ?></p>
            </td>
        </tr>
    </table>
    <?php
    $paymentCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
    ?>
    
    <table class="full-width">
        <tr>
            <td>
                <strong>CONDICIÓN DE PAGO: <?php echo e($paymentCondition); ?> </strong>
            </td>
        </tr>
    </table>

    <?php if($document->payment_method_type_id): ?>
    <table class="full-width">
        <tr>
            <td>
                <strong>MÉTODO DE PAGO: </strong><?php echo e($document->payment_method_type->description); ?>

            </td>
        </tr>
    </table>
    <?php endif; ?>

    <?php if($document->payment_condition_id === '01'): ?>
    <?php if($payments->count()): ?>
    <table class="full-width">
        <tr>
            <td><strong>PAGOS:</strong></td>
        </tr>
        <?php $payment = 0; ?>
        <?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>&#8226; <?php echo e($row->payment_method_type->description); ?>

                - <?php echo e($row->reference ? $row->reference.' - ':''); ?> <?php echo e($document->currency_type->symbol); ?> <?php echo e($row->payment + $row->change); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tr>
    </table>
    <?php endif; ?>
    <?php else: ?>
    <table class="full-width">
        <?php $__currentLoopData = $document->fee; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                &#8226; <?php echo e((empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType())); ?>

                / Fecha: <?php echo e($quote->date->format('d-m-Y')); ?> /
                Monto: <?php echo e($quote->currency_type->symbol); ?><?php echo e($quote->amount); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tr>
    </table>
    <?php endif; ?>

    <?php if($document->retention): ?>
    <br>
    <table class="full-width">
        <tr>
            <td>
                <strong>Información de la retención:</strong>
            </td>
        </tr>
        <tr>
            <td>Valor total del comprobante:
                <?php echo e($document->currency_type->symbol); ?>

                <?php echo e($document->currency_type->id == 'USD' ? number_format(($document->getRetentionTaxBase()/$document->exchange_rate_sale), 2) : $document->getRetentionTaxBase()); ?>

                
            </td>
        </tr>
        <tr>
            <td>Porcentaje de la retención: <?php echo e($document->retention->percentage * 100); ?>%</td>
        </tr>
        <tr>
            <td>Monto de la retención <?php echo e($document->currency_type->id == 'USD' ? 'soles' : ''); ?>:
                S/ <?php echo e($document->retention->amount_pen); ?>

            </td>
        </tr>
        <?php if($document->currency_type->id == 'USD'): ?>
        <tr>
            <td>Monto de la retención dólares:
                <?php echo e($document->currency_type->symbol); ?> <?php echo e(number_format(($document->retention->amount_pen/$document->exchange_rate_sale), 2)); ?>

            </td>
        </tr>

        <?php endif; ?>
    </table>
    <?php endif; ?>

    <?php if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf): ?>
    <br>
    <table class="full-width">
        <tr>
            <td>
                <strong>Vendedor:</strong>
            </td>
        </tr>
        <tr>
            <?php if($document->seller): ?>
            <td><?php echo e($document->seller->name); ?></td>
            <?php else: ?>
            <td><?php echo e($document->user->name); ?></td>
            <?php endif; ?>
        </tr>
    </table>
    <?php endif; ?>

    

    <?php if($document->terms_condition): ?>
    <br>
    <table class="full-width">
        <tr>
            <td>
                <h6 style="font-size: 12px; font-weight: bold;">Términos y condiciones del servicio</h6>
                <?php echo $document->terms_condition; ?>

            </td>
        </tr>
    </table>
    <?php endif; ?>
</body>

</html><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\app\CoreFacturalo\Templates/pdf/default/invoice_a4.blade.php ENDPATH**/ ?>