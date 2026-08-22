<template>
    <div>
        <header class="page-header">
            <h2><a href="/dashboard">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-briefcase" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 9a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -9" /><path d="M8 7v-2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v2" /><path d="M12 12l0 .01" /><path d="M3 13a20 20 0 0 0 18 0" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Giro de Negocio</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button type="button" class="btn btn-custom btn-sm mt-2 me-2" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo</button>
            </div>
        </header>

        <div class="card">
            <div class="card-body">

                <div v-loading="loading" class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th class="text-center">Módulos</th>
                                <th class="text-center">Apps</th>
                                <th class="text-center">Activo</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in records" :key="row.id">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    {{ row.name }}
                                    <el-tag v-if="row.locked" type="warning" size="mini" class="ms-1">Solo lectura</el-tag>
                                    <el-tag v-else-if="row.is_default" type="info" size="mini" class="ms-1">Predeterminado</el-tag>
                                </td>
                                <td>{{ row.description }}</td>
                                <td class="text-center">{{ (row.modules || []).length }}</td>
                                <td class="text-center">{{ (row.apps || []).length }}</td>
                                <td class="text-center">
                                    <el-switch
                                        v-model="row.active"
                                        :disabled="row.loading_active === true"
                                        @change="changeActive(row, $event)">
                                    </el-switch>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-info btn-sm me-1" @click.prevent="clickCreate(row.id)">
                                        <svg v-if="row.locked" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                                        {{ row.locked ? 'Ver' : 'Editar' }}
                                    </button>
                                    <button
                                        v-if="!row.is_default"
                                        type="button"
                                        class="btn btn-danger btn-sm"
                                        @click.prevent="clickDelete(row.id)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!loading && !records.length">
                                <td colspan="7" class="text-center text-muted">
                                    Aún no hay giros de negocio registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <business-turns-form
            :showDialog.sync="showDialog"
            :recordId="recordId"
            @close="onCloseForm">
        </business-turns-form>
    </div>
</template>

<script>
    import BusinessTurnsForm from './form.vue'
    import {deletable} from '../../../mixins/deletable'

    export default {
        components: {BusinessTurnsForm},
        mixins: [deletable],
        data() {
            return {
                resource: 'business-turns',
                records: [],
                loading: false,
                showDialog: false,
                recordId: null,
            }
        },
        created() {
            this.$eventHub.$on('reloadData', () => this.getRecords())
            this.getRecords()
        },
        methods: {
            getRecords() {
                this.loading = true
                this.$http.get(`/${this.resource}/records`)
                    .then(response => {
                        this.records = response.data
                    })
                    .catch(() => {
                        this.$message.error('No se pudieron cargar los giros de negocio')
                    })
                    .then(() => {
                        this.loading = false
                    })
            },
            clickCreate(recordId = null) {
                this.recordId = recordId
                this.showDialog = true
            },
            onCloseForm() {
                this.showDialog = false
                this.recordId = null
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() => this.getRecords())
            },
            changeActive(row, value) {
                const previousValue = !value
                this.$set(row, 'loading_active', true)

                this.$http.post(`/${this.resource}/change-active`, {
                    id: row.id,
                    active: value,
                })
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                        } else {
                            row.active = previousValue
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(() => {
                        row.active = previousValue
                        this.$message.error('No se pudo actualizar el estado del giro de negocio')
                    })
                    .then(() => {
                        this.$set(row, 'loading_active', false)
                    })
            },
        }
    }
</script>
