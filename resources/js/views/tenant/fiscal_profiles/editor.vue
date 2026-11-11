<template>
    <div v-if="entityId" :class="kind === 'company' ? 'card card-config' : 'mt-3 mb-3'">
        <div v-if="kind === 'company'" class="card-header bg-info">
            <h3 class="my-0">Perfil fiscal y condición SPE</h3>
        </div>
        <component :is="kind === 'company' ? 'div' : 'el-collapse'" v-model="openPanels">
            <component :is="kind === 'company' ? 'div' : 'el-collapse-item'"
                       :class="{'card-body': kind === 'company'}" title="Perfil fiscal y condición SPE" name="fiscal">
                <p>La condición SPE requiere calificación y notificación expresa del SENIAT. Las versiones utilizadas en documentos y cobros se conservan.</p>
                <p v-if="loading">Cargando perfil fiscal…</p>
                <template v-if="loaded">
                    <p class="fiscal-field-status">Los campos marcados como «opcional» pueden dejarse vacíos. Las casillas se marcan solo cuando la condición corresponda.</p>
                    <div v-if="error" class="fiscal-error-summary" role="alert">{{ error }}</div>
                    <div class="row">
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.rif}" data-fiscal-field="rif"><label>RIF completo (separado de la cédula) <el-tooltip content="Indique el RIF completo con guiones, por ejemplo J-12345678-9. Para empresas debe coincidir con el RIF registrado." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre rif"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.rif" @change="onFieldChange('rif')" :aria-invalid="Boolean(fieldErrors.rif)" :aria-describedby="fieldErrors.rif ? 'fiscal-error-rif' : null" :disabled="!canEdit" placeholder="V-12345678-9"/><p v-if="fieldErrors.rif" id="fiscal-error-rif" class="fiscal-field-error" role="alert">{{ fieldErrors.rif }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.nature}" data-fiscal-field="nature"><label>Naturaleza jurídica <el-tooltip content="Seleccione la naturaleza de la persona o entidad titular del RIF." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre nature"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.nature" @change="onFieldChange('nature')" :aria-invalid="Boolean(fieldErrors.nature)" :aria-describedby="fieldErrors.nature ? 'fiscal-error-nature' : null" :disabled="!canEdit" class="w-100"><el-option value="natural" label="Persona natural"/><el-option value="legal" label="Persona jurídica"/><el-option value="public" label="Órgano o ente público"/></el-select><p v-if="fieldErrors.nature" id="fiscal-error-nature" class="fiscal-field-error" role="alert">{{ fieldErrors.nature }}</p></div>
                        <div v-if="profile.nature === 'natural' || profile.personal_firm" class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.personal_firm}" data-fiscal-field="personal_firm"><el-checkbox v-model="profile.personal_firm" @change="onFieldChange('personal_firm')" :aria-invalid="Boolean(fieldErrors.personal_firm)" :aria-describedby="fieldErrors.personal_firm ? 'fiscal-error-personal_firm' : null" :disabled="!canEdit">Constituida como firma personal</el-checkbox> <el-tooltip content="Marque esta opción si la persona natural está constituida como firma personal." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre personal firm"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><p v-if="fieldErrors.personal_firm" id="fiscal-error-personal_firm" class="fiscal-field-error" role="alert">{{ fieldErrors.personal_firm }}</p></div>
                        <div v-if="profile.nature === 'public' || profile.public_kind" class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.public_kind}" data-fiscal-field="public_kind"><label>Clase de ente público <span class="fiscal-field-status">(obligatorio para entes públicos)</span> <el-tooltip content="Seleccione la clase de órgano o ente público." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre public kind"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select clearable v-model="profile.public_kind" @change="onFieldChange('public_kind')" :aria-invalid="Boolean(fieldErrors.public_kind)" :aria-describedby="fieldErrors.public_kind ? 'fiscal-error-public_kind' : null" :disabled="!canEdit" class="w-100"><el-option v-for="option in publicKinds" :key="option.value" :value="option.value" :label="option.label"/></el-select><p v-if="fieldErrors.public_kind" id="fiscal-error-public_kind" class="fiscal-field-error" role="alert">{{ fieldErrors.public_kind }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.commercial_activity}" data-fiscal-field="commercial_activity"><el-checkbox v-model="profile.commercial_activity" @change="onFieldChange('commercial_activity')" :aria-invalid="Boolean(fieldErrors.commercial_activity)" :aria-describedby="fieldErrors.commercial_activity ? 'fiscal-error-commercial_activity' : null" :disabled="!canEdit">Actividad comercial habilitada</el-checkbox> <el-tooltip content="Indique si la actividad comercial está habilitada. Es necesaria para el emisor." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre commercial activity"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><p v-if="fieldErrors.commercial_activity" id="fiscal-error-commercial_activity" class="fiscal-field-error" role="alert">{{ fieldErrors.commercial_activity }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.iva_status}" data-fiscal-field="iva_status"><label>Condición frente al IVA <el-tooltip content="Seleccione la condición del titular frente al IVA según su documentación fiscal." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva status"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_status" @change="onFieldChange('iva_status')" :aria-invalid="Boolean(fieldErrors.iva_status)" :aria-describedby="fieldErrors.iva_status ? 'fiscal-error-iva_status' : null" :disabled="!canEdit" class="w-100"><el-option value="ordinary" label="Ordinario"/><el-option value="formal" label="Formal"/><el-option value="not_subject" label="No sujeto"/></el-select><p v-if="fieldErrors.iva_status" id="fiscal-error-iva_status" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_status }}</p></div>
                        <div class="col-12 form-group" :class="{'fiscal-invalid': fieldErrors.spe}" data-fiscal-field="spe"><el-checkbox v-model="profile.spe" @change="onFieldChange('spe')" :aria-invalid="Boolean(fieldErrors.spe)" :aria-describedby="fieldErrors.spe ? 'fiscal-error-spe' : null" :disabled="!canEdit">Sujeto Pasivo Especial</el-checkbox> <el-tooltip content="Marque solo si existe calificación y notificación expresa del SENIAT." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre spe"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><p v-if="fieldErrors.spe" id="fiscal-error-spe" class="fiscal-field-error" role="alert">{{ fieldErrors.spe }}</p></div>
                        <template v-if="profile.spe || fieldErrors.notification_reference || fieldErrors.notification_date">
                            <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.notification_reference}" data-fiscal-field="notification_reference"><label>Referencia de notificación SENIAT <span class="fiscal-field-status">{{ profile.spe ? '(obligatorio para SPE)' : '(opcional)' }}</span> <el-tooltip content="Indique la referencia de la notificación de calificación como sujeto pasivo especial." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre notification reference"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.notification_reference" @change="onFieldChange('notification_reference')" :aria-invalid="Boolean(fieldErrors.notification_reference)" :aria-describedby="fieldErrors.notification_reference ? 'fiscal-error-notification_reference' : null" :disabled="!canEdit"/><p v-if="fieldErrors.notification_reference" id="fiscal-error-notification_reference" class="fiscal-field-error" role="alert">{{ fieldErrors.notification_reference }}</p></div>
                            <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.notification_date}" data-fiscal-field="notification_date"><label>Fecha de notificación <span class="fiscal-field-status">{{ profile.spe ? '(obligatorio para SPE)' : '(opcional)' }}</span> <el-tooltip content="Indique la fecha de notificación; debe ser anterior o igual al inicio de vigencia." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre notification date"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.notification_date" @change="onFieldChange('notification_date')" :aria-invalid="Boolean(fieldErrors.notification_date)" :aria-describedby="fieldErrors.notification_date ? 'fiscal-error-notification_date' : null" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.notification_date" id="fiscal-error-notification_date" class="fiscal-field-error" role="alert">{{ fieldErrors.notification_date }}</p></div>
                        </template>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.effective_from}" data-fiscal-field="effective_from"><label>Vigencia desde <el-tooltip content="Fecha desde la que comienza a aplicarse esta versión del perfil fiscal." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre effective from"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.effective_from" @change="onFieldChange('effective_from')" :aria-invalid="Boolean(fieldErrors.effective_from)" :aria-describedby="fieldErrors.effective_from ? 'fiscal-error-effective_from' : null" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.effective_from" id="fiscal-error-effective_from" class="fiscal-field-error" role="alert">{{ fieldErrors.effective_from }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.effective_until}" data-fiscal-field="effective_until"><label>Vigencia hasta <span class="fiscal-field-status">(opcional)</span> <el-tooltip content="Opcional. Fecha final de vigencia del perfil, si se conoce." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre effective until"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.effective_until" @change="onFieldChange('effective_until')" :aria-invalid="Boolean(fieldErrors.effective_until)" :aria-describedby="fieldErrors.effective_until ? 'fiscal-error-effective_until' : null" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.effective_until" id="fiscal-error-effective_until" class="fiscal-field-error" role="alert">{{ fieldErrors.effective_until }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.revoked_at}" data-fiscal-field="revoked_at"><label>Revocación SPE desde <span class="fiscal-field-status">(opcional)</span> <el-tooltip content="Opcional. Fecha desde la que fue revocada la condición SPE, si corresponde." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre revoked at"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-date-picker v-model="profile.revoked_at" @change="onFieldChange('revoked_at')" :aria-invalid="Boolean(fieldErrors.revoked_at)" :aria-describedby="fieldErrors.revoked_at ? 'fiscal-error-revoked_at' : null" value-format="yyyy-MM-dd" :disabled="!canEdit" class="w-100"/><p v-if="fieldErrors.revoked_at" id="fiscal-error-revoked_at" class="fiscal-field-error" role="alert">{{ fieldErrors.revoked_at }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.iva_agent_basis}" data-fiscal-field="iva_agent_basis"><label>Facultad de agente IVA <el-tooltip content="Seleccione la facultad acreditada. Si no dispone de ella, seleccione Sin facultad acreditada." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva agent basis"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_agent_basis" @change="onFieldChange('iva_agent_basis')" :aria-invalid="Boolean(fieldErrors.iva_agent_basis)" :aria-describedby="fieldErrors.iva_agent_basis ? 'fiscal-error-iva_agent_basis' : null" :disabled="!canEdit" class="w-100"><el-option value="none" label="Sin facultad acreditada"/><el-option value="SNAT/2025/000054" label="SNAT/2025/000054"/><el-option value="SNAT/2013/0029" label="Entes públicos — SNAT/2013/0029"/></el-select><p v-if="fieldErrors.iva_agent_basis" id="fiscal-error-iva_agent_basis" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_agent_basis }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.islr_agent || fieldErrors.islr_basis}" data-fiscal-field="islr_agent"><el-checkbox v-model="profile.islr_agent" @change="onFieldChange('islr_agent')" :aria-invalid="Boolean(fieldErrors.islr_agent)" :aria-describedby="fieldErrors.islr_agent ? 'fiscal-error-islr_agent' : null" :disabled="!canEdit">Facultad ISLR acreditada</el-checkbox> <el-tooltip content="Marque si la facultad de agente ISLR está acreditada e indique su fundamento independiente de SPE." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre islr agent"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip><label v-if="profile.islr_agent || fieldErrors.islr_basis" class="d-block mt-2">Fundamento ISLR <span class="fiscal-field-status">{{ profile.islr_agent ? '(obligatorio al acreditar ISLR)' : '(opcional)' }}</span></label><el-input v-if="profile.islr_agent || fieldErrors.islr_basis" v-model="profile.islr_basis" @change="onFieldChange('islr_basis')" :aria-invalid="Boolean(fieldErrors.islr_basis)" :aria-describedby="fieldErrors.islr_basis ? 'fiscal-error-islr_basis' : null" :disabled="!canEdit" placeholder="Fundamento ISLR independiente de SPE"/><p v-if="fieldErrors.islr_agent" id="fiscal-error-islr_agent" class="fiscal-field-error" role="alert">{{ fieldErrors.islr_agent }}</p><p v-if="fieldErrors.islr_basis" id="fiscal-error-islr_basis" class="fiscal-field-error" role="alert">{{ fieldErrors.islr_basis }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.iva_retention_rate}" data-fiscal-field="iva_retention_rate"><label>Retención IVA aplicable al proveedor <el-tooltip content="Porcentaje aplicable al proveedor. El 100 % requiere fundamento y evidencia; elegir un porcentaje no otorga facultad de agente." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva retention rate"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_retention_rate" @change="onFieldChange('iva_retention_rate')" :aria-invalid="Boolean(fieldErrors.iva_retention_rate)" :aria-describedby="fieldErrors.iva_retention_rate ? 'fiscal-error-iva_retention_rate' : null" :disabled="!canEdit" class="w-100"><el-option :value="75" label="75 %"/><el-option :value="100" label="100 % con fundamento"/></el-select><p v-if="fieldErrors.iva_retention_rate" id="fiscal-error-iva_retention_rate" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_retention_rate }}</p></div>
                        <div v-if="Number(profile.iva_retention_rate) === 100" class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.iva_100_basis}" data-fiscal-field="iva_100_basis"><label>Fundamento del 100 % <span class="fiscal-field-status">(obligatorio para el 100 %)</span> <el-tooltip content="Seleccione la causal documentada que sustenta la retención del 100 %." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre el fundamento del 100 %"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-select v-model="profile.iva_100_basis" @change="onFieldChange('iva_100_basis')" :aria-invalid="Boolean(fieldErrors.iva_100_basis)" :aria-describedby="fieldErrors.iva_100_basis ? 'fiscal-error-iva_100_basis' : null" :disabled="!canEdit" class="w-100"><el-option v-for="option in fullBases" :key="option.value" :value="option.value" :label="option.label"/></el-select><p v-if="fieldErrors.iva_100_basis" id="fiscal-error-iva_100_basis" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_100_basis }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.iva_exclusion}" data-fiscal-field="iva_exclusion"><label>Exclusión IVA acreditada <span class="fiscal-field-status">(opcional)</span> <el-tooltip content="Opcional. Indique la exclusión de retención IVA acreditada, si corresponde." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre iva exclusion"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.iva_exclusion" @change="onFieldChange('iva_exclusion')" :aria-invalid="Boolean(fieldErrors.iva_exclusion)" :aria-describedby="fieldErrors.iva_exclusion ? 'fiscal-error-iva_exclusion' : null" :disabled="!canEdit"/><p v-if="fieldErrors.iva_exclusion" id="fiscal-error-iva_exclusion" class="fiscal-field-error" role="alert">{{ fieldErrors.iva_exclusion }}</p></div>
                        <div class="col-md-6 form-group" :class="{'fiscal-invalid': fieldErrors.evidence_reference}" data-fiscal-field="evidence_reference"><label>Referencia de evidencia fiscal <span class="fiscal-field-status">{{ Number(profile.iva_retention_rate) === 100 || profile.iva_agent_basis === 'SNAT/2013/0029' ? '(obligatorio)' : '(opcional)' }}</span> <el-tooltip content="Referencia del documento que sustenta la condición fiscal; es necesaria para ciertas facultades y para el 100 %." placement="top"><button type="button" class="fiscal-help" aria-label="Información sobre evidence reference"><i class="el-icon-info" aria-hidden="true"></i></button></el-tooltip></label><el-input v-model="profile.evidence_reference" @change="onFieldChange('evidence_reference')" :aria-invalid="Boolean(fieldErrors.evidence_reference)" :aria-describedby="fieldErrors.evidence_reference ? 'fiscal-error-evidence_reference' : null" :disabled="!canEdit"/><p v-if="fieldErrors.evidence_reference" id="fiscal-error-evidence_reference" class="fiscal-field-error" role="alert">{{ fieldErrors.evidence_reference }}</p></div>
                    </div>
                    <div class="text-end"><el-button v-if="canEdit" type="primary" native-type="button" :loading="saving" @click="save">Guardar nueva versión fiscal</el-button></div>
                    <p v-if="!canEdit">Modificación reservada al administrador en instalaciones con historial fiscal.</p>
                    <div v-if="kind === 'company'" class="mt-4">
                        <label class="d-block">RIF impreso de la empresa <span class="fiscal-field-status">(opcional)</span></label>
                        <p class="text-muted"><small>PDF, JPG o PNG, hasta 10 MB.</small></p>
                        <p v-if="rifDocument">Archivo actual: <a :href="`/companies/${entityId}/rif-document/download`">{{ rifDocument.name }}</a></p>
                        <p v-else-if="!rifError">No hay un RIF adjunto.</p>
                        <input ref="rifFile" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="d-none" @change="uploadRif">
                        <el-button v-if="canEdit" native-type="button" :loading="uploading" @click="$refs.rifFile.click()">Adjuntar imagen o PDF</el-button>
                        <p v-if="rifError" class="text-danger mt-2">{{ rifError }}</p>
                    </div>
                    <div class="table-responsive mt-4"><table class="table"><thead><tr><th>Versión</th><th>Vigencia desde</th><th>Actor</th><th>Registro</th></tr></thead><tbody><tr v-for="row in history" :key="row.id"><td>{{ row.id }}</td><td>{{ row.effective_from }}</td><td>{{ row.actor_id }}</td><td>{{ row.created_at }}</td></tr></tbody></table></div>
                </template>
                <p v-if="error && !loaded" class="text-danger" role="alert">{{ error }}</p>
            </component>
        </component>
    </div>
