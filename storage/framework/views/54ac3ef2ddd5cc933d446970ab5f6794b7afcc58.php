

<?php $__env->startSection('content'); ?>
    <tenant-items-index
        type="<?php echo e($type ?? ''); ?>"
        :configuration="<?php echo e(\App\Models\Tenant\Configuration::first()->toJson()); ?>"
        :type-user="<?php echo e(json_encode(Auth::user()->type)); ?>"
    ></tenant-items-index>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/items/index.blade.php ENDPATH**/ ?>