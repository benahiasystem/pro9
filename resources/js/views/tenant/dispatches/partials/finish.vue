<template>
    <el-dialog :title="titleDialog"
               :visible="showDialog"
               append-to-body
               width="30%"
               :close-on-click-modal="false"
               :close-on-press-escape="false"
               :show-close="false"
               @open="create">
        <!-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## -->
        <fiscal-status v-if="recordId && showDialog" :key="recordId" :document-id="Number(recordId)" resource="dispatches" />
        <!-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## -->
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

        <template>
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
              v-if="!loading_record"
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
// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
import FiscalStatus from '../../documents/partials/fiscal-status.vue'
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
import {whatsappNumber} from "@helpers/phone";
import {mapState} from "vuex/dist/vuex.mjs";
import QrApi from '@viewsModuleQrApi/QrApiTemplate.vue'

export default {
    props: ['showDialog',
        'recordId',
        'showClose'
    ],
    components: {
        FiscalStatus,
        QrApi
    },
    name: 'DispatchFinish',
    data() {
        return {
            titleDialog: null,
            loading: false,
            loading_record: false,
            resource: 'dispatches',
            errors: {},
            form: {},
            text_button: null,
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
    },
    methods: {
        initForm() {
            this.loading_record = false;
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
                state_type_id: '05',
            }
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
            const phone = whatsappNumber(this.form.customer_telephone)
            if (!phone) return this.$message.error('Ingrese un teléfono venezolano válido.')
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(this.form.message_text)}`, '_blank');
            // ########### FIN CAMBIO TELEFONÍA VENEZUELA
        },
        async create() {
            this.initForm();
            this.loading_record = true;
            await this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                this.form = response.data.data;
                this.titleDialog = 'Orden de entrega: ' + this.form.number;
            });
            this.loading_record = false;
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
    }
}
</script>
