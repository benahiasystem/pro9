

<?php $__env->startSection('content'); ?>

    <section class="body-sign">
        <div class="center-sign">
            <div class="logo-login">
                <?php
                    use App\Models\System\Configuration;
                    $configuration = Configuration::first();
                    $logo = $configuration->login->logo ?? null;
                ?>

                <?php if($logo): ?>
                    <img class="uk-logo-inverse" width="100" height="auto" src="<?php echo e($logo); ?>" alt="Logo" />
                <?php elseif(file_exists(public_path('theme/logo.svg'))): ?>
                    <img class="uk-logo-inverse" width="100" height="auto" src="<?php echo e(asset('theme/logo.svg')); ?>" alt="Logo" />
                <?php else: ?>

                <?php endif; ?>
            </div>
            <div class="">
                <div class="card card-header card-primary bg-info">
                    <p class="card-title text-center">Acceso al Sistema</p>
                    <h1 class="display-3 position-absolute text-left font-weight-bold"
                        style="left: 90%; margin-top: -35px !important; color: rgba(255,255,255,.1) !important; font-size: 4.5rem !important; font-weight: 600 !important;">9</h1>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="<?php echo e(route('login')); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="form-group mb-3">
                            <label>Correo electrónico</label>
                            <div class="input-group">
                                <input id="email" type="email" name="email" class="form-control form-control-lg"
                                    value="<?php echo e(old('email')); ?>">
                                <span class="input-group-append">
                                    <span class="input-group-text h-100">
                                        <i class="fas fa-user"></i>
                                    </span>
                                </span>
                            </div>
                            <?php if($errors->has('email')): ?>
                                <label class="error">
                                    <strong><?php echo e($errors->first('email')); ?></strong>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="form-group mb-3 <?php echo e($errors->has('password') ? ' error' : ''); ?>">
                            <label>Contraseña</label>
                            <div class="input-group">
                                <input name="password" type="password" class="form-control form-control-lg">
                                <span class="input-group-append">
                                    <span class="input-group-text h-100">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                </span>
                            </div>
                            <?php if($errors->has('password')): ?>
                                <label class="error">
                                    <strong><?php echo e($errors->first('password')); ?></strong>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-sm-8">
                                
                                <div class="mt-2">
                                    
                                    <a href="<?php echo e(route('password.request')); ?>" class="text-primary font-weight-bold">
                                    
                                    ¿Olvidaste tu contraseña?
                                    </a>
                                </div>
                            </div>
                            <div class="col-sm-4 text-right">
                                <button type="submit" class="btn btn-primary mt-2">Iniciar sesión</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted mt-3 mb-3"><?php echo e(config('app.name')); ?> &copy; Copyright <?php echo e(date('Y')); ?>. Todos los
                derechos reservados</p>
        </div>
    </section>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('system.layouts.auth', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/system/auth/login.blade.php ENDPATH**/ ?>