<template>
    <!-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## -->
    <el-dialog title="Numeración y emisión" :visible="showDialog" @open="open" @close="close" :close-on-click-modal="false" width="95%" top="4vh">
        <p v-if="establishment"><strong>{{ establishment.description }}</strong> · Código interno {{ establishment.code }}</p>
        <p class="text-muted">Ambiente: {{ data.environment === 'production' ? 'Producción' : 'Demo' }}. El número del documento y el número de control son independientes.</p>
        <el-alert v-if="loadError" :title="loadError" type="error" :closable="false" show-icon />
        <el-button v-if="loadError" size="small" @click="load">Reintentar carga</el-button>
        <div v-if="loading" role="status">Cargando configuración fiscal…</div>
        <el-tabs v-else-if="!loadError" v-model="tab" @tab-click="cancel">
            <el-tab-pane label="Numeración documental" name="sequences">
                <p>Sin código de serie, la numeración se identifica únicamente por el tipo y número del documento. Una secuencia centralizada se comparte entre sucursales.</p>
                <el-button size="small" type="primary" @click="create('sequences')">Nueva numeración</el-button>
                <div class="table-responsive">
                    <table class="table table-sm"><thead><tr><th>Documento</th><th>Serie</th><th>Ámbito</th><th>Inicial</th><th>Último asignado</th><th>Próximo</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody><tr v-for="row in data.sequences" :key="row.id">
                            <td>{{ typeLabel(row.document_type_id) }}</td><td>{{ row.series_code || 'Sin serie' }}</td><td>{{ row.establishment_id ? 'Esta sucursal' : 'Centralizada' }}</td>
                            <td>{{ row.initial_number }}</td><td>{{ row.last_assigned == null ? 'Sin emisiones' : row.last_assigned }}</td><td>{{ row.next_number }}</td>
                            <td>{{ row.active ? (row.in_use ? 'En uso' : 'Disponible') : 'Archivada' }}</td>
                            <td><el-button size="mini" :disabled="!!row.in_use || !row.active" @click="editSequence(row)">Editar inicio</el-button><el-button v-if="row.active" size="mini" @click="archive('sequence', row)">Archivar</el-button></td>
                        </tr><tr v-if="!data.sequences.length"><td colspan="8">No hay numeraciones configuradas.</td></tr></tbody>
                    </table>
                </div>
            </el-tab-pane>
            <el-tab-pane label="Imprentas y controles" name="lots">
                <p>Registre los rangos preimpresos recibidos de su imprenta. Los controles digitales los asignará el proveedor integrado.</p>
                <el-button size="small" type="primary" @click="create('lots')">Registrar lote preimpreso</el-button>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Imprenta</th><th>RIF</th><th>Autorización</th><th>Desde</th><th>Hasta</th><th>Próximo control</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody><tr v-for="row in data.lots" :key="row.id"><td>{{ row.printer_name }}</td><td>{{ row.printer_rif }}</td><td>{{ row.authorization }}</td><td>{{ row.start_control }}</td><td>{{ row.end_control }}</td><td>{{ row.next_control || 'Agotado' }}</td><td>{{ !row.active ? 'Archivado' : row.exhausted ? 'Agotado' : 'Disponible' }}</td><td><el-button v-if="row.active" size="mini" @click="archive('lot', row)">Archivar</el-button></td></tr>
                    <tr v-if="!data.lots.length"><td colspan="8">No hay lotes preimpresos registrados.</td></tr></tbody></table></div>
            </el-tab-pane>
            <el-tab-pane label="Modalidades y canales" name="profiles">
                <p>Configure un perfil para cada documento y canal. Los simuladores sólo permiten pruebas en DEMO.</p>
                <el-button size="small" type="primary" @click="create('profiles')">Nuevo perfil</el-button>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Perfil</th><th>Canal</th><th>Documento</th><th>Modalidad</th><th>Grupo</th><th>Integración</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody><tr v-for="row in data.profiles" :key="row.id"><td>{{ row.name }}</td><td>{{ data.channels[row.channel] }}</td><td>{{ typeLabel(row.document_type_id) }}</td><td>{{ data.modes[row.mode] }}</td><td>{{ groupLabel(row.device_group_id) }}</td><td>{{ integrationLabel(row.integration_status) }}</td><td>{{ row.active ? 'Activo' : 'Archivado' }}</td><td><el-button size="mini" @click="editProfile(row)">Editar</el-button><el-button v-if="row.active" size="mini" @click="archive('profile', row)">Archivar</el-button></td></tr>
                    <tr v-if="!data.profiles.length"><td colspan="8">No hay perfiles fiscales configurados.</td></tr></tbody></table></div>
            </el-tab-pane>
            <el-tab-pane label="Documentos internos y grupos" name="internal">
                <p>Las notas de venta y movimientos de almacén utilizan numeración comercial. Los grupos de equipos sirven para seleccionar puntos de emisión.</p>
                <el-button size="small" @click="showInternal = true">Configurar documentos internos y grupos</el-button>
            </el-tab-pane>
        </el-tabs>
        <el-form v-if="editor && !loading" label-position="top" class="fiscal-editor" @submit.native.prevent="save">
            <el-alert v-if="saveError" :title="saveError" type="error" :closable="false" />
            <div v-for="(messages, field) in errors" :key="field" class="text-danger" role="alert">{{ messages.join(' ') }}</div>
            <template v-if="editor === 'sequences'">
                <el-form-item label="Tipo de documento" :error="fieldError('document_type_id')"><el-select v-model="form.document_type_id" :disabled="!!form.id"><el-option v-for="(label, id) in data.document_types" :key="id" :label="label" :value="id" /></el-select></el-form-item>
                <el-form-item label="Código de serie (opcional)" :error="fieldError('series_code')"><el-input v-model="form.series_code" maxlength="32" :disabled="!!form.id" placeholder="Ejemplo: CAJA-A" /></el-form-item>
                <el-form-item label="Número inicial" :error="fieldError('initial_number')"><el-input v-model="form.initial_number" inputmode="numeric" /></el-form-item>
                <el-checkbox v-model="form.centralized" :disabled="!!form.id">Compartir esta secuencia entre sucursales</el-checkbox>
            </template>
            <template v-if="editor === 'lots'">
                <el-form-item label="Razón social de la imprenta" :error="fieldError('printer_name')"><el-input v-model="form.printer_name" maxlength="255" /></el-form-item>
                <el-form-item label="RIF de la imprenta" :error="fieldError('printer_rif')"><el-input v-model="form.printer_rif" maxlength="32" /></el-form-item>
                <el-form-item label="Providencia de autorización" :error="fieldError('authorization')"><el-input v-model="form.authorization" maxlength="255" /></el-form-item>
                <el-form-item label="Fecha de autorización"><el-date-picker v-model="form.authorization_date" value-format="yyyy-MM-dd" format="dd/MM/yyyy" /></el-form-item>
                <el-form-item label="Fecha de elaboración" :error="fieldError('prepared_at')"><el-date-picker v-model="form.prepared_at" value-format="yyyy-MM-dd" format="dd/MM/yyyy" /></el-form-item>
                <el-form-item label="Control inicial" :error="fieldError('start')"><el-input v-model="form.start" placeholder="00-00000001" maxlength="11" /></el-form-item>
                <el-form-item label="Control final" :error="fieldError('end')"><el-input v-model="form.end" placeholder="00-00000100" maxlength="11" /></el-form-item>
            </template>
            <template v-if="editor === 'profiles'">
                <el-alert v-if="form.in_use" title="Perfil utilizado: puede cambiar el nombre, las credenciales o archivarlo. Para otra configuración fiscal, cree un perfil nuevo." type="info" :closable="false" />
                <el-form-item label="Nombre" :error="fieldError('name')"><el-input v-model="form.name" maxlength="120" /></el-form-item>
                <el-form-item label="Canal" :error="fieldError('channel')"><el-select v-model="form.channel" :disabled="form.in_use"><el-option v-for="(label, id) in data.channels" :key="id" :label="label" :value="id" /></el-select></el-form-item>
                <el-form-item label="Modalidad" :error="fieldError('mode')"><el-select v-model="form.mode" :disabled="form.in_use" @change="modeChanged"><el-option v-for="(label, id) in data.modes" :key="id" :label="label" :value="id" :disabled="(form.channel === 'digital' && id !== 'digital') || (form.channel === 'contingency' && id !== 'free_form')" /></el-select></el-form-item>
                <el-form-item label="Documento" :error="fieldError('document_type_id')"><el-select v-model="form.document_type_id" :disabled="form.in_use" @change="form.sequence_id = null"><el-option v-for="id in availableTypes" :key="id" :label="typeLabel(id)" :value="id" /></el-select></el-form-item>
                <el-form-item label="Numeración" :error="fieldError('sequence_id')"><el-select v-model="form.sequence_id" :disabled="form.in_use"><el-option v-for="row in profileSequences" :key="row.id" :label="(row.series_code || 'Sin serie') + ' · próximo ' + row.next_number" :value="row.id" /></el-select></el-form-item>
                <el-form-item label="Grupo de equipo (opcional)" :error="fieldError('device_group_id')"><el-select v-model="form.device_group_id" clearable :disabled="form.in_use"><el-option v-for="group in data.groups" :key="group.id" :label="group.name" :value="group.id" /></el-select></el-form-item>
                <template v-if="form.mode === 'free_form'">
                    <el-form-item label="Lote preimpreso" :error="fieldError('control_lot_id')"><el-select v-model="form.control_lot_id" clearable :disabled="form.in_use"><el-option v-for="row in data.lots" :key="row.id" :label="row.printer_name + ' · ' + row.start_control + ' a ' + row.end_control" :value="row.id" :disabled="!row.active || row.exhausted" /></el-select></el-form-item>
                    <el-form-item label="Máximo de líneas por documento preimpreso" :error="fieldError('configuration.page_capacity')"><el-input v-model="form.configuration.page_capacity" inputmode="numeric" :disabled="form.in_use" /></el-form-item>
                </template>
                <template v-else>
                    <el-form-item label="Proveedor" :error="fieldError('provider')"><el-select v-model="form.provider" :disabled="form.in_use"><el-option label="Pendiente de integración" value="none" /><el-option label="Simulador DEMO" value="simulator" /></el-select></el-form-item>
                    <template v-if="form.mode === 'digital'">
                        <el-form-item label="Usuario emisor de pedidos automáticos" :error="fieldError('configuration.emitter_user_id')"><el-select v-model="form.configuration.emitter_user_id" clearable :disabled="form.in_use"><el-option v-for="user in data.emitters" :key="user.id" :value="user.id" :label="user.name" /></el-select></el-form-item>
                        <el-form-item label="Autorización del emisor" :error="fieldError('configuration.authorization')"><el-input v-model="form.configuration.authorization" :disabled="form.in_use" /></el-form-item>
                        <el-form-item label="Fecha de autorización"><el-date-picker v-model="form.configuration.authorization_date" value-format="yyyy-MM-dd" :disabled="form.in_use" /></el-form-item>
                    </template>
                    <template v-else><el-form-item v-for="field in machineFields" :key="field.key" :label="field.label"><el-input v-model="form.configuration[field.key]" :disabled="form.in_use" /></el-form-item></template>
                    <el-form-item :label="form.credentials_configured ? 'Reemplazar credenciales (vacío conserva las actuales)' : 'Credenciales'"><el-input v-model="form.credentials" type="password" autocomplete="new-password" /></el-form-item>
                    <el-checkbox v-model="form.clear_credentials">Eliminar credenciales guardadas</el-checkbox>
                </template>
                <el-checkbox v-model="form.active">Perfil activo</el-checkbox>
            </template>
            <div class="mt-3"><el-button native-type="submit" type="primary" :loading="saving">Guardar</el-button><el-button :disabled="saving" @click="cancel">Cancelar</el-button></div>
        </el-form>
        <internal-series v-if="showInternal" :showDialog.sync="showInternal" :establishmentId="establishmentId" :establishment="establishment" :internal-only="true" />
    </el-dialog>
    <!-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## -->
