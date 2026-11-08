<template>
    <el-dialog title="Registrar control impreso" :visible="visible" @close="close">
        <p v-if="loading">Consultando documento…</p>
        <template v-else-if="record">
            <p><strong>Documento:</strong> {{ record.number_full }}</p>
            <p v-if="record.can_register">Transcriba el control de la nota física y conserve la referencia de su revisión.</p>
            <p v-else>El documento ya no está disponible para registrar un control.</p>
            <form v-if="record.can_register" @submit.prevent="submit">
                <label>Control impreso</label><el-input v-model="form.control_number" placeholder="00-00000001"/>
                <label>Referencia de verificación</label><el-input v-model="form.evidence_reference" placeholder="Referencia de la nota física"/>
                <el-button native-type="submit" type="primary" :loading="saving" :disabled="saving">Registrar control</el-button>
            </form>
        </template>
        <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
        <el-button @click="close">Cerrar</el-button>
    </el-dialog>
</template>
<script>
export default {
    data: () => ({visible:false,loading:false,saving:false,documentId:null,record:null,error:'',sequence:0,
        form:{control_number:'',evidence_reference:''}}),
    beforeDestroy() { this.sequence++; },
    methods: {
        async open(id) {
            if (!id || this.saving) return;
            const sequence=++this.sequence;
            this.visible=true; this.loading=true; this.documentId=id; this.record=null; this.error='';
            this.form={control_number:'',evidence_reference:''};
            try {
                const response=await this.$http.get(`/documents/${id}/free-form-control`);
                if (sequence===this.sequence && this.visible) this.record=response.data.data;
            } catch (error) { if (sequence===this.sequence && this.visible) this.error='No se pudo consultar el documento.'; }
            finally { if (sequence===this.sequence) this.loading=false; }
        },
        close() { this.sequence++; this.visible=false; this.loading=false; this.saving=false; this.record=null; this.form={control_number:'',evidence_reference:''}; },
        async submit() {
            if (!this.visible || !this.record?.can_register || this.loading || this.saving) return;
            const sequence=this.sequence, id=this.documentId;
            this.saving=true; this.error='';
            try {
                await this.$http.post(`/documents/${id}/free-form-control`,{...this.form});
                if (sequence!==this.sequence || !this.visible) return;
                this.$emit('saved',id); this.close();
            } catch (error) {
                if (sequence!==this.sequence || !this.visible) return;
                const body=error.response?.data || {};
                const errors=body.errors || (body.message && typeof body.message==='object' ? body.message : {});
                this.error=Object.values(errors).flat().join(' ') || (typeof body.message==='string' ? body.message : 'No se pudo registrar el control.');
            } finally { if (sequence===this.sequence) this.saving=false; }
        },
    },
};
</script>
