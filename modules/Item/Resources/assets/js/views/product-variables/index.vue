<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/list-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24" height="24" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M9 15h-4.5a4.5 4.5 0 1 1 .81 -8.92a4 4 0 0 1 7.66 -.83a4.5 4.5 0 0 1 4.85 6.27" />
                        <path d="M12 19a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
                        <path d="M15 16v-6a2 2 0 0 1 2 -2h1" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Atributos de productos</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-custom btn-sm mt-2 me-2" type="button" @click.prevent="clickCreate()">
                    <i class="fa fa-plus-circle"></i> Nueva variable
                </button>
            </div>
        </div>

        <div class="card mb-0 tab-content-default row-new">
            <div class="card-body">
                <p class="text-muted">
                    Crea variables (Talla, Color, Material...) y sus valores. Se usan en la pestaña Atributos
                    del formulario de productos para generar variaciones.
                </p>
                <div v-loading="loading" class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th style="width: 70px">Activo</th>
                            <th style="width: 22%">Nombre</th>
                            <th style="width: 110px">Tipo</th>
                            <th>Valores</th>
                            <th style="width: 100px" class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="row in records" :key="row.id">
                            <td class="align-middle">
                                <el-switch :value="row.active" @change="toggleActive(row)"></el-switch>
                            </td>
                            <td class="align-middle">
                                <span class="fw-semibold">{{ row.name }}</span>
                                <small v-if="row.items_count > 0" class="text-muted d-block">
                                    Usada en {{ row.items_count }} producto{{ row.items_count !== 1 ? 's' : '' }}
                                </small>
                            </td>
                            <td class="align-middle">
                                <el-tag size="mini" effect="plain" :type="row.value_type === 'color' ? 'warning' : 'primary'">
                                    {{ row.value_type === 'color' ? 'Con color' : 'Lista' }}
                                </el-tag>
                            </td>
                            <td class="align-middle">
                                <el-tag v-for="value in row.values"
                                        :key="value.id"
                                        size="mini"
                                        effect="plain"
                                        type="info"
                                        class="me-1 mb-1">
                                    <span v-if="row.value_type === 'color'"
                                          class="pv-color-dot"
                                          :style="{background: value.color}"></span>{{ value.value }}
                                </el-tag>
                            </td>
                            <td class="align-middle text-end text-nowrap">
                                <el-button size="mini"
                                           plain
                                           icon="el-icon-edit"
                                           title="Editar"
                                           @click.prevent="clickEdit(row)"></el-button>
                                <el-button size="mini"
                                           type="danger"
                                           plain
                                           icon="el-icon-delete"
                                           title="Eliminar"
                                           @click.prevent="clickDelete(row)"></el-button>
                            </td>
                        </tr>
                        <tr v-if="!loading && records.length === 0">
                            <td colspan="5" class="text-center text-muted py-3">Aún no hay variables registradas</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <el-dialog :title="titleForm"
                   :visible="showDialogForm"
                   :close-on-click-modal="false"
                   width="540px"
                   top="7vh"
                   @close="cancelEdit">
            <form autocomplete="off" @submit.prevent="submit">
                <div class="row">
                    <div class="col-md-7">
                        <div :class="{'has-danger': errors.name}" class="form-group">
                            <label class="control-label">Nombre de la variable</label>
                            <el-input v-model="form.name" placeholder="Ej: Talla, Color, Material"></el-input>
                            <small v-if="errors.name"
                                   class="form-control-feedback"
                                   v-text="errors.name[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div :class="{'has-danger': errors.value_type}" class="form-group">
                            <label class="control-label">Tipo de valor</label>
                            <el-select v-model="form.value_type">
                                <el-option label="Lista simple" value="list"></el-option>
                                <el-option label="Lista con color" value="color"></el-option>
                            </el-select>
                            <small v-if="errors.value_type"
                                   class="form-control-feedback"
                                   v-text="errors.value_type[0]"></small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                <div class="form-group">
                    <label class="control-label">Valores de la variable</label>
                    <div class="pv-add-row mb-2">
                        <el-input v-model="new_value.value"
                                  placeholder="Ej: S, M, L (separa con comas para agregar varios)"
                                  @keyup.enter.native.prevent="addValue"></el-input>
                        <el-color-picker v-if="form.value_type === 'color'"
                                         v-model="new_value.color"
                                         title="Color del valor"></el-color-picker>
                        <el-button plain size="small" @click.prevent="addValue">+ Agregar</el-button>
                    </div>
                    <div>
                        <el-tag v-for="(value, index) in form.values"
                                :key="'draft-value-' + index"
                                size="small"
                                effect="plain"
                                closable
                                class="me-1 mb-1"
                                :type="value.active === false ? 'info' : ''"
                                @close="removeValue(index)">
                            <span v-if="form.value_type === 'color'"
                                  class="pv-color-dot"
                                  :style="{background: value.color}"></span>{{ value.value }}
                        </el-tag>
                    </div>
                    <small v-for="(message, key) in valueErrors"
                           :key="'value-error-' + key"
                           class="form-control-feedback text-danger d-block"
                           v-text="message"></small>
                    <p class="text-muted mb-0">
                        <small>Escribe un valor y presiona Enter o el botón Agregar. Quita valores con la ✕ de cada uno.</small>
                    </p>
                </div>
                    </div>
                </div>

                <div class="form-actions text-end pt-2">
                    <el-button class="second-buton me-2" @click.prevent="cancelEdit">Cancelar</el-button>
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar variable</el-button>
                </div>
            </form>
        </el-dialog>
    </div>
