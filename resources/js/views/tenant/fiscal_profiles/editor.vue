<template>
    <div v-if="entityId" :class="kind === 'company' ? 'card card-config' : 'mt-3 mb-3'">
        <div v-if="kind === 'company'" class="card-header bg-info">
            <h3 class="my-0">Perfil fiscal y condición SPE</h3>
        </div>
        <component :is="kind === 'company' ? 'div' : 'el-collapse'">
            <component :is="kind === 'company' ? 'div' : 'el-collapse-item'"
                       :class="{'card-body': kind === 'company'}" title="Perfil fiscal y condición SPE" name="fiscal">
                <p>La condición SPE requiere calificación y notificación expresa del SENIAT. Las versiones utilizadas en documentos y cobros se conservan.</p>
                <p v-if="loading">Cargando perfil fiscal…</p>
                <template v-if="loaded">
                    <div class="row">
                        <div class="col-md-6 form-group"><label>RIF completo (separado de la cédula)</label><el-input v-model="profile.rif" :disabled="!canEdit" placeholder="V-12345678-9"/></div>
                        <div class="col-md-6 form-group"><label>Naturaleza jurídica</label><el-select v-model="profile.nature" :disabled="!canEdit" class="w-100"><el-option value="natural" label="Persona natural"/><el-option value="legal" label="Persona jurídica"/><el-option value="public" label="Órgano o ente público"/></el-select></div>
                        <div v-if="profile.nature === 'natural'" class="col-md-6 form-group"><el-checkbox v-model="profile.personal_firm" :disabled="!canEdit">Constituida como firma personal</el-checkbox></div>
                        <div v-if="profile.nature === 'public'" class="col-md-6 form-group"><label>Clase de ente público</label><el-select v-model="profile.public_kind" :disabled="!canEdit" class="w-100"><el-option v-for="option in publicKinds" :key="option.value" :value="option.value" :label="option.label"/></el-select></div>
                        <div class="col-md-6 form-group"><el-checkbox v-model="profile.commercial_activity" :disabled="!canEdit">Actividad comercial habilitada</el-checkbox></div>
                        <div class="col-md-6 form-group"><label>Condición frente al IVA</label><el-select v-model="profile.iva_status" :disabled="!canEdit" class="w-100"><el-option value="ordinary" label="Ordinario"/><el-option value="formal" label="Formal"/><el-option value="not_subject" label="No sujeto"/></el-select></div>
                        <div class="col-12 form-group"><el-checkbox v-model="profile.spe" :disabled="!canEdit">Sujeto Pasivo Especial</el-checkbox></div>
                        <template v-if="profile.spe">
                            <div class="col-md-6 form-group"><label>Referencia de notificación SENIAT</label><el-input v-model="profile.notification_reference" :disabled="!canEdit"/></div>
                            <div class="col-md-6 form-group"><label>Fecha de notificación</label><el-date-picker v-model="profile.notification_date" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/></div>
                        </template>
                        <div class="col-md-6 form-group"><label>Vigencia desde</label><el-date-picker v-model="profile.effective_from" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/></div>
                        <div class="col-md-6 form-group"><label>Vigencia hasta</label><el-date-picker v-model="profile.effective_until" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/></div>
                        <div class="col-md-6 form-group"><label>Revocación SPE desde</label><el-date-picker v-model="profile.revoked_at" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/></div>
                        <div class="col-md-6 form-group"><label>Facultad de agente IVA</label><el-select v-model="profile.iva_agent_basis" :disabled="!canEdit" class="w-100"><el-option value="none" label="Sin facultad acreditada"/><el-option value="SNAT/2025/000054" label="SNAT/2025/000054"/><el-option value="SNAT/2013/0029" label="Entes públicos — SNAT/2013/0029"/></el-select></div>
                        <div class="col-md-6 form-group"><el-checkbox v-model="profile.islr_agent" :disabled="!canEdit">Facultad ISLR acreditada</el-checkbox><el-input v-if="profile.islr_agent" v-model="profile.islr_basis" :disabled="!canEdit" placeholder="Fundamento ISLR independiente de SPE"/></div>
                        <div class="col-md-6 form-group"><label>Retención IVA aplicable al proveedor</label><el-select v-model="profile.iva_retention_rate" :disabled="!canEdit" class="w-100"><el-option :value="75" label="75 %"/><el-option :value="100" label="100 % con fundamento"/></el-select></div>
                        <div v-if="Number(profile.iva_retention_rate) === 100" class="col-md-6 form-group"><label>Fundamento del 100 %</label><el-select v-model="profile.iva_100_basis" :disabled="!canEdit" class="w-100"><el-option v-for="option in fullBases" :key="option.value" :value="option.value" :label="option.label"/></el-select></div>
                        <div class="col-md-6 form-group"><label>Exclusión IVA acreditada, si corresponde</label><el-input v-model="profile.iva_exclusion" :disabled="!canEdit"/></div>
                        <div class="col-md-6 form-group"><label>Referencia de evidencia fiscal</label><el-input v-model="profile.evidence_reference" :disabled="!canEdit"/></div>
                    </div>
                    <div class="text-end"><el-button v-if="canEdit" type="primary" native-type="button" :loading="saving" @click="save">Guardar nueva versión fiscal</el-button></div>
                    <p v-if="!canEdit">Modificación reservada al administrador en instalaciones con historial fiscal.</p>
                    <div v-if="kind === 'company'" class="mt-4">
                        <label class="d-block">RIF impreso de la empresa</label>
                        <p class="text-muted"><small>PDF, JPG o PNG, hasta 10 MB.</small></p>
                        <p v-if="rifDocument">Archivo actual: <a :href="`/companies/${entityId}/rif-document/download`">{{ rifDocument.name }}</a></p>
                        <p v-else-if="!rifError">No hay un RIF adjunto.</p>
                        <input ref="rifFile" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="d-none" @change="uploadRif">
                        <el-button v-if="canEdit" native-type="button" :loading="uploading" @click="$refs.rifFile.click()">Adjuntar imagen o PDF</el-button>
                        <p v-if="rifError" class="text-danger mt-2">{{ rifError }}</p>
                    </div>
                    <div class="table-responsive mt-4"><table class="table"><thead><tr><th>Versión</th><th>Vigencia</th><th>Actor</th><th>Registro</th></tr></thead><tbody><tr v-for="row in history" :key="row.id"><td>{{ row.id }}</td><td>{{ row.effective_from }}</td><td>{{ row.actor_id }}</td><td>{{ row.created_at }}</td></tr></tbody></table></div>
                </template>
                <p v-if="error" class="text-danger">{{ error }}</p>
            </component>
        </component>
    </div>
