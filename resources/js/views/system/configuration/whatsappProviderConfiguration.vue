<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Proveedor de conexión WhatsApp</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Define qué proveedor usan las conexiones nuevas de WhatsApp (bot conversacional y envío de
                comprobantes) en toda la instalación. Cambiarlo no afecta a los tenants ya conectados con el
                proveedor anterior.
            </p>
            <form autocomplete="off" @submit.prevent="submit">
                <div class="form-group">
                    <label class="control-label">Proveedor</label>
                    <el-select v-model="form.whatsapp_provider" style="width: 100%">
                        <el-option label="Evolution API" value="evolution"></el-option>
                        <el-option label="WAHA" value="waha"></el-option>
                    </el-select>
                </div>
                <div v-if="form.whatsapp_provider === 'waha'" class="alert alert-light border mt-2 mb-3">
                    WAHA requiere registrar manualmente sus servidores (uno por motor: NOWEB, GOWS, WEBJS, WPP)
                    antes de que un tenant pueda conectarse — ver la sección "Servidores WAHA" más abajo.
                </div>
                <div class="text-end pt-2">
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
export default {
    created() {
        this.fetch();
    },
    data() {
        return {
            resource: 'configurations',
            loading_submit: false,
            form: {
                whatsapp_provider: 'evolution',
            },
        };
    },
    methods: {
        async fetch() {
            try {
                const { data } = await this.$http.get(`${this.resource}/whatsapp-provider`);
                this.form.whatsapp_provider = data.whatsapp_provider || 'evolution';
            } catch (e) {
                // silent
            }
        },
        async submit() {
            this.loading_submit = true;
            try {
                const { data } = await this.$http.post(`${this.resource}/whatsapp-provider`, this.form);
                this.$message({ message: data.message || 'Guardado', type: data.success ? 'success' : 'error' });
            } catch (e) {
                this.$message.error('Error al guardar');
            } finally {
                this.loading_submit = false;
            }
        },
    },
};
</script>