</template>

<script>
export default {
    name: 'TenantProductVariablesIndex',
    data() {
        return {
            resource: 'product-variables',
            records: [],
            loading: false,
            loading_submit: false,
            showDialogForm: false,
            errors: {},
            form: {},
            new_value: {value: '', color: '#409EFF'},
        }
    },
    computed: {
        titleForm() {
            return this.form.id ? `Editar variable · ${this.form.name || ''}` : 'Nueva variable'
        },
        valueErrors() {
            const messages = {}
            Object.keys(this.errors).forEach(key => {
                if (key.startsWith('values')) {
                    messages[key] = this.errors[key][0]
                }
            })
            return messages
        },
    },
    created() {
        this.initForm()
        this.load()
    },
    methods: {
        initForm() {
            this.errors = {}
            this.form = {
                id: null,
                name: null,
                value_type: 'list',
                values: [],
            }
            this.new_value = {value: '', color: '#409EFF'}
        },
        load() {
            this.loading = true
            this.$http.get(`/${this.resource}/records`)
                .then(response => {
                    this.records = response.data.data || []
                })
                .then(() => {
                    this.loading = false
                })
        },
        clickCreate() {
            this.initForm()
            this.showDialogForm = true
        },
        clickEdit(row) {
            this.errors = {}
            this.form = {
                id: row.id,
                name: row.name,
                value_type: row.value_type,
                values: row.values.map(value => ({...value})),
            }
            this.new_value = {value: '', color: '#409EFF'}
            this.showDialogForm = true
        },
        cancelEdit() {
            this.showDialogForm = false
            this.initForm()
        },
        addValue() {
            const values = (this.new_value.value || '')
                .split(',')
                .map(value => value.trim())
                .filter(value => value !== '')

            if (values.length === 0) {
                return this.$message.warning('Escribe un valor primero')
            }

            const skipped = []
            values.forEach(value => {
                const exists = this.form.values.some(row => row.value.toLowerCase() === value.toLowerCase())
                if (exists) {
                    skipped.push(value)
                    return
                }
                const row = {id: null, value: value, active: true}
                if (this.form.value_type === 'color') {
                    row.color = this.new_value.color
                }
                this.form.values.push(row)
            })

            if (skipped.length > 0) {
                this.$message.warning(`Ya existe${skipped.length !== 1 ? 'n' : ''}: ${skipped.join(', ')}`)
            }
            this.new_value.value = ''
        },
        removeValue(index) {
            this.form.values.splice(index, 1)
        },
        submit() {
            this.errors = {}
            if (this.form.values.length === 0) {
                return this.$message.warning('Agrega al menos un valor')
            }
            this.loading_submit = true
            this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        (response.data.warnings || []).forEach(warning => {
                            this.$message.warning(warning)
                        })
                        this.showDialogForm = false
                        this.initForm()
                        this.load()
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data.errors || error.response.data
                    } else {
                        this.$message.error('Error inesperado al guardar la variable')
                    }
                })
                .then(() => {
                    this.loading_submit = false
                })
        },
        toggleActive(row) {
            this.$http.post(`/${this.resource}/toggle/${row.id}`)
                .then(response => {
                    if (response.data.success) {
                        row.active = !row.active
                        this.$message.success(response.data.message)
                    }
                })
                .catch(() => {
                    this.$message.error('No se pudo cambiar el estado de la variable')
                })
        },
        clickDelete(row) {
            this.$confirm(`¿Eliminar la variable "${row.name}" y sus valores?`, 'Confirmar', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            }).then(() => {
                this.$http.delete(`/${this.resource}/${row.id}`)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.load()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
            }).catch(() => {
            })
        },
    }
}
</script>

<style scoped>
.pv-color-dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    margin-right: 6px;
    vertical-align: middle;
}
.pv-add-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.pv-add-row .el-input {
    flex: 1;
}
.pv-add-row ::v-deep .el-color-picker {
    flex: 0 0 auto;
    height: auto;
}
.pv-add-row ::v-deep .el-color-picker__trigger {
    width: 40px;
    height: 40px;
    padding: 4px;
}
.pv-add-row .el-button {
    flex: 0 0 auto;
    white-space: nowrap;
}
</style>