</template>
<script>
// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
import InternalSeries from './series.vue'
export default {
    components: {InternalSeries},
    props: ['showDialog', 'establishmentId', 'establishment'],
    data() { return {loading: false, saving: false, loadError: '', saveError: '', errors: {}, tab: 'sequences', editor: null, form: {}, showInternal: false, data: {sequences: [], lots: [], profiles: [], groups: [], emitters: [], modes: {}, channels: {}, document_types: {}, capabilities: {}}, machineFields: [{key: 'model', label: 'Modelo'}, {key: 'serial', label: 'Registro/serial fiscal'}, {key: 'port', label: 'Puerto del controlador'}]} },
    computed: {
        base() { return `/establishments/${this.establishmentId}/fiscal-numbering` },
        availableTypes() { return this.data.capabilities[this.form.mode] || [] },
        profileSequences() { return this.data.sequences.filter(row => row.document_type_id === this.form.document_type_id && row.active) },
    },
    methods: {
        async open() { this.cancel(); this.tab = 'sequences'; await this.load() },
        close() { this.cancel(); this.showInternal = false; this.$emit('update:showDialog', false) },
        async load() {
            this.loading = true; this.loadError = ''
            try { const response = await this.$http.get(this.base); this.data = response.data.data }
            catch (error) { this.loadError = error.response && error.response.status === 403 ? 'Sólo un administrador puede configurar la numeración fiscal.' : 'No se pudo cargar la configuración fiscal. Intente nuevamente.' }
            finally { this.loading = false }
        },
        fieldError(field) { return (this.errors[field] || []).join(' ') },
        typeLabel(id) { return this.data.document_types[id] || id },
        groupLabel(id) { const group = this.data.groups.find(row => row.id === id); return group ? group.name : 'General' },
        integrationLabel(status) { return {manual_printing: 'Impresión física', simulated: 'Simulación DEMO', not_integrated: 'Sin integración'}[status] || 'Sin verificar' },
        cancel() { this.editor = null; this.form = {}; this.errors = {}; this.saveError = '' },
        create(kind) {
            this.cancel(); this.editor = kind
            this.form = kind === 'sequences' ? {document_type_id: '01', series_code: '', initial_number: '1', centralized: true} : kind === 'lots' ? {printer_name: '', printer_rif: '', authorization: '', authorization_date: null, prepared_at: null, start: '', end: ''} : {name: '', channel: 'presential', document_type_id: '01', mode: 'free_form', provider: 'none', configuration: {}, sequence_id: null, control_lot_id: null, device_group_id: null, active: true, credentials: '', clear_credentials: false}
        },
        editSequence(row) { this.create('sequences'); this.form = {id: row.id, document_type_id: row.document_type_id, series_code: row.series_code, initial_number: String(row.initial_number), centralized: row.establishment_id == null} },
        editProfile(row) { this.create('profiles'); this.form = {...row, active: !!row.active, configuration: {...row.configuration}, credentials: '', clear_credentials: false} },
        modeChanged() { this.form.configuration = {}; this.form.control_lot_id = null; this.form.provider = 'none'; this.form.credentials = ''; this.form.clear_credentials = false; if (!this.availableTypes.includes(this.form.document_type_id)) { this.form.document_type_id = this.availableTypes[0]; this.form.sequence_id = null } },
        async save() {
            if (this.saving) return
            this.errors = {}; this.saveError = ''; this.saving = true
            const payload = {...this.form}
            if (this.editor === 'sequences') payload.series_code = payload.series_code.toUpperCase()
            if (this.editor === 'profiles') { payload.device_group_id = payload.device_group_id || null; payload.control_lot_id = payload.control_lot_id || null }
            try { await this.$http.post(`${this.base}/${this.editor}`, payload); this.cancel(); await this.load() }
            catch (error) {
                if (error.response && error.response.status === 422) {
                    const body = error.response.data || {}
                    const fields = body.errors || (body.message && typeof body.message === 'object' ? body.message : body)
                    this.errors = fields && typeof fields === 'object' ? fields : {}
                    this.saveError = this.errors.configuration ? this.fieldError('configuration') : 'Revise los campos indicados antes de guardar.'
                } else this.saveError = error.response && error.response.status === 403 ? 'Sólo un administrador puede configurar la numeración fiscal.' : 'No se pudo guardar la configuración.'
            }
            finally { this.saving = false }
        },
        async archive(entity, row) {
            try { await this.$confirm('Se conservarán los documentos y la numeración utilizada. El registro dejará de estar disponible para nuevas emisiones.', 'Archivar configuración', {confirmButtonText: 'Archivar', cancelButtonText: 'Cancelar'}); await this.$http.post(`${this.base}/archive`, {entity, id: row.id}); await this.load() }
            catch (error) { if (error !== 'cancel' && error !== 'close') this.$message.error('No se pudo archivar la configuración.') }
        },
    },
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
</script>
<style scoped>
/* ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## */
.fiscal-editor { max-width: 720px; padding: 20px; margin-top: 16px; border: 1px solid #dcdfe6; border-radius: 8px; }
.fiscal-editor .el-select { width: 100%; }
table { margin-top: 12px; }
/* ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## */
</style>
