export default {
    computed: {
        invoicePrepaymentScope() {
            const {customer_id, currency_type_id, establishment_id} = this.form || {}
            if (!customer_id || !currency_type_id || !establishment_id) return null
            return JSON.stringify({customer_id:String(customer_id), currency_type_id, establishment_id:String(establishment_id)})
        },
    },
    watch: {
        invoicePrepaymentScope() {
            if (!this.prepayment_deduction) return
            this.resetInvoicePrepaymentOptions()
            this.clearInvoicePrepaymentRows()
            this.changeTotalPrepayment()
            this.getDocumentsPrepayment()
        },
    },
    methods: {
        clearInvoicePrepaymentRows() {
            this.form.prepayments = []
            this.form.total_prepayment = '0'
            this.form.discounts = (this.form.discounts || []).filter(row => !['04', '05', '06'].includes(row.discount_type_id))
        },
        resetInvoicePrepaymentOptions() {
            this._invoicePrepaymentRequest = null
            this.prepayment_documents = []
        },
        async getDocumentsPrepayment() {
            this.resetInvoicePrepaymentOptions()
            const form = this.form
            const scope = this.invoicePrepaymentScope
            const affectation = form?.affectation_type_prepayment
            if (!this.prepayment_deduction || !scope || !affectation) return

            const token = Symbol()
            this._invoicePrepaymentRequest = token
            const isCurrent = () => this._invoicePrepaymentRequest === token && this.form === form &&
                this.prepayment_deduction && this.invoicePrepaymentScope === scope && form.affectation_type_prepayment === affectation
            try {
                const {data} = await this.$http.get(`/${this.resource}/prepayments/${affectation}`, {params:JSON.parse(scope)})
                if (isCurrent()) this.prepayment_documents = data
            } catch (error) {
                if (isCurrent()) this.$message.error(error.response?.data?.message || 'No se pudieron consultar los anticipos.')
            }
        },
    },
}
