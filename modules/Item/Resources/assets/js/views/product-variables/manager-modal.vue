<template>
    <el-dialog :title="editing ? titleForm : 'Gestionar atributos'"
               :visible="showDialog"
               :close-on-click-modal="false"
               append-to-body
               top="7vh"
               :width="editing ? '540px' : '760px'"
               @close="handleCloseDialog"
               @open="open">

        <template v-if="!editing">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted">
                    Crea variables (Talla, Color, Material...) y sus valores para generar variaciones de productos.
                </span>
                <el-button type="primary" size="small" @click.prevent="clickCreate">+ Nueva variable</el-button>
            </div>

            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th style="width: 70px">Activo</th>
                        <th style="width: 22%">Nombre</th>
                        <th style="width: 110px">Tipo</th>
                        <th>Valores</th>
                        <th style="width: 100px">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="row in records" :key="row.id">
                        <td class="align-middle">
                            <el-switch :value="row.active" @change="toggleActive(row)"></el-switch>
                        </td>
                        <td class="align-middle">
                            <span class="font-weight-bold">{{ row.name }}</span>
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
                        <td class="align-middle text-nowrap">
                            <el-button size="mini"
                                       plain
                                       icon="el-icon-edit"
                                       title="Editar"
                                       class="me-1"
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
        </template>

        <template v-else>
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

                <div class="form-group">
                    <label class="control-label">Valores de la variable</label>
                    <div class="pv-add-row mb-2">
                        <el-input v-model="new_value.value"
                                  placeholder="Ej: S, M, L (separa con comas para agregar varios)"
                                  @keyup.enter.native.prevent="addValue"></el-input>
                        <el-color-picker v-if="form.value_type === 'color'"
                                         v-model="new_value.color"
                                         title="Color del valor"></el-color-picker>
                        <el-button plain @click.prevent="addValue">+ Agregar</el-button>
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

                <div class="form-actions text-end pt-2">
                    <el-button class="second-buton me-2" @click.prevent="cancelEdit">Volver</el-button>
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar variable</el-button>
                </div>
            </form>
        </template>
    </el-dialog>
</template>

<script>
export default {
    name: 'ProductVariablesManagerModal',
    props: ['showDialog'],
    data() {
        return {
            resource: 'product-variables',
            records: [],
            loading: false,
            loading_submit: false,
            editing: false,
            changed: false,
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
        open() {
            this.editing = false
            this.changed = false
            this.load()
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
            this.editing = true
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
            this.editing = true
        },
        cancelEdit() {
            this.editing = false
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
                        this.changed = true
                        this.editing = false
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
                        this.changed = true
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
                            this.changed = true
                            this.load()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
            }).catch(() => {
            })
        },
        handleCloseDialog() {
            if (this.editing) {
                this.$confirm('¿Cerrar sin guardar la variable en edición?', 'Confirmar', {
                    confirmButtonText: 'Cerrar sin guardar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.forceClose()
                }).catch(() => {
                })
            } else {
                this.forceClose()
            }
        },
        forceClose() {
            this.editing = false
            this.initForm()
            this.$emit('update:showDialog', false)
            if (this.changed) {
                this.$emit('updated')
                this.changed = false
            }
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
