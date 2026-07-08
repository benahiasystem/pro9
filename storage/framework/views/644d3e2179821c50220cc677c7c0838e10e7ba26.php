

<?php $__env->startSection('content'); ?>

    <div class="row">
        <!--<div class="col-lg-6 col-md-12 pt-2 pt-md-0">
            <system-companies-form></system-companies-form>
        </div> -->
        <div class="col-6">

            <system-php-configuration
                :memory_bytes="'<?php echo $memory_in_byte; ?>'"
                :memory_write="'<?php echo $memory_limit; ?>'"
                :backtrack_limit="'<?php echo $pcre_backtrack_limit; ?>'"
                :all_config="<?php echo e(json_encode($all_config)); ?>"
            ></system-php-configuration>
        </div>
        <div class="col-6">
            <system-server-status></system-server-status>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('system.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/system/configuration/info.blade.php ENDPATH**/ ?>