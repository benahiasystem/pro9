<template>
    <el-dialog :visible="showDialog" @open="openPreview" :fullscreen="true"
               :close-on-click-modal="false" :show-close="false" custom-class="d-own">
        <div class="document-preview-layout">
            <div>
                <strong v-if="digitalInvoice">Vista previa 80MM</strong>
                <el-select v-else v-model="format" @change="loadPDF">
                    <el-option value="a4" label="A4" />
                    <el-option v-if="ShowTicket80" value="ticket" label="80MM" />
                    <el-option v-if="ShowTicket58" value="ticket_58" label="58MM" />
                    <el-option value="a5" label="A5" />
                </el-select>
            </div>
            <div class="document-preview-viewer" v-loading="loading">
                <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
                <iframe v-if="URL" :src="URL" title="Vista previa de la factura" width="100%" height="100%" />
            </div>
            <el-button @click="clickClose">Cerrar</el-button>
        </div>
    </el-dialog>
</template>
<script>
import { mapState } from 'vuex/dist/vuex.mjs';
export default {
    props: { showDialog: Boolean, preview: Function, digitalInvoice: Boolean },
    data() { return { format: 'a4', URL: null, loading: false, error: '', requestSequence: 0 }; },
    computed: {
        ...mapState(['config']),
        ShowTicket80() { return Boolean(this.config?.show_ticket_80); },
        ShowTicket58() { return Boolean(this.config?.show_ticket_58); },
    },
    beforeDestroy() { this.clearPreview(); },
    methods: {
        clearPreview() {
            this.requestSequence++;
            if (this.URL) window.URL.revokeObjectURL(this.URL);
            this.URL = null;
            this.loading = false;
        },
        async openPreview() {
            this.format = this.digitalInvoice ? 'ticket' : this.format;
            await this.loadPDF();
        },
        async loadPDF() {
            this.clearPreview();
            const sequence = this.requestSequence;
            this.loading = true;
            this.error = '';
            try {
                const url = await this.preview(this.digitalInvoice ? 'ticket' : this.format);
                if (sequence !== this.requestSequence || !this.showDialog) {
                    if (url) window.URL.revokeObjectURL(url);
                    return;
                }
                if (!url) throw new Error('No se pudo cargar la vista previa.');
                this.URL = url;
            } catch (error) {
                if (sequence === this.requestSequence) this.error = error.message || 'No se pudo cargar la vista previa.';
            } finally {
                if (sequence === this.requestSequence) this.loading = false;
            }
        },
        clickClose() { this.clearPreview(); this.$emit('update:showDialog', false); },
    },
};
</script>
<style>
.d-own > .el-dialog__header { display: none; }
.document-preview-layout { height: calc(100vh - 80px); display: grid; grid-template-rows: auto minmax(320px, 1fr) auto; gap: 12px; }
.document-preview-viewer { min-height: 320px; overflow: auto; }
.document-preview-viewer iframe { display: block; border: 0; }
</style>
