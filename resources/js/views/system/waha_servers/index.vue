<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin d-flex justify-content-between align-items-center">
            <h3 class="my-0">Servidores WAHA</h3>
            <button type="button" class="btn btn-light btn-sm" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo servidor</button>
        </div>
        <div class="card-body">
                <p class="text-muted">
                    Cada servidor WAHA corre un único motor de conexión (NOWEB, GOWS, WEBJS o WPP). Registra aquí
                    los servidores disponibles para que los tenants elijan a cuál conectarse cuando el proveedor
                    activo sea WAHA.
                </p>
                <el-table :data="records" v-loading="loading" style="width: 100%">
                    <el-table-column prop="name" label="Nombre"></el-table-column>
                    <el-table-column prop="key" label="Clave" width="140"></el-table-column>
                    <el-table-column prop="engine" label="Motor" width="100"></el-table-column>
                    <el-table-column prop="url" label="URL"></el-table-column>
                    <el-table-column label="Activo" width="90">
                        <template slot-scope="scope">
                            <el-tag :type="scope.row.active ? 'success' : 'info'" size="mini">
                                {{ scope.row.active ? 'Sí' : 'No' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Predeterminado" width="170">
                        <template slot-scope="scope">
                            <el-tag v-if="scope.row.is_default" type="warning" size="mini">Predeterminado</el-tag>
                            <el-button v-else type="text" size="mini" @click="setDefault(scope.row)">Marcar predeterminado</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column label="Acciones" width="140">
                        <template slot-scope="scope">
                            <el-button type="text" size="mini" @click="clickEdit(scope.row)">Editar</el-button>
                            <el-button type="text" size="mini" class="text-danger" @click="clickDelete(scope.row)">Eliminar</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <p class="text-muted mt-3" v-if="!loading && !records.length">
                    Aún no hay servidores WAHA registrados.
                </p>
                <p class="text-muted mt-3" v-if="!loading && records.length && !hasDefault">
                    ⚠ No hay ningún servidor marcado como predeterminado — las conexiones nuevas con WAHA fallarán hasta que marques uno.
                </p>
        </div>

        <el-dialog :title="form.id ? 'Editar servidor WAHA' : 'Nuevo servidor WAHA'" :visible.sync="showDialog" width="520px">
            <el-form label-position="top" @submit.native.prevent="submit">
                <el-form-item label="Nombre" :error="fieldError('name')">
                    <el-input v-model="form.name" placeholder="ej. WAHA Europa NOWEB"></el-input>
                </el-form-item>
                <el-form-item label="URL del servidor" :error="fieldError('url')">
                    <el-input v-model="form.url" placeholder="https://waha.midominio.com"></el-input>
                </el-form-item>
                <el-form-item label="API Key" :error="fieldError('api_key')">
                    <el-input v-model="form.api_key" show-password></el-input>
                </el-form-item>
                <el-form-item label="Motor" :error="fieldError('engine')">
                    <el-select v-model="form.engine" style="width: 100%">
                        <el-option label="NOWEB (Baileys)" value="NOWEB"></el-option>
                        <el-option label="GOWS" value="GOWS"></el-option>
                        <el-option label="WEBJS" value="WEBJS"></el-option>
                        <el-option label="WPP" value="WPP"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Notas (opcional)">
                    <el-input v-model="form.notes"></el-input>
                </el-form-item>
                <el-form-item>
                    <el-switch v-model="form.active" active-text="Activo"></el-switch>
                </el-form-item>
            </el-form>
            <span slot="footer">
                <el-button @click="showDialog = false">Cancelar</el-button>
                <el-button type="primary" :loading="loading_submit" @click="submit">Guardar</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
import { deletable } from '../../../mixins/deletable';

export default {
    mixins: [deletable],
    data() {
        return {
            resource: 'waha-servers',
            records: [],
            loading: false,
            loading_submit: false,
            showDialog: false,
            errors: {},
            form: this.emptyForm(),
        };
    },
    computed: {
        hasDefault() {
            return this.records.some(r => r.is_default);
        },
    },
    created() {
        this.$eventHub.$on('reloadData', () => this.fetch());
        this.fetch();
    },
    methods: {
        emptyForm() {
            return { id: null, name: '', url: '', api_key: '', engine: 'NOWEB', active: true, notes: null };
        },
        fieldError(field) {
            return this.errors[field] ? this.errors[field][0] : null;
        },
        fetch() {
            this.loading = true;
            this.$http.get(`/${this.resource}/records`)
                .then(response => {
                    this.records = response.data;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        clickCreate() {
            this.errors = {};
            this.form = this.emptyForm();
            this.showDialog = true;
        },
        clickEdit(row) {
            this.errors = {};
            this.form = { ...row };
            this.showDialog = true;
        },
        submit() {
            this.loading_submit = true;
            this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.showDialog = false;
                        this.fetch();
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data.errors || {};
                    } else {
                        this.$message.error('Error al guardar el servidor');
                    }
                })
                .finally(() => {
                    this.loading_submit = false;
                });
        },
        clickDelete(row) {
            this.destroy(`/${this.resource}/${row.id}`).then(() => this.fetch());
        },
        setDefault(row) {
            this.$http.post(`/${this.resource}/${row.id}/set-default`)
                .then(response => {
                    this.$message({ message: response.data.message, type: response.data.success ? 'success' : 'error' });
                    this.fetch();
                })
                .catch(() => {
                    this.$message.error('Error al marcar como predeterminado');
                });
        },
    },
};
</script>
