

<?php $__env->startSection('content'); ?>

    <?php echo $__env->make('system.auth.partials.theme', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php
        use App\Models\System\Configuration;
        $configuration = Configuration::first();
        $logo = $configuration->login->logo ?? null;
    ?>

    <section class="body-sign">
        <div class="center-sign">

            
            <div class="logo-login mb-3">
                <?php if($logo): ?>
                    <img class="uk-logo-inverse" src="<?php echo e($logo); ?>" alt="Logo" />
                <?php elseif(file_exists(public_path('theme/logo.svg'))): ?>
                    <img class="uk-logo-inverse" src="<?php echo e(asset('theme/logo.svg')); ?>" alt="Logo" />
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header bg-info d-flex align-items-center justify-content-between py-3">
                    <p class="card-title mb-0 text-white font-weight-bold">Acceso al Sistema</p>
                    <span class="login-version">
                        <i class="ti ti-sparkles"></i> Versión <b>9</b>
                    </span>
                </div>

                <div class="card-body p-4">

                    
                    <p class="text-muted mb-4">Acceso solo para administradores de cuentas</p>

                    <form method="POST" action="<?php echo e(route('login')); ?>">
                        <?php echo csrf_field(); ?>

                        
                        <div class="form-group mb-3">
                            <label class="control-label">Correo electrónico</label>
                            <div>
                                <input id="email" type="email" name="email" autocomplete="email"
                                    class="form-control" placeholder="tucorreo@empresa.com"
                                    value="<?php echo e(old('email')); ?>" autofocus>
                            </div>
                            <?php if($errors->has('email')): ?>
                                <label class="error text-danger mt-1">
                                    <i class="ti ti-alert-triangle"></i> <?php echo e($errors->first('email')); ?>

                                </label>
                            <?php endif; ?>
                        </div>

                        
                        <div class="form-group mb-3">
                            <label class="control-label">Contraseña</label>
                            <div class="login-pass">
                                <input id="password" name="password" type="password" autocomplete="current-password"
                                    class="form-control" placeholder="••••••••">
                                <span class="login-pass-toggle" data-toggle-password role="button"
                                    aria-label="Mostrar contraseña">
                                    <i class="ti ti-eye"></i>
                                </span>
                            </div>
                            <?php if($errors->has('password')): ?>
                                <label class="error text-danger mt-1">
                                    <i class="ti ti-alert-triangle"></i> <?php echo e($errors->first('password')); ?>

                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="text-right mb-3">
                            <a href="<?php echo e(route('password.request')); ?>" class="text-primary font-weight-bold">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block login-submit">
                            <i class="ti ti-login-2"></i> Iniciar sesión
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted mt-3 mb-3">
                <?php echo e(config('app.name')); ?> &copy; Copyright <?php echo e(date('Y')); ?>. Todos los derechos reservados
            </p>
        </div>
    </section>

    <?php $__env->startPush('scripts'); ?>
        <script>
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-toggle-password]');
                if (!btn) return;
                const input = document.getElementById('password');
                const icon = btn.querySelector('i');
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                if (icon) icon.className = show ? 'ti ti-eye-off' : 'ti ti-eye';
                btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
            });
        </script>
    <?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('system.layouts.auth', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/system/auth/login.blade.php ENDPATH**/ ?>