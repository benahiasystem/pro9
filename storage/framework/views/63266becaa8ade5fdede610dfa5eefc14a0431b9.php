<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="googlebot" content="noindex">
    <meta name="robots" content="noindex">

    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

    <!-- <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/bootstrap/css/bootstrap.css')); ?>" /> -->
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/animate/animate.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/font-awesome/css/fontawesome-all.min.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/css/theme.css')); ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />
    <link rel="stylesheet" href="<?php echo e(asset('theme/admin_styles.css')); ?>" />

    <?php if(file_exists(public_path('theme/custom_styles.css'))): ?>
        <link rel="stylesheet" href="<?php echo e(asset('theme/custom_styles.css')); ?>" />
    <?php endif; ?>

    <!-- vite aqui -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/system.js']); ?>
</head>

<body>

    <div class="app">
        <?php echo $__env->yieldContent('content'); ?>
    </div>

    <?php echo $__env->yieldPushContent('scripts'); ?>
    <!-- <script src="//code.tidio.co/1vliqewz9v7tfosw5wxiktpkgblrws5w.js"></script> -->
</body>

</html>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/system/layouts/auth.blade.php ENDPATH**/ ?>