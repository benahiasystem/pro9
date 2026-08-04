<?php
    // Filtrar solo los spots que tienen imagen
    $spotsArray = isset($spots) ? $spots->toArray() : [];
    $spotsArray = array_filter($spotsArray, function($spot) {
        return $spot && !empty($spot['image_url']);
    });
?>

<?php if(count($spotsArray) > 0): ?>
<div class="container offers">
    <div class="row d-flex justify-content-center">
        <?php $__currentLoopData = array_slice($spotsArray, 0, 2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $spot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-6 mb-3 image-offers-container">
                <?php if(!empty($spot['spot_url'])): ?>
                    <a href="<?php echo e($spot['spot_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <img class="image-offers" src="<?php echo e($spot['image_url']); ?>" alt="Anuncio <?php echo e($index + 1); ?>" width="100%"/>
                    </a>
                <?php else: ?>
                    <img class="image-offers" src="<?php echo e($spot['image_url']); ?>" alt="Anuncio <?php echo e($index + 1); ?>" width="100%"/>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php if(count($spotsArray) > 2): ?>
    <div class="row d-flex justify-content-center">
        <?php $__currentLoopData = array_slice($spotsArray, 2, 2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $spot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-6 mb-3 image-offers-container">
                <?php if(!empty($spot['spot_url'])): ?>
                    <a href="<?php echo e($spot['spot_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <img class="image-offers" src="<?php echo e($spot['image_url']); ?>" alt="Anuncio <?php echo e($index + 3); ?>" width="100%"/>
                    </a>
                <?php else: ?>
                    <img class="image-offers" src="<?php echo e($spot['image_url']); ?>" alt="Anuncio <?php echo e($index + 3); ?>" width="100%"/>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?><?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/partials_ecommerce/offers.blade.php ENDPATH**/ ?>