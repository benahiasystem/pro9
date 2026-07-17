<?php
    $invoice = $document->invoice;
    $establishment = $document->establishment;
    $customer = $document->customer;
    $itinerant = $document->itinerant;
    $document_xml_service = new Modules\Document\Services\DocumentXmlService;

    // Cargos globales que no afectan la base imponible del IGV/IVAP
    $tot_charges = $document_xml_service->getGlobalChargesNoBase($document);

    //descuento global - item que no afectan la base imponible
    $total_discount_no_base = $document_xml_service->getGlobalDiscountsNoBase($document) + $document_xml_service->getItemsDiscountsNoBase($document);

?>
<?php echo '<'.'?xml version="1.0" encoding="utf-8" standalone="no"?'.'>'; ?>

<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <ext:UBLExtensions>
        <ext:UBLExtension>
            <ext:ExtensionContent/>
        </ext:UBLExtension>
    </ext:UBLExtensions>
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>2.0</cbc:CustomizationID>
    <cbc:ID><?php echo e($document->series); ?>-<?php echo e($document->number); ?></cbc:ID>
    <cbc:IssueDate><?php echo e($document->date_of_issue->format('Y-m-d')); ?></cbc:IssueDate>
    <cbc:IssueTime><?php echo e($document->time_of_issue); ?></cbc:IssueTime>
    <?php if($invoice->date_of_due): ?>
    <cbc:DueDate><?php echo e($invoice->date_of_due->format('Y-m-d')); ?></cbc:DueDate>
    <?php endif; ?>
    <cbc:InvoiceTypeCode listID="<?php echo e($invoice->operation_type_id); ?>"><?php echo e($document->document_type_id); ?></cbc:InvoiceTypeCode>
    <?php $__currentLoopData = $document->legends; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cbc:Note languageLocaleID="<?php echo e($leg->code); ?>"><![CDATA[<?php echo e($leg->value); ?>]]></cbc:Note>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <cbc:DocumentCurrencyCode><?php echo e($document->currency_type_id); ?></cbc:DocumentCurrencyCode>
    <?php if($document->purchase_order): ?>
    <cac:OrderReference>
        <cbc:ID><?php echo e($document->purchase_order); ?></cbc:ID>
    </cac:OrderReference>
    <?php endif; ?>
    <?php if($document->guides): ?>
    <?php $__currentLoopData = $document->guides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $guide): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($guide->document_type_id!='31'): ?>
        <cac:DespatchDocumentReference>
            <cbc:ID><?php echo e($guide->number); ?></cbc:ID>
            <cbc:DocumentTypeCode><?php echo e($guide->document_type_id); ?></cbc:DocumentTypeCode>
        </cac:DespatchDocumentReference>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if($document->related): ?>
    <?php $__currentLoopData = $document->related; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cac:AdditionalDocumentReference>
        <cbc:ID><?php echo e($rel->number); ?></cbc:ID>
        <cbc:DocumentTypeCode><?php echo e($rel->document_type_id); ?></cbc:DocumentTypeCode>
    </cac:AdditionalDocumentReference>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if($document->prepayments): ?>
    <?php $__currentLoopData = $document->prepayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prepayment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cac:AdditionalDocumentReference>
        <cbc:ID><?php echo e($prepayment->number); ?></cbc:ID>
        <cbc:DocumentTypeCode><?php echo e($prepayment->document_type_id); ?></cbc:DocumentTypeCode>
        <cbc:DocumentStatusCode><?php echo e($loop->iteration); ?></cbc:DocumentStatusCode>
        <cac:IssuerParty>
            <cac:PartyIdentification>
                <cbc:ID schemeID="6"><?php echo e($company->number); ?></cbc:ID>
            </cac:PartyIdentification>
        </cac:IssuerParty>
    </cac:AdditionalDocumentReference>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <cac:Signature>
        <cbc:ID><?php echo e(config('configuration.signature_uri')); ?></cbc:ID>
        <cbc:Note><?php echo e(config('configuration.signature_note')); ?></cbc:Note>
        <cac:SignatoryParty>
            <cac:PartyIdentification>
                <cbc:ID><?php echo e($company->number); ?></cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name><![CDATA[<?php echo e($company->trade_name); ?>]]></cbc:Name>
            </cac:PartyName>
        </cac:SignatoryParty>
        <cac:DigitalSignatureAttachment>
            <cac:ExternalReference>
                <cbc:URI>#<?php echo e(config('configuration.signature_uri')); ?></cbc:URI>
            </cac:ExternalReference>
        </cac:DigitalSignatureAttachment>
    </cac:Signature>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="6"><?php echo e($company->number); ?></cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name><![CDATA[<?php echo e($company->trade_name); ?>]]></cbc:Name>
            </cac:PartyName>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName><![CDATA[<?php echo e($document_xml_service->getTextWithoutEspecialCharacter($company->name)); ?>]]></cbc:RegistrationName>
                <cac:RegistrationAddress>
                    <cbc:ID><?php echo e($establishment->district_id); ?></cbc:ID>
                    <cbc:AddressTypeCode><?php echo e($establishment->code); ?></cbc:AddressTypeCode>
                    <?php if($establishment->urbanization): ?>
                    <cbc:CitySubdivisionName><?php echo e($establishment->urbanization); ?></cbc:CitySubdivisionName>
                    <?php endif; ?>
                    <cbc:CityName><?php echo e($establishment->province->description); ?></cbc:CityName>
                    <cbc:CountrySubentity><?php echo e($establishment->department->description); ?></cbc:CountrySubentity>
                    <cbc:District><?php echo e($establishment->district->description); ?></cbc:District>
                    <?php if($establishment->address && $establishment->address !== '-'): ?>
                    <cac:AddressLine>
                        <cbc:Line><![CDATA[<?php echo e($establishment->address); ?>]]></cbc:Line>
                    </cac:AddressLine>
                    <?php endif; ?>
                    <cac:Country>
                        <cbc:IdentificationCode><?php echo e($establishment->country_id); ?></cbc:IdentificationCode>
                    </cac:Country>
                </cac:RegistrationAddress>
            </cac:PartyLegalEntity>
            <?php if($establishment->email || $establishment->telephone): ?>
            <cac:Contact>
                <?php if($establishment->telephone): ?>
                <cbc:Telephone><?php echo e($establishment->telephone); ?></cbc:Telephone>
                <?php endif; ?>
                <?php if($establishment->email): ?>
                <cbc:ElectronicMail><?php echo e($establishment->email); ?></cbc:ElectronicMail>
                <?php endif; ?>
            </cac:Contact>
            <?php endif; ?>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="<?php echo e($customer->identity_document_type_id); ?>"><?php echo e($customer->number); ?></cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName><![CDATA[<?php echo e($document_xml_service->getTextWithoutEspecialCharacter($customer->name)); ?>]]></cbc:RegistrationName>
                <?php if($customer->address && $customer->address !== '-'): ?>
                <cac:RegistrationAddress>
                    <?php if($customer->district_id): ?>
                    <cbc:ID><?php echo e($customer->district_id); ?></cbc:ID>
                    <?php endif; ?>
                    <cac:AddressLine>
                        <cbc:Line><![CDATA[<?php echo e($customer->address); ?>]]></cbc:Line>
                    </cac:AddressLine>
                    <cac:Country>
                        <cbc:IdentificationCode><?php echo e($customer->country_id); ?></cbc:IdentificationCode>
                    </cac:Country>
                </cac:RegistrationAddress>
                <?php endif; ?>
            </cac:PartyLegalEntity>
            <?php if($customer->email || $customer->telephone): ?>
            <cac:Contact>
                <?php if($customer->telephone): ?>
                <cbc:Telephone><?php echo e($customer->telephone); ?></cbc:Telephone>
                <?php endif; ?>
                <?php if($customer->email): ?>
                <cbc:ElectronicMail><?php echo e($customer->email); ?></cbc:ElectronicMail>
                <?php endif; ?>
            </cac:Contact>
            <?php endif; ?>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <?php if($document->detraction): ?>
        <?php ($detraction = $document->detraction); ?>
        <cac:PaymentMeans>
            <cbc:ID>Detraccion</cbc:ID>
            <cbc:PaymentMeansCode><?php echo e($detraction->payment_method_id); ?></cbc:PaymentMeansCode>
            <cac:PayeeFinancialAccount>
                <cbc:ID><?php echo e($detraction->bank_account); ?></cbc:ID>
            </cac:PayeeFinancialAccount>
        </cac:PaymentMeans>
        <cac:PaymentTerms>
            <cbc:ID>Detraccion</cbc:ID>
            <cbc:PaymentMeansID><?php echo e($detraction->detraction_type_id); ?></cbc:PaymentMeansID>
            <cbc:PaymentPercent><?php echo e($detraction->percentage); ?></cbc:PaymentPercent>
            <cbc:Amount currencyID="PEN"><?php echo e($detraction->amount); ?></cbc:Amount>
        </cac:PaymentTerms>
    <?php endif; ?>
    <?php if($document->payment_condition_id === '01'): ?>
    <cac:PaymentTerms>
        <cbc:ID>FormaPago</cbc:ID>
        <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>
    </cac:PaymentTerms>
    <?php endif; ?>
    <?php if($document->payment_condition_id === '02'): ?>
    <cac:PaymentTerms>
        <cbc:ID>FormaPago</cbc:ID>
        <cbc:PaymentMeansID>Credito</cbc:PaymentMeansID>
        <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->fee()->sum('amount')); ?></cbc:Amount>
    </cac:PaymentTerms>
    <?php $__currentLoopData = $document->fee; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <cac:PaymentTerms>
            <cbc:ID>FormaPago</cbc:ID>
            <cbc:PaymentMeansID>Cuota<?php echo e(sprintf("%03d", $loop->iteration)); ?></cbc:PaymentMeansID>
            <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($fee->amount); ?></cbc:Amount>
            <cbc:PaymentDueDate><?php echo e($fee->date->format('Y-m-d')); ?></cbc:PaymentDueDate>
        </cac:PaymentTerms>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if($document->perception): ?>
    <?php ($perception = $document->perception); ?>
    <cac:PaymentTerms>
        <cbc:ID>Percepcion</cbc:ID>
        <cbc:Amount currencyID="PEN"><?php echo e($perception->amount); ?></cbc:Amount>
    </cac:PaymentTerms>
    <?php endif; ?>
    <?php if($document->prepayments): ?>
    <?php $__currentLoopData = $document->prepayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prepayment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cac:PrepaidPayment>
        <cbc:ID><?php echo e($loop->iteration); ?></cbc:ID>
        <cbc:PaidAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($prepayment->total); ?></cbc:PaidAmount>
    </cac:PrepaidPayment>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if($document->charges): ?>
    <?php $__currentLoopData = $document->charges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $charge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cac:AllowanceCharge>
        <cbc:ChargeIndicator>true</cbc:ChargeIndicator>
        <cbc:AllowanceChargeReasonCode><?php echo e($charge->charge_type_id); ?></cbc:AllowanceChargeReasonCode>
        <cbc:MultiplierFactorNumeric><?php echo e($document->generalApplyNumberFormat($charge->factor, 5)); ?></cbc:MultiplierFactorNumeric>
        
        <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->generalApplyNumberFormat($charge->amount)); ?></cbc:Amount>
        <cbc:BaseAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->generalApplyNumberFormat($charge->base)); ?></cbc:BaseAmount>
    </cac:AllowanceCharge>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if($document->discounts): ?>
    <?php $__currentLoopData = $document->discounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $discount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cac:AllowanceCharge>
        <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
        <cbc:AllowanceChargeReasonCode><?php echo e($discount->discount_type_id); ?></cbc:AllowanceChargeReasonCode>
        <cbc:MultiplierFactorNumeric><?php echo e($discount->factor); ?></cbc:MultiplierFactorNumeric>
        <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($discount->amount); ?></cbc:Amount>
        <cbc:BaseAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($discount->base); ?></cbc:BaseAmount>
    </cac:AllowanceCharge>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if($document->perception): ?>
    <?php ($perception = $document->perception); ?>
    <cac:AllowanceCharge>
        <cbc:ChargeIndicator>true</cbc:ChargeIndicator>
        <cbc:AllowanceChargeReasonCode><?php echo e($perception->code); ?></cbc:AllowanceChargeReasonCode>
        <cbc:MultiplierFactorNumeric><?php echo e($perception->percentage); ?></cbc:MultiplierFactorNumeric>
        <cbc:Amount currencyID="PEN"><?php echo e($perception->amount); ?></cbc:Amount>
        <cbc:BaseAmount currencyID="PEN"><?php echo e($perception->base); ?></cbc:BaseAmount>
    </cac:AllowanceCharge>
    <?php endif; ?>
    <?php if($document->retention): ?>
    <?php ($retention = $document->retention); ?>
    <cac:AllowanceCharge>
        <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
        <cbc:AllowanceChargeReasonCode><?php echo e($retention->code); ?></cbc:AllowanceChargeReasonCode>
        <cbc:MultiplierFactorNumeric><?php echo e($retention->percentage); ?></cbc:MultiplierFactorNumeric>
        <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($retention->amount); ?></cbc:Amount>
        <cbc:BaseAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($retention->base); ?></cbc:BaseAmount>
    </cac:AllowanceCharge>
    <?php endif; ?>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_taxes); ?></cbc:TaxAmount>
        <?php if($document->total_isc > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_base_isc); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_isc); ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>2000</cbc:ID>
                    <cbc:Name>ISC</cbc:Name>
                    <cbc:TaxTypeCode>EXC</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_taxed > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_taxed); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_igv); ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>1000</cbc:ID>
                    <cbc:Name>IGV</cbc:Name>
                    <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php elseif(collect($document->prepayments)->count() > 0 && collect($document->discounts)->where('discount_type_id', '04')->count() === 1 && $document->total_taxed >= 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_taxed); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_igv); ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>1000</cbc:ID>
                    <cbc:Name>IGV</cbc:Name>
                    <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_unaffected > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_unaffected); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>">0.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9998</cbc:ID>
                    <cbc:Name>INA</cbc:Name>
                    <cbc:TaxTypeCode>FRE</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php elseif(collect($document->prepayments)->count() > 0 && collect($document->discounts)->where('discount_type_id', '06')->count() === 1 && $document->total_unaffected >= 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_unaffected); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>">0.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9998</cbc:ID>
                    <cbc:Name>INA</cbc:Name>
                    <cbc:TaxTypeCode>FRE</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_exonerated > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_exonerated); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>">0.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9997</cbc:ID>
                    <cbc:Name>EXO</cbc:Name>
                    <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php elseif(collect($document->prepayments)->count() > 0 && collect($document->discounts)->where('discount_type_id', '05')->count() === 1 && $document->total_exonerated >= 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_exonerated); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>">0.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9997</cbc:ID>
                    <cbc:Name>EXO</cbc:Name>
                    <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_free > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_free); ?></cbc:TaxableAmount>
            
            
            
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_igv_free); ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9996</cbc:ID>
                    <cbc:Name>GRA</cbc:Name>
                    <cbc:TaxTypeCode>FRE</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_exportation > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_exportation); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>">0.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9995</cbc:ID>
                    <cbc:Name>EXP</cbc:Name>
                    <cbc:TaxTypeCode>FRE</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_other_taxes > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_other_taxes); ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_base_other_taxes); ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>9999</cbc:ID>
                    <cbc:Name>OTROS</cbc:Name>
                    <cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
        <?php if($document->total_plastic_bag_taxes > 0): ?>
        <cac:TaxSubtotal>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_plastic_bag_taxes); ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme>
                    <cbc:ID>7152</cbc:ID>
                    <cbc:Name>ICBPER</cbc:Name>
                    <cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endif; ?>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_value); ?></cbc:LineExtensionAmount>
        
        <?php if($tot_charges > 0): ?>
        <cbc:TaxInclusiveAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total - $tot_charges); ?></cbc:TaxInclusiveAmount>
        <?php else: ?>
        <cbc:TaxInclusiveAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->subtotal); ?></cbc:TaxInclusiveAmount>
        <?php endif; ?>
        
        <?php if($total_discount_no_base > 0): ?>
        <cbc:AllowanceTotalAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($total_discount_no_base); ?></cbc:AllowanceTotalAmount>
        <?php endif; ?>
        <?php if($document->total_charge > 0): ?>
        <cbc:ChargeTotalAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_charge); ?></cbc:ChargeTotalAmount>
        <?php endif; ?>
        <?php if($document->total_prepayment > 0): ?>
        <cbc:PrepaidAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total_prepayment); ?></cbc:PrepaidAmount>
        <?php endif; ?>
        
        
        <cbc:PayableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($document->total); ?></cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <?php $__currentLoopData = $document->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <cac:InvoiceLine>
        <cbc:ID><?php echo e($loop->iteration); ?></cbc:ID>
        <cbc:InvoicedQuantity unitCode="<?php echo e($row->item->unit_type_id); ?>"><?php echo e($row->quantity); ?></cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_value); ?></cbc:LineExtensionAmount>
        <cac:PricingReference>
            <cac:AlternativeConditionPrice>
                <cbc:PriceAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->unit_price); ?></cbc:PriceAmount>
                <cbc:PriceTypeCode><?php echo e($row->price_type_id); ?></cbc:PriceTypeCode>
            </cac:AlternativeConditionPrice>
        </cac:PricingReference>
        <?php if($document->detraction && $invoice->operation_type_id == '1004'): ?>
        <cac:Delivery>
            <cac:DeliveryLocation>
                <cac:Address>
                    <cbc:ID><?php echo e($document->detraction->delivery_location_id[2]); ?></cbc:ID>
                    <cac:AddressLine>
                        <cbc:Line><?php echo e($document->detraction->delivery_address); ?></cbc:Line>
                    </cac:AddressLine>
                </cac:Address>
            </cac:DeliveryLocation>
            <cac:Despatch>
                <cbc:Instructions><?php echo e($document->detraction->trip_detail); ?></cbc:Instructions>
                <cac:DespatchAddress>
                    <cbc:ID><?php echo e($document->detraction->origin_location_id[2]); ?></cbc:ID>
                    <cac:AddressLine>
                        <cbc:Line><?php echo e($document->detraction->origin_address); ?></cbc:Line>
                    </cac:AddressLine>
                </cac:DespatchAddress>
            </cac:Despatch>
            <cac:DeliveryTerms>
                <cbc:ID>01</cbc:ID>
                <cbc:Amount currencyID="PEN"><?php echo e($document->detraction->reference_value_service); ?></cbc:Amount>
            </cac:DeliveryTerms>
            <cac:DeliveryTerms>
                <cbc:ID>02</cbc:ID>
                <cbc:Amount currencyID="PEN"><?php echo e($document->detraction->reference_value_effective_load); ?></cbc:Amount>
            </cac:DeliveryTerms>
            <cac:DeliveryTerms>
                <cbc:ID>03</cbc:ID>
                <cbc:Amount currencyID="PEN"><?php echo e($document->detraction->reference_value_payload); ?></cbc:Amount>
            </cac:DeliveryTerms>
        </cac:Delivery>
        <?php endif; ?>
        <?php if($row->charges): ?>
        <?php $__currentLoopData = $row->charges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $charge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <cac:AllowanceCharge>
            <cbc:ChargeIndicator>true</cbc:ChargeIndicator>
            <cbc:AllowanceChargeReasonCode><?php echo e($charge->charge_type_id); ?></cbc:AllowanceChargeReasonCode>
            <cbc:MultiplierFactorNumeric><?php echo e($charge->factor); ?></cbc:MultiplierFactorNumeric>
            <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($charge->amount); ?></cbc:Amount>
            <cbc:BaseAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($charge->base); ?></cbc:BaseAmount>
        </cac:AllowanceCharge>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
        <?php if($row->discounts): ?>
        <?php $__currentLoopData = $row->discounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $discount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <cac:AllowanceCharge>
            <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
            <cbc:AllowanceChargeReasonCode><?php echo e($discount->discount_type_id); ?></cbc:AllowanceChargeReasonCode>
            <cbc:MultiplierFactorNumeric><?php echo e($discount->factor); ?></cbc:MultiplierFactorNumeric>
            <cbc:Amount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($discount->amount); ?></cbc:Amount>
            <cbc:BaseAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($discount->base); ?></cbc:BaseAmount>
        </cac:AllowanceCharge>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_taxes); ?></cbc:TaxAmount>
            <?php if($row->total_isc > 0): ?>
            <cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_base_isc); ?></cbc:TaxableAmount>
                <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_isc); ?></cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:Percent><?php echo e($row->percentage_isc); ?></cbc:Percent>
                    <cbc:TierRange><?php echo e($row->system_isc_type_id); ?></cbc:TierRange>
                    <cac:TaxScheme>
                        <cbc:ID>2000</cbc:ID>
                        <cbc:Name>ISC</cbc:Name>
                        <cbc:TaxTypeCode>EXC</cbc:TaxTypeCode>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
            <?php endif; ?>
            <cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_base_igv); ?></cbc:TaxableAmount>
                <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_igv); ?></cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:Percent><?php echo e($row->percentage_igv); ?></cbc:Percent>
                    <cbc:TaxExemptionReasonCode><?php echo e($row->affectation_igv_type_id); ?></cbc:TaxExemptionReasonCode>
                    <?php ($affectation = \App\CoreFacturalo\Templates\FunctionTribute::getByAffectation($row->affectation_igv_type_id)); ?>
                    <cac:TaxScheme>
                        <cbc:ID><?php echo e($affectation['id']); ?></cbc:ID>
                        <cbc:Name><?php echo e($affectation['name']); ?></cbc:Name>
                        <cbc:TaxTypeCode><?php echo e($affectation['code']); ?></cbc:TaxTypeCode>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
            <?php if($row->total_other_taxes > 0): ?>
            <cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_base_other_taxes); ?></cbc:TaxableAmount>
                <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_other_taxes); ?></cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:Percent><?php echo e($row->percentage_other_taxes); ?></cbc:Percent>
                    <cac:TaxScheme>
                        <cbc:ID>9999</cbc:ID>
                        <cbc:Name>OTROS</cbc:Name>
                        <cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
            <?php endif; ?>
            <?php if($row->total_plastic_bag_taxes > 0): ?>
            <cac:TaxSubtotal>
                <cbc:TaxAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->total_plastic_bag_taxes); ?></cbc:TaxAmount>
                <cbc:BaseUnitMeasure unitCode="NIU"><?php echo e(round($row->quantity,0)); ?></cbc:BaseUnitMeasure>
                <cac:TaxCategory>
                    <cbc:PerUnitAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->item->amount_plastic_bag_taxes); ?></cbc:PerUnitAmount>
                    <cac:TaxScheme>
                        <cbc:ID>7152</cbc:ID>
                        <cbc:Name>ICBPER</cbc:Name>
                        <cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
            <?php endif; ?>
        </cac:TaxTotal>
        <cac:Item>
            <?php if($row->name_product_xml): ?>
            <cbc:Description><![CDATA[<?php echo e($row->name_product_xml); ?>]]></cbc:Description>
            <?php else: ?>
            <cbc:Description><![CDATA[<?php echo e($row->item->description); ?>]]></cbc:Description>
            <?php endif; ?>
            <?php if($row->item->internal_id): ?>
            <cac:SellersItemIdentification>
                <cbc:ID><?php echo e($row->item->internal_id); ?></cbc:ID>
            </cac:SellersItemIdentification>
            <?php endif; ?>
            <?php if($row->item->item_code): ?>
            <cac:CommodityClassification>
                <cbc:ItemClassificationCode><?php echo e($row->item->item_code); ?></cbc:ItemClassificationCode>
            </cac:CommodityClassification>
            <?php endif; ?>
            <?php if($row->item->item_code_gs1): ?>
            <cac:StandardItemIdentification>
                <cbc:ID><?php echo e($row->item->item_code_gs1); ?></cbc:ID>
            </cac:StandardItemIdentification>
            <?php endif; ?>
            <?php if($row->attributes): ?>
            <?php $__currentLoopData = $row->attributes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <cac:AdditionalItemProperty>
                <cbc:Name><![CDATA[<?php echo e($attr->description); ?>]]></cbc:Name>
                <cbc:NameCode><?php echo e($attr->attribute_type_id); ?></cbc:NameCode>
                <?php if($attr->value): ?>
                <cbc:Value><?php echo e($attr->value); ?></cbc:Value>
                <?php endif; ?>
                <?php if($attr->start_date || $attr->end_date || $attr->duration): ?>
                <cac:UsabilityPeriod>
                    <?php if($attr->start_date): ?>
                    <cbc:StartDate><?php echo e($attr->start_date); ?></cbc:StartDate>
                    <?php endif; ?>
                    <?php if($attr->end_date): ?>
                    <cbc:EndDate><?php echo e($attr->end_date); ?></cbc:EndDate>
                    <?php endif; ?>
                    <?php if($attr->duration): ?>
                    <cbc:DurationMeasure unitCode="DAY"><?php echo e($attr->duration); ?></cbc:DurationMeasure>
                    <?php endif; ?>
                </cac:UsabilityPeriod>
                <?php endif; ?>
            </cac:AdditionalItemProperty>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="<?php echo e($document->currency_type_id); ?>"><?php echo e($row->unit_value); ?></cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</Invoice>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\app\CoreFacturalo\Templates/xml/invoice.blade.php ENDPATH**/ ?>