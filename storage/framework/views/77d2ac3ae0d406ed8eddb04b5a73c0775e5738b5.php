

<?php $__env->startSection('content'); ?>

    <tenant-documents-index :is-client="<?php echo e(json_encode($is_client)); ?>"
                            :type-user="<?php echo e(json_encode(auth()->user()->type)); ?>"
                            :import_documents="<?php echo e(json_encode($import_documents)); ?>"
                            user-id="<?php echo e(auth()->user()->id); ?>"
                            :user-permission-edit-cpe="<?php echo e(json_encode(auth()->user()->permission_edit_cpe)); ?>"
                            :import_documents_second="<?php echo e(json_encode($import_documents_second)); ?>"
                            :document_import_excel="<?php echo e(json_encode($document_import_excel)); ?>"
                            :configuration="<?php echo e($configuration); ?>"
                            :view_apiperudev_validator_cpe="<?php echo e(json_encode($view_apiperudev_validator_cpe)); ?>"
                            :view_validator_cpe="<?php echo e(json_encode($view_validator_cpe)); ?>"></tenant-documents-index>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script type="text/javascript">
	$(function(){
    'use strict';
        $(".tableScrollTop,.tableWide-wrapper").scroll(function(){
            $(".tableWide-wrapper,.tableScrollTop")
                .scrollLeft($(this).scrollLeft());
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/documents/index.blade.php ENDPATH**/ ?>