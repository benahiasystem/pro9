

<?php $__env->startSection('content'); ?>

    <tenant-persons-index 
        :type-user="<?php echo e(json_encode(Auth::user()->type)); ?>" 
        :type="<?php echo e(json_encode($type)); ?>"
        :configuration="<?php echo e(\App\Models\Tenant\Configuration::getPublicConfig()); ?>"
    ></tenant-persons-index>
    

<?php $__env->stopSection(); ?>

<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/persons/index.blade.php ENDPATH**/ ?>