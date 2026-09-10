<template>
    <el-dialog :title="titleDialog"
               :visible="showDialog"
               :append-to-body="true"
               @close="close"
               @open="create">
        <p class="text-muted">
            Vincula otra empresa a la cuenta del usuario: ingresará a todas sus empresas con el mismo correo y contraseña.
        </p>

        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div :class="{'has-danger': errors.composed_id}"
                                class="form-group">
                            <label class="control-label">1. Usuario (dueño de la cuenta)</label>
                            <el-select
                                v-model="form.composed_id"
                                filterable
                                :loading="loading_tables"
                                placeholder="Busca por nombre o correo"
                                popper-class="mu-select-dropdown"
                                @change="changeUser">

                                <el-option
                                    v-for="option in users"
                                    :key="option.composed_id"
                                    :label="option.full_name"
                                    :value="option.composed_id">
                                    <div class="mu-option">
                                        <span class="mu-option-name">{{ option.name }}</span>
                                        <span class="mu-option-caption">{{ option.email }} · {{ option.client_full_name }}</span>
                                    </div>
                                </el-option>

                            </el-select>
                            <div v-if="form.user && form.user.client_full_name" class="mu-origin-hint">
                                Su empresa principal: <b>{{ form.user.client_full_name }}</b>
                            </div>
                            <small v-if="errors.composed_id" class="form-control-feedback" v-text="errors.composed_id[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div :class="{'has-danger': errors.destination_client_id}"
                                class="form-group">
                            <label class="control-label">2. Empresa a vincular con su cuenta</label>
                            <el-select v-model="form.destination_client_id"
                                        filterable
                                        :loading="loading_tables"
                                        placeholder="Selecciona su otra empresa">
                                <el-option v-for="option in availableClients"
                                            :key="option.id"
                                            :label="option.full_name"
                                            :value="option.id"></el-option>
                            </el-select>
                            <small v-if="errors.destination_client_id" class="form-control-feedback" v-text="errors.destination_client_id[0]"></small>
                        </div>
                    </div>

                    <div v-if="showSummary" class="col-md-12">
                        <div class="mu-summary">
                            <b>{{ destinationName }}</b> quedará vinculada a la cuenta de <b>{{ form.user.email }}</b>: podrá alternar entre sus empresas con su misma contraseña.
                        </div>
                    </div>
                </div>

            </div>
            <div class="form-actions text-end mt-4 px-2">
                <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                <el-button :loading="loading_submit"
                           :disabled="!form.composed_id || !form.destination_client_id"
                           native-type="submit"
                           type="primary">Vincular empresa
                </el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>

export default {
    props: [
        'showDialog',
        'recordId',
    ],
    data() {
        return {
            loading_submit: false,
            loading_tables: false,
            titleDialog: null,
            resource: 'multi-users',
            errors: {},
            form: {},
            clients: [],
            users: [],
        }
    },
    async created()
    {
        await this.initForm()
        await this.getTables()
    },
    computed:
    {
        availableClients()
        {
            if (this.form.user && this.form.user.client_id) {
                return this.clients.filter(client => client.id !== this.form.user.client_id)
            }
            return this.clients
        },
        destinationName()
        {
            const client = _.find(this.clients, {id: this.form.destination_client_id})
            return client ? client.full_name : null
        },
        showSummary()
        {
            return !!(this.form.composed_id && this.form.destination_client_id && this.form.user && this.form.user.email)
        },
    },
    methods:
    {
        changeUser()
        {
            this.form.user = { ..._.find(this.users, {composed_id : this.form.composed_id}) }

            if (this.form.destination_client_id && this.form.destination_client_id === this.form.user.client_id) {
                this.form.destination_client_id = null
            }
        },
        async getTables()
        {
            this.loading_tables = true
            await this.$http.get(`/${this.resource}/tables`)
                        .then(response => {
                            this.clients = response.data.clients
                            this.users = response.data.users
                        })
                        .finally(() => {
                            this.loading_tables = false
                        })
        },
        initForm() {
            this.errors = {}
            this.form = {
                destination_client_id: null,
                composed_id: null,
                user: {}
            }

        },
        create()
        {
            this.titleDialog = 'Vincular otra empresa al usuario'
        },
        async submit()
        {
            this.loading_submit = true

            await this.$http.post(`/${this.resource}`, this.form)
                .then(response => {

                    if (response.data.success)
                    {
                        this.$message.success(response.data.message)
                        this.$eventHub.$emit('reloadData')
                        this.getTables()
                        this.close()
                    }
                    else
                    {
                        this.$message.error(response.data.message)
                    }

                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    } else {
                        console.log(error)
                    }
                })
                .finally(() => {
                    this.loading_submit = false
                })
        },
        close()
        {
            this.$emit('update:showDialog', false)
            this.initForm()
        },
    }
}
</script>
