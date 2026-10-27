<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group" :class="{'has-danger': errors.id}">
                        <label class="control-label">Código</label>
                        <el-input v-model="form.id" :readonly="recordId !== null" :maxlength="2"></el-input>
                        <small class="form-control-feedback" v-if="errors.id" v-text="errors.id[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group" :class="{'has-danger': errors.description}">
                            <label class="control-label">Descripción</label>
                            <el-input v-model="form.description"></el-input>
                            <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                        </div>
                    </div>
                    <!-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## -->
                    <div class="col-md-4">
                        <label>Naturaleza del medio</label>
                        <el-select v-model="form.collection_kind" :disabled="form.classification_editable === false" @change="changeCollectionKind">
                            <el-option value="cash" label="Efectivo físico"/><el-option value="account" label="Bancario / electrónico"/>
                            <el-option value="credit" label="Crédito"/><el-option value="unclassified" label="Sin clasificar"/>
                        </el-select>
                        <small class="text-danger" v-if="errors.collection_kind">{{ errors.collection_kind[0] }}</small>
                    </div>
                    <div class="col-md-4" v-if="form.collection_kind === 'cash'">
                        <label>Moneda del efectivo</label><el-select v-model="form.cash_currency_type_id" :disabled="form.classification_editable === false"><el-option value="VES" label="Bolívares"/><el-option value="USD" label="Dólares"/></el-select>
                    </div>
                    <div class="col-md-4"><el-checkbox v-model="form.is_active" :disabled="form.collection_kind === 'unclassified'">Disponible</el-checkbox><el-checkbox v-model="form.has_card">Solicitar datos de tarjeta</el-checkbox></div>
                    <!-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## -->
                    <div class="col-md-3" v-show="form.is_credit">
                        <label class="control-label">Número de dias</label>
                        <el-input v-model="form.number_days" :maxlength="3"></el-input>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end mt-4">
                <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>

    export default {
        props: ['showDialog', 'recordId'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'payment-method-types',
                errors: {},
                form: {},
                options: [],
            }
        },
        created() {
            this.initForm()
        },
        methods: {
            changeCollectionKind() {
                this.form.is_credit = this.form.collection_kind === 'credit' ? 1 : 0
                this.form.cash_currency_type_id = this.form.collection_kind === 'cash' ? (this.form.cash_currency_type_id || 'VES') : null
                if (this.form.collection_kind === 'unclassified') this.form.is_active = false
            },
            initForm() {
                this.errors = {}
                this.form = {
                    id: null,
                    description: null,
                    collection_kind: 'account',
                    cash_currency_type_id: null,
                    is_active: true, has_card: false,
                    is_credit: 0,
                    number_days: 0,
                }
            },
            create() {
                this.titleDialog = (this.recordId)? 'Editar método de pago':'Nuevo método de pago'
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`)
                        .then(response => {
                            this.form = {...response.data, is_active: !!response.data.is_active, has_card: !!response.data.has_card}
                        })
                }
            },
            submit() {
                this.loading_submit = true
                // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
                this.errors = {}
                const request = this.recordId !== null
                    ? this.$http.put(`/${this.resource}/${encodeURIComponent(this.recordId)}`, this.form)
                    : this.$http.post(`/${this.resource}`, this.form)
                request
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response && error.response.status === 422) {
                            const data = error.response.data
                            this.errors = data.errors || (typeof data.message === 'object' ? data.message : data)
                        } else {
                            this.$message.error(error.response?.data?.message || 'No se pudo guardar el método de pago')
                        }
                    })
                // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
                    .then(() => {
                        this.loading_submit = false
                    })
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
        }
    }
</script>
