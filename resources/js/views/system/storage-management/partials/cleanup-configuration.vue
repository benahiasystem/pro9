<template>
    <el-dialog :title="title" :visible="showDialog" @open="open" @close="close" :close-on-click-modal="false">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Empresa<span class="text-danger"> *</span></label>
                            <el-select v-model="form.website_id"
                                       filterable
                                       :disabled="!!form.id"
                                       placeholder="Seleccione una empresa"
                                       class="w-100">
                                <el-option v-for="website in websites"
                                           :key="website.id"
                                           :label="website.description"
                                           :value="website.id">
                                </el-option>
                            </el-select>
                            <small v-if="errors.website_id" class="form-control-feedback" v-text="errors.website_id[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Carpetas a limpiar<span class="text-danger"> *</span></label>
                            <el-select v-model="form.packages"
                                       multiple
                                       placeholder="Seleccione las carpetas"
                                       popper-class="cc-packages-popper"
                                       class="w-100">
                                <el-option v-for="item in packages"
                                           :key="item.package"
                                           :label="item.label"
                                           :value="item.package">
                                    <!-- la etiqueta sola no dice que documentos se borran -->
                                    <div class="cc-option">
                                        <span class="cc-option__label">{{ item.label }}</span>
                                        <span class="cc-option__description" v-if="item.description">
                                            {{ item.description }}
                                        </span>
                                    </div>
                                </el-option>
                            </el-select>
                            <small v-if="errors.packages" class="form-control-feedback" v-text="errors.packages[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Frecuencia<span class="text-danger"> *</span></label>
                            <el-select v-model="form.frequency" placeholder="Seleccione" class="w-100">
                                <el-option v-for="item in frequencies"
                                           :key="item.value"
                                           :label="item.label"
                                           :value="item.value">
                                </el-option>
                            </el-select>
                            <small v-if="errors.frequency" class="form-control-feedback" v-text="errors.frequency[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Hora<span class="text-danger"> *</span></label>
                            <el-time-select v-model="form.time"
                                            :picker-options="{ start: '00:00', step: '00:30', end: '23:30' }"
                                            placeholder="Seleccione la hora"
                                            class="w-100">
                            </el-time-select>
                            <small v-if="errors.time" class="form-control-feedback" v-text="errors.time[0]"></small>
                        </div>
                    </div>

                    <!-- el dia solo aparece cuando la frecuencia lo necesita -->
                    <div class="col-md-6" v-if="form.frequency === 'weekly'">
                        <div class="form-group">
                            <label class="control-label">Día de la semana<span class="text-danger"> *</span></label>
                            <el-select v-model="form.day_of_week" placeholder="Seleccione" class="w-100">
                                <el-option v-for="item in days_of_week"
                                           :key="item.value"
                                           :label="item.label"
                                           :value="item.value">
                                </el-option>
                            </el-select>
                            <small v-if="errors.day_of_week" class="form-control-feedback" v-text="errors.day_of_week[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-6" v-if="form.frequency === 'monthly'">
                        <div class="form-group">
                            <label class="control-label">Día del mes<span class="text-danger"> *</span></label>
                            <el-select v-model="form.day_of_month" placeholder="Seleccione" class="w-100">
                                <el-option v-for="day in daysOfMonth"
                                           :key="day"
                                           :label="day"
                                           :value="day">
                                </el-option>
                            </el-select>
                            <small class="text-muted d-block">
                                Hasta {{ max_day_of_month }} para que se ejecute todos los meses
                            </small>
                            <small v-if="errors.day_of_month" class="form-control-feedback" v-text="errors.day_of_month[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <el-checkbox v-model="form.active">Programación activa</el-checkbox>
                        </div>
                    </div>

                    <div class="col-md-12 text-end pt-2">
                        <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                        <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                    </div>

                </div>
            </div>
        </form>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'record'],

        data() {
            return {
                resource: 'storage-management',
                loading_submit: false,
                websites: [],
                packages: [],
                frequencies: [],
                days_of_week: [],
                max_day_of_month: 28,
                form: {},
                errors: {},
            }
        },
        computed: {
            title() {
                return this.form.id ? 'Editar programación de limpieza' : 'Nueva programación de limpieza'
            },
            daysOfMonth() {
                return Array.from({ length: this.max_day_of_month }, (item, index) => index + 1)
            },
        },
        methods: {
            open() {
                this.initForm()
                this.getTables()

                // al editar se recibe la fila ya cargada
                if (this.record && this.record.id) {
                    this.form = {
                        id: this.record.id,
                        website_id: this.record.website_id,
                        packages: [...this.record.packages],
                        frequency: this.record.frequency,
                        day_of_week: this.record.day_of_week,
                        day_of_month: this.record.day_of_month,
                        // el backend guarda H:i:s, el selector trabaja con H:i
                        time: (this.record.time || '').substring(0, 5),
                        active: this.record.active,
                    }
                }
            },
            initForm() {
                this.errors = {}
                this.form = {
                    id: null,
                    website_id: null,
                    packages: [],
                    frequency: 'daily',
                    day_of_week: null,
                    day_of_month: null,
                    time: '03:00',
                    active: true,
                }
            },
            getTables() {
                this.$http.get(`/${this.resource}/configurations/tables`)
                    .then(response => {
                        this.websites = response.data.websites
                        this.packages = response.data.packages
                        this.frequencies = response.data.frequencies
                        this.days_of_week = response.data.days_of_week
                        this.max_day_of_month = response.data.max_day_of_month
                    })
                    .catch(() => {
                        this.$message.error('No se pudieron cargar las opciones')
                    })
            },
            submit() {

                this.loading_submit = true
                this.errors = {}

                this.$http.post(`/${this.resource}/configurations`, this.form)
                    .then(response => {

                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadConfigurations')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }

                    })
                    .catch(error => {

                        if (error.response && error.response.status === 422) {
                            this.errors = error.response.data.errors
                        } else {
                            this.$message.error('No se pudo guardar la programación')
                        }

                    })
                    .then(() => {
                        this.loading_submit = false
                    })

            },
            close() {
                this.initForm()
                this.$emit('update:showDialog', false)
            },
        }
    }
</script>

<!--
    sin scope a proposito: el desplegable de el-select se monta en el body,
    fuera del arbol del componente, y un estilo scoped no lo alcanza
-->
<style>
    .cc-packages-popper .el-select-dropdown__item {
        /* element fija 34px de alto y recorta, la descripcion necesita dos lineas */
        height: auto;
        line-height: 1.4;
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .cc-option {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .cc-option__label {
        font-size: 14px;
    }

    .cc-option__description {
        font-size: 12px;
        opacity: 0.65;
        white-space: normal;
    }
</style>
