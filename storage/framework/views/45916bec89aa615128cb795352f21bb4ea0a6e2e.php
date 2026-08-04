<!DOCTYPE html>
<html lang="es">

<!-- Mirrored from portotheme.com/html/porto_ecommerce/demo-6/cart.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 07 Sep 2019 03:40:04 GMT -->
<head>
    <?php ($pageCompany = $company ?? $vc_company ?? null); ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e(data_get($pageCompany, 'title_web') ?: data_get($pageCompany, 'trade_name') ?: 'eCommerce'); ?></title>

    <meta name="keywords" content="eCommerce, <?php echo e(data_get($pageCompany, 'trade_name')); ?>" />
    <meta name="description" content="<?php echo e($ecommerceDescription ?? 'eCommerce'); ?>" />
    <meta name="author" content="SW-THEMES">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo e(data_get($pageCompany, 'title_web') ?: data_get($pageCompany, 'trade_name')); ?>" />
    <meta property="og:description" content="<?php echo e($ecommerceDescription ?? 'eCommerce'); ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo e(url()->current()); ?>" />
    <?php ($headerLogo = data_get($company ?? null, 'logo') ?: data_get($information ?? null, 'logo')); ?>
    <meta property="og:image" content="<?php echo e($headerLogo ? asset('storage/uploads/logos/'.$headerLogo) : asset('logo/tulogo.png')); ?>" />
    <meta property="og:site_name" content="<?php echo e(data_get($pageCompany, 'title_web') ?: data_get($pageCompany, 'trade_name')); ?>" />

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo e(data_get($pageCompany, 'title_web') ?: data_get($pageCompany, 'trade_name')); ?>" />
    <meta name="twitter:description" content="<?php echo e($ecommerceDescription ?? 'eCommerce'); ?>" />
    <meta name="twitter:image" content="<?php echo e($headerLogo ? asset('storage/uploads/logos/'.$headerLogo) : asset('logo/tulogo.png')); ?>" />

    <!-- Schema.org JSON-LD (ItemList para listado de productos) -->
    <?php if(isset($products) && count($products)): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": "Listado de productos",
        "itemListElement": [
            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            {
                "@type": "Product",
                "position": <?php echo e($index + 1); ?>,
                "name": "<?php echo e(addslashes($product->name)); ?>",
                "image": "<?php echo e($product->image_url ?? ($headerLogo ? asset('storage/uploads/logos/'.$headerLogo) : asset('logo/tulogo.png'))); ?>",
                "url": "<?php echo e(route('ecommerce.product.show', $product->slug)); ?>"
            }<?php if(!$loop->last): ?>,<?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        ]
    }
    </script>
    <?php endif; ?>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('porto-ecommerce/assets/images/icons/favicon.svg')); ?>">

    <!-- Plugins CSS File -->
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/bootstrap.min.css')); ?>">

    <!-- Main CSS File -->
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/style.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/custom.css')); ?>">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css')); ?>">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/css/styles_ecommerce.css')); ?>" />
    <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.primary_color_style', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Element UI CSS -->
    <link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">

    <?php echo $__env->yieldPushContent('styles'); ?>

</head>
<body>
    <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.announcement_bar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <div class="page-wrapper">
        <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.header_bottom_sticky', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <main class="main">
            <nav aria-label="breadcrumb" class="breadcrumb-nav">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo e(url('ecommerce')); ?>"><i class="icon-home"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
                    </ol>
                </div><!-- End .container -->
            </nav>

            <div class="container">
                 <?php echo $__env->yieldContent('content'); ?>
            </div><!-- End .container -->

            <div class="mb-6"></div><!-- margin -->
        </main><!-- End .main -->

        <footer class="footer">
            <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </footer><!-- End .footer -->
    </div><!-- End .page-wrapper -->

    <div class="mobile-menu-overlay"></div><!-- End .mobil-menu-overlay -->

    <div class="mobile-menu-container">
        <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.mobile_menu', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div><!-- End .mobile-menu-container -->



    <a id="scroll-top" href="#top" title="Top" role="button"><i class="icon-angle-up"></i></a>

     <!-- Plugins JS File -->
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/jquery.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/plugins.min.js')); ?>"></script>
    <script src="https://checkout.culqi.com/js/v4"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/sweetalert2.all.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/moment.min.js')); ?>"></script>

    <!-- Main JS File -->
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/main.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/vue.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/axios.min.js')); ?>"></script>

    <!-- Element UI JavaScript -->
    <script src="https://unpkg.com/element-ui/lib/index.js"></script>
    <!-- Element UI Spanish Locale -->
    <script src="https://unpkg.com/element-ui/lib/umd/locale/es.js"></script>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>

<!-- Mirrored from portotheme.com/html/porto_ecommerce/demo-6/cart.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 07 Sep 2019 03:40:04 GMT -->
</html>
<?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/layout_ecommerce_cart/index.blade.php ENDPATH**/ ?>