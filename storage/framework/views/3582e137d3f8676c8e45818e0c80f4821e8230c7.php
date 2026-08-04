<?php
    use Modules\Template\Helpers\TemplatePdf;
    //dd($document->customer);
    $establishment = $document->establishment;
    $customer = $document->customer;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $accounts = \App\Models\Tenant\BankAccount::all();
    //$accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $tittle = $document->pdf_title;

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $configuration_decimal_quantity = App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationDecimalQuantity();
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
?>
<html>
<head>
    
    
</head>
<body>
<table class="full-width">
    <tr>
        <?php if($company->logo): ?>
            <td width="20%">
                <div class="company_logo_box">
                    <img src="data:<?php echo e(mime_content_type(public_path("{$logo}"))); ?>;base64, <?php echo e(base64_encode(file_get_contents(public_path("{$logo}")))); ?>" alt="<?php echo e(\App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company)); ?>" class="company_logo" style="max-width: 150px;">
                </div>
            </td>
            <td width="50%" class="text-center">
                <div class="text-left">
                    <?php echo $__env->make('pdf.partials.company_document_header_names', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    <h5><?php echo e('RUC '.$company->number); ?></h5>
                    <h6 style="text-transform: uppercase;">
                        <?php echo e(($establishment->address !== '-')? $establishment->address : ''); ?>

                        <?php echo e(($establishment->district_id !== '-')? ', '.$establishment->district->description : ''); ?>

                        <?php echo e(($establishment->province_id !== '-')? ', '.$establishment->province->description : ''); ?>

                        <?php echo e(($establishment->department_id !== '-')? '- '.$establishment->department->description : ''); ?>

                    </h6>

                    <?php if(isset($establishment->trade_address)): ?>
                        <h6><?php echo e(($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : ''); ?></h6>
                    <?php endif; ?>
                    <h6><?php echo e(($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : ''); ?></h6>

                    <h6><?php echo e(($establishment->email !== '-')? 'Email: '.$establishment->email : ''); ?></h6>

                    <?php if(isset($establishment->web_address)): ?>
                        <h6><?php echo e(($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : ''); ?></h6>
                    <?php endif; ?>

                    <?php if(isset($establishment->aditional_information)): ?>
                        <h6><?php echo e(($establishment->aditional_information !== '-')? $establishment->aditional_information : ''); ?></h6>
                    <?php endif; ?>
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h5 class="text-center">COTIZACIÓN</h5>
                <h3 class="text-center"><?php echo e($tittle); ?></h3>
            </td>
        <?php else: ?>
            <td width="70%" class="pl-1">
                <div class="text-left">
                    <?php echo $__env->make('pdf.partials.company_document_header_names', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    <h5><?php echo e('RUC '.$company->number); ?></h5>
                    <h6 style="text-transform: uppercase;">
                        <?php echo e(($establishment->address !== '-')? $establishment->address : ''); ?>

                        <?php echo e(($establishment->district_id !== '-')? ', '.$establishment->district->description : ''); ?>

                        <?php echo e(($establishment->province_id !== '-')? ', '.$establishment->province->description : ''); ?>

                        <?php echo e(($establishment->department_id !== '-')? '- '.$establishment->department->description : ''); ?>

                    </h6>

                    <?php if(isset($establishment->trade_address)): ?>
                        <h6><?php echo e(($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : ''); ?></h6>
                    <?php endif; ?>
                    <h6><?php echo e(($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : ''); ?></h6>

                    <h6><?php echo e(($establishment->email !== '-')? 'Email: '.$establishment->email : ''); ?></h6>

                    <?php if(isset($establishment->web_address)): ?>
                        <h6><?php echo e(($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : ''); ?></h6>
                    <?php endif; ?>

                    <?php if(isset($establishment->aditional_information)): ?>
                        <h6><?php echo e(($establishment->aditional_information !== '-')? $establishment->aditional_information : ''); ?></h6>
                    <?php endif; ?>
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h5 class="text-center">COTIZACIÓN</h5>
                <h3 class="text-center"><?php echo e($tittle); ?></h3>
            </td>
        <?php endif; ?>        
    </tr>
</table>
<table class="full-width mt-5">
    <tr>
        <td width="15%">Cliente:</td>
        <td width="45%"><?php echo e($customer->name); ?></td>
        <td width="25%">Fecha de emisión:</td>
        <td width="25%"><?php echo e($document->date_of_issue->format('Y-m-d')); ?> / <?php echo e($document->time_of_issue); ?></td>
    </tr>
    <tr>
        <td><?php echo e($customer->identity_document_type->description); ?>:</td>
        <td><?php echo e($customer->number); ?></td>
        <?php if($document->date_of_due): ?>
            <td width="25%">Tiempo de Validez:</td>
            <td width="15%"><?php echo e($document->date_of_due); ?></td>
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

    <tr>
        <td class="align-top">Dirección:</td>
        <td>
            <?php echo e($fullAddress ?: 'No disponible'); ?>

        </td>
        <?php if($document->delivery_date): ?>
            <td width="25%">Tiempo de Entrega:</td>
            <td width="15%"><?php echo e($document->delivery_date); ?></td>
        <?php endif; ?>
    </tr>
    <?php if($document->payment_method_type): ?>
    <tr>
        <td class="align-top">T. Pago:</td>
        <td colspan="">
            <?php echo e($document->payment_method_type->description); ?>

        </td>
        <?php if($document->sale_opportunity): ?>
            <td width="25%">O. Venta:</td>
            <td width="15%"><?php echo e($document->sale_opportunity->number_full); ?></td>
        <?php endif; ?>
    </tr>
    <?php endif; ?>
        <tr>
            <td class="align-top">MONEDA: 
            </td>
            <td>
                <?php if($document->currency_type_id == 'PEN'): ?>
                Soles
                <?php elseif($document->currency_type_id == 'USD'): ?>
                Dolares
                <?php endif; ?>
            </td>
        </tr>
    <?php if($document->account_number): ?>
    <tr>
        <td class="align-top">N° Cuenta:</td>
        <td colspan="1">
            <?php echo e($document->account_number); ?>

        </td>
    </tr>
    <?php endif; ?>
    <?php if($document->shipping_address): ?>
    <tr>
        <td class="align-top">Dir. Envío:</td>
        <td colspan="3">
            <?php echo e($document->shipping_address); ?>

        </td>
    </tr>
    <?php endif; ?>
    <?php if($customer->telephone): ?>
    <tr>
        <td class="align-top">Teléfono:</td>
        <td colspan="3">
            <?php echo e($customer->telephone); ?>

        </td>
    </tr>
    <?php endif; ?>
    <?php if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf): ?>
        <tr>
            <td class="align-top">Vendedor:</td>
            <td colspan="3">
                <?php if($document->seller->name): ?>
                    <?php echo e($document->seller->name); ?>

                <?php else: ?>
                    <?php echo e($document->user->name); ?>

                <?php endif; ?>
            </td>
        </tr>
    <?php endif; ?>
    <?php if($document->contact): ?>
    <tr>
        <td class="align-top">Contacto:</td>
        <td colspan="3">
            <?php echo e($document->contact); ?>

        </td>
    </tr>
    <?php endif; ?>
    <?php if($document->phone): ?>
    <tr>
        <td class="align-top">Telf. Contacto:</td>
        <td colspan="3">
            <?php echo e($document->phone); ?>

        </td>
    </tr>
    <?php endif; ?>
</table>

<table class="full-width mt-3">
    <?php if($document->description): ?>
        <tr>
            <td width="15%" class="align-top">Observación: </td>
            <td width="85%"><?php echo str_replace("\n", "<br/>", $document->description); ?></td>
            
        </tr>
    <?php endif; ?>
</table>

<?php if($document->guides): ?>
<br/>

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

<?php
    $show_brand = $document->items->contains(function ($row) {
        return !empty($row->item->brand);
    });

    $show_model = $document->items->contains(function ($row) {
        return !empty($row->item->model);
    });

    $show_lot = $document->items->contains(function ($row) {
        return !empty($row->getSaleLotGroupCodeDescription());
    });

    $show_due = $document->items->contains(function ($row) {
        return !empty(optional($row->relation_item)->date_of_due);
    });
    $total_weight = 0;
    $show_weight_attribute = $document->items->some(function($row) {
        $at = (array)$row->attributes;
        if (isset($row->attributes) && count(($at)) > 0) {
            $attributes = (array)(($at)[0]);
            return collect($attributes)->where('attribute_type_id', '5031');
        }
        return false;
    });

?>

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr class="bg-grey">
        <th class="border-top-bottom text-center py-2" width="8%">COD.</th>
        <th class="border-top-bottom text-center py-2" width="8%">CANT.</th>
        <th class="border-top-bottom text-center py-2" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-left py-2">DESCRIPCIÓN</th>
        <?php if($show_model): ?>
            <th class="border-top-bottom text-left py-2 px-1">MODELO</th>
        <?php endif; ?>

        <?php if($show_brand): ?>
            <th class="border-top-bottom text-left py-2 px-1">MARCA</th>
        <?php endif; ?>

        <?php if($show_lot): ?>
            <th class="border-top-bottom text-center py-2 px-1">LOTE</th>
        <?php endif; ?>

        <?php if($show_due): ?>
            <th class="border-top-bottom text-center py-2 px-1">F. VENC.</th>
        <?php endif; ?> 
        <th class="border-top-bottom text-right py-2 col-total">P.UNIT</th>
        <th class="border-top-bottom text-right py-2" width="8%">DTO.</th>
        <th class="border-top-bottom text-right py-2 col-total">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    <?php
        $colspan_total = 6;

        if($show_model) $colspan_total++;
        if($show_brand) $colspan_total++;
        if($show_lot) $colspan_total++;
        if($show_due) $colspan_total++;
    ?>

    <?php $__currentLoopData = $document->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <?php
                $internal_id = optional($row->item)->internal_id;
                $at = (array)$row->attributes;
                if (isset($row->attributes) && count(($at)) > 0) {
                    $attributes = (array)(($at)[0]);
                    $total_weight += (float)($attributes['value'] ?? 0) * (float)$row->quantity;
                }
            ?>
            <td class="text-center align-top"><?php echo e($internal_id); ?></td>
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
                    <br/><span style="font-size: 9px">ISC : <?php echo e($row->total_isc); ?> (<?php echo e($row->percentage_isc); ?>%)</span>
                <?php endif; ?>

                <?php if(!empty($row->item->presentation)): ?> <?php echo $row->item->presentation->description; ?> <?php endif; ?>

                <?php if($row->total_plastic_bag_taxes > 0): ?>
                    <br/><span style="font-size: 9px">ICBPER : <?php echo e($row->total_plastic_bag_taxes); ?></span>
                <?php endif; ?>

                <?php if($row->attributes): ?>
                    <?php $__currentLoopData = $row->attributes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <br/><span style="font-size: 9px"><?php echo $attr->description; ?> : <?php echo e($attr->value); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
                <?php if($row->discounts): ?>
                    <?php $__currentLoopData = $row->discounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dtos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <br/><span style="font-size: 9px"><?php echo e($dtos->factor * 100); ?>% <?php echo e($dtos->description); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>

                <?php if($row->charges): ?>
                    <?php $__currentLoopData = $row->charges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $charge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <br/><span style="font-size: 9px"><?php echo e($document->currency_type->symbol); ?> <?php echo e($charge->amount); ?> (<?php echo e($charge->factor * 100); ?>%) <?php echo e($charge->description); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>

                <?php if(($row->item->is_set ?? 0) == 1): ?>
                    <br>
                    <?php $itemSet = app('App\Services\ItemSetService'); ?>
                    <?php $__currentLoopData = $itemSet->getItemsSet($row->item_id); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php echo e($item); ?><br>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>

                <?php if($row->item !== null && property_exists($row->item,'extra_attr_value') && $row->item->extra_attr_value != ''): ?>
                    <br/><span style="font-size: 9px"><?php echo e($row->item->extra_attr_name); ?>: <?php echo e($row->item->extra_attr_value); ?></span>
                <?php endif; ?>

                <?php if($row->item->used_points_for_exchange ?? false): ?>
                    <br>
                    <span
                        style="font-size: 9px">*** Canjeado por <?php echo e($row->item->used_points_for_exchange); ?>  puntos ***</span>
                <?php endif; ?>

                <?php if($document->has_prepayment): ?>
                    <br>
                    *** Pago Anticipado ***
                <?php endif; ?>
            </td>
            <?php if($show_model): ?>
                <td class="text-left"><?php echo e($row->item->model ?? ''); ?></td>
            <?php endif; ?>

            <?php if($show_brand): ?>
                <td class="text-left align-top"><?php echo e($row->item->brand ?? ''); ?></td>
            <?php endif; ?>

            <?php if($show_lot): ?>
                <td class="text-center align-top"><?php echo e($row->getSaleLotGroupCodeDescription()); ?></td>
            <?php endif; ?>

            <?php if($show_due): ?>
                <td class="text-center align-top">
                    <?php if(isset($row->relation_item->date_of_due)): ?>
                        <?php echo e($row->relation_item->date_of_due->format('Y-m-d')); ?>

                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            <?php endif; ?>    
            <td class="text-right align-top"><?php echo e(number_format($row->unit_price, 2)); ?></td>
            <td class="text-right align-top">
                <?php if($row->discounts): ?>
                    <?php
                        $total_discount_line = 0;
                        foreach ($row->discounts as $disto) {
                            $total_discount_line = $total_discount_line + $disto->amount;
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
        <?php if($document->total_exportation > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">OP. EXPORTACIÓN: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_exportation, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <?php if($document->total_free > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">OP. GRATUITAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_free, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <?php if($document->total_unaffected > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">OP. INAFECTAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_unaffected, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <?php if($document->total_exonerated > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">OP. EXONERADAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_exonerated, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <?php if($document->total_taxed > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">OP. GRAVADAS: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_taxed, 2)); ?></td>
            </tr>
        <?php endif; ?>
       <?php if($document->total_discount_with_igv > 0): ?>
            <tr>
                <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold"><?php echo e((($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')); ?>: <?php echo e($document->currency_type->symbol); ?></td>
                <td class="text-right font-bold"><?php echo e(number_format($document->total_discount_with_igv, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">IGV: <?php echo e($document->currency_type->symbol); ?></td>
            <td class="text-right font-bold"><?php echo e(number_format($document->total_igv, 2)); ?></td>
        </tr>
        <tr>
            <td colspan="<?php echo e($colspan_total); ?>" class="text-right font-bold">TOTAL A PAGAR: <?php echo e($document->currency_type->symbol); ?></td>
            <td class="text-right font-bold"><?php echo e(number_format($document->total, 2)); ?></td>
        </tr>
    </tbody>
</table>
<?php if($show_weight_attribute): ?>
    <table class="full-width">
        <tr>
            <td colspan="<?php echo e($colspan_total); ?>" class="text-left font-bold">Peso estimado: <?php echo e(number_format($total_weight, 2)); ?> Kg</td>
        </tr>
    </table>
<?php endif; ?>
<table class="full-width">
    <?php if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf): ?>
        <tr>
            <td width="65%" style="text-align: top; vertical-align: top;">
                <br>
                <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p>
                    <span class="font-bold"><?php echo e($account->bank->description); ?></span> <?php echo e($account->currency_type->description); ?>

                    <span class="font-bold">N°:</span> <?php echo e($account->number); ?>

                    <?php if($account->cci): ?>
                    - <span class="font-bold">CCI:</span> <?php echo e($account->cci); ?>

                    <?php endif; ?>
                    </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
            
            
        
    </tr>
    <?php if($document->referential_information): ?>
    <tr>
        <td>
            <strong>Información adicional: </strong>
        </td>
    </tr>
    <tr>
        <td><?php echo e($document->referential_information); ?></td>
    </tr>
    <?php endif; ?>
</table>
<table class="full-width">
    <tr>
        <td class="">
        
            <?php echo $document->terms_condition; ?>

        </td>
    </tr>
    <br><br>
</table>


<br>
<table class="full-width">
<tr>
    <td>
    <strong>PAGOS:</strong> </td></tr>
        <?php
            $payment = 0;
        ?>
        <?php $__currentLoopData = $document->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr><td>- <?php echo e($row->payment_method_type->description); ?> - <?php echo e($row->reference ? $row->reference.' - ':''); ?> <?php echo e($document->currency_type->symbol); ?> <?php echo e($row->payment); ?></td></tr>
            <?php
                $payment += (float) $row->payment;
            ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr><td><strong>SALDO:</strong> <?php echo e($document->currency_type->symbol); ?> <?php echo e(number_format($document->total - $payment, 2)); ?></td>
    </tr>

</table>

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
            <td width="30%" class="font-bold">
                <?php echo e($field_name); ?>

            </td>
            <td width="2%">:</td>
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
</body>
</html>
<?php /**PATH C:\laragon\www\Pro9\app\CoreFacturalo\Templates/pdf/default/quotation_a4.blade.php ENDPATH**/ ?>