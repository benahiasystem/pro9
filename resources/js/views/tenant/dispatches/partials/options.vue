<template>
    <el-dialog :close-on-click-modal="false"
               :close-on-press-escape="false"
               :show-close="false"
               :title="titleDialog"
               :visible="showDialog"
               append-to-body
               width="30%"
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
        <!-- Las opciones locales no muestran firma, envío ni respuesta CDR. -->
        <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->

        <div class="row">
            <div
                v-if="form && form.external_id"
                class="col-lg-4 col-md-4 col-sm-6 text-center font-weight-bold mt-3"
            >
                <button
                    class="btn btn-lg btn-info waves-effect waves-light"
                    type="button"
                    @click="clickDownload('a4')"
                >
                    <i class="fa fa-file-alt"></i>
                </button>
                <p>A4</p>
            </div>
            <div
                v-if="form && form.external_id"
                class="col-lg-4 col-md-4 col-sm-6 text-center font-weight-bold mt-3"
            >
                <button
                    class="btn btn-lg btn-info waves-effect waves-light"
                    type="button"
                    @click="clickDownload('ticket')"
                >
                    <i class="fa fa-file-alt"></i>
                </button>
                <p>80MM</p>
            </div>
            <div
                v-if="form && form.external_id"
                class="col-lg-4 col-md-4 col-sm-6 text-center font-weight-bold mt-3"
            >
                <button
                    class="btn btn-lg btn-info waves-effect waves-light"
                    type="button"
                    @click="clickDownload('ticket_58')"
                >
                    <i class="fa fa-file-alt"></i>
                </button>
                <p>58MM</p>
            </div>
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
            <div class="col-md-12">
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
        </div>
        <span slot="footer"
              class="dialog-footer">
            <template v-if="showClose">
                <el-button @click="clickClose">Cerrar</el-button>
            </template>
            <template v-else>
                <el-button class="list"
                           @click="clickFinalize">Ir al listado</el-button>
                <el-button v-if="!isUpdate"
                           type="primary"
                           @click="clickNewDocument">{{ text_button }}</el-button>
            </template>
        </span>
    </el-dialog>
</template>

<script>
import {whatsappNumber} from "@helpers/phone";
export default {
    props: ['showDialog', 'recordId', 'showClose', 'isUpdate'],
    data() {
        return {
            titleDialog: null,
            loading: false,
            resource: 'dispatches',
            errors: {},
            form: {},
            company: {},
            locked_emission: {},
            text_button: null,
        }
    },
    async created() {
        this.initForm()

        this.text_button = 'Nueva orden de entrega'
    },
    methods: {

        clickDownload(format = 'a4') {
            if( (this.form && this.form.external_id)) {
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
        initForm() {
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

            this.locked_emission = {
                success: true,
                message: null
            }
            this.company = {
                fiscal_environment: null,
            }
        },
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // La descarga CDR fue retirada de las opciones de orden de entrega.
        // ######### FIN CAMBIO SIN XML CDR SUNAT
        async create() {
            await this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                this.form = response.data.data;
                this.titleDialog = 'Orden de entrega: ' + this.form.number;
            });

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
            this.clickClose()
        },
        clickClose() {
            this.$emit('update:showDialog', false)
            this.initForm()
        },
    }
}
</script>
