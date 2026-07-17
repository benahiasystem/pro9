<?php
use Illuminate\Support\Facades\Storage;
?>
<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="googlebot" content="noindex">
    <meta name="robots" content="noindex">

    
    <title><?php echo e(data_get($vc_company ?? null, 'title_web') ?: data_get($vc_company ?? null, 'trade_name')); ?></title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/bootstrap/css/bootstrap.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/animate/animate.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/font-awesome/css/fontawesome-all.min.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/css/theme.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('css/auth.css')); ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />
    
    
    <?php if(isset($selectedSkin) && $selectedSkin): ?>
        <?php if(Storage::disk('public')->exists('skins/' . $selectedSkin->filename)): ?>
            <link rel="stylesheet" href="<?php echo e(asset('storage/skins/' . $selectedSkin->filename)); ?>" />
        <?php endif; ?>
    <?php else: ?>
        
        <?php if(Storage::disk('public')->exists('skins/default.css')): ?>
            <link rel="stylesheet" href="<?php echo e(asset('storage/skins/default.css')); ?>" />
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if(file_exists(public_path('theme/custom_styles.css'))): ?>
        <link rel="stylesheet" href="<?php echo e(asset('theme/custom_styles.css')); ?>" />
    <?php endif; ?>

    
    <?php if(isset($themeColors) && $themeColors): ?>
        <!-- Debug: Tema aplicado: <?php echo e($selectedTheme ?? 'none'); ?> -->
        <style>
            :root {
                <?php $__currentLoopData = $themeColors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $property => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php echo e($property); ?>: <?php echo $value; ?>;
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            }
        </style>
    <?php endif; ?>

    

</head>

<body>

    <div class="app">
        <?php echo $__env->yieldContent('content'); ?>
    </div>

    <?php echo $__env->yieldPushContent('scripts'); ?>
    <!-- <script src="//code.tidio.co/1vliqewz9v7tfosw5wxiktpkgblrws5w.js"></script> -->
</body>

</html>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/layouts/auth.blade.php ENDPATH**/ ?>