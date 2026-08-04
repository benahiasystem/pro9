

<?php $__env->startSection('content'); ?>
    <tenant-orders-index :user="<?php echo e(json_encode(auth()->user())); ?>"></tenant-orders-index>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\Pro9\resources\views/tenant/orders/index.blade.php ENDPATH**/ ?>