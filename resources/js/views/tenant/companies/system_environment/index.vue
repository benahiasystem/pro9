<template>
    <div class="card card-config">
        <!-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## -->
        <div class="card-header bg-info"><h3 class="my-0">Modalidad de emisión fiscal</h3></div>
        <div class="card-body">
            <form v-if="loaded" autocomplete="off" @submit.prevent="submit">
                <fiscal-emission-fields :form="form" :errors="errors" />
                <div class="text-end pt-2"><el-button type="primary" native-type="submit" :loading="loading">Guardar</el-button></div>
            </form>
            <p v-else>{{ loadMessage }}</p>
        </div>
        <!-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## -->
    </div>
</template>
<script>
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
import FiscalEmissionFields from '../../../../components/FiscalEmissionFields.vue'
export default {
    components: { FiscalEmissionFields },
    data: () => ({ form: {}, errors: {}, loading: false, loaded: false, loadMessage: 'Cargando configuración…' }),
    async created() {
        try {
            const response = await this.$http.get('/companies/fiscal-emission')
            this.setForm(response.data.data)
            this.loaded = true
        } catch (error) { this.loadMessage = 'La configuración fiscal está disponible para el administrador del tenant.' }
    },
    methods: {
        setForm(data) { this.form = { ...data, fiscal_configuration: data.fiscal_configuration || {}, fiscal_credentials: '', clear_fiscal_credentials: false } },
        async submit() {
            this.loading = true
            this.errors = {}
            const { fiscal_emission_mode, fiscal_environment, fiscal_configuration, fiscal_credentials, clear_fiscal_credentials } = this.form
            try {
                const response = await this.$http.post('/companies/fiscal-emission', { fiscal_emission_mode, fiscal_environment, fiscal_configuration, fiscal_credentials, clear_fiscal_credentials })
                this.setForm(response.data.data)
                this.$message.success(response.data.message)
            } catch (error) {
                this.errors = (error.response && error.response.data) || {}
                this.$message.error('No se pudo guardar la configuración. Revise los campos y sus permisos.')
            } finally { this.loading = false }
        }
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
</script>
