<article class="auth__image<?php echo e(!$useLoginGlobal ? ' d-none' : ''); ?>" style="padding: <?php echo e(($login->padding_in_form ?? false) ? '0' : '2.5%'); ?>; display: flex; justify-content: center; align-items: center; overflow: hidden; background-color: <?php echo e($loginBgColor ?? '#ffffff'); ?>;">
    <img 
        src="<?php echo e($login->image); ?>" 
        alt="Background Image" 
        style="width: 100%; height: 100%; object-fit: <?php echo e(($login->padding_in_form ?? false) ? 'cover' : 'contain'); ?>" 
    />
    <?php if($useLoginGlobal): ?>
        <?php if($login->logo ?? false): ?>
            <?php if($login->position_logo != 'none' && $login->position_logo != 'on-form'): ?>
                <img class="auth__logo <?php echo e($login->position_logo); ?>" src="<?php echo e($login->logo); ?>" alt="Logo" />
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <?php if($company->logo): ?>
            <?php if($login->position_logo != 'on-form'): ?>
                <img class="auth__logo <?php echo e($login->position_logo); ?>" src="<?php echo e(asset('storage/uploads/logos/' . $company->logo)); ?>" alt="Logo" />
            <?php endif; ?>
        <?php else: ?>
            <?php if($login->position_logo != 'on-form'): ?>
                <img class="auth__logo <?php echo e($login->position_logo); ?>" src="<?php echo e(asset('logo/tulogo.png')); ?>" alt="Logo" />
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</article>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/auth/partials/side_left.blade.php ENDPATH**/ ?>