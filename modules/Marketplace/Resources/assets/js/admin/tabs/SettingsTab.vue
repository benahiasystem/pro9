<template>
    <div v-loading="loading">
        <!-- El interruptor va arriba y separado: es el control con más
             consecuencias de toda la pantalla. -->
        <div class="mkt-switch mb-4" :class="{ 'is-on': form.is_enabled }">
            <div>
                <strong>Marketplace {{ form.is_enabled ? 'publicado' : 'apagado' }}</strong>
                <p class="mb-0 text-muted">
                    <template v-if="form.is_enabled">
                        El público puede navegar el marketplace y las apps sincronizan con normalidad.
                    </template>
                    <template v-else>
                        Los visitantes ven una página de «no disponible» y las apps reciben un aviso, no un error.
                        <strong>No se pierde ningún dato.</strong>
                    </template>
                </p>
            </div>
            <el-switch v-model="form.is_enabled" @change="confirmToggle"/>
        </div>

        <form autocomplete="off" @submit.prevent="save">
            <div class="row">
                <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.community_name }">
                    <label class="control-label">Nombre de la comunidad</label>
                    <el-input v-model="form.community_name" :maxlength="120"
                              placeholder="Ej. Condominio Los Parques de Santa Clara"/>
                    <small class="text-muted">Se muestra en la cabecera, el titular y el pie del marketplace.</small>
                    <small v-if="errors.community_name" class="form-control-feedback" v-text="errors.community_name[0]"></small>
                </div>
                <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.title }">
                    <label class="control-label">Título (SEO)</label>
                    <el-input v-model="form.title" :maxlength="120"/>
                    <small v-if="errors.title" class="form-control-feedback" v-text="errors.title[0]"></small>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-12 mb-2" :class="{ 'has-danger': errors.description }">
                    <label class="control-label">Descripción (SEO)</label>
                    <el-input v-model="form.description" :maxlength="255"/>
                    <small v-if="errors.description" class="form-control-feedback" v-text="errors.description[0]"></small>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-lg-8 col-md-7 col-sm-12 mb-2" :class="{ 'has-danger': errors.hero_title }">
                    <label class="control-label">Titular de la portada</label>
                    <el-input v-model="form.hero_title" :maxlength="120"/>
                    <small v-if="errors.hero_title" class="form-control-feedback" v-text="errors.hero_title[0]"></small>
                </div>
                <div class="form-group col-lg-4 col-md-5 col-sm-12 mb-2" :class="{ 'has-danger': errors.hero_highlight }">
                    <label class="control-label">Remate destacado</label>
                    <el-input v-model="form.hero_highlight" :maxlength="60"/>
                    <small v-if="errors.hero_highlight" class="form-control-feedback" v-text="errors.hero_highlight[0]"></small>
                </div>
                <div class="col-12">
                    <div class="mkt-hero-preview">
                        <span>{{ form.hero_title }}</span>
                        <em v-if="form.hero_highlight">{{ form.hero_highlight }}</em>
                    </div>
                    <small class="text-muted">
                        Así se verá en la portada. El remate se pinta en rosa y cursiva.
                        Deja ambos vacíos para ocultar el titular.
                    </small>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-lg-6 col-md-12 mb-2" :class="{ 'has-danger': errors.whatsapp_greeting }">
                    <label class="control-label">Saludo de WhatsApp (un producto)</label>
                    <el-input v-model="form.whatsapp_greeting" :maxlength="255"/>
                    <small class="text-muted">
                        Se antepone al nombre y código del producto en el mensaje que abre el comprador.
                    </small>
                    <small v-if="errors.whatsapp_greeting" class="form-control-feedback" v-text="errors.whatsapp_greeting[0]"></small>
                </div>
                <div class="form-group col-lg-6 col-md-12 mb-2" :class="{ 'has-danger': errors.whatsapp_cart_greeting }">
                    <label class="control-label">Saludo del pedido (carrito)</label>
                    <el-input v-model="form.whatsapp_cart_greeting" :maxlength="255"/>
                    <small class="text-muted">
                        Encabeza el mensaje del carrito; debajo va la lista de productos con sus cantidades.
                    </small>
                    <small v-if="errors.whatsapp_cart_greeting" class="form-control-feedback" v-text="errors.whatsapp_cart_greeting[0]"></small>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.items_per_page }">
                    <label class="control-label">Productos por página</label>
                    <el-input-number v-model="form.items_per_page" :min="6" :max="96"
                                     controls-position="right" class="w-100"/>
                    <small v-if="errors.items_per_page" class="form-control-feedback" v-text="errors.items_per_page[0]"></small>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.max_items_per_store }">
                    <label class="control-label">Máximo de productos por tienda</label>
                    <el-input-number v-model="form.max_items_per_store" :min="1" :max="5000"
                                     controls-position="right" class="w-100"/>
                    <small class="text-muted">Un sync que lo supere se rechaza con error 422.</small>
                    <small v-if="errors.max_items_per_store" class="form-control-feedback" v-text="errors.max_items_per_store[0]"></small>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.currency_symbol }">
                    <label class="control-label">Símbolo de moneda</label>
                    <el-input v-model="form.currency_symbol" :maxlength="8" placeholder="S/"/>
                    <small class="text-muted">Con el que se pintan los precios de las tiendas que los muestran.</small>
                    <small v-if="errors.currency_symbol" class="form-control-feedback" v-text="errors.currency_symbol[0]"></small>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.auto_block_reports }">
                    <label class="control-label">Bloqueo automático por denuncias</label>
                    <el-input-number v-model="form.auto_block_reports" :min="0" :max="10000"
                                     controls-position="right" class="w-100"/>
                    <small class="text-muted">
                        <template v-if="form.auto_block_reports > 0">
                            Un producto se retira solo al llegar a {{ form.auto_block_reports }} denuncias.
                            Solo tú puedes desbloquearlo.
                        </template>
                        <template v-else>
                            Desactivado: ningún producto se bloqueará solo.
                        </template>
                    </small>
                    <small v-if="errors.auto_block_reports" class="form-control-feedback" v-text="errors.auto_block_reports[0]"></small>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2" :class="{ 'has-danger': errors.ranking_threshold }">
                    <label class="control-label">Umbral del ranking</label>
                    <el-input-number v-model="form.ranking_threshold" :min="0" :max="10000"
                                     controls-position="right" class="w-100"/>
                    <small class="text-muted">
                        <template v-if="form.ranking_threshold > 0">
                            Una tienda muestra su número de recomendaciones y gana posición solo al llegar a
                            {{ form.ranking_threshold }} vecinos distintos. Por debajo no destaca.
                        </template>
                        <template v-else>
                            Sin umbral: el ranking se muestra desde la primera recomendación.
                        </template>
                    </small>
                    <small v-if="errors.ranking_threshold" class="form-control-feedback" v-text="errors.ranking_threshold[0]"></small>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-12 mb-2">
                    <label class="text-muted">Motivos de denuncia</label>
                    <div class="mkt-chips">
                        <el-tag v-for="(reason, i) in form.report_reasons" :key="i" closable
                                @close="form.report_reasons.splice(i, 1)">
                            {{ reason }}
                        </el-tag>
                        <el-input v-if="addingReason" ref="reasonInput" v-model="newReason"
                                  class="mkt-chips__input" :maxlength="60"
                                  @keyup.enter.native="addReason" @blur="addReason"/>
                        <el-button v-else icon="el-icon-plus" @click="startAddingReason">Añadir</el-button>
                    </div>
                </div>
            </div>

            <div v-if="!termsUrl" class="alert alert-warning mt-2" role="alert">
                <strong>No hay términos y condiciones activos.</strong>
                El enlace no se mostrará en el marketplace ni en las apps.
                Actívalos en Configuración → Términos si los necesitas.
            </div>

            <div class="col-md-12 text-end pt-2 px-0">
                <el-button type="primary" native-type="submit" :loading="saving">Guardar ajustes</el-button>
            </div>
        </form>
    </div>
