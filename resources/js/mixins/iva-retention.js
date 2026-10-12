export default {
    computed: {
        ivaCollectionTarget() {
            const forecast = this.form.iva_retention_prevision;
            const amount = this.form.document_type_id === '01' && forecast && forecast.enabled ? Number(forecast.amount || 0) : 0;
            return Math.round(Math.max(0, Number(this.form.total || 0) - amount) * 100) / 100;
        },
    },
    watch: { ivaCollectionTarget() { if (typeof this.inputAmount === 'function') this.inputAmount(); } },
    methods: {
        applyIvaNetCollection(amount) {
            // This explicit user action changes the suggested collection, never the invoice total.
            if (this.isUpdateDocument) return;
            if ((this.form.payments || []).length > 1) {
                this.$message.info('Conserve los abonos existentes y ajuste el importe que desea cobrar.');
                return;
            }
            if (typeof this.setAmount === 'function') this.setAmount(amount);
            if (this.form_payment) this.$set(this.form_payment,'payment',amount);
            const row = (this.form.payments || [])[0];
            if (row) {
                if (row.currency_type_id && row.currency_type_id !== this.form.currency_type_id) {
                    this.$message.info('Ajuste el abono en su moneda de recepción.');
                    return;
                }
                this.$set(row,'payment',amount);
                this.$set(row,'original_amount',amount);
                if (row.igtf_status === 'subject') this.$message.info('Revise el IGTF del importe efectivamente recibido.');
            }
        },
    },
}