</template>
<script>
export default {
    props: ['kind', 'entityId'],
    data: () => ({loaded: false, loading: false, canEdit: false, saving: false, uploading: false, history: [], profile: {}, fieldErrors: {}, serverErrors: {}, validationAttempted: false, openPanels: [], error: '', rifDocument: null, rifError: '',
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
            this.loaded = false; this.loading = true; this.error = ''; this.fieldErrors = {}; this.serverErrors = {}; this.validationAttempted = false
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
            const empty = value => value === null || value === undefined || String(value).trim() === ''
            const required = (key, message) => { if (empty(p[key])) errors[key] = message }
            const option = (key, values, message) => { if (!values.includes(p[key])) errors[key] = message }
            if (!/^[VJEGCR]-[0-9]{8}-[0-9]$/.test(p.rif || '')) errors.rif = 'Escriba el RIF con una letra, ocho dígitos y un dígito final, separados por guiones. Ejemplo: J-12345678-9.'
            const prefixes = {natural: ['V', 'E', 'R'], legal: ['J', 'C'], public: ['G', 'J']}
            if (!errors.rif && prefixes[p.nature] && !prefixes[p.nature].includes(p.rif[0])) errors.rif = `El RIF debe comenzar por ${prefixes[p.nature].join(', ')} para la naturaleza seleccionada. Revise el RIF y la naturaleza jurídica.`
            option('nature', Object.keys(prefixes), 'Seleccione Persona natural, Persona jurídica u Órgano o ente público.')
            option('iva_status', ['ordinary', 'formal', 'not_subject'], 'Seleccione su condición frente al IVA: Ordinario, Formal o No sujeto.')
            option('iva_agent_basis', ['none', 'SNAT/2025/000054', 'SNAT/2013/0029'], 'Seleccione una facultad de agente IVA. Si no está acreditada, seleccione «Sin facultad acreditada».')
            if (![75, 100].includes(Number(p.iva_retention_rate))) errors.iva_retention_rate = 'Seleccione 75 % o 100 % con fundamento.'
            if (this.kind === 'company' && !p.commercial_activity) errors.commercial_activity = 'Marque «Actividad comercial habilitada» para guardar el perfil de la empresa emisora, si corresponde a su situación real.'
            if (p.nature === 'public') option('public_kind', this.publicKinds.map(row => row.value), 'Seleccione la clase de órgano o ente público.')
            else if (p.public_kind) errors.public_kind = 'La clase de ente público solo corresponde a la naturaleza Órgano o ente público. Borre esta selección o revise la naturaleza jurídica.'
            if (p.nature !== 'natural' && p.personal_firm) errors.personal_firm = 'La firma personal solo corresponde a una persona natural. Desmarque esta opción o revise la naturaleza jurídica.'
            required('effective_from', 'Seleccione la fecha desde la que se aplica esta versión fiscal.')
            const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value
            const dateLabels = {effective_from: 'Vigencia desde', effective_until: 'Vigencia hasta', revoked_at: 'Revocación SPE desde', notification_date: 'Fecha de notificación'}
            Object.entries(dateLabels).forEach(([key, label]) => {
                if (!empty(p[key]) && !validDate(p[key])) errors[key] = `Seleccione una fecha válida en «${label}».`
            })
            if (p.spe) {
                required('notification_reference', 'Escriba la referencia de la notificación del SENIAT que acredita la condición SPE.')
                required('notification_date', 'Seleccione la fecha de la notificación del SENIAT.')
                if (!errors.notification_date && !errors.effective_from && p.notification_date > p.effective_from) errors.notification_date = 'La fecha de notificación no puede ser posterior a «Vigencia desde». Revise ambas fechas.'
            }
            if (!errors.effective_until && !errors.effective_from && p.effective_until && p.effective_until < p.effective_from) errors.effective_until = 'Seleccione una fecha igual o posterior a «Vigencia desde», o deje este campo vacío si no hay fecha final.'
            if (!errors.revoked_at && !errors.effective_from && p.revoked_at && p.revoked_at < p.effective_from) errors.revoked_at = 'La revocación no puede ser anterior a «Vigencia desde». Revise la fecha o deje el campo vacío si no existe revocación.'
            if (p.iva_agent_basis === 'SNAT/2025/000054') {
                if (!p.spe) errors.iva_agent_basis = 'Seleccionó SNAT/2025/000054, pero no está marcada la condición SPE. Seleccione «Sin facultad acreditada» si no dispone de esa facultad; si está acreditada, complete la condición SPE y su notificación.'
                else if (p.nature === 'natural' && !p.personal_firm) errors.iva_agent_basis = 'Esta facultad exige que la persona natural esté constituida como firma personal. Revise su documentación o seleccione «Sin facultad acreditada».'
                else if (p.nature === 'public' && p.public_kind !== 'decentralized_business') errors.iva_agent_basis = 'Esta facultad no corresponde a la clase de ente público seleccionada. Revise la facultad acreditada y la clase de ente público.'
            }
            if (p.iva_agent_basis === 'SNAT/2013/0029') {
                if (p.nature !== 'public') errors.iva_agent_basis = 'La opción SNAT/2013/0029 corresponde a órganos o entes públicos. Revise la naturaleza jurídica o seleccione otra facultad acreditada.'
                required('evidence_reference', 'Escriba la referencia del documento que acredita la facultad de agente IVA del ente público.')
            }
            if (p.islr_agent) required('islr_basis', 'Escriba el fundamento que acredita la facultad ISLR. Si no está acreditada, desmarque «Facultad ISLR acreditada».')
            if (Number(p.iva_retention_rate) === 100) {
                option('iva_100_basis', this.fullBases.map(row => row.value), 'Seleccione la causal que justifica la retención del 100 %.')
                required('evidence_reference', 'Escriba la referencia del documento que sustenta la retención del 100 %.')
            }
            // The server validates supplied optional values too, even when their controls are hidden.
            if (Number(p.iva_retention_rate) !== 100 && p.iva_100_basis && !this.fullBases.some(row => row.value === p.iva_100_basis)) errors.iva_retention_rate = 'El perfil conserva un fundamento del 100 % inválido. Seleccione 100 % y corrija el fundamento.'
            for (const key of ['notification_reference', 'islr_basis', 'iva_exclusion', 'evidence_reference']) {
                if (p[key] && Array.from(String(p[key])).length > 255) errors[key] = 'Acorte el texto a un máximo de 255 caracteres.'
            }
            this.fieldErrors = Object.assign({}, this.serverErrors, errors)
            return Object.keys(this.fieldErrors).length === 0
        },
        onFieldChange(field) {
            if (!this.validationAttempted) return
            // Editing a related condition permits retrying the corresponding server validation.
            const groups = [
                ['rif', 'nature', 'personal_firm', 'public_kind', 'iva_agent_basis'],
                ['spe', 'notification_reference', 'notification_date', 'effective_from', 'iva_agent_basis'],
                ['effective_from', 'effective_until', 'revoked_at'],
                ['islr_agent', 'islr_basis'],
                ['iva_retention_rate', 'iva_100_basis', 'evidence_reference', 'iva_agent_basis'],
            ]
            const affected = new Set([field])
            groups.filter(group => group.includes(field)).forEach(group => group.forEach(key => affected.add(key)))
            this.serverErrors = Object.fromEntries(Object.entries(this.serverErrors).filter(([key]) => !affected.has(key)))
            this.validateProfile()
            this.updateValidationSummary()
        },
        updateValidationSummary() {
            const count = Object.keys(this.fieldErrors).length
            this.error = count ? `Revise ${count === 1 ? 'el campo indicado' : `los ${count} campos indicados`}. Debajo de cada campo se explica cómo corregirlo.` : ''
        },
        showFirstError() {
            this.openPanels = ['fiscal']
            this.$nextTick(() => {
                const group = this.$el.querySelector('.fiscal-invalid')
                if (!group) return
                group.scrollIntoView({behavior: 'smooth', block: 'center'})
                const control = group.querySelector('input:not([type="hidden"]), select, textarea, button')
                if (control) control.focus({preventScroll: true})
            })
        },
        async save() {
            if (this.saving) return
            this.error = ''; this.serverErrors = {}; this.validationAttempted = true
            if (!this.validateProfile()) { this.updateValidationSummary(); this.showFirstError(); return }
            this.saving = true
            try {await this.$http.post(`/fiscal-profiles/${this.kind}/${this.entityId}`,this.profile); await this.load(); this.$message.success('Nueva versión fiscal guardada.')}
            catch (e) {
                const data = e.response?.data || {}
                const errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : null) || (e.response?.status === 422 && data && typeof data === 'object' ? data : null)
                if (errors && typeof errors === 'object') {
                    this.serverErrors = Object.fromEntries(Object.entries(errors).filter(([key, value]) => !['success', 'message', 'file', 'line'].includes(key) && (typeof value === 'string' || Array.isArray(value))).map(([key, value]) => [key, Array.isArray(value) ? value.join(' ') : value]))
                    this.fieldErrors = Object.assign({}, this.serverErrors)
                    this.updateValidationSummary()
                    // Unmapped errors must remain visible instead of disappearing in a hidden field.
                    const unknown = Object.entries(this.fieldErrors).filter(([key]) => !(key in this.profile)).map(([, value]) => value)
                    if (unknown.length) this.error += ' ' + unknown.join(' ')
                    if (!Object.keys(this.fieldErrors).length) this.error = this.errorMessage(e, 'No se pudo guardar la versión fiscal. Intente nuevamente.')
                    this.showFirstError()
                } else this.error = this.errorMessage(e, 'No se pudo guardar la versión fiscal. Compruebe su conexión e intente nuevamente.')
            }
            finally {this.saving = false}
        }
    }
}
</script>

<style scoped>
.fiscal-field-status { color: #596579; font-size: 0.875rem; font-weight: normal; }
.fiscal-help { border: 0; background: transparent; color: #73849e; padding: 0 3px; cursor: help; }
.fiscal-help:focus { outline: 2px solid #73849e; outline-offset: 2px; }
.fiscal-error-summary { color: #9d2525; background: #fff3f3; border: 1px solid #e2a6a6; border-radius: 4px; padding: 10px 12px; margin-bottom: 16px; }
.fiscal-invalid ::v-deep .el-input__inner, .fiscal-invalid ::v-deep .el-checkbox__inner { border-color: #c0392b; }
.fiscal-field-error { color: #c0392b; font-size: 0.875rem; margin: 4px 0 0; }
</style>
