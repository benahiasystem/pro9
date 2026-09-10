<template>
    <div>
        <!-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## -->
        <div class="row">
            <div class="col-md-6 form-group">
                <label>Modalidad de emisión fiscal *</label>
                <el-select v-model="form.fiscal_emission_mode" placeholder="Seleccione una modalidad" @change="changeMode">
                    <el-option v-for="(label, key) in modes" :key="key" :label="label" :value="key" />
                </el-select>
                <small class="text-danger">{{ error('fiscal_emission_mode') }}</small>
            </div>
            <div class="col-md-6 form-group">
                <label>Ambiente *</label>
                <el-select v-model="form.fiscal_environment" :disabled="form.fiscal_environment_locked">
                    <el-option label="Demo" value="demo" />
                    <el-option label="Producción" value="production" />
                </el-select>
                <small class="text-danger">{{ error('fiscal_environment') }}</small>
            </div>
        </div>
        <p v-if="form.fiscal_environment_locked" class="text-muted">Este tenant tiene operaciones. Para cambiar de ambiente cree otro tenant limpio.</p>
        <div class="row" v-if="form.fiscal_configuration">
            <div v-for="field in fields" :key="field.key" class="col-md-6 form-group">
                <label>{{ field.label }}</label>
                <el-input :value="form.fiscal_configuration[field.key]" :type="field.numeric ? 'number' : 'text'"
                          :min="field.numeric ? 0 : undefined" maxlength="255"
                          @input="$set(form.fiscal_configuration, field.key, $event)" />
                <small class="text-danger">{{ error('fiscal_configuration.' + field.key) }}</small>
            </div>
        </div>
        <div v-if="form.fiscal_emission_mode === 'digital'" class="form-group">
            <label>Credenciales del proveedor</label>
            <el-input v-model="form.fiscal_credentials" type="password" autocomplete="new-password" maxlength="8192"
                      placeholder="Ingrese credenciales nuevas para reemplazarlas" />
            <p v-if="form.fiscal_credentials_configured" class="text-muted">Credenciales guardadas. Deje el campo vacío para conservarlas.</p>
            <el-checkbox v-if="form.fiscal_credentials_configured" v-model="form.clear_fiscal_credentials">Eliminar credenciales guardadas</el-checkbox>
            <small class="text-danger">{{ error('fiscal_credentials') }}</small>
        </div>
        <small class="text-danger">{{ error('fiscal_configuration') }}</small>
        <p class="text-muted mb-0">Configuración progresiva: puede completar los parámetros después. Pro9 registra los documentos localmente; esta selección no conecta una máquina ni activa envíos a un proveedor fiscal.</p>
        <!-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## -->
    </div>
</template>
<script>
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
const parameters = {
    fiscal_machine: [['model', 'Modelo'], ['serial', 'Serial'], ['port', 'Puerto'], ['provider', 'Proveedor / controlador']],
    digital: [['provider', 'Imprenta digital / proveedor'], ['authorization', 'Autorización']],
    free_form: [['printer', 'Imprenta autorizada'], ['control_number', 'Número de control'], ['range_start', 'Inicio del rango', true], ['range_end', 'Final del rango', true]]
}
export default {
    props: { form: { type: Object, required: true }, errors: { type: Object, default: () => ({}) } },
    data: () => ({ modes: { fiscal_machine: 'Máquina fiscal', digital: 'Medios digitales', free_form: 'Forma libre' } }),
    computed: {
        fields() { return (parameters[this.form.fiscal_emission_mode] || []).map(([key, label, numeric]) => ({ key, label, numeric })) }
    },
    methods: {
        error(key) { const errors = this.errors.errors || this.errors; return (errors[key] || [])[0] || '' },
        changeMode() {
            this.$set(this.form, 'fiscal_configuration', {})
            this.$set(this.form, 'fiscal_credentials', '')
            this.$set(this.form, 'clear_fiscal_credentials', false)
            this.$set(this.form, 'fiscal_credentials_configured', false)
        }
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
</script>
