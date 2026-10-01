<template>
    <el-dialog
        :visible="showDialog"
        @open="create"
        @opened="opened"
        width="75%"
        top="4vh"
        custom-class="pos-success"
        :close-on-click-modal="false"
        :close-on-press-escape="false"
        :show-close="false"
    >
        <Keypress key-event="keyup" :key-code="13" @success="someMethod" />

        <span slot="title">
            <div class="pos-success__header">
                <div class="pos-success__icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="pos-success__heading">
                    <span class="pos-success__eyebrow">Venta registrada</span>
                    <h4 class="pos-success__title">{{ form.number }}</h4>
                </div>
                <div class="pos-success__badges">
                    <span
                        class="pos-chip is-ok"
                    >
                        <!-- ########## INICIO CAMBIO SIN XML CDR SUNAT -->
                        <i class="fas fa-check-circle"></i>
                        Registro local completado
                        <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->
                    </span>
                </div>
            </div>
        </span>

        <div class="pos-success__body">

            <!-- Vista previa del comprobante -->
            <div class="pos-success__preview">
                <el-tabs v-model="activeName">
                    <el-tab-pane
                        label="Ticket 80mm"
                        name="first"
                        v-if="config !== null && config.show_ticket_80"
                    >
                        <embed
                            v-if="config !== null && config.show_ticket_80"
                            id="nemo"
                            class="pos-ticket-embed"
                            :src="form.print_ticket"
                            type="application/pdf"
                            width="100%"
                        />
                    </el-tab-pane>
                    <el-tab-pane
                        label="Ticket 58mm"
                        name="second"
                        v-if="config.show_ticket_58"
                    >
                        <embed
                            v-if="config.show_ticket_58"
                            class="pos-ticket-embed"
                            :src="form.print_ticket_58"
                            type="application/pdf"
                            width="100%"
                        />
                    </el-tab-pane>
                    <el-tab-pane label="A4" name="quarter" v-if="!isNrus">
                        <embed
                            class="pos-ticket-embed"
                            :src="form.print_a4"
                            type="application/pdf"
                            width="100%"
                        />
                    </el-tab-pane>
                    <el-tab-pane label="A5" name="fifth" v-if="!isNrus">
                        <embed
                            class="pos-ticket-embed"
                            :src="form.print_a5"
                            type="application/pdf"
                            width="100%"
                        />
                    </el-tab-pane>
                </el-tabs>
            </div>

            <!-- Acciones -->
            <aside class="pos-success__side">

                <section class="pos-success__block">
                    <h5 class="pos-success__block-title">Enviar comprobante</h5>

                    <label class="pos-success__label">Correo electrónico</label>
                    <el-input
                        v-model="form.customer_email"
                        ref="ref_customer_email"
                        placeholder="correo@ejemplo.com"
                        @keyup.native="keyupCustomerEmail"
                    >
                        <el-button
                            slot="append"
                            icon="el-icon-message"
                            @click="clickSendEmail"
                            :loading="loading"
                            >Enviar</el-button
                        >
                    </el-input>

                    <label class="pos-success__label">WhatsApp</label>
                    <div v-if="!config.qr_api_enable_ws">
                        <el-input v-model="form.customer_telephone"
                                  placeholder="999 999 999">
                            <!-- ########### INICIO CAMBIO TELEFONÍA VENEZUELA -->
                            <template slot="prepend">+58</template>
                            <!-- ########### FIN CAMBIO TELEFONÍA VENEZUELA -->
                            <el-button slot="append" @click="clickSendWhatsapp"
                                >Enviar
                                <el-tooltip
                                    class="item"
                                    effect="dark"
                                    content="Es necesario tener aperturado Whatsapp web"
                                    placement="top-start"
                                >
                                    <i class="fab fa-whatsapp"></i>
                                </el-tooltip>
                            </el-button>
                        </el-input>
                    </div>
                    <template v-else>
                        <QrApi
                            colClass="pos-success__qr"
                            :wsPhone="form.customer_telephone"
                            :wsFile="form.print_ticket"
                            :wsFileA4="form.print_a4"
                            :wsDocument="form.number"
                            :wsMessage="form.message_text"
                            :wsData="form.pdf_a4_data"
                        />
                    </template>
                </section>

                <section class="pos-success__block">
                    <h5 class="pos-success__block-title">Imprimir o descargar</h5>
                    <div class="pos-success__formats">
                        <button
                            v-if="config !== null && config.show_ticket_80"
                            type="button"
                            class="pos-success__format"
                            @click="clickPrint(form.print_ticket)"
                        >
                            <i class="fa fa-receipt"></i> Ticket 80
                        </button>
                        <button
                            v-if="config.show_ticket_58"
                            type="button"
                            class="pos-success__format"
                            @click="clickPrint(form.print_ticket_58)"
                        >
                            <i class="fa fa-receipt"></i> Ticket 58
                        </button>
                        <button
                            v-if="!isNrus"
                            type="button"
                            class="pos-success__format"
                            @click="clickPrint(form.print_a4)"
                        >
                            <i class="fa fa-file-alt"></i> A4
                        </button>
                        <button
                            v-if="!isNrus"
                            type="button"
                            class="pos-success__format"
                            @click="clickPrint(form.print_a5)"
                        >
                            <i class="fa fa-file-alt"></i> A5
                        </button>
                    </div>
                </section>

                <div class="pos-success__actions">
                    <button
                        type="button"
                        class="pos-success__new"
                        @click="clickNewSale"
                    >
                        <i class="fas fa-plus"></i> Nueva venta
                    </button>

                    <button
                        v-if="showButtonConvertCpePos && isFromPos"
                        type="button"
                        class="pos-success__convert"
                        @click="clickConvertCpe"
                    >
                        Convertir a CPE
                    </button>
                </div>
            </aside>
        </div>

        <sale-note-generate
            :show.sync="showDialogGenerate"
            :recordId="recordId"
            :showGenerate="true"
            :showClose="false"
            @hasGeneratedDocument="hasGeneratedDocument"
        ></sale-note-generate>
    </el-dialog>
