<template>
    <el-dialog :title="titleDialog"
               :visible="showDialog"
               append-to-body
               width="30%"
               :close-on-click-modal="false"
               :close-on-press-escape="false"
               :show-close="false"
               @open="create">
        <div v-if="form.response_message"
             class="row mb-4">
            <div class="col-md-12">
                <el-alert
                    :title="form.response_message"
                    :type="form.response_type"
                    show-icon>
                </el-alert>
            </div>
        </div>
        <!-- ########## INICIO CAMBIO SIN XML CDR SUNAT -->
        <!-- El cierre de la orden de entrega local no muestra firma, envío, ticket ni CDR fiscal. -->
        <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->

        <template v-if="showDocumentActions">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-6 text-center font-weight-bold mt-3">
                    <button class="btn btn-lg btn-info waves-effect waves-light"
                            type="button"
                            @click="clickDownload('a4')">
                        <i class="fa fa-file-alt"></i>
                    </button>
                    <p>Descargar A4</p>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-6 text-center font-weight-bold mt-3">
                    <button class="btn btn-lg btn-info waves-effect waves-light"
                            type="button"
                            @click="clickDownload('ticket')">
                        <i class="fa fa-file-alt"></i>
                    </button>
                    <p>80MM</p>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-6 text-center font-weight-bold mt-3">
                    <button class="btn btn-lg btn-info waves-effect waves-light"
                            type="button"
                            @click="clickDownload('ticket_58')">
                        <i class="fa fa-file-alt"></i>
                    </button>
                    <p>58MM</p>
                </div>
                <!-- ########## INICIO CAMBIO SIN XML CDR SUNAT -->
                <!-- Sólo se ofrecen formatos PDF locales. -->
                <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <el-input v-model="form.customer_email">
                        <el-button slot="append"
                                   :loading="loading"
                                   icon="el-icon-message"
                                   @click="clickSendEmail">Enviar
                        </el-button>
                    </el-input>
                    <small v-if="errors.customer_email"
                           class="form-control-feedback"
                           v-text="errors.customer_email[0]"></small>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12"
                v-if="!config.qr_api_enable_ws">
                    <el-input v-model="form.customer_telephone">
                        <!-- ########### INICIO CAMBIO TELEFONÍA VENEZUELA -->
                        <template slot="prepend">+58</template>
                        <!-- ########### FIN CAMBIO TELEFONÍA VENEZUELA -->
                        <el-button slot="append"
                                   @click="clickSendWhatsapp">Enviar
                            <el-tooltip class="item"
                                        content="Es necesario tener aperturado Whatsapp web"
                                        effect="dark"
                                        placement="top-start">
                                <i class="fab fa-whatsapp"></i>
                            </el-tooltip>
                        </el-button>
                    </el-input>
                    <small v-if="errors.customer_telephone"
                           class="form-control-feedback"
                           v-text="errors.customer_telephone[0]"></small>
                </div>
                    <template v-else>
                        <QrApi 
                            colClass="col-md-12"
                            :wsPhone="form.customer_telephone"
                            :wsFile="form.print_ticket"
                            :wsFileA4="form.pdf_a4_filename"
                            :wsDocument="form.number"
                            :wsMessage="form.message_text"
                            :wsData="form.pdf_a4_data"
                        />
                    </template>
            </div>
        </template>

        <span slot="footer"
              v-if="!loading_sunat_send"
              class="dialog-footer">
            <template v-if="showClose">
                <el-button @click="clickClose">Cerrar</el-button>
            </template>
            <template v-else>
                <el-button class="list"
                           @click="clickFinalize">Ir al listado</el-button>
                <el-button type="primary"
                           @click="clickNewDocument">{{ text_button }}</el-button>
            </template>
        </span>
    </el-dialog>
</template>

<script>
import {mapState, mapActions} from "vuex/dist/vuex.mjs";
import QrApi from '@viewsModuleQrApi/QrApiTemplate.vue'