</template>
<script>
export default {
    props: ['kind', 'entityId'],
    data: () => ({loaded: false, loading: false, canEdit: false, saving: false, uploading: false, history: [], profile: {}, error: '', rifDocument: null, rifError: '',
        publicKinds: [{value:'national',label:'Órgano nacional'},{value:'state',label:'Órgano estadal'},{value:'municipal',label:'Órgano municipal'},{value:'autonomous_institute',label:'Instituto autónomo'},{value:'decentralized_non_business',label:'Ente descentralizado sin fines empresariales'},{value:'decentralized_business',label:'Ente descentralizado empresarial'}],
        fullBases: [{value:'undisclosed_iva',label:'IVA no discriminado'},{value:'invoice_formalities',label:'Incumplimiento de formalidades'},{value:'portal_100',label:'Portal fiscal: 100 %'},{value:'unregistered_rif',label:'No inscrito en RIF'},{value:'precious_metals',label:'Metales o piedras preciosas'}]}),
    watch: {entityId: {immediate: true, handler(value) {if (value) {this.load(); if (this.kind === 'company') this.loadRif()}}}},
    methods: {
        errorMessage(e, fallback) {
            const data = e.response?.data || {}
            const errors = data.errors || (typeof data.message === 'object' ? data.message : null)
            return errors ? Object.values(errors).flat().join(' ') : (typeof data.message === 'string' && data.message ? data.message : fallback)
        },
        async load() {
            this.loaded = false; this.loading = true; this.error = ''
            try {
                const {data} = await this.$http.get(`/fiscal-profiles/${this.kind}/${this.entityId}`)
                this.canEdit = data.can_edit; this.history = data.history
                this.profile = Object.assign({rif:'',nature:'legal',personal_firm:false,public_kind:null,commercial_activity:true,iva_status:'ordinary',spe:false,
                    notification_reference:null,notification_date:null,effective_from:new Date().toLocaleDateString('en-CA',{timeZone:'America/Caracas'}),effective_until:null,revoked_at:null,
                    iva_agent_basis:'none',islr_agent:false,islr_basis:null,iva_retention_rate:75,iva_100_basis:null,iva_exclusion:null,evidence_reference:null},data.data)
                this.profile.iva_retention_rate = Number(this.profile.iva_retention_rate)
                this.loaded = true
            } catch (e) {this.error = 'No se pudo cargar el perfil fiscal.'}
            finally {this.loading = false}
        },
        async loadRif() {
            this.rifDocument = null; this.rifError = ''
            try {const {data} = await this.$http.get(`/companies/${this.entityId}/rif-document`); this.rifDocument = data.data}
            catch (e) {this.rifError = 'No se pudo consultar el RIF adjunto.'}
        },
        async uploadRif(event) {
            const file = event.target.files[0]
            if (!file) return
            this.rifError = ''
            if (!['application/pdf', 'image/jpeg', 'image/png'].includes(file.type) || file.size > 10 * 1024 * 1024) {
                this.rifError = 'Seleccione un PDF, JPG o PNG de hasta 10 MB.'; event.target.value = ''; return
            }
            const body = new FormData(); body.append('file', file); this.uploading = true
            try {const {data} = await this.$http.post(`/companies/${this.entityId}/rif-document`, body); this.rifDocument = data.data; this.$message.success('RIF adjuntado correctamente.')}
            catch (e) {this.rifError = this.errorMessage(e, 'No se pudo adjuntar el RIF.')}
            finally {this.uploading = false; event.target.value = ''}
        },
        async save() {
            this.saving = true; this.error = ''
            try {await this.$http.post(`/fiscal-profiles/${this.kind}/${this.entityId}`,this.profile); await this.load(); this.$message.success('Nueva versión fiscal guardada.')}
            catch (e) {this.error = this.errorMessage(e, 'No se pudo guardar el perfil fiscal.')}
            finally {this.saving = false}
        }
    }
}
</script>
