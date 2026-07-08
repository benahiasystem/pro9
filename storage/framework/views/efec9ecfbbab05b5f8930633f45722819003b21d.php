

<?php $__env->startSection('code', '404'); ?>
<?php $__env->startSection('title', __('Page Not Found')); ?>

<?php $__env->startSection('image'); ?>
<div style="background-image: url(<?php echo e(asset('/svg/404.svg')); ?>);" class="absolute pin bg-cover bg-no-repeat md:bg-left lg:bg-center">
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('message', __('Lo sentimos, no se pudo encontrar la página que estás buscando.')); ?>

<?php echo $__env->make('errors::illustrated-layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/errors/404.blade.php ENDPATH**/ ?>