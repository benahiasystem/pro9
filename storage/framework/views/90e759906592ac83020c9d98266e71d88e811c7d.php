

<?php $__env->startSection('content'); ?>
<section class="auth auth__form-<?php echo e($login->position_form); ?> <?php echo e(!$useLoginGlobal ? ' d-flex align-items-center justify-content-center' : ''); ?>">
    <?php echo $__env->make('tenant.auth.partials.side_left', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <article class="auth__form <?php echo e(!$useLoginGlobal ? 'h-auto login-container px-5 py-4' : ''); ?>">
        <form method="POST" action="<?php echo e(route('login')); ?>">
            <?php echo csrf_field(); ?>
            <?php if($useLoginGlobal): ?>
                <?php if(($login->logo ?? false) && $login->show_logo_in_form): ?>
                    <div class="d-flex justify-content-center">
                        <div class="row form-logo-container">
                            <?php echo $__env->make('tenant.auth.partials.form_logo', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if($login->show_logo_in_form && ($company->logo ?? false)): ?>
                    <div class="d-flex justify-content-center">
                        <div class="row form-logo-container">
                            <?php echo $__env->make('tenant.auth.partials.form_logo', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>            
            <div class="text-center title-login-container">
                <h1 class="auth__title <?php echo e(!$useLoginGlobal ? 'mt-0' : ''); ?>"><span class="text-xs">Bienvenido a</span><br><b><?php echo e($company->trade_name); ?></b></h1>
                <p class="auth__subtitle">Ingresa a tu cuenta</p>
                <p class="auth__subtitle__black d-none">
                    Ingrese su correo electrónico y contraseña a continuación para iniciar sesión en su cuenta.
                </p>
            </div>
            <div class="form-group form-group-email">
                <label for="email" class="label-email">Correo electrónico</label>
                <input type="email" name="email" id="email" placeholder="correo@ejemplo.com" class="form-control <?php echo e($errors->has('email') ? 'is-invalid' : ''); ?>" value="<?php echo e(old('email')); ?>" autofocus>
                <?php if($errors->has('email')): ?>
                <div class="invalid-feedback"><?php echo e($errors->first('email')); ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password" class="label-password">
                    Contraseña
                    <a class="forgot-password d-none" href="<?php echo e(route('password.request')); ?>" tabindex="5">¿Olvidaste tu contraseña?</a>
                </label>
                <div class="position-relative">
                    <input type="password" name="password" id="password" placeholder="********" class="form-control hide-password <?php echo e($errors->has('password') ? 'is-invalid' : ''); ?>">
                    <button type="button" class="btn btn-eye" id="btnEye" tabindex="4">
                        <i class="fa fa-eye"></i>
                    </button>                    
                </div>
                <?php if($errors->has('password')): ?>
                <div class="invalid-feedback"><?php echo e($errors->first('password')); ?></div>
                <?php endif; ?>
            </div>

            <div class="auth__forgot-password d-flex justify-content-between align-items-center">
                <div class="checkbox-custom checkbox-default d-none">
                    <input name="remember" id="RememberMe" type="checkbox" <?php echo e(old('remember') ? 'checked' : ''); ?>>
                    <label class="m-0" for="RememberMe">Recordarme</label>
                </div>
                <a class="forgot-password-modern d-none" href="<?php echo e(route('password.request')); ?>" tabindex="5">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-signin btn-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-login-2 mr-1 icon-login d-none"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2"></path><path d="M3 12h13l-3 -3"></path><path d="M13 15l3 -3"></path></svg>
                iniciar sesión
            </button>
            <div class="text-center p-4 password-down">
                <a href="<?php echo e(route('password.request')); ?>" tabindex="5">¿Has olvidado tu contraseña?</a>
            </div>
            <?php echo $__env->make('tenant.auth.partials.socials', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </form>
    </article>
</section>
    
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>
    <script>
        var inputPassword = document.getElementById('password');
        var btnEye = document.getElementById('btnEye');
        btnEye.addEventListener('click', function () {
            if (inputPassword.classList.contains('hide-password')) {
                inputPassword.type = 'text';
                inputPassword.classList.remove('hide-password');
                btnEye.innerHTML = '<i class="fa fa-eye-slash"></i>'
            } else {
                inputPassword.type = 'password';
                inputPassword.classList.add('hide-password');
                btnEye.innerHTML = '<i class="fa fa-eye"></i>'
            }
        });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('tenant.layouts.auth', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/auth/login.blade.php ENDPATH**/ ?>