<template>
    <div v-if="entityId" class="mt-3 mb-3">
        <el-collapse><el-collapse-item title="Perfil fiscal y condición SPE" name="fiscal">
            <p>La condición SPE requiere calificación y notificación expresa del SENIAT. Las versiones utilizadas en documentos y cobros se conservan.</p>
            <div v-if="loaded">
                <label>RIF completo (separado de la cédula)</label><el-input v-model="profile.rif" :disabled="!canEdit" placeholder="V-12345678-9"/>
                <label>Naturaleza jurídica</label><el-select v-model="profile.nature" :disabled="!canEdit"><el-option value="natural" label="Persona natural"/><el-option value="legal" label="Persona jurídica"/><el-option value="public" label="Órgano o ente público"/></el-select>
                <el-checkbox v-if="profile.nature === 'natural'" v-model="profile.personal_firm" :disabled="!canEdit">Constituida como firma personal</el-checkbox>
                <template v-if="profile.nature === 'public'"><label>Clase de ente público</label><el-select v-model="profile.public_kind" :disabled="!canEdit"><el-option v-for="option in publicKinds" :key="option.value" :value="option.value" :label="option.label"/></el-select></template>
                <el-checkbox v-model="profile.commercial_activity" :disabled="!canEdit">Actividad comercial habilitada</el-checkbox>
                <label>Condición frente al IVA</label><el-select v-model="profile.iva_status" :disabled="!canEdit"><el-option value="ordinary" label="Ordinario"/><el-option value="formal" label="Formal"/><el-option value="not_subject" label="No sujeto"/></el-select>
                <el-checkbox v-model="profile.spe" :disabled="!canEdit">Sujeto Pasivo Especial</el-checkbox>
                <template v-if="profile.spe"><label>Referencia de notificación SENIAT</label><el-input v-model="profile.notification_reference" :disabled="!canEdit"/><label>Fecha de notificación</label><el-date-picker v-model="profile.notification_date" value-format="yyyy-MM-dd" :disabled="!canEdit"/></template>
                <label>Vigencia desde</label><el-date-picker v-model="profile.effective_from" value-format="yyyy-MM-dd" :disabled="!canEdit"/>
                <label>Vigencia hasta</label><el-date-picker v-model="profile.effective_until" value-format="yyyy-MM-dd" :disabled="!canEdit"/>
                <label>Revocación SPE desde</label><el-date-picker v-model="profile.revoked_at" value-format="yyyy-MM-dd" :disabled="!canEdit"/>
                <label>Facultad de agente IVA</label><el-select v-model="profile.iva_agent_basis" :disabled="!canEdit"><el-option value="none" label="Sin facultad acreditada"/><el-option value="SNAT/2025/000054" label="SNAT/2025/000054"/><el-option value="SNAT/2013/0029" label="Entes públicos — SNAT/2013/0029"/></el-select>
                <el-checkbox v-model="profile.islr_agent" :disabled="!canEdit">Facultad ISLR acreditada</el-checkbox><el-input v-if="profile.islr_agent" v-model="profile.islr_basis" :disabled="!canEdit" placeholder="Fundamento ISLR independiente de SPE"/>
                <label>Retención IVA aplicable al proveedor</label><el-select v-model="profile.iva_retention_rate" :disabled="!canEdit"><el-option :value="75" label="75 %"/><el-option :value="100" label="100 % con fundamento"/></el-select>
                <el-select v-if="profile.iva_retention_rate === 100" v-model="profile.iva_100_basis" :disabled="!canEdit" placeholder="Fundamento del 100 %"><el-option v-for="option in fullBases" :key="option.value" :value="option.value" :label="option.label"/></el-select>
                <label>Exclusión IVA acreditada, si corresponde</label><el-input v-model="profile.iva_exclusion" :disabled="!canEdit"/>
                <label>Referencia de evidencia fiscal</label><el-input v-model="profile.evidence_reference" :disabled="!canEdit"/>
                <el-button v-if="canEdit" type="primary" :loading="saving" @click="save">Guardar nueva versión fiscal</el-button>
                <p v-else>Modificación reservada al administrador en instalaciones con historial fiscal.</p>
                <p v-if="error" class="text-danger">{{ error }}</p>
                <table class="table"><thead><tr><th>Versión</th><th>Vigencia</th><th>Actor</th><th>Registro</th></tr></thead><tbody><tr v-for="row in history" :key="row.id"><td>{{ row.id }}</td><td>{{ row.effective_from }}</td><td>{{ row.actor_id }}</td><td>{{ row.created_at }}</td></tr></tbody></table>
            </div>
        </el-collapse-item></el-collapse>
    </div>
</template>
<script>
export default {
    props: ['kind', 'entityId'],
    data: () => ({loaded: false, canEdit: false, saving: false, history: [], profile: {}, error: '',
        publicKinds: [{value:'national',label:'Órgano nacional'},{value:'state',label:'Órgano estadal'},{value:'municipal',label:'Órgano municipal'},{value:'autonomous_institute',label:'Instituto autónomo'},{value:'decentralized_non_business',label:'Ente descentralizado sin fines empresariales'},{value:'decentralized_business',label:'Ente descentralizado empresarial'}],
        fullBases: [{value:'undisclosed_iva',label:'IVA no discriminado'},{value:'invoice_formalities',label:'Incumplimiento de formalidades'},{value:'portal_100',label:'Portal fiscal: 100 %'},{value:'unregistered_rif',label:'No inscrito en RIF'},{value:'precious_metals',label:'Metales o piedras preciosas'}]}),
    watch: {entityId: {immediate: true, handler(value) {if (value) this.load()}}},
    methods: {
        async load() {
            this.loaded = false
            try {
                const {data} = await this.$http.get(`/fiscal-profiles/${this.kind}/${this.entityId}`)
                this.canEdit = data.can_edit; this.history = data.history
                this.profile = Object.assign({rif:'',nature:'legal',personal_firm:false,public_kind:null,commercial_activity:true,iva_status:'ordinary',spe:false,
                    notification_reference:null,notification_date:null,effective_from:new Date().toLocaleDateString('en-CA',{timeZone:'America/Caracas'}),effective_until:null,revoked_at:null,
                    iva_agent_basis:'none',islr_agent:false,islr_basis:null,iva_retention_rate:75,iva_100_basis:null,iva_exclusion:null,evidence_reference:null},data.data)
                this.loaded = true
            } catch (e) {this.error = 'No se pudo cargar el perfil fiscal.'; this.loaded = true}
        },
        async save() {
            this.saving = true; this.error = ''
            try {await this.$http.post(`/fiscal-profiles/${this.kind}/${this.entityId}`,this.profile); await this.load(); this.$message.success('Nueva versión fiscal guardada.')}
            catch (e) {this.error = e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : 'No se pudo guardar el perfil fiscal.'}
            finally {this.saving = false}
        }
    }
}
</script>
