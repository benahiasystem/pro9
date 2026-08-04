<div class="header-bottom sticky-header">
    <div class="container d-flex">
        <nav class="main-nav flex-grow-1">
            <ul class="menu sf-arrows">

                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><a href="<?php echo e(route("tenant.ecommerce.category", ['category' => $item->id])); ?>"><?php echo e($item->name); ?></a></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            </ul>
        </nav>
    </div><!-- End .header-bottom -->
</div><!-- End .header-bottom -->
<?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/partials_ecommerce/header_bottom_sticky.blade.php ENDPATH**/ ?>