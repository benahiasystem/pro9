<script>
// ######## INICIO ANULACION DIRECTA DESDE LISTADO ########
import {documentFiscal} from '@mixins/document-fiscal';

export default {
    data() {
        return {...documentFiscal.data(), loading: false, active: false, documentId: null, record: null};
    },
    computed: {
        recordId() { return this.documentId; },
        fiscalRecord() { return this.record; },
        fiscalActionVisible() { return this.active; },
    },
    beforeDestroy() { this.active = false; this.resetFiscalAction(); },
    methods: {
        ...documentFiscal.methods,
        async open(documentId) {
            if (!documentId || this.active || this.loading || this.fiscalActionBusy || this.annulmentPromptBusy) return;
            this.resetFiscalAction();
            const session = this.fiscalActionSession;
            this.documentId = documentId;
            this.record = null;
            this.active = true;
            this.loading = true;
            try {
                const response = await this.$http.get(`/documents/record/${documentId}`);
                if (!this.active || session !== this.fiscalActionSession) return;
                this.record = response.data.data;
                this.loading = false;
                const annulment = this.record?.fiscal_emission?.annulment || {};
                if (!annulment.can_request) {
                    await this.$alert(annulment.diagnostic || 'Esta nota no está disponible para anulación.', 'No se puede anular la nota', {type: 'warning'}).catch(() => {});
                    return;
                }
                await this.requestNoteAnnulment();
            } catch (error) {
                if (this.active && session === this.fiscalActionSession) {
                    this.$message.error('No se pudo consultar la nota para anularla. Actualice el listado e intente nuevamente.');
                }
            } finally {
                if (session === this.fiscalActionSession) { this.loading = false; this.active = false; }
            }
        },
        async refreshFiscalDocument() {
            const id = this.documentId, session = this.fiscalActionSession;
            const response = await this.$http.get(`/documents/record/${id}`);
            if (this.isCurrentFiscalDocument(id, session)) this.record = response.data.data;
        },
    },
    render() { return null; },
};
// ######## FIN ANULACION DIRECTA DESDE LISTADO ########
</script>
