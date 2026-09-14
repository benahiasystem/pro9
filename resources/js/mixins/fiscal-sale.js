import { newFiscalOperationKey } from '../helpers/fiscal-operation';
import FiscalProfileSummary from '../components/FiscalProfileSummary.vue';

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
export const fiscalSale = {
    components: { FiscalProfileSummary },
    data() { return { fiscalProfiles: [] }; },
    computed: {
        fiscalProfile() { return this.fiscalProfiles.find(profile => profile.document_type_id === this.form.document_type_id) || null; }
    },
    methods: {
        prepareFiscalSale() {
            if (this.form.document_type_id === '80') return true;
            if (!this.fiscalProfile) {
                this.$message.error('Configure la numeración y emisión fiscal de la sucursal antes de guardar.');
                return false;
            }
            if (!this.form.operation_key) this.$set(this.form, 'operation_key', newFiscalOperationKey());
            return true;
        }
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
