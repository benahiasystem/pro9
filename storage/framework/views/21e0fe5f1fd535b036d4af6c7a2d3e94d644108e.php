
<?php $__env->startSection('account_content'); ?>
<?php
    $contact = auth('ecommerce')->user()->contact;
    $first_name = $contact && isset($contact->first_name) ? $contact->first_name : auth('ecommerce')->user()->name;
    $paternal_last_name = $contact && isset($contact->paternal_last_name) ? $contact->paternal_last_name : '';
    $maternal_last_name = $contact && isset($contact->maternal_last_name) ? $contact->maternal_last_name : '';
    $date_of_birth = $contact && isset($contact->date_of_birth) ? $contact->date_of_birth : '';
    $gender = $contact && isset($contact->gender) ? $contact->gender : '';
?>

<div id="app">
    <div class="panel-head">
        <span class="panel-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
        </span>
        <div>
            <h2 class="m-0">Mi perfil</h2>
            <p class="m-0">Tus datos personales para pedidos y comprobantes.</p>
        </div>
    </div>
    <div class="panel-body">
        <div class="">
            <div class="row">
                <div class="col-md-6 form-group-container">
                    <label>Nombres</label>
                    <el-input v-model="form.first_name"></el-input>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Apellido Paterno</label>
                    <el-input v-model="form.paternal_last_name"></el-input>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Apellido Materno</label>
                    <el-input v-model="form.maternal_last_name"></el-input>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Correo electrónico</label>
                    <el-input v-model="form.email" type="email"></el-input>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Tipo de documento <span class="tag-ecommerce disabled ml-3">No editable</span></label>
                    <el-select v-model="form.identity_document_type_id" disabled class="w-100">
                        <?php $__currentLoopData = $identity_document_types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <el-option value="<?php echo e($type->id); ?>" label="<?php echo e($type->description); ?>"></el-option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </el-select>
                    <small class="text-muted">El documento no se puede modificar por seguridad.</small>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Número de documento <span class="tag-ecommerce disabled ml-3">No editable</span></label>
                    <el-input v-model="form.number" readonly disabled></el-input>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Fecha de nacimiento</label>
                    <el-date-picker v-model="form.date_of_birth" type="date" placeholder="dd/mm/yyyy" format="dd/MM/yyyy" value-format="yyyy-MM-dd" class="w-100"></el-date-picker>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Género</label>
                    <el-select v-model="form.gender" placeholder="Selecciona" class="w-100">
                        <el-option value="Masculino" label="Masculino"></el-option>
                        <el-option value="Femenino" label="Femenino"></el-option>
                        <el-option value="Otro" label="Otro"></el-option>
                    </el-select>
                </div>
                <div class="col-md-6 form-group-container">
                    <label>Número de celular</label>
                    <el-input v-model="form.telephone"></el-input>
                </div>
                <div class="col-md-12 d-flex align-items-center justify-content-end mt-3">
                    <button class="pay-btn w-auto" @click="saveData" :loading="loading">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-floppy"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2" /><path d="M10 14a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M14 4l0 4l-6 0l0 -4" /></svg>
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script type="text/javascript">
    Vue.use(ELEMENT, { locale: ELEMENT.lang.es });
    var app_account = new Vue({
        el: '#app',
        data: {
            form: {
                first_name: <?php echo json_encode($first_name, 15, 512) ?>,
                paternal_last_name: <?php echo json_encode($paternal_last_name, 15, 512) ?>,
                maternal_last_name: <?php echo json_encode($maternal_last_name, 15, 512) ?>,
                email: <?php echo json_encode(auth('ecommerce')->user()->email, 15, 512) ?>,
                identity_document_type_id: <?php echo json_encode(auth('ecommerce')->user()->identity_document_type_id, 15, 512) ?>,
                number: <?php echo json_encode(auth('ecommerce')->user()->number, 15, 512) ?>,
                date_of_birth: <?php echo json_encode($date_of_birth, 15, 512) ?>,
                gender: <?php echo json_encode($gender, 15, 512) ?>,
                telephone: <?php echo json_encode(auth('ecommerce')->user()->telephone, 15, 512) ?>,
                address: <?php echo json_encode(auth('ecommerce')->user()->address, 15, 512) ?>
            },
            loading: false
        },
        methods: {
            async saveData() {
                if (!this.form.first_name || !this.form.paternal_last_name || !this.form.email) {
                    this.$message.error('Los nombres, apellido paterno y correo electrónico son obligatorios.');
                    return;
                }
                
                this.loading = true;
                try {
                    let response = await axios.post(`<?php echo e(route('tenant_ecommerce_user_data')); ?>`, this.form);
                    if (response.data.success) {
                        this.$message.success(response.data.message || 'Datos actualizados correctamente');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.$message.error(response.data.message || 'Error al actualizar los datos');
                    }
                } catch (error) {
                    console.error(error);
                    if (error.response && error.response.data && error.response.data.message) {
                        this.$message.error(error.response.data.message);
                    } else {
                        this.$message.error('Error de conexión.');
                    }
                } finally {
                    this.loading = false;
                }
            }
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('ecommerce::layouts.layout_account', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/document_list/account.blade.php ENDPATH**/ ?>