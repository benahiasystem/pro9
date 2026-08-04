

<?php $__env->startSection('content'); ?>
<div class="page-header pr-0">
    <h2><a href="/ecommerce/configuration">
    <i class="fas fa-cogs"></i>
    </a></h2>
    <ol class="breadcrumbs">
        <li class="active"><span> Configuración </span></li>
    </ol>
</div>
<div class="row tab-content-default row-new bg-transparent mt-1 row-mx-0" style="background: transparent !important;">
    <tenant-ecommerce-configuration-info></tenant-ecommerce-configuration-info>
    <!-- <tenant-ecommerce-configuration-culqi></tenant-ecommerce-configuration-culqi> -->
    <!-- <tenant-ecommerce-configuration-paypal></tenant-ecommerce-configuration-paypal> -->

    <!-- <tenant-ecommerce-configuration-logo></tenant-ecommerce-configuration-logo> -->
    <!-- <tenant-ecommerce-configuration-social></tenant-ecommerce-configuration-social> -->
    <!-- <tenant-ecommerce-configuration-tag></tenant-ecommerce-configuration-tag> -->
    <!-- <tenant-ecommerce-configuration-links></tenant-ecommerce-configuration-links> -->
    <!-- <tenant-ecommerce-configuration-color></tenant-ecommerce-configuration-color> -->
    <tenant-ecommerce-other-settings></tenant-ecommerce-other-settings>

</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/configuration/index.blade.php ENDPATH**/ ?>