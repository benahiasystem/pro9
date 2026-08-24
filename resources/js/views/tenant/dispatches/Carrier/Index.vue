<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/dispatch_carrier">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-truck"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path
                            d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"
                        />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Guias de remisión del transportista</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <a
                    :href="`/${resource}/create`"
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    ><i class="fa fa-plus-circle"></i> Nuevo</a
                >
                <!--                <a href="#" @click.prevent="showModalGenerateCPE = true" class="btn btn-custom btn-sm  mt-2 me-2">Generar-->
                <!--                    comprobante desde múltiples guías</a>-->
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th class="text-start">Fecha Emisión</th>
                        <th>Remitente</th>
                        <th>Destinatario</th>
                        <th>Número</th>
                        <th>Estado</th>
                        <th class="text-center">Fecha Envío</th>
                        <!--                        <th class="text-center">N° Comprobante</th>-->
                        <th class="text-center">Descargas</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                    <tr
                        slot-scope="{ index, row }"
                        :class="{ 'text-danger': row.state_type_id === '11' }"
                    >
                        <!-- <td>{{ index }}</td> -->
                        <td class="text-start">
                            {{ formatDate(row.date_of_issue) }}
                        </td>
                        <td>
                            {{ row.sender_name }} <br />
                            <small>{{ row.sender_number }}</small>
                        </td>
                        <td>
                            {{ row.receiver_name }} <br />
                            <small>{{ row.receiver_number }}</small>
                        </td>
                        <td>{{ row.number }}</td>
                        <td>
                            <span
                                class="badge bg-secondary text-white"
                                :class="{
                                    'bg-secondary': row.state_type_id === '01',
                                    'bg-info': row.state_type_id === '03',
                                    'bg-success': row.state_type_id === '05',
                                    'bg-secondary': row.state_type_id === '07',
                                    'bg-dark': row.state_type_id === '09'
                                }"
                                >{{ row.state_type_description }}</span
                            >
                        </td>
                        <td class="text-center">
                            {{ formatDate(row.date_of_shipping) }}
                        </td>

                        <!--                        <td class="text-center">-->
                        <!--                            <template v-for="(row,index) in row.documents">-->
                        <!--                                <label class="d-block" :key="index">{{ row.description }}</label>-->
                        <!--                            </template>-->
                        <!--                        </td>-->

                        <td class="text-center">
                            <!-- ########## INICIO CAMBIO SIN XML CDR SUNAT -->
                            <!-- La guía local ofrece únicamente PDF. -->
                            <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info me-1"
                                @click.prevent="
                                    clickDownload(row.download_external_pdf)
                                "
                                v-if="row.btn_pdf"
                            >
                                PDF
                            </button>
                        </td>
                        <td class="text-center">
                            <!--                            <button type="button" class="btn waves-effect waves-light btn-xs btn-info"-->
                            <!--                                    @click.prevent="onGenerateDocument(row.id)" v-if="row.btn_generate_document">Generar comprobante-->
                            <!--                            </button>-->
                            <!-- ########## INICIO CAMBIO SIN XML CDR SUNAT -->
                            <!-- Envío y consulta de ticket fiscal no se ofrecen. -->
                            <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info me-1"
                                @click.prevent="clickOptions(row.id)"
                                v-if="row.btn_options"
                            >
                                Opciones
                            </button>
                            <!--                            <a :href="`/dispatches/create_new/dispatch/${row.id}`"-->
                            <!--                               class="btn waves-effect waves-light btn-xs btn-warning m-1__2" v-if="row.btn_edit">Editar</a>-->
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>
        <dispatch-options
            :showDialog.sync="showDialogOptions"
            :recordId="recordId"
            :showClose="true"
        ></dispatch-options>

        <FormGenerateDocument
            :showDialog.sync="showDialogGenerateDocument"
            :recordId="recordId"
            :showClose="true"
            :showGenerate="true"
            :configuration="configuration"
        ></FormGenerateDocument>
        <ModalGenerateCPE :show.sync="showModalGenerateCPE"></ModalGenerateCPE>
    </div>
</template>
<style>
@media only screen and (max-width: 390px) {
    .filter-content {
        margin-top: 0px;
        display: flex;
        align-items: start;
        justify-content: start;
    }
}
</style>
<script>
import DataTable from "../../../../components/DataTableDispatch.vue";
import DispatchOptions from "../partials/options.vue";
import FormGenerateDocument from "../generate-document.vue";
import ModalGenerateCPE from "../ModalGenerateCPE.vue";

export default {
    name: "DispatchCarrierIndex",
    components: {
        DataTable,
        DispatchOptions,
        FormGenerateDocument,
        ModalGenerateCPE
    },
    props: ["configuration"],
    data() {
        return {
            resource: "dispatch_carrier",
            showDialogOptions: false,
            recordId: null,
            showDialogGenerateDocument: false,
            showModalGenerateCPE: false
        };
    },
    created() {
        this.$setStorage("configuration", this.configuration);
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY")
                : null;
        },
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // El listado local no conserva acciones de envío ni consulta fiscal.
        // ######### FIN CAMBIO SIN XML CDR SUNAT
        onGenerateDocument(dispatchId) {
            this.recordId = dispatchId;
            this.showDialogGenerateDocument = true;
        },
        clickOptions(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptions = true;
        },
        clickDownload(download) {
            window.open(download, "_blank");
        },
        clickPrint(external_id) {
            window.open(`/print/dispatch/${external_id}/a4`, "_blank");
        }
    }
};
</script>
