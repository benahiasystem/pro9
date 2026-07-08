<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="sidebar-light sidebar-left-big-icons dashboard-system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="googlebot" content="noindex">
    <meta name="robots" content="noindex">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title>Facturación Electrónica</title>

    <!-- Scripts -->

    <!-- Fonts -->
    
    


    <!-- Styles -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

    
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/animate/animate.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/font-awesome/css/fontawesome-all.min.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/select2/css/select2.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/select2-bootstrap-theme/select2-bootstrap.min.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/datatables/media/css/dataTables.bootstrap4.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/css/theme.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/css/custom.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('theme/admin_styles.css')); ?>" />
    <?php if(file_exists(public_path('theme/custom_styles.css'))): ?>
        <link rel="stylesheet" href="<?php echo e(asset('theme/custom_styles.css')); ?>" />
    <?php endif; ?>

    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css')); ?>" />

    <!-- Specific Page Vendor CSS -->
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/jquery-ui/jquery-ui.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/jquery-ui/jquery-ui.theme.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/select2/css/select2.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/select2-bootstrap-theme/select2-bootstrap.min.css')); ?>" />

    <!-- Daterange picker plugins css -->
    <link href="<?php echo e(asset('porto-light/vendor/bootstrap-timepicker/css/bootstrap-timepicker.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('porto-light/vendor/bootstrap-daterangepicker/daterangepicker.css')); ?>" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/bootstrap-timepicker/css/bootstrap-timepicker.css')); ?>" />

    <link rel="stylesheet" href="<?php echo e(asset('porto-light/vendor/jquery-loading/dist/jquery.loading.css')); ?>" />

    <?php echo $__env->yieldPushContent('styles'); ?>
    <script src="<?php echo e(asset('porto-light/vendor/modernizr/modernizr.js')); ?>"></script>

    <style>
        .descarga {
            color:black;
            padding:5px;
        }
        html.sidebar-light:not(.dark) ul.nav-main > li.nav-active > a {
            color: #0088CC;
        }

        ul.nav-main > li.nav-active > a {
            box-shadow: 2px 0 0 #0088CC inset;
        }
    </style>
    <!-- vite aqui -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/system.js']); ?>
</head>
<body class="pr-0">
    <section class="body">
        <!-- start: header -->
        <?php echo $__env->make('system.layouts.partials.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <!-- end: header -->
        <div class="inner-wrapper">
            <!-- start: sidebar -->
            <?php echo $__env->make('system.layouts.partials.sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <!-- end: sidebar -->
            <section role="main" class="content-body" id="main-wrapper">
              <?php echo $__env->yieldContent('content'); ?>
            </section>
        </div>
    </section>

    <!-- Vendor -->
    <script src="<?php echo e(asset('porto-light/vendor/jquery/jquery.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/jquery-browser-mobile/jquery.browser.mobile.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/jquery-cookie/jquery-cookie.js')); ?>"></script>
        
    <script src="<?php echo e(asset('porto-light/vendor/popper/umd/popper.min.js')); ?>"></script>
    <!-- <script src="<?php echo e(asset('porto-light/vendor/bootstrap/js/bootstrap.js')); ?>"></script> -->
    
    <script src="<?php echo e(asset('porto-light/vendor/nanoscroller/nanoscroller.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/magnific-popup/jquery.magnific-popup.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/jquery-placeholder/jquery-placeholder.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/select2/js/select2.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/datatables/media/js/jquery.dataTables.min.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/datatables/media/js/dataTables.bootstrap4.min.js')); ?>"></script>

    
    <script src="<?php echo e(asset('porto-light/vendor/jquery-ui/jquery-ui.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/jqueryui-touch-punch/jqueryui-touch-punch.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/select2/js/select2.js')); ?>"></script>

    <script src="<?php echo e(asset('porto-light/vendor/jquery-loading/dist/jquery.loading.js')); ?>"></script>

    <!--<script src="assets/vendor/select2/js/select2.js"></script>-->
    <script src="<?php echo e(asset('porto-light/vendor/bootstrap-multiselect/bootstrap-multiselect.js')); ?>"></script>

    <!-- Moment -->
    <script src="<?php echo e(asset('porto-light/vendor/moment/moment.js')); ?>"></script>

    <!-- DatePicker -->
    <script src="<?php echo e(asset('porto-light/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js')); ?>"></script>

    <!-- Date range Plugin JavaScript -->
    <script src="<?php echo e(asset('porto-light/vendor/bootstrap-timepicker/bootstrap-timepicker.js')); ?>"></script>
    <script src="<?php echo e(asset('porto-light/vendor/bootstrap-daterangepicker/daterangepicker.js')); ?>"></script>

    <!-- Theme Custom -->
    <script src="<?php echo e(asset('porto-light/js/custom.js')); ?>"></script>

    <!-- Theme Initialization Files -->
    

    
    

    <?php echo $__env->yieldPushContent('scripts'); ?>

    
    
    <!-- Theme Base, Components and Settings -->
    <script src="<?php echo e(asset('porto-light/js/theme.js')); ?>"></script>


</body>

</html>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/system/layouts/app.blade.php ENDPATH**/ ?>