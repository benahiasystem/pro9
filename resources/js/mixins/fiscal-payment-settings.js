import {igtfSettings, loadIgtfSettings, fiscalPaymentProblem} from '../helpers/igtf-settings'
import IgtfSettingsStatus from '../components/IgtfSettingsStatus.vue'

// ######## INICIO CONFIGURACION COMPARTIDA IGTF ########
export default {
    components: {IgtfSettingsStatus},
    data: () => ({igtfSettings, igtfPreparingSubmission: false}),
    computed: {
        fiscalPaymentsApplicable() {
            const type = this.documentTypeId || (this.form && this.form.document_type_id) || (this.document && this.document.document_type_id)
            return ['01', '08'].includes(type)
        },
        igtfEnabled() { return this.igtfSettings.loaded && this.igtfSettings.enabled },
    },
    created() { if (this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
    watch: {
        fiscalPaymentsApplicable(value) { if (value) this.refreshIgtfSettings() },
        showDialog(value) { if (value && this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
        is_payment(value) { if (value && this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
        documentId() { if (this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
    },
    methods: {
        refreshIgtfSettings() { return loadIgtfSettings(this.$http) },
        async ensureFiscalPaymentsReady(payments) {
            if (!this.fiscalPaymentsApplicable) return true
            if (this.igtfPreparingSubmission || this.loading_submit) return false
            this.igtfPreparingSubmission = true
            const form = this.form
            const documentId = this.documentId
            try {
                await this.refreshIgtfSettings()
                if (this.form !== form || this.documentId !== documentId || this.showDialog === false || this.is_payment === false) return false
                const problem = fiscalPaymentProblem(payments)
                if (problem) { this.$message.error(problem); return false }
                return true
            } finally {
                this.igtfPreparingSubmission = false
            }
        },
    },
}
// ######## FIN CONFIGURACION COMPARTIDA IGTF ########
