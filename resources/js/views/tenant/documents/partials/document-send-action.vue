<script>
// ######## INICIO ENVIO HKA DESDE ACCIONES DEL LISTADO ########
import {documentFiscal} from '@mixins/document-fiscal';

export default {
    data() {
        return {...documentFiscal.data(), loading:false, active:false, documentId:null, record:null};
    },
    computed: {
        recordId() { return this.documentId; },
        fiscalRecord() { return this.record; },
        fiscalActionVisible() { return this.active; },
    },
    beforeDestroy() { this.active=false; this.resetFiscalAction(); },
    methods: {
        ...documentFiscal.methods,
        async open(documentId) {
            if (!documentId || this.active) return;
            this.resetFiscalAction();
            const session=this.fiscalActionSession;
            this.documentId=documentId; this.record=null; this.active=true; this.loading=true;
            try {
                const {data}=await this.$http.get(`/documents/record/${documentId}`);
                if (!this.active || session!==this.fiscalActionSession) return;
                this.record=data.data; this.loading=false;
                if (!this.record?.fiscal_emission?.can_send) {
                    this.$message.warning(this.record?.fiscal_emission?.diagnostic || 'El comprobante no está disponible para envío HKA.');
                    return;
                }
                await this.runFiscalAction('send');
            } catch (error) {
                if (this.active && session===this.fiscalActionSession) this.$message.error('No se pudo consultar el comprobante para enviarlo. Actualice el listado e intente nuevamente.');
            } finally {
                if (session===this.fiscalActionSession) { this.loading=false; this.active=false; }
            }
        },
        async refreshFiscalDocument() {
            const id=this.documentId,session=this.fiscalActionSession;
            const {data}=await this.$http.get(`/documents/record/${id}`);
            if (this.isCurrentFiscalDocument(id,session)) this.record=data.data;
        },
    },
    render() { return null; },
};
// ######## FIN ENVIO HKA DESDE ACCIONES DEL LISTADO ########
</script>
