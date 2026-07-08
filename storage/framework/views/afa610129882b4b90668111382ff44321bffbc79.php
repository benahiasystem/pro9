

<?php $__env->startSection('content'); ?>

    <system-clients-index :delete-permission="<?php echo e(json_encode($delete_permission)); ?>"
                          :disc-used="<?php echo e(json_encode($disc_used)); ?>"
                          :i-used="<?php echo e(json_encode($i_used)); ?>"
                          :storage-size="<?php echo e(json_encode($storage_size)); ?>"
                          :version="<?php echo e(json_encode($version)); ?>"
                          :can-create-clients="<?php echo json_encode(auth('admin')->user()->canCreateClients(), 15, 512) ?>"></system-clients-index>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('system.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/system/dashboard.blade.php ENDPATH**/ ?>