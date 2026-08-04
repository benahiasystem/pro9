<?php
    $path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
?>
<head>
    <link href="<?php echo e($path_style); ?>" rel="stylesheet" />
</head>
<body>
<table class="full-width">
    <tr>
        <td class="text-center desc font-bold">
            <?php if($configurationInPdf->legend_footer_sale): ?>
                <?php echo $configurationInPdf->legend_footer_sale; ?>

            <?php endif; ?>
            
            <?php if(!is_null($document) && !in_array($document->document_type_id, ['09'])): ?>
                Para consultar el comprobante ingresar a <?php echo url('/buscar'); ?>

                <br>
            <?php endif; ?>          
            <?php if($document && $document->document_type): ?>
                Representacion impresa de la <span style="text-transform: capitalize" class="text-capitalize"><?php echo e($document->document_type->description); ?></span>
            <?php endif; ?>
        </td>
    </tr>
</table>
</body>
<?php /**PATH C:\laragon\www\Pro9\app\CoreFacturalo\Templates/pdf/default/partials/footer.blade.php ENDPATH**/ ?>