<template>
    <!-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## -->
    <section class="mb-3" v-loading="loading" aria-label="Estado de emisión fiscal">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
        <el-button v-if="error" size="small" :disabled="loading" @click="load">Volver a consultar</el-button>
        <template v-if="fiscal">
            <el-alert :title="statusLabel" :type="fiscal.status === 'issued' ? 'success' : 'warning'" :closable="false" show-icon />
            <p v-if="fiscal.environment === 'demo'" class="mt-2 mb-2"><strong>DEMO: no acredita emisión fiscal real.</strong></p>
            <p class="mt-2 mb-2">
                Documento: {{ fiscal.series ? fiscal.series + '-' : '' }}{{ fiscal.document_number }}<br>
                Control: {{ fiscal.control_number || 'No asignado' }}
            </p>
            <p v-if="fiscal.invalidation_reason">Motivo: {{ fiscal.invalidation_reason }}</p>
            <p v-if="fiscal.contingency"><strong>Contingencia:</strong> {{ fiscal.contingency.reason }}. Reserva original: {{ fiscal.contingency.original_series }} {{ fiscal.contingency.original_document_number }}.</p>
            <div class="d-flex flex-wrap" style="gap: 6px">
                <el-button v-if="fiscal.can_process" size="small" :disabled="loading" @click="act('process')">
                    {{ fiscal.status === 'reserved' ? 'Procesar emisión' : 'Consultar resultado' }}
                </el-button>
                <el-button v-if="fiscal.can_confirm_print" size="small" type="primary" :disabled="loading" @click="confirmPrint">Confirmar impresión</el-button>
                <el-button v-if="fiscal.can_invalidate_print" size="small" type="danger" :disabled="loading" @click="invalidatePrint">Inutilizar control</el-button>
                <el-button v-if="fiscal.can_start_contingency" size="small" :disabled="loading" @click="contingencyOpen = true">Iniciar contingencia</el-button>
                <el-button size="small" :disabled="loading" @click="load">Actualizar estado</el-button>
            </div>
            <small v-if="fiscal.can_confirm_print" class="d-block mt-2">Confirme únicamente después de comprobar la impresión en el formato preimpreso.</small>
            <el-dialog title="Emisión en contingencia" :visible.sync="contingencyOpen" append-to-body :close-on-click-modal="false" width="520px">
                <p>Se conservará esta venta y se reservará un documento de forma libre vinculado al intento original. Confirme la impresión después de comprobar el papel.</p>
                <el-alert v-if="!fiscal.contingency_profiles.length" title="Configure un perfil de contingencia con numeración y lote disponible en este establecimiento." type="warning" :closable="false" />
                <label class="d-block mt-2">Perfil de contingencia</label>
                <el-select v-model="contingencyProfile" placeholder="Seleccione un perfil" :disabled="loading">
                    <el-option v-for="profile in fiscal.contingency_profiles" :key="profile.id" :value="profile.id" :label="profile.name" />
                </el-select>
                <label class="d-block mt-2">Causa de contingencia</label>
                <el-input v-model="contingencyReason" type="textarea" maxlength="255" :disabled="loading" />
                <el-alert v-if="error" :title="error" type="error" :closable="false" class="mt-2" />
                <span slot="footer">
                    <el-button :disabled="loading" @click="contingencyOpen = false">Cancelar</el-button>
                    <el-button type="primary" :loading="loading" :disabled="!contingencyProfile || !contingencyReason.trim()" @click="startContingency">Reservar forma libre</el-button>
                </span>
            </el-dialog>
            <details v-if="fiscal.attempts.length" class="mt-2">
                <summary>Intentos de emisión ({{ fiscal.attempts.length }})</summary>
                <ul><li v-for="(attempt, index) in fiscal.attempts" :key="index">{{ attempt.action === 'lookup' ? 'Consulta' : 'Emisión' }}: {{ label(attempt.status) }} · {{ attempt.created_at }}</li></ul>
            </details>
        </template>
        <el-alert v-else-if="!loading && !error" title="Este registro comercial no tiene reserva fiscal." type="warning" :closable="false" />
    </section>
    <!-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## -->
</template>

<script>
// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
export default {
    props: { documentId: { type: Number, required: true }, resource: { type: String, default: 'documents' } },
    data() { return { fiscal: null, loading: false, error: null, contingencyOpen: false, contingencyProfile: null, contingencyReason: '' }; },
    computed: { statusLabel() { return this.label(this.fiscal.status); } },
    mounted() { this.load(); },
    methods: {
        label(status) {
            return { reserved: 'Numeración reservada; emisión pendiente', processing: 'Emisión en curso', uncertain: 'Respuesta incierta; requiere consulta', awaiting_print: 'Pendiente de confirmar impresión', issued: 'Emisión confirmada', rejected: 'Emisión rechazada', inutilized: 'Control inutilizado', not_found: 'El proveedor no encontró la operación' }[status] || status;
        },
        failure(error) {
            const data = error.response && error.response.data;
            const errors = data && (data.errors || (typeof data.message === 'object' && data.message));
            this.error = errors ? Object.values(errors).flat().join(' ') : (data && typeof data.message === 'string' && data.message) || 'No se pudo consultar el estado fiscal.';
        },
        async load() {
            if (this.loading) return;
            this.loading = true;
            this.error = null;
            try {
                this.fiscal = (await this.$http.get(`/${this.resource}/${this.documentId}/fiscal`)).data.data;
            } catch (error) { this.failure(error); }
            finally { this.loading = false; }
        },
        async act(action, payload = {}) {
            if (this.loading) return;
            this.loading = true;
            this.error = null;
            try {
                this.fiscal = (await this.$http.post(`/${this.resource}/${this.documentId}/fiscal/${action}`, payload)).data.data;
                this.$emit('changed', this.fiscal);
                return true;
            } catch (error) { this.failure(error); }
            finally { this.loading = false; }
        },
        async startContingency() {
            if (!this.contingencyProfile || !this.contingencyReason.trim()) return;
            if (await this.act('contingency', { profile_id: this.contingencyProfile, reason: this.contingencyReason.trim() })) {
                this.contingencyOpen = false;
                this.contingencyProfile = null;
                this.contingencyReason = '';
            }
        },
        async confirmPrint() {
            try {
                await this.$confirm(`¿La impresión con el control ${this.fiscal.control_number} terminó correctamente?`, 'Confirmar impresión', { confirmButtonText: 'Sí, confirmar', cancelButtonText: 'Cancelar', type: 'warning' });
            } catch (_) { return; }
            await this.act('confirm-print');
        },
        async invalidatePrint() {
            let result;
            try {
                result = await this.$prompt('Indique por qué el formato preimpreso no puede utilizarse. El control no volverá a estar disponible.', 'Inutilizar control', { confirmButtonText: 'Inutilizar', cancelButtonText: 'Cancelar', inputValidator: value => !!value && !!value.trim() && value.trim().length <= 255, inputErrorMessage: 'Indique un motivo de hasta 255 caracteres.' });
            } catch (_) { return; }
            await this.act('invalidate-print', { reason: result.value.trim() });
        }
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
</script>
