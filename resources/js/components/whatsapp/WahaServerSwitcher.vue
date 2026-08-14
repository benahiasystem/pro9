<template>
    <div v-if="provider === 'waha'" class="waha-switcher mt-3">
        <el-alert type="info" :closable="false" class="mb-2">
            <span slot="title">Salvaguarda: cambio de servidor WAHA</span>
            Esta conexión usa WAHA en el servidor <strong>{{ currentServerLabel }}</strong>. Si ese motor falla,
            elige otro servidor registrado y vuelve a escanear el QR — la conexión con el bot/comprobantes sigue
            funcionando igual.
        </el-alert>
        <div class="d-flex phone-input-group">
            <el-select v-model="selectedKey" placeholder="Selecciona un servidor" style="flex: 1;">
                <el-option
                    v-for="s in otherServers"
                    :key="s.key"
                    :label="`${s.name} (${s.engine})`"
                    :value="s.key">
                </el-option>
            </el-select>
            <el-button
                type="warning"
                plain
                :disabled="!selectedKey"
                :loading="loading"
                @click="confirmSwitch">
                Cambiar de servidor
            </el-button>
        </div>
        <p v-if="!loadingServers && !otherServers.length" class="text-muted small mt-1">
            No hay otro servidor WAHA activo registrado. Un superadministrador puede agregar más en
            Configuraciones → Servidores WAHA.
        </p>
    </div>
</template>

<script>
export default {
    props: {
        provider: { type: String, default: 'evolution' },
        currentServerKey: { type: String, default: null },
        renewUrl: { type: String, required: true },
    },
    data() {
        return {
            servers: [],
            selectedKey: null,
            loading: false,
            loadingServers: false,
        };
    },
    computed: {
        otherServers() {
            return this.servers.filter(s => s.key !== this.currentServerKey);
        },
        currentServerLabel() {
            const current = this.servers.find(s => s.key === this.currentServerKey);
            return current ? `${current.name} (${current.engine})` : (this.currentServerKey || '—');
        },
    },
    watch: {
        provider(value) {
            if (value === 'waha' && !this.servers.length) this.fetchServers();
        },
    },
    created() {
        if (this.provider === 'waha') this.fetchServers();
    },
    methods: {
        async fetchServers() {
            this.loadingServers = true;
            try {
                const { data } = await this.$http.get('/whatsapp-bot/waha-servers');
                this.servers = data || [];
            } catch (e) {
                // silencioso — la selección simplemente queda vacía
            } finally {
                this.loadingServers = false;
            }
        },
        confirmSwitch() {
            if (!this.selectedKey) return;
            this.$confirm(
                'Se moverá la conexión al servidor seleccionado. Tendrás que volver a escanear el QR. ¿Continuar?',
                'Cambiar de servidor',
                { confirmButtonText: 'Sí, cambiar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.doSwitch()).catch(() => {});
        },
        async doSwitch() {
            this.loading = true;
            try {
                const { data } = await this.$http.post(this.renewUrl, { waha_server_key: this.selectedKey });
                if (data.success) {
                    this.$message({ message: data.message, type: 'success' });
                    this.$emit('switched', this.selectedKey);
                    this.selectedKey = null;
                } else {
                    this.$message({ message: data.message || 'No se pudo cambiar de servidor', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al cambiar de servidor', type: 'error' });
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.waha-switcher .phone-input-group {
    gap: 8px;
}
</style>
