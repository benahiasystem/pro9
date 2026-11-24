<template>
    <div class="card card-config">
        <div class="card-header bg-info">
            <h3 class="my-0">Configuración de pagos
            </h3>
            
            <div class="card-actions white-text">
                <a href="#" class="card-action card-action-toggle text-white" data-card-toggle=""></a>
            </div>
        </div>
        <div class="card-body">

            <el-tabs v-model="form.type">

                <el-tab-pane label="Mercado Pago" name="02">

                    <div class="row pt-1">
                        <div class="col-md-6">
                            <h4 class="control-label">Habilitar</h4>
                            <div :class="{'has-danger': errors.enabled_mp}"
                                    class="form-group">
                                <el-switch v-model="form.enabled_mp"
                                            active-text="Si"
                                            inactive-text="No"></el-switch>
                                <small v-if="errors.enabled_mp"
                                        class="form-control-feedback"
                                        v-text="errors.enabled_mp[0]"></small>
                            </div>
                        </div>
                        
                        <template v-if="form.enabled_mp">

                            <div class="col-md-12 mt-3">
                                <div class="form-group" :class="{'has-danger': errors.public_key_mp}">
                                    <label class="control-label">Token público <span class="text-danger">*</span></label>
                                    <el-input v-model="form.public_key_mp"></el-input>
                                    <small class="form-control-feedback" v-if="errors.public_key_mp" v-text="errors.public_key_mp[0]"></small>
                                </div>
                            </div>


                            <div class="col-md-12 mt-3">
                                <div class="form-group" :class="{'has-danger': errors.access_token_mp}">
                                    <label class="control-label">Token de acceso (privado) <span class="text-danger">*</span>
                                        <el-tooltip class="item" effect="dark" content="Se muestran los últimos 8 caracteres del token guardado. Ingrese un valor nuevo para reemplazarlo." placement="top-end">
                                            <i class="fa fa-info-circle"></i>
                                        </el-tooltip>
                                    </label>
                                    <el-input
                                        :value="accessTokenInputValue"
                                        :type="accessTokenInputType"
                                        :readonly="isShowingSavedAccessToken"
                                        autocomplete="new-password"
                                        placeholder=""
                                        @input="onAccessTokenInput"
                                        @focus="onAccessTokenFocus"
                                        @blur="onAccessTokenBlur"
                                    >
                                        <span
                                            slot="suffix"
                                            class="access-token-mp-toggle"
                                            @mousedown.prevent
                                            @click="toggleAccessTokenVisibility"
                                        >
                                            <i
                                                v-if="accessTokenMpRevealLoading"
                                                class="fa fa-spinner fa-spin"
                                            ></i>
                                            <i
                                                v-else
                                                :class="accessTokenMpVisible ? 'fa fa-eye-slash' : 'fa fa-eye'"
                                            ></i>
                                        </span>
                                    </el-input>
                                    <small class="form-control-feedback" v-if="errors.access_token_mp" v-text="errors.access_token_mp[0]"></small>
                                </div>
                            </div>

                        </template>
        
                    </div>
                </el-tab-pane>
                <div class="form-actions text-end mt-3">
                    <el-button type="primary" @click="submit" :loading="loading_submit">Guardar</el-button>
                </div>
            </el-tabs>

        </div> 
    </div>
</template>

<style>
    .access-token-mp-toggle {
        display: inline-flex;
        align-items: center;
        height: 100%;
        padding-right: 8px;
        cursor: pointer;
        color: #909399;
    }


</style>

