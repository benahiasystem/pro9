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
                        <div class="col-md-6 form-group"><label>RIF completo (separado de la cédula) <el-tooltip content="Indique el RIF completo con guiones, por ejemplo J-12345678-9. Para empresas debe coincidir con el RIF registrado." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre rif"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.rif" :disabled="!canEdit" placeholder="V-12345678-9"/><p v-if="fieldErrors.rif" class="fiscal-field-error" role="alert">{{ fieldErrors.rif }}</p></div>
                        <div class="col-md-6 form-group"><label>Naturaleza jurídica <el-tooltip content="Seleccione la naturaleza de la persona o entidad titular del RIF." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre nature"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.nature" :disabled="!canEdit" class="w-100"><el-option value="natural" label="Persona natural"/><el-option value="legal" label="Persona jurídica"/><el-option value="public" label="Órgano o ente público"/></el-select><p v-if="fieldErrors.nature" class="fiscal-field-error" role="alert">{{ fieldErrors.nature }}</p></div>
                        <div v-if="profile.nature === 'natural'" class="col-md-6 form-group"><el-checkbox v-model="profile.personal_firm" :disabled="!canEdit">Constituida como firma personal</el-checkbox> <el-tooltip content="Marque esta opción si la persona natural está constituida como firma personal." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre personal firm"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><p v-if="fieldErrors.personal_firm" class="fiscal-field-error" role="alert">{{ fieldErrors.personal_firm }}</p></div>
                        <div v-if="profile.nature === 'public'" class="col-md-6 form-group"><label>Clase de ente público <el-tooltip content="Seleccione la clase de órgano o ente público." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre public kind"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.public_kind" :disabled="!canEdit" class="w-100"><el-option v-for="option in publicKinds" :key="option.value" :value="option.value" :label="option.label"/></el-select><p v-if="fieldErrors.public_kind" class="fiscal-field-error" role="alert">{{ fieldErrors.public_kind }}</p></div>
                        <div class="col-md-6 form-group"><el-checkbox v-model="profile.commercial_activity" :disabled="!canEdit">Actividad comercial habilitada</el-checkbox> <el-tooltip content="Indique si la actividad comercial está habilitada. Es necesaria para el emisor." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre commercial activity"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><p v-if="fieldErrors.commercial_activity" class="fiscal-field-error" role="alert">{{ fieldErrors.commercial_activity }}</p></div>
                        <div class="col-md-6 form-group"><label>Condición frente al IVA <el-tooltip content="Seleccione la condición del titular frente al IVA según su documentación fiscal." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva status"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_status" :disabled="!canEdit" class="w-100"><el-option value="ordinary" label="Ordinario"/><el-option value="formal" label="Formal"/><el-option value="not_subject" label="No sujeto"/></el-select><p v-if="fieldErrors.iva_status" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_status }}</p></div>
                        <div class="col-12 form-group"><el-checkbox v-model="profile.spe" :disabled="!canEdit">Sujeto Pasivo Especial</el-checkbox> <el-tooltip content="Marque solo si existe calificación y notificación expresa del SENIAT." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre spe"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><p v-if="fieldErrors.spe" class="fiscal-field-error" role="alert">{{ fieldErrors.spe }}</p></div>
                        <template v-if="profile.spe">
                            <div class="col-md-6 form-group"><label>Referencia de notificación SENIAT <el-tooltip content="Indique la referencia de la notificación de calificación como sujeto pasivo especial." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre notification reference"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.notification_reference" :disabled="!canEdit"/><p v-if="fieldErrors.notification_reference" class="fiscal-field-error" role="alert">{{ fieldErrors.notification_reference }}</p></div>
                            <div class="col-md-6 form-group"><label>Fecha de notificación <el-tooltip content="Indique la fecha de notificación; debe ser anterior o igual al inicio de vigencia." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre notification date"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.notification_date" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.notification_date" class="fiscal-field-error" role="alert">{{ fieldErrors.notification_date }}</p></div>
                        </template>
                        <div class="col-md-6 form-group"><label>Vigencia desde <el-tooltip content="Fecha desde la que comienza a aplicarse esta versión del perfil fiscal." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre effective from"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.effective_from" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.effective_from" class="fiscal-field-error" role="alert">{{ fieldErrors.effective_from }}</p></div>
                        <div class="col-md-6 form-group"><label>Vigencia hasta <el-tooltip content="Opcional. Fecha final de vigencia del perfil, si se conoce." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre effective until"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.effective_until" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.effective_until" class="fiscal-field-error" role="alert">{{ fieldErrors.effective_until }}</p></div>
                        <div class="col-md-6 form-group"><label>Revocación SPE desde <el-tooltip content="Opcional. Fecha desde la que fue revocada la condición SPE, si corresponde." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre revoked at"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.revoked_at" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.revoked_at" class="fiscal-field-error" role="alert">{{ fieldErrors.revoked_at }}</p></div>
                        <div class="col-md-6 form-group"><label>Facultad de agente IVA <el-tooltip content="Seleccione la facultad acreditada. Si no dispone de ella, seleccione Sin facultad acreditada." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva agent basis"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_agent_basis" :disabled="!canEdit" class="w-100"><el-option value="none" label="Sin facultad acreditada"/><el-option value="SNAT/2025/000054" label="SNAT/2025/000054"/><el-option value="SNAT/2013/0029" label="Entes públicos — SNAT/2013/0029"/></el-select><p v-if="fieldErrors.iva_agent_basis" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_agent_basis }}</p></div>
                        <div class="col-md-6 form-group"><el-checkbox v-model="profile.islr_agent" :disabled="!canEdit">Facultad ISLR acreditada</el-checkbox> <el-tooltip content="Marque si la facultad de agente ISLR está acreditada e indique su fundamento independiente de SPE." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre islr agent"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><el-input v-if="profile.islr_agent" v-model="profile.islr_basis" :disabled="!canEdit" placeholder="Fundamento ISLR independiente de SPE"/><p v-if="fieldErrors.islr_agent" class="fiscal-field-error" role="alert">{{ fieldErrors.islr_agent }}</p><p v-if="fieldErrors.islr_basis" class="fiscal-field-error" role="alert">{{ fieldErrors.islr_basis }}</p></div>
                        <div class="col-md-6 form-group"><label>Retención IVA aplicable al proveedor <el-tooltip content="Porcentaje aplicable al proveedor. El 100 % requiere fundamento y evidencia; elegir un porcentaje no otorga facultad de agente." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva retention rate"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_retention_rate" :disabled="!canEdit" class="w-100"><el-option :value="75" label="75 %"/><el-option :value="100" label="100 % con fundamento"/></el-select><p v-if="fieldErrors.iva_retention_rate" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_retention_rate }}</p></div>
                        <div v-if="Number(profile.iva_retention_rate) === 100" class="col-md-6 form-group"><label>Fundamento del 100 %</label><el-select v-model="profile.iva_100_basis" :disabled="!canEdit" class="w-100"><el-option v-for="option in fullBases" :key="option.value" :value="option.value" :label="option.label"/></el-select></div>
                        <div class="col-md-6 form-group"><label>Exclusión IVA acreditada, si corresponde <el-tooltip content="Opcional. Indique la exclusión de retención IVA acreditada, si corresponde." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva exclusion"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.iva_exclusion" :disabled="!canEdit"/><p v-if="fieldErrors.iva_exclusion" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_exclusion }}</p></div>
                        <div class="col-md-6 form-group"><label>Referencia de evidencia fiscal <el-tooltip content="Referencia del documento que sustenta la condición fiscal; es necesaria para ciertas facultades y para el 100 %." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre evidence reference"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.evidence_reference" :disabled="!canEdit"/><p v-if="fieldErrors.evidence_reference" class="fiscal-field-error" role="alert">{{ fieldErrors.evidence_reference }}</p></div>
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
    data: () => ({loaded: false, loading: false, canEdit: false, saving: false, uploading: false, history: [], profile: {}, fieldErrors: {}, error: '', rifDocument: null, rifError: '',
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
        validateProfile() {
            const p = this.profile
            const errors = {}
            const required = (key, message) => { if (p[key] === null || p[key] === undefined || p[key] === '') errors[key] = message }
            if (!/^[VJEGCR]-[0-9]{8}-[0-9]$/.test(p.rif || '')) errors.rif = 'Indique el RIF con el formato J-12345678-9.'
            const prefixes = {natural: ['V', 'E', 'R'], legal: ['J', 'C'], public: ['G', 'J']}
            if (!errors.rif && prefixes[p.nature] && !prefixes[p.nature].includes(p.rif[0])) errors.rif = 'El RIF debe corresponder a la naturaleza declarada.'
            required('nature', 'Seleccione la naturaleza jurídica.')
            required('iva_status', 'Seleccione la condición frente al IVA.')
            required('effective_from', 'Indique la fecha de inicio de vigencia.')
            required('iva_agent_basis', 'Seleccione la facultad de agente IVA.')
            required('iva_retention_rate', 'Seleccione el porcentaje de retención IVA.')
            if (this.kind === 'company' && !p.commercial_activity) errors.commercial_activity = 'El emisor requiere actividad comercial habilitada.'
            if (p.nature === 'public') required('public_kind', 'Seleccione la clase de ente público.')
            if (p.spe) {
                required('notification_reference', 'Indique la referencia de la notificación SENIAT.')
                required('notification_date', 'Indique la fecha de notificación.')
                if (p.notification_date && p.effective_from && p.notification_date > p.effective_from) errors.notification_date = 'La notificación debe ser anterior o igual al inicio de vigencia.'
            }
            if (p.effective_until && p.effective_from && p.effective_until < p.effective_from) errors.effective_until = 'La fecha final no puede ser anterior al inicio de vigencia.'
            if (p.revoked_at && p.effective_from && p.revoked_at < p.effective_from) errors.revoked_at = 'La revocación no puede ser anterior al inicio de vigencia.'
            const publicSpecific = p.nature === 'public' && p.public_kind !== 'decentralized_business'
            if (p.iva_agent_basis === 'SNAT/2025/000054' && (!p.spe || (p.nature === 'natural' && !p.personal_firm) || publicSpecific)) errors.iva_agent_basis = 'Esta facultad requiere un perfil SPE compatible. Si no está acreditada, seleccione Sin facultad acreditada.'
            if (p.iva_agent_basis === 'SNAT/2013/0029' && p.nature !== 'public') errors.iva_agent_basis = 'Esta facultad corresponde a entes públicos.'
            if (p.iva_agent_basis === 'SNAT/2013/0029' && !String(p.evidence_reference || '').trim()) errors.evidence_reference = 'Indique la referencia del fundamento de la facultad pública.'
            if (p.islr_agent && !String(p.islr_basis || '').trim()) errors.islr_basis = 'Indique el fundamento de la facultad ISLR.'
            if (Number(p.iva_retention_rate) === 100) {
                required('iva_100_basis', 'Seleccione el fundamento del 100 %.')
                if (!String(p.evidence_reference || '').trim()) errors.evidence_reference = 'Indique la referencia de evidencia del 100 %.'
            }
            this.fieldErrors = errors
            return Object.keys(errors).length === 0
        },
        async save() {
            this.error = ''
            if (!this.validateProfile()) { this.error = 'Revise los campos indicados antes de guardar.'; return }
            this.saving = true
            try {await this.$http.post(`/fiscal-profiles/${this.kind}/${this.entityId}`,this.profile); await this.load(); this.$message.success('Nueva versión fiscal guardada.')}
            catch (e) {
                const data = e.response?.data || {}
                const errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : null) || (e.response?.status === 422 ? data : null)
                if (errors && typeof errors === 'object') {
                    this.fieldErrors = Object.fromEntries(Object.entries(errors).filter(([key]) => !['success', 'message'].includes(key)).map(([key, value]) => [key, Array.isArray(value) ? value.join(' ') : String(value)]))
                    this.error = Object.values(this.fieldErrors).join(' ') || this.errorMessage(e, 'No se pudo guardar el perfil fiscal.')
                } else this.error = this.errorMessage(e, 'No se pudo guardar el perfil fiscal.')
            }
            finally {this.saving = false}
        }
    }
}
</script>

<style scoped>
.fiscal-help { border: 0; background: transparent; color: #73849e; padding: 0 3px; cursor: help; }
.fiscal-help:focus { outline: 2px solid #73849e; outline-offset: 2px; }
.fiscal-field-error { color: #c0392b; font-size: 0.875rem; margin: 4px 0 0; }
</style>
