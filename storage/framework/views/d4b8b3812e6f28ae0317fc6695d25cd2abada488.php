

<?php $__env->startSection('content'); ?>

    <tenant-quotations-index
    	:type-user="<?php echo e(json_encode(Auth::user()->type)); ?>"
    	:soap-company="<?php echo e(json_encode($soap_company)); ?>"
    	:generate-order-note-from-quotation="<?php echo e(json_encode($generate_order_note_from_quotation)); ?>">
    </tenant-quotations-index>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\Pro9\resources\views/tenant/quotations/index.blade.php ENDPATH**/ ?>