export default {
    props: ['showDialog',
        'recordId',
        'showClose'
    ],
    components: {
        QrApi
    },
    name: 'DispatchFinish',
    data() {
        return {
            titleDialog: null,
            loading: false,
            loading_sunat_send: false,
            loading_sunat_send_response: false,
            loading_sunat_status_ticket: false,
            loading_sunat_status_ticket_response: false,
            resource: 'dispatches',
            errors: {},
            form: {},
            company: {},
            locked_emission: {},
            text_button: null,
            response_sunat_send: {},
            response_sunat_status_ticket: {},
        }
    },
    async created() {
        this.initForm()
        this.text_button = 'Nueva orden de entrega'
    },
    computed: {
        ...mapState([
            'config',
        ]),
        /**
         * Tipo de alerta devuelto por statusTicket: success (aceptado),
         * warning (observado), error (rechazado) o info (en proceso)
         */
        statusTicketType() {
            const response = this.response_sunat_status_ticket
            if (!response) return 'info'
            if (response.response_type) return response.response_type

            return response.success ? 'success' : 'error'
        },
        statusTicketTitle() {
            const response = this.response_sunat_status_ticket
            if (!response) return ''

            const state = response.state_description ? `${response.state_description}: ` : ''

            return `${state}${response.message || ''}`
        },
        statusTicketCode() {
            const response = this.response_sunat_status_ticket

            return response ? response.sunat_code : null
        },
        statusTicketNotes() {
            const response = this.response_sunat_status_ticket

            return (response && Array.isArray(response.notes)) ? response.notes : []
        },
        /**
         * En proceso (03) o error de comunicacion: permite volver a consultar
         */
        showRetryStatusTicket() {
            const response = this.response_sunat_status_ticket
            if (!response) return false

            return !response.success || response.state_type_id === '03'
        },
        showDocumentActions() {
            // ########## INICIO CAMBIO SIN XML CDR SUNAT
            return true
            // ######### FIN CAMBIO SIN XML CDR SUNAT
        },
        showDownloadCdr() {
            const response = this.response_sunat_status_ticket
            if (!response) return true

            return response.has_cdr !== false
        },
    },
    methods: {
        ...mapActions(['loadConfiguration']),
        initForm() {
            this.loading_sunat_send = false;
            this.errors = {};
            this.form = {
                customer_email: null,
                download_external_pdf: null,
                external_id: null,
                number: null,
                id: null,
                response_message: null,
                response_type: null,
                customer_telephone: null,
                message_text: null,
                download_cdr: null,
                state_type_id: '05',
                has_cdr: true,
            }
            this.locked_emission = {
                success: true,
                message: null
            }
            this.company = {
                fiscal_environment: null,
            }
            this.response_sunat_send = {
                'success': false,
                message: null
            }
            this.response_sunat_status_ticket = null
        },
        clickDownload(format = 'a4') {
            if ((this.form && this.form.external_id)) {
                window.open(`/print/dispatch/${this.form.external_id}/${format}`, '_blank');
            }
        },
        clickSendWhatsapp() {
            if (!this.form.customer_telephone) {
                return this.$message.error('El número es obligatorio')
            }
            // ########### INICIO CAMBIO TELEFONÍA VENEZUELA
            const phone = String(this.form.customer_telephone).replace(/\D/g, '').replace(/^(58|51)/, '')
            window.open(`https://wa.me/58${phone}?text=${encodeURIComponent(this.form.message_text)}`, '_blank');
            // ########### FIN CAMBIO TELEFONÍA VENEZUELA
        },
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // La descarga CDR fue retirada del cierre de orden de entrega.
        // ######### FIN CAMBIO SIN XML CDR SUNAT
        timeout(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        async create() {
            this.initForm();
            this.loading_sunat_send = true;
            await this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                this.form = response.data.data;
                this.titleDialog = 'Orden de entrega: ' + this.form.number;
            });
            // ########## INICIO CAMBIO SIN XML CDR SUNAT
            // La orden de entrega ya está registrada localmente; no se envía ni consulta ticket.
            // ######### FIN CAMBIO SIN XML CDR SUNAT
            this.loading_sunat_send = false;
        },
        clickPrint(format) {
            window.open(`/${this.resource}/print/${this.form.external_id}/${format}`, '_blank');
        },
        clickSendEmail() {
            this.loading = true
            this.$http.post(`/${this.resource}/email`, {
                customer_email: this.form.customer_email,
                id: this.form.id
            })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success('El correo fue enviado satisfactoriamente')
                    } else {
                        this.$message.error('Error al enviar el correo')
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors
                    } else {
                        this.$message.error(error.response.data.message)
                    }
                })
                .then(() => {
                    this.loading = false
                })
        },
        clickFinalize() {
            location.href = `/${this.resource}`
        },
        clickNewDocument() {
            location.href = `/${this.resource}/create`
            // this.clickClose()
        },
        clickClose() {
            this.$emit('update:showDialog', false);
        }
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // El cierre local termina con PDF, correo y navegación; no consulta tickets.
        // ######### FIN CAMBIO SIN XML CDR SUNAT
    }
}
</script>
