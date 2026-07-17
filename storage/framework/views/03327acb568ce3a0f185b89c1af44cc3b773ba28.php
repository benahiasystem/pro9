

<?php $__env->startPush('styles'); ?>
    <style type="text/css">
        .v-modal {
            opacity: 0.2 !important;
        }
        .border-custom {
            border-color: rgba(0,136,204, .5) !important;
        }
        @media only screen and (min-width: 768px) {
        	.inner-wrapper {
			    padding-top: 60px !important;
			}
        }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <tenant-documents-invoice-generate
        :is_contingency="<?php echo e(json_encode($is_contingency)); ?>"
        :type-user="<?php echo e(json_encode(Auth::user()->type)); ?>"
        :auth-user="<?php echo e(json_encode(Auth::user()->getDataOnlyAuthUser())); ?>"
        :configuration="<?php echo e(\App\Models\Tenant\Configuration::getPublicConfig()); ?>"
        :document-id="<?php echo e($documentId ?? 0); ?>"
        :is-update="<?php echo e(json_encode($isUpdate ?? false)); ?>"
        :table="<?php echo e(json_encode($table ?? null)); ?>"
        :table-id="<?php echo e(json_encode($table_id ?? null)); ?>"
        :id-user="<?php echo e(json_encode(Auth::user()->id)); ?>"></tenant-documents-invoice-generate>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('tenant.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/documents/form.blade.php ENDPATH**/ ?>