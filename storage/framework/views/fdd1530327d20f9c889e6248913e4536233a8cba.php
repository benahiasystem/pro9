

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row">
        <div class="col-lg-12 ecommerce-view" style="<?php echo e(isset($full_width_banner) && $full_width_banner ? 'padding-top: 60px' : 'padding-top: 8rem'); ?>">
            <?php
                $tagid = Request::segment(3);
            ?>

            <?php if(!$tagid && !isset($category)): ?>
                <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.home_slider', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?>
            <?php if(isset($category)): ?>
                <div class="row">
                    <div class="col-12 text-center py-5">
                        
                        <h1 class="title-category text-uppercase" style="font-size: 2.5rem; letter-spacing: 2px;">
                            <?php echo e($category->name); ?>

                        </h1>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row py-4 mx-0">
                <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.categories', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>
                
            <div class="row py-4">
                <div class="container d-flex justify-content-between align-items-center">
                    <h1 class="title-category m-0">Explora nuestros productos</h1>

                    <div class="filter-sort">
                        <select class="" onchange="location = this.value;" style="width: 200px;">
                            <option value="<?php echo e(request()->fullUrlWithQuery(['order' => 'name_asc'])); ?>" <?php echo e(request('order') == 'name_asc' ? 'selected' : ''); ?>>Nombre: A - Z</option>
                            <option value="<?php echo e(request()->fullUrlWithQuery(['order' => 'name_desc'])); ?>" <?php echo e(request('order') == 'name_desc' ? 'selected' : ''); ?>>Nombre: Z - A</option>
                            <option value="<?php echo e(request()->fullUrlWithQuery(['order' => 'price_asc'])); ?>" <?php echo e(request('order') == 'price_asc' ? 'selected' : ''); ?>>Precio: Menor a Mayor</option>
                            <option value="<?php echo e(request()->fullUrlWithQuery(['order' => 'price_desc'])); ?>" <?php echo e(request('order') == 'price_desc' ? 'selected' : ''); ?>>Precio: Mayor a Menor</option>
                        </select>
                    </div>
                </div>
            </div>
                <div class="row row-sm">
                <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.list_products', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>
            <div class="row row-sm mt-0">
            </div>
            <div class="row page-pagination mt-2">
              <div class="col-md-12 col-lg-12 d-flex justify-content-end mb-4">
                <?php echo e($dataPaginate->onEachSide(1)->links('restaurant::layouts.partials.pagination')); ?>

              </div>
            </div>
            <div class="row">
                <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.offers', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('ecommerce::layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/index.blade.php ENDPATH**/ ?>