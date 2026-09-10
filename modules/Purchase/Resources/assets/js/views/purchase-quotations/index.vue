<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/purchase-quotations">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z"
                        />
                        <path d="M9 11v-5a3 3 0 0 1 6 0v5" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Solicitar cotización</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <a
                    :href="`/${resource}/create`"
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    ><i class="fa fa-plus-circle"></i> Nuevo</a
                >
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="data-table-visible-columns"></div>
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th class="text-start">Fecha Emisión</th>
                        <th>Estado</th>
                        <th>Documento</th>
                        <th class="text-center">Descarga</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td class="text-start">{{ row.date_of_issue }}</td>
                        <td>{{ row.state_type_description }}</td>
                        <td>
                            <span
                                class="customer-link"
                                role="button"
                                tabindex="0"
                                @click="clickDetail(row)"
                                @keyup.enter.prevent="clickDetail(row)"
                            >
                                {{ row.identifier }}
                            </span>
                        </td>
                        <td class="text-center" @click.stop>
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickDownload(row.external_id)"
                            >
                                PDF
                            </button>
                        </td>

                        <td class="text-end" @click.stop>
                            <el-dropdown trigger="click" @command="handleCommand($event, row)">
                                <el-button class="btn-dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                    <i class="fas fa-ellipsis-h" style="display: none;"></i>
                                </el-button>

                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="detail">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                                            Ver detalle
                                        </el-dropdown-item>

                                        <el-dropdown-item divided />

                                        <el-dropdown-item
                                            v-if="!row.has_purchase_orders"
                                            command="generate"
                                        >
                                            Generar OC
                                        </el-dropdown-item>

                                        <el-dropdown-item
                                            v-if="!row.has_purchase_orders"
                                            command="edit"
                                        >
                                            Editar
                                        </el-dropdown-item>

                                        <el-dropdown-item v-if="!row.has_purchase_orders" divided></el-dropdown-item>

                                        <el-dropdown-item command="options">
                                            Opciones
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>

            <purchase-quotation-options
                :showDialog.sync="showDialogOptions"
                :recordId="recordId"
                :showGenerate="true"
                :showClose="true"
            ></purchase-quotation-options>

            <purchase-quotation-detail-drawer
                :showDrawer.sync="showDetailDrawer"
                :recordId="detailRecordId"
                :initialRow.sync="detailInitialRow"
                :resource="resource"
                @edit="openEditFromDrawer"
                @generate="openGenerateFromDrawer"
                @options="openOptionsFromDrawer"
            ></purchase-quotation-detail-drawer>
        </div>
    </div>
</template>
<style scoped>
.anulate_color {
    color: red;
}

.purchase-quotation-document-link {
    color: inherit;
    cursor: pointer;
    font-weight: inherit;
    text-decoration: underline;
}

.purchase-quotation-document-link:hover,
.purchase-quotation-document-link:focus {
    color: inherit;
    font-weight: inherit;
    text-decoration: underline;
    outline: none;
}
</style>
<style>
@media only screen and (max-width: 485px) {
    .filter-container {
        margin-top: 0px;
        & .btn-filter-content,
        .btn-container-mobile {
            display: flex;
            align-items: center;
            justify-content: start;
        }
    }
}
</style>
<script>
import PurchaseQuotationOptions from "./partials/options.vue";
import PurchaseQuotationDetailDrawer from "./partials/detail-drawer.vue";
import DataTable from "@components/DataTable.vue";
// import {deletable} from '../../../mixins/deletable'

export default {
    // mixins: [deletable],
    components: { DataTable, PurchaseQuotationOptions, PurchaseQuotationDetailDrawer },
    data() {
        return {
            resource: "purchase-quotations",
            recordId: null,
            showDialogOptions: false,
            showDetailDrawer: false,
            detailRecordId: null,
            detailInitialRow: null
        };
    },
    created() {},
    methods: {
        clickCreate(id = "") {
            location.href = `/${this.resource}/create/${id}`;
        },
        clickGenerateOc(id = "") {
            location.href = `/purchase-orders/generate/${id}`;
        },
        clickDownload(external_id) {
            window.open(`/${this.resource}/download/${external_id}`, "_blank");
        },
        clickOptions(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptions = true;
        },
        clickDetail(row) {
            this.detailRecordId = row.id;
            this.detailInitialRow = { ...row };
            this.showDetailDrawer = true;
        },
        openEditFromDrawer(recordId) {
            this.showDetailDrawer = false;
            this.clickCreate(recordId);
        },
        openGenerateFromDrawer(recordId) {
            this.showDetailDrawer = false;
            this.clickGenerateOc(recordId);
        },
        openOptionsFromDrawer(recordId) {
            this.showDetailDrawer = false;
            this.clickOptions(recordId);
        },
        handleCommand(command, row) {
            switch (command) {
                case 'detail':
                    this.clickDetail(row);
                    break;
                case 'generate':
                    this.clickGenerateOc(row.id);
                    break;
                case 'edit':
                    if (row && row.id) window.location.href = `/${this.resource}/create/${row.id}`;
                    break;
                case 'options':
                    this.clickOptions(row.id);
                    break;
            }
        }
    }
};
</script>