<script>

    export default {
        data() {
            return {
                resource: 'payment-configurations',
                recordId: null,
                form: {},
                errors: {},
                loading_submit: false,
                accessTokenMpVisible: false,
                accessTokenMpEditing: false,
                accessTokenMpDraft: null,
                accessTokenMpRevealLoading: false,
                accessTokenMpUnchangedSinceReveal: false,
            }
        },
        computed: {
            isShowingSavedAccessToken() {
                return this.form.has_access_token_mp
                    && !this.accessTokenMpDraft
                    && !this.accessTokenMpEditing
            },
            accessTokenInputType() {
                if (this.isShowingSavedAccessToken) {
                    return 'text'
                }

                return this.accessTokenMpVisible ? 'text' : 'password'
            },
            accessTokenInputValue() {
                if (this.isShowingSavedAccessToken) {
                    const suffix = this.form.access_token_mp_suffix || ''

                    if (!suffix) {
                        return ''
                    }

                    const totalLength = this.form.access_token_mp_length || suffix.length
                    const hiddenLength = Math.max(totalLength - suffix.length, 0)

                    return '*'.repeat(hiddenLength) + suffix
                }

                return this.accessTokenMpDraft || ''
            },
        },
        async created() {
            await this.initForm()
            await this.getData()
        },
        methods: {
            onAccessTokenInput(value) {
                this.accessTokenMpDraft = value
                this.accessTokenMpUnchangedSinceReveal = false
            },
            onAccessTokenFocus() {
                if (this.isShowingSavedAccessToken) {
                    this.accessTokenMpEditing = true
                    this.accessTokenMpDraft = ''
                    this.accessTokenMpVisible = false
                    this.accessTokenMpUnchangedSinceReveal = false
                }
            },
            onAccessTokenBlur() {
                if (this.accessTokenMpVisible) {
                    this.maskAccessTokenMp()
                }

                if (this.accessTokenMpEditing && !this.accessTokenMpDraft) {
                    this.accessTokenMpEditing = false
                }
            },
            maskAccessTokenMp() {
                this.accessTokenMpVisible = false

                if (this.accessTokenMpUnchangedSinceReveal) {
                    this.accessTokenMpDraft = null
                }
            },
            async revealAccessTokenMp() {
                this.accessTokenMpRevealLoading = true

                try {
                    const { data } = await this.$http.get(`/${this.resource}/access-token-mp`)

                    if (!data.success || !data.access_token_mp) {
                        throw new Error(data.message || 'No se pudo obtener el token de acceso')
                    }

                    this.accessTokenMpDraft = data.access_token_mp
                    this.accessTokenMpVisible = true
                    this.accessTokenMpUnchangedSinceReveal = true
                    this.accessTokenMpEditing = false
                } catch (error) {
                    const message = error.response?.data?.message
                        || error.message
                        || 'No se pudo obtener el token de acceso'

                    this.$message.error(message)
                } finally {
                    this.accessTokenMpRevealLoading = false
                }
            },
            async toggleAccessTokenVisibility() {
                if (this.accessTokenMpRevealLoading) {
                    return
                }

                if (this.accessTokenMpVisible) {
                    this.maskAccessTokenMp()
                    return
                }

                if (this.isShowingSavedAccessToken) {
                    await this.revealAccessTokenMp()
                    return
                }

                this.accessTokenMpVisible = true
            },
            resetAccessTokenMpState() {
                this.accessTokenMpVisible = false
                this.accessTokenMpEditing = false
                this.accessTokenMpDraft = null
                this.accessTokenMpRevealLoading = false
                this.accessTokenMpUnchangedSinceReveal = false
            },
            submit(){

                this.loading_submit = true

                const payload = { ...this.form }

                if (payload.type === '02') {
                    if (this.accessTokenMpDraft) {
                        payload.access_token_mp = this.accessTokenMpDraft
                    } else if (this.form.has_access_token_mp) {
                        delete payload.access_token_mp
                    }
                }

                this.$http.post(`/${this.resource}`, payload)
                    .then(response => {
                        if (response.data && response.data.success) {
                            this.$message.success(response.data.message || 'Configuración actualizada')

                            if (this.form.type === '02' && this.accessTokenMpDraft) {
                                this.form.has_access_token_mp = true
                                this.form.access_token_mp_suffix = this.accessTokenMpDraft.slice(-8)
                                this.form.access_token_mp_length = this.accessTokenMpDraft.length
                                this.resetAccessTokenMpState()
                            }
                        } else {
                            this.$message.error((response.data && response.data.message) || 'No se pudo guardar la configuración')
                        }
                    })
                    .catch(error => {
                        if (error.response && error.response.status === 422) {
                            const data = error.response.data || {}
                            this.errors = data.errors || data
                            const firstError = data.errors
                                ? Object.values(data.errors).flat()[0]
                                : null
                            this.$message.error(firstError || data.message || 'Error de validación')
                        } else {
                            console.log(error)
                            const message = error.response && error.response.data
                                ? error.response.data.message
                                : null
                            this.$message.error(message || 'Error al guardar la configuración')
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })

            },
            initForm(){

                this.form = {
                    type: '02',

                    enabled_mp : false,
                    access_token_mp: null,
                    public_key_mp: null,
                    has_access_token_mp: false,
                    access_token_mp_suffix: null,
                    access_token_mp_length: null,
                    default_payment_for_payment_links: null,
                }

                this.errors = {}

            },
            async getData() {
                await this.$http.get(`/${this.resource}/record`)
                    .then(response => {
                        const data = response.data.data

                        this.form = {
                            ...data,
                            type: '02',
                            access_token_mp: null,
                            has_access_token_mp: !!data.has_access_token_mp,
                            access_token_mp_suffix: data.access_token_mp_suffix || null,
                            access_token_mp_length: data.access_token_mp_length || null,
                        }

                        this.resetAccessTokenMpState()
                    })
            }, 
        }
    }
</script>
