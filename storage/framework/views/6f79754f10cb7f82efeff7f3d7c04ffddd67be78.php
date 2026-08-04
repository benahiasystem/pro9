<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php
        $pageCompany = $company ?? $vc_company ?? null;
        $titleWeb = data_get($pageCompany, 'title_web') ?: data_get($pageCompany, 'trade_name') ?: 'eCommerce';
        $headerLogo = data_get($company ?? null, 'logo') ?: data_get($information ?? null, 'logo');
        $ecommerceDescription = $ecommerceDescription ?? 'eCommerce';
    ?>
    <title><?php echo e($titleWeb); ?></title>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="keywords" content="ecommerce, <?php echo e($titleWeb); ?>" />
    <meta name="description" content="<?php echo e($ecommerceDescription); ?>" />
    
    <meta property="og:title" content="<?php echo e($titleWeb); ?>" />
    <meta property="og:description" content="<?php echo e($ecommerceDescription); ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo e(url()->current()); ?>" />
    <meta property="og:image" content="<?php echo e($headerLogo ? asset('storage/uploads/logos/'.$headerLogo) : asset('logo/tulogo.png')); ?>" />
    
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('porto-ecommerce/assets/images/icons/favicon.svg')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/bootstrap.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/style.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/custom.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/css/rating.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/css/styles_ecommerce.css')); ?>" />
    <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.primary_color_style', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</head>

<body data-company-title="<?php echo e(data_get($pageCompany, 'title_web') ?: data_get($pageCompany, 'trade_name')); ?>">

    <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.announcement_bar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php
        
        $configurationModel = \App\Models\Tenant\Configuration::first();
        $ecommerceConfiguration = \App\Models\Tenant\ConfigurationEcommerce::first();
        $phoneWhatsapp = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
        $showWhatsapp = ($configurationModel && ($configurationModel->enable_whatsapp ?? false)) && !empty($phoneWhatsapp);
        $waPhone = $phoneWhatsapp ? preg_replace('/\D+/', '', $phoneWhatsapp) : '';
        $waText = rawurlencode('Hola, tengo una consulta desde la tienda online');
        $waLink = $waPhone ? "https://wa.me/{$waPhone}?text={$waText}" : '';
    ?>

    <div class="page-wrapper">
        <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <main class="main">
            <?php echo $__env->yieldContent('content'); ?>
        </main>
        <footer class="footer">
            <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </footer>
    </div>
    <div class="mobile-menu-overlay"></div>
    <div class="mobile-menu-container">
        <?php echo $__env->make('ecommerce::layouts.partials_ecommerce.mobile_menu', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>
    <a id="scroll-top" href="#top" title="Top" role="button"><i class="icon-angle-up"></i></a>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/jquery.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/plugins.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/cart.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/main.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-ecommerce/assets/js/vue.min.js')); ?>"></script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/master.blade.php ENDPATH**/ ?>