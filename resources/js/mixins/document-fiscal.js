/** Shared manual fiscal actions for document detail and options. */
export const documentFiscal = {
    data() {
        return { fiscalActionBusy: false, fiscalActionSession: 0 };
    },
    methods: {
        resetFiscalAction() {
            this.fiscalActionSession = (this.fiscalActionSession || 0) + 1;
            this.fiscalActionBusy = false;
        },
        isCurrentFiscalDocument(id, session) {
            return this.fiscalActionVisible
                && this.fiscalActionSession === session
                && String(this.recordId) === String(id)
                && this.fiscalRecord && String(this.fiscalRecord.id) === String(id);
        },
        async runFiscalAction(action) {
            const record = this.fiscalRecord;
            const permission = { query: 'can_query', send: 'can_send' }[action];
            if (!permission || !record || !record.id || !this.fiscalActionVisible || this.loading
                || this.fiscalActionBusy || !record.fiscal_emission || !record.fiscal_emission[permission]) return;

            const id = record.id;
            const session = this.fiscalActionSession;
            if (!this.isCurrentFiscalDocument(id, session)) return;
            this.fiscalActionBusy = true;
            let updated = false;
            try {
                const response = await this.$http.post(`/documents/${id}/${action}-hka`);
                if (!this.isCurrentFiscalDocument(id, session)) return;
                const emission = response.data && response.data.fiscal_emission;
                if (!emission || !emission.status) throw new Error('Missing fiscal result');
                this.$set(this.fiscalRecord, 'fiscal_emission', emission);
                updated = true;
                this.$emit('fiscal-updated', { id, fiscal_emission: emission });
                this.$message.info(emission.diagnostic || emission.description);
                await this.refreshFiscalDocument();
            } catch (error) {
                if (!this.isCurrentFiscalDocument(id, session)) return;
                this.$message.error(updated
                    ? 'El estado fiscal se actualizó, pero no se pudo recargar el detalle del comprobante.'
                    : 'La venta está guardada. No se pudo actualizar el estado HKA.');
            } finally {
                if (this.fiscalActionSession === session) this.fiscalActionBusy = false;
            }
        },
    },
};
