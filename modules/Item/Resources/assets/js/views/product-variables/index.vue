<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/list-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard-list"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 5a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2" /><path d="M9 12l.01 0" /><path d="M13 12l2 0" /><path d="M9 16l.01 0" /><path d="M13 16l2 0" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Atributos de productos</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-custom btn-sm mt-2 me-2" type="button" @click.prevent="showImportDialog = true">
                    <i class="fa fa-upload"></i> Importar
                </button>
                <button class="btn btn-custom btn-sm mt-2 me-2" type="button" @click.prevent="clickCreate()">
                    <i class="fa fa-plus-circle"></i> Nueva variable
                </button>
            </div>
        </div>

        <div class="card mb-0 tab-content-default row-new">
            <div class="card-body">
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
                                <button type="button" plain title="Editar" class="btn btn-xs btn-info btn-shad me-1" @click.prevent="clickEdit(row)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415"></path><path d="M16 5l3 3"></path></svg>
                                </button>
                                <button type="button" plain title="Eliminar" class="btn btn-xs btn-danger btn-shad" @click.prevent="clickDelete(row)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg>
                                </button>
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
                    <label class="mt-2">Valores de la variable</label>
                    <div class="pv-add-row mb-2">
                        <el-input v-model="new_value.value"
                                  placeholder="Ej: S, M, L (separa con comas para agregar varios)"
                                  @keyup.enter.native.prevent="addValue"></el-input>
                        <label v-if="form.value_type === 'color'"
                               class="pv-color-input"
                               title="Color del valor">
                            <input v-model="new_value.color" type="color" @input="color_touched = true">
                        </label>
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
                    </div>
                </div>

                <div class="form-actions text-end pt-2">
                    <el-button class="second-buton me-2" @click.prevent="cancelEdit">Cancelar</el-button>
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar variable</el-button>
                </div>
            </form>
        </el-dialog>

        <product-variables-import
            :showDialog.sync="showImportDialog"
            @imported="load"
        ></product-variables-import>
    </div>
</template>

<script>
import ProductVariablesImport from './import.vue'

const DEFAULT_COLOR = '#409EFF'

const COLOR_NAMES = {
    'rojo': '#E53935',
    'rojo oscuro': '#8E1414',
    'vino': '#6D1B2E',
    'guinda': '#6D1B2E',
    'granate': '#7B1E1E',
    'coral': '#FF7F50',
    'salmon': '#FA8072',
    'naranja': '#FB8C00',
    'anaranjado': '#FB8C00',
    'mandarina': '#F4711F',
    'durazno': '#FFCBA4',
    'amarillo': '#FDD835',
    'mostaza': '#D4A017',
    'dorado': '#D4AF37',
    'oro': '#D4AF37',
    'ocre': '#CC7722',
    'verde': '#43A047',
    'verde claro': '#81C784',
    'verde oscuro': '#1B5E20',
    'verde limon': '#B2D732',
    'limon': '#B2D732',
    'menta': '#98E2C6',
    'oliva': '#808000',
    'esmeralda': '#0F9D58',
    'turquesa': '#1ABC9C',
    'aqua': '#00FFFF',
    'cian': '#00BCD4',
    'celeste': '#4FC3F7',
    'azul': '#1E88E5',
    'azul claro': '#64B5F6',
    'azul oscuro': '#0D47A1',
    'azul marino': '#1A237E',
    'marino': '#1A237E',
    'indigo': '#3F51B5',
    'morado': '#8E24AA',
    'purpura': '#6A1B9A',
    'violeta': '#7B1FA2',
    'lila': '#B39DDB',
    'lavanda': '#B57EDC',
    'magenta': '#D81B60',
    'fucsia': '#E91E8C',
    'rosado': '#F06292',
    'rosa': '#F06292',
    'palo rosa': '#E8B4B8',
    'marron': '#795548',
    'cafe': '#795548',
    'chocolate': '#5D4037',
    'terracota': '#C56E4E',
    'camel': '#C19A6B',
    'beige': '#E8DCC4',
    'crema': '#F3E9D2',
    'arena': '#D9CBA3',
    'khaki': '#C3B091',
    'caqui': '#C3B091',
    'hueso': '#F2EDE4',
    'blanco': '#FFFFFF',
    'gris': '#9E9E9E',
    'gris claro': '#D5D8DC',
    'gris oscuro': '#4F4F4F',
    'plateado': '#C0C0C0',
    'plata': '#C0C0C0',
    'negro': '#111111',
}

export default {
    name: 'TenantProductVariablesIndex',
    components: {
        ProductVariablesImport,
    },
    data() {
        return {
            resource: 'product-variables',
            records: [],
            loading: false,
            loading_submit: false,
            showDialogForm: false,
            showImportDialog: false,
            errors: {},
            form: {},
            new_value: {value: '', color: DEFAULT_COLOR},
            color_touched: false,
        }
    },
    watch: {
        'new_value.value'(value) {
            if (this.form.value_type !== 'color' || this.color_touched) {
                return
            }
            const last = (value || '').split(',').pop()
            const color = this.colorFromName(last)
            if (color) {
                this.new_value.color = color
            }
        },
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
            this.new_value = {value: '', color: DEFAULT_COLOR}
            this.color_touched = false
        },
        colorFromName(name) {
            const key = (name || '')
                .toString()
                .normalize('NFD')
                .toLowerCase()
                .replace(/[^a-z0-9 #]/g, '')
                .replace(/\s+/g, ' ')
                .trim()

            if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/.test(key)) {
                return key.toUpperCase()
            }
            return COLOR_NAMES[key] || COLOR_NAMES[key.replace(/s$/, '')] || null
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
            this.new_value = {value: '', color: DEFAULT_COLOR}
            this.color_touched = false
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
                    row.color = (!this.color_touched && this.colorFromName(value)) || this.new_value.color
                }
                this.form.values.push(row)
            })

            if (skipped.length > 0) {
                this.$message.warning(`Ya existe${skipped.length !== 1 ? 'n' : ''}: ${skipped.join(', ')}`)
            }
            this.new_value.value = ''
            this.color_touched = false
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
    align-items: stretch;
    gap: 8px;
}
.pv-add-row .el-input {
    flex: 1 1 auto;
    min-width: 0;
}
.pv-add-row .el-button {
    flex: 0 0 auto;
    white-space: nowrap;
}
.pv-color-input {
    flex: 0 0 auto;
    display: block;
    width: 40px;
    margin-bottom: 0;
    padding: 4px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
}
.pv-color-input:hover {
    border-color: #c0c4cc;
}
.pv-color-input input[type="color"] {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 22px;
    padding: 0;
    border: 0;
    background: none;
    cursor: pointer;
}
.pv-color-input input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
}
.pv-color-input input[type="color"]::-webkit-color-swatch {
    border: 0;
    border-radius: 2px;
}
.pv-color-input input[type="color"]::-moz-color-swatch {
    border: 0;
    border-radius: 2px;
}
</style>
