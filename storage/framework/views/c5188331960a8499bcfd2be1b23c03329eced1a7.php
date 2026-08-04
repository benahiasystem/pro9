<?php
    // $tagid = request()->query('tagid');
    $tagid = Request::segment(3);
    $catetgory_segment = strtolower(Request::segment(2));
?>
<div class="mobile-menu-wrapper">
    <span class="mobile-menu-close"><i class="icon-cancel"></i></span>
    <nav class="mobile-nav">
        <ul class="mobile-menu">
            <li class="<?php echo e((!$tagid) ? 'active':''); ?>"><a href="<?php echo e(route("tenant.ecommerce.index")); ?>">Home</a></li>
            <li class="<?php echo e(($catetgory_segment) && ($catetgory_segment == 'category') ? 'active':''); ?>">
                <a href="#">Categorias</a>
                <ul>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="<?php echo e(($tagid == $item->id) ? 'active':''); ?>">
                            <a href="<?php echo e(route("tenant.ecommerce.category", ['category' => $item->id])); ?>">
                                <?php echo e($item->name); ?>

                            </a>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </li>
            
            
            
            <li><a href="<?php echo e(route('tenant_detail_cart')); ?>">Ver carrito</a></li>
            
        </ul>
    </nav><!-- End .mobile-nav -->

    <div class="social-icons">
        <?php if($information->link_facebook): ?>
            <a href="<?php echo e($information->link_facebook); ?>" class="social-icon" target="_blank"><i class="icon-facebook"></i></a>
        <?php endif; ?>

        <?php if($information->link_twitter): ?>
            <a href="<?php echo e($information->link_twitter); ?>" class="social-icon" target="_blank"><i class="icon-twitter"></i></a>
        <?php endif; ?>

        <?php if($information->link_youtube): ?>
            <a href="<?php echo e($information->link_youtube); ?>" class="social-icon" target="_blank"><i class="fab fa-youtube"></i></a>
        <?php endif; ?>
    </div><!-- End .social-icons -->
</div><!-- End .mobile-menu-wrapper -->
<?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/partials_ecommerce/mobile_menu.blade.php ENDPATH**/ ?>