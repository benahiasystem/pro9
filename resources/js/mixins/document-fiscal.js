import {noteRequestId} from '../helpers/fiscal-note';
/** Shared manual fiscal actions for document detail and options. */
export const documentFiscal = {
    data() {
        return { fiscalActionBusy: false, fiscalActionSession: 0, annulmentRequest: null, annulmentDocumentId: null, annulmentPromptBusy: false };
    },
    methods: {
        resetFiscalAction() {
            this.fiscalActionSession = (this.fiscalActionSession || 0) + 1;
            this.fiscalActionBusy = false;
            this.annulmentPromptBusy = false;
        },
        isCurrentFiscalDocument(id, session) {
            return this.fiscalActionVisible
                && this.fiscalActionSession === session
                && String(this.recordId) === String(id)
                && this.fiscalRecord && String(this.fiscalRecord.id) === String(id);
        },
        async requestNoteAnnulment() {
            const record = this.fiscalRecord;
            if (!record || !record.fiscal_emission || !(record.fiscal_emission.annulment || {}).can_request || this.fiscalActionBusy || this.annulmentPromptBusy) return;
            if (String(this.annulmentDocumentId) !== String(record.id) || (record.fiscal_emission.annulment || {}).status === 'rejected') this.annulmentRequest = null;
            if (!this.annulmentRequest) {
                const session = this.fiscalActionSession;
                this.annulmentPromptBusy = true;
                try {
                    const answer = await this.$prompt('Al confirmar se solicitará la anulación y se revertirán el ajuste comercial y las existencias devueltas. Indique el motivo.', 'Anular nota', {
                        confirmButtonText: 'Confirmar anulación', cancelButtonText: 'Cancelar', inputValidator: value => !!value && !!value.trim() && value.trim().length <= 255 || 'Indique un motivo de hasta 255 caracteres.'
                    });
                    if (!this.isCurrentFiscalDocument(record.id, session)) return;
                    this.annulmentRequest = {operation_key: noteRequestId(), reason: answer.value.trim()};
                    this.annulmentDocumentId = record.id;
                } catch (ignored) { return; }
                finally { if (session === this.fiscalActionSession) this.annulmentPromptBusy = false; }
            }
            await this.runFiscalAction('annul', this.annulmentRequest);
        },
        async runFiscalAction(action, body = {}) {
            const record = this.fiscalRecord;
            const permission = {query: 'can_query', send: 'can_send', annul: 'can_request', 'query-annulment':'can_query', 'complete-annulment':'can_complete'}[action];
            const cancellation = ['annul','query-annulment','complete-annulment'].includes(action);
            const capabilities = cancellation ? ((record && record.fiscal_emission || {}).annulment || {}) : (record && record.fiscal_emission || {});
            const endpoint = {annul:'annul','query-annulment':'query-annulment-hka','complete-annulment':'complete-annulment'}[action] || `${action}-hka`;
            if (!permission || !record || !record.id || !this.fiscalActionVisible || this.loading
                || this.fiscalActionBusy || !record.fiscal_emission || !capabilities[permission]) return;

            const id = record.id;
            const session = this.fiscalActionSession;
            if (!this.isCurrentFiscalDocument(id, session)) return;
            this.fiscalActionBusy = true;
            let updated = false;
            try {
                const response = await this.$http.post(`/documents/${id}/${endpoint}`, body);
                if (!this.isCurrentFiscalDocument(id, session)) return;
                const emission = response.data && response.data.fiscal_emission;
                if (!emission || !emission.status) throw new Error('Missing fiscal result');
                this.$set(this.fiscalRecord, 'fiscal_emission', emission);
                updated = true;
                this.$emit('fiscal-updated', { id, fiscal_emission: emission });
                this.$message.info(cancellation ? (emission.annulment || {}).diagnostic : emission.diagnostic || emission.description);
                await this.refreshFiscalDocument();
            } catch (error) {
                if (!this.isCurrentFiscalDocument(id, session)) return;
                const data = error.response && error.response.data || {};
                const validation = data.errors || data;
                const detail = error.response && error.response.status === 422 ? Object.values(validation).flat().filter(v => typeof v === 'string').join(' ') : null;
                this.$message.error(detail || (updated
                    ? 'El estado fiscal se actualizó, pero no se pudo recargar el detalle del comprobante.'
                    : 'La venta está guardada. No se pudo actualizar el estado HKA.'));
            } finally {
                if (this.fiscalActionSession === session) this.fiscalActionBusy = false;
            }
        },
    },
};