</template>

<script>
export default {
    data() {
        return {
            loading: false,
            saving: false,
            addingReason: false,
            newReason: '',
            termsUrl: null,
            errors: {},
            form: {
                is_enabled: false, title: '', description: '', community_name: '',
                hero_title: '', hero_highlight: '',
                whatsapp_greeting: '', whatsapp_cart_greeting: '', currency_symbol: 'S/',
                items_per_page: 24, max_items_per_store: 500,
                auto_block_reports: 100, ranking_threshold: 10, report_reasons: [],
            },
        }
    },

    created() {
        this.load()
    },

    methods: {
        load() {
            this.loading = true
            this.$http.get('/marketplace/admin/settings').then(({ data }) => {
                this.form = { ...this.form, ...data.data }
                this.termsUrl = data.terms_url
            }).finally(() => { this.loading = false })
        },

        confirmToggle(value) {
            if (value) {
                this.persist({ is_enabled: true })
                return
            }

            this.$confirm(
                'Los visitantes verán una página de «no disponible» y las apps recibirán un aviso en lugar de un error. No se pierde ningún dato y puedes volver a encenderlo cuando quieras.',
                'Apagar el marketplace',
                { confirmButtonText: 'Apagar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => {
                this.persist({ is_enabled: false })
            }).catch(() => {
                // Revertir el switch si cancela.
                this.form.is_enabled = true
            })
        },

        startAddingReason() {
            this.addingReason = true
            this.$nextTick(() => this.$refs.reasonInput && this.$refs.reasonInput.focus())
        },

        addReason() {
            const value = this.newReason.trim()
            if (value && !this.form.report_reasons.includes(value)) {
                this.form.report_reasons.push(value)
            }
            this.newReason = ''
            this.addingReason = false
        },

        save() {
            this.saving = true
            this.persist(this.form).finally(() => { this.saving = false })
        },

        persist(payload) {
            this.errors = {}

            return this.$http.put('/marketplace/admin/settings', payload).then(({ data }) => {
                this.$notify({ title: 'Éxito', message: data.message, type: 'success' })
                this.form = { ...this.form, ...data.data }
                this.$emit('changed')
            }).catch((error) => {
                const body = error.response && error.response.data
                const message = body && body.message

                if (error.response && error.response.status === 422 && typeof message === 'object') {
                    this.errors = message
                } else {
                    this.$notify({
                        title: 'Error',
                        message: typeof message === 'string' ? message : 'Ocurrió un error al guardar',
                        type: 'error',
                    })
                }
            })
        },
    },
}
</script>

<style scoped>
.mkt-switch {
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    padding: 16px 20px; border: 1px solid #e4e7ed; border-radius: 8px; background: #fafafa;
}
.mkt-switch.is-on { border-color: #c2e7b0; background: #f0f9eb; }
.mkt-switch p { font-size: 12px; max-width: 620px; }

/* gap en vez de márgenes por chip: espaciado uniforme y sin sobrantes */
.mkt-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.mkt-chips__input { width: 200px; }

/* Vista previa del titular, con los colores reales de la portada */
.mkt-hero-preview {
    margin: 4px 0 2px;
    padding: 14px 18px;
    border-radius: 8px;
    background: #faf7f2;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #020f3c;
}
.mkt-hero-preview em { font-style: italic; font-weight: 300; color: #ff006c; }
</style>
