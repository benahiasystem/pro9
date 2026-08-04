<?php if($vc_check_last_password_update): ?>

    <?php if($vc_check_last_password_update->enabled_remember_change_password): ?>
        
        <tenant-remember-change-password :configuration-last-password-update="<?php echo e(json_encode($vc_check_last_password_update)); ?>"></tenant-remember-change-password>
    
    <?php endif; ?>
    
<?php endif; ?>
<?php /**PATH C:\laragon\www\Pro9\resources\views/tenant/layouts/partials/check_last_password_update.blade.php ENDPATH**/ ?>