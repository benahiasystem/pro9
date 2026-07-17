<?php
    use App\CoreFacturalo\Helpers\CompanyDocumentDisplay as CompanyDocHeader;
    $tp = $tagPrimary ?? 'h4';
    $tl = $tagLegal ?? 'h5';
    $primary = CompanyDocHeader::commercialLine($company);
    $legal = CompanyDocHeader::legalLine($company);
    $legalInlineStyle = $legalLineStyle ?? 'font-weight:400;font-size:0.95em;margin:0.1rem 0 0 0;line-height:1.25;';
?>
<<?php echo e($tp); ?><?php if(!empty($primaryClass)): ?> class="<?php echo e($primaryClass); ?>"<?php endif; ?> <?php if(!empty($primaryStyle)): ?> style="<?php echo e($primaryStyle); ?>"<?php endif; ?>><?php echo e($primary); ?></<?php echo e($tp); ?>>
<?php if($legal): ?>
<<?php echo e($tl); ?><?php if(!empty($legalClass)): ?> class="<?php echo e($legalClass); ?>"<?php endif; ?> style="<?php echo e($legalInlineStyle); ?>"><?php echo e($legal); ?></<?php echo e($tl); ?>>
<?php endif; ?>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\app\CoreFacturalo\Templates/pdf/partials/company_document_header_names.blade.php ENDPATH**/ ?>