</template>
<style>
/* =========================================================================
   Modal de venta exitosa
   ========================================================================= */

/* Misma cadena de resolución que pos.css: variables legacy de los temas
   claros -> familia --black-* del skin black -> derivado del color de marca
   (skin modern). Ningún tema define --border-color: no se consulta. */
.pos-success {
    --ps-primary: var(--primary-color, #3d6bf5);
    --ps-text: var(--dark-color, var(--black-dark, #3a4658));
    --ps-muted: var(--muted, var(--black-accent, color-mix(in srgb, var(--ps-text) 62%, #fff)));
    --ps-surface: #fff;
    --ps-surface-2: var(--light-color, var(--black-highlight, color-mix(in srgb, var(--ps-primary) 5%, #fff)));
    --ps-border: var(--accent-color, color-mix(in srgb, var(--black-accent, var(--ps-primary)) 16%, #fff));
    --ps-border-strong: color-mix(in srgb, var(--black-accent, var(--ps-primary)) 28%, #fff);
    --ps-success: var(--success, #00c666);
    --ps-warning: var(--warning, #ff8400);
    --ps-radius: var(--border-radius-sm, 8px);
    --ps-radius-lg: var(--border-radius-lg, 14px);

    max-width: 1120px;
    border-radius: var(--ps-radius-lg);
    overflow: hidden;
}

html.dark .pos-success {
    --ps-text: #d1d2d6;
    --ps-muted: #8b93ab;
    --ps-surface: var(--contents-dark, var(--black-content-dark, var(--md-content-dark, #283046)));
    --ps-surface-2: var(--background-dark, var(--black-bg-dark, var(--md-bg-dark, #212c56)));
    --ps-border: var(--borders-dark, var(--black-border-dark, var(--md-border-dark, #314267)));
    --ps-border-strong: color-mix(in srgb, var(--borders-dark, var(--black-border-dark, var(--md-border-dark, #3a4870))) 78%, #fff);
}

.pos-success .el-dialog__header {
    padding: 0;
    border-bottom: 1px solid var(--ps-border);
}

.pos-success .el-dialog__body {
    padding: 0;
}

/* ---- Cabecera ---- */

.pos-success__header {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding: 16px 22px;
}

.pos-success__icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: var(--ps-success);
    color: #fff;
    font-size: 16px;
}

.pos-success__heading {
    flex: 1 1 auto;
    min-width: 0;
}

.pos-success__eyebrow {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--ps-muted);
}

.pos-success__title {
    margin: 1px 0 0;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--ps-text);
}

.pos-success__badges {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.pos-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.pos-chip.is-ok {
    background: color-mix(in srgb, var(--ps-success) 12%, transparent);
    color: var(--ps-success);
}

.pos-chip.is-warn {
    background: color-mix(in srgb, var(--ps-warning) 14%, transparent);
    color: var(--ps-warning);
}

.pos-chip.is-muted {
    background: var(--ps-surface-2);
    color: var(--ps-muted);
}

/* ---- Cuerpo ---- */

.pos-success__body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
}

.pos-success__preview {
    min-width: 0;
    padding: 10px 18px 16px;
    background: var(--ps-surface-2);
}

.pos-success__preview .el-tabs__header {
    margin-bottom: 10px;
}

.pos-success__preview .el-tabs__nav-wrap::after {
    display: none;
}

.pos-success__preview .pos-ticket-embed {
    display: block;
    border: 1px solid var(--ps-border);
    border-radius: var(--ps-radius);
    background: #fff;
    height: calc(92vh - 330px);
    min-height: 420px;
}

/* ---- Panel de acciones ---- */

.pos-success__side {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 16px 20px 18px;
    border-left: 1px solid var(--ps-border);
    background: var(--ps-surface);
}

.pos-success__block-title {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--ps-text);
}

.pos-success__label {
    display: block;
    margin: 0 0 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--ps-muted);
}

.pos-success__block .el-input + .pos-success__label,
.pos-success__block > div + .pos-success__label,
.pos-success__block .pos-success__qr + .pos-success__label {
    margin-top: 12px;
}

.pos-success__qr {
    width: 100%;
}

.pos-success__formats {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.pos-success__format {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border: 1px solid var(--ps-border-strong);
    border-radius: 999px;
    background: var(--ps-surface);
    color: var(--ps-text);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}

.pos-success__format:hover {
    background: var(--ps-primary);
    border-color: var(--ps-primary);
    color: #fff;
}

.pos-success__actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: auto;
    padding-top: 4px;
}

.pos-success__new {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 13px 16px;
    border: 0;
    border-radius: var(--ps-radius-lg);
    background: var(--ps-primary);
    color: #fff;
    font-size: 14.5px;
    font-weight: 700;
    letter-spacing: .03em;
    cursor: pointer;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--ps-primary) 28%, transparent);
    transition: filter .15s ease;
}

.pos-success__new:hover {
    filter: brightness(1.06);
}

.pos-success__convert {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid var(--ps-success);
    border-radius: var(--ps-radius);
    background: transparent;
    color: var(--ps-success);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}

.pos-success__convert:hover {
    background: var(--ps-success);
    color: #fff;
}

/* ---- Responsive ---- */

@media (max-width: 991.98px) {
    .pos-success {
        width: 95% !important;
    }

    .pos-success__body {
        grid-template-columns: minmax(0, 1fr);
    }

    .pos-success__side {
        border-left: 0;
        border-top: 1px solid var(--ps-border);
    }

    .pos-success__header {
        gap: 10px;
    }

    .pos-success__badges {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
        width: 100%;
    }
}
</style>
<script>
import {whatsappNumber} from "@helpers/phone";
import { mapState, mapActions } from "vuex/dist/vuex.mjs";
import QrApi from "@viewsModuleQrApi/QrApiTemplate.vue";
import Keypress from "vue-keypress";
import SaleNoteGenerate from "@views/sale_notes/partials/option_documents.vue";
import { buhoprinter } from "@mixins/buhoprinter";

export default {
    props: ["showDialog", "recordId", "statusDocument", "resource", "fromPos", "isPrint"],
    components: {
        Keypress,
        SaleNoteGenerate,
        QrApi
    },
    mixins: [buhoprinter],
    data() {
        return {
            titleDialog: null,
            loading: false,
            errors: {},
            form: {},
            company: {},
            configuration: {},
            activeName: "first",
            showDialogGenerate: false,
            button_convert_cpe_pos: true
        };
    },
    created() {
        this.initForm();
        this.loadConfiguration();
        /*
            this.$http.get(`/pos/status_configuration`).then(response => {
                this.$store.commit('setConfiguration', response.data)
            });
            */
    },
    mounted() {},
    computed: {
        ...mapState(["config"]),
        isNrus() {
            return !!(this.config && this.config.is_nrus);
        },
        applyConvertCpePos() {
            if (this.configuration && this.configuration.show_convert_cpe_pos)
                return this.configuration.show_convert_cpe_pos;

            return false;
        },
        showButtonConvertCpePos() {
            return (
                this.applyConvertCpePos &&
                this.resource === "sale-notes" &&
                this.button_convert_cpe_pos
            );
        },
        isFromPos() {
            return this.fromPos != undefined && this.fromPos;
        }
    },
    methods: {
        hasGeneratedDocument() {
            this.button_convert_cpe_pos = false;
        },
        clickConvertCpe() {
            this.showDialogGenerate = true;
        },
        ...mapActions(["loadConfiguration"]),
        clickSendWhatsapp() {
            if (!this.form.customer_telephone) {
                return this.$message.error("El número es obligatorio");
            }

            const phone = whatsappNumber(this.form.customer_telephone);
            if (!phone) return this.$message.error('Ingrese un teléfono venezolano válido.');
            window.open(
                // ########### INICIO CAMBIO TELEFONÍA VENEZUELA
                `https://wa.me/${phone}?text=${
                    encodeURIComponent(this.form.message_text)
                }`,
                // ########### FIN CAMBIO TELEFONÍA VENEZUELA
                "_blank"
            );
        },
        someMethod(response) {
            if (!this.showDialog) {
                return;
            }

            const external_id = this.form.external_id;

            let format = "a4";

            switch (this.activeName) {
                case "first":
                    format = "ticket";
                    break;
                case "second":
                    format = "ticket_58";
                    break;
                case "quarter":
                    format = "a4";
                    break;
                case "fifth":
                    format = "a5";
                    break;
            }

            if (this.resource == "sale-notes") {
                window.open(
                    `/sale-notes/downloadExternal/${external_id}/${format}`,
                    "_blank"
                );
            } else if (this.resource == "documents") {
                if (format == "ticket") {
                    window.open(
                        `/downloads/Document/${type}/${external_id}/pdf`,
                        "_blank"
                    );
                } else {
                    window.open(
                        `downloads/documents/${type}/${external_id}/${format}`,
                        "_blank"
                    );
                }
            }
        },
        keyupCustomerEmail(e) {
            if (e.keyCode === 9) {
                this.clickNewSale();
            }
            // console.log(e.keyCode)
        },
        initFocus() {
            this.$refs.ref_customer_email.$el
                .getElementsByTagName("input")[0]
                .focus();
        },
        async clickNewSale() {
            await this.initForm();
            await this.$eventHub.$emit("cancelSale");
            await this.$eventHub.$emit("cancelSaleGarage");
            this.$emit("update:showDialog", false);
        },
        initForm() {
            this.errors = {};
            this.configuration = {};
            this.form = {
                customer_email: null,
                download_pdf: null,
                print_a4: null,
                print_a5: null,
                print_ticket: null,
                print_ticket_58: null,
                external_id: null,
                number: null,
                customer_telephone: null,
                message_text: null,
                id: null
            };

            this.changeActiveName();

            this.button_convert_cpe_pos = true;
        },
        create() {
            this.$http
                .get(`/${this.resource}/record/${this.recordId}`)
                .then(response => {
                    this.form = response.data.data;
                    this.titleDialog = "Comprobante: " + this.form.number;

                    // Si el componente recibió la señal de imprimir automáticamente,
                    // intentar imprimir el PDF asociado (print_ticket)
                    this.$nextTick(() => {
                        this.autoPrint();
                    });
                });

            this.$http.get(`/pos/status_configuration`).then(response => {
                this.configuration = response.data;
            });
        },
        opened() {
            this.initFocus();
        },
        clickSendEmail() {
            if (
                this.form.customer_email == null ||
                this.form.customer_email == ""
            )
                return this.$message.error("Ingrese el correo");
            this.loading = true;
            this.$http
                .post(`/${this.resource}/email`, {
                    customer_email: this.form.customer_email,
                    id: this.form.id
                })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(
                            "El correo fue enviado satisfactoriamente"
                        );
                    } else {
                        this.$message.error("Error al enviar el correo");
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    } else {
                        this.$message.error(error.response.data.message);
                    }
                })
                .then(() => {
                    this.loading = false;
                });
        },
        clickPrint(url) {
            window.open(`${url}`, "_blank");
        },
        changeActiveName() {
            this.loadConfiguration();
            if (this.config !== null && this.config.show_ticket_80) {
                this.activeName = "first";
            } else if (this.config !== null && this.config.show_ticket_58) {
                this.activeName = "second";
            } else {
                this.activeName = "quarter";
            }
        },
        async autoPrint() {
            if (!this.isPrint) return;
            if (!this.form || !this.form.print_ticket) return;

            try {
                // Centraliza la impresión vía backend → Redis → BuhoPrinter agent
                await this.printDocument(this.form.print_ticket, this.configuration?.printer_name_documents);
            } catch (e) {
                console.error('options autoPrint error', e);
            }
        },
        // clickConsultCdr(document_id) {
        //         .then(response => {
        //             if (response.data.success) {
        //                 this.$message.success(response.data.message)
        //                 this.$eventHub.$emit('reloadData')
        //             } else {
        //                 this.$message.error(response.data.message)
        //             }
        //         })
        //         .catch(error => {
        //             this.$message.error(error.response.data.message)
        //         })
        // },
        // clickFinalize() {
        //     location.href = (this.isContingency) ? `/contingencies` : `/${this.resource}`
        // },
        // clickNewDocument() {
        //     this.clickClose()
        // },
        // clickClose() {
        //     this.$emit('update:showDialog', false)
        //     this.initForm()
        // },
    }
};
</script>
