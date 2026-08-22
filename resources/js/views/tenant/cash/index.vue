<template>
    <div class="cash">
        <div class="page-header pe-0">
            <h2>
                <a href="/cash">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-calculator"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M4 3m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"
                        />
                        <path
                            d="M8 7m0 1a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1z"
                        />
                        <path d="M8 14l0 .01" />
                        <path d="M12 14l0 .01" />
                        <path d="M16 14l0 .01" />
                        <path d="M8 17l0 .01" />
                        <path d="M12 17l0 .01" />
                        <path d="M16 17l0 .01" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Cajas chicas</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <template v-if="open_cash">
                    <button
                        type="button"
                        class="btn btn-custom btn-sm  mt-2 me-2"
                        @click.prevent="clickDownloadGeneral()"
                    >
                        <i class="fas fa-shopping-cart"></i> Reporte general
                    </button>

                    <button
                        type="button"
                        class="btn btn-custom btn-sm  mt-2 me-2"
                        @click.prevent="clickCreate()"
                    >
                        <i class="fas fa-shopping-cart"></i> Aperturar caja chica
                    </button>
                </template>
                <!-- <template v-else>                 -->
                <!-- <button type="button" class="btn btn-success btn-sm  mt-2 me-2" @click.prevent="clickOpenPos()"><i class="fas fa-shopping-cart" ></i> Aperturar punto de venta</button> -->
                <!-- </template> -->
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de cajas</h3>
            </div> -->
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th>Referencia</th>
                        <th>Vendedor</th>
                        <th class="text-start">Apertura</th>
                        <th class="text-start">Cierre</th>
                        <th class="text-end">Saldo inicial</th>
                        <th class="text-end">Saldo final</th>
                        <!-- <th>Ingreso</th> -->
                        <!-- <th>Egreso</th> -->
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>

                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.reference_number }}</td>
                        <td>
                            {{ row.user }}
                            <br>
                            <small class="text-muted">{{ row.user_email }}</small>
                        </td>
                        <td class="text-start">{{ formatDate(row.opening) }}</td>
                        <td class="text-start">{{ formatDate(row.closed) }}</td>
                        <td class="text-end">{{ row.beginning_balance }}</td>
                        <td class="text-end">{{ row.final_balance }}</td>
                        <!-- <td>{{ row.income }}</td>
                        <td>{{ row.expense }}</td> -->
                        <td>{{ row.state_description }}</td>
                        <td class="text-end">
                            <button
                                v-if="availableReportForSeller"
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-primary me-1"
                                @click.prevent="clickReports(row)"
                            >
                                Reportes
                            </button>

                            <template v-if="row.state">
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-warning me-1"
                                    @click.prevent="clickCloseCash(row.id)"
                                >
                                    Cerrar caja
                                </button>
                                <button
                                    v-if="typeUser === 'admin'"
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-info me-1"
                                    @click.prevent="clickCreate(row.id)"
                                >
                                    Editar
                                </button>
                                <button
                                    v-if="typeUser === 'admin'"
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-danger me-1"
                                    @click.prevent="clickDelete(row.id)"
                                >
                                    Eliminar
                                </button>
                            </template>

                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info me-1"
                                @click.prevent="clickOptions(row.id)"
                            >
                                C. Electrónico
                            </button>
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>
        <cash-form
            :showDialog.sync="showDialog"
            :typeUser="typeUser"
            :recordId="recordId"
        ></cash-form>

        <cash-options
            :showDialog.sync="showDialogOptions"
            :recordId="recordId"
        ></cash-options>

        <cash-reports
            :showDialog.sync="showDialogReports"
            :recordId="recordId"
            :cashLabel="cashLabel"
        ></cash-reports>
    </div>
</template>
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
import DataTable from "../../../components/DataTable.vue";
import { deletable } from "../../../mixins/deletable";
import CashForm from "./form.vue";
import CashOptions from "./partials/options.vue";
import CashReports from "./partials/reports.vue";

export default {
    mixins: [deletable],
    components: { DataTable, CashForm, CashOptions, CashReports },
    props: ["typeUser", "configuration"],
    data() {
        return {
            showDialog: false,
            showDialogOptions: false,
            showDialogReports: false,
            cashLabel: null,
            open_cash: true,
            resource: "cash",
            recordId: null,
            cash: null
        };
    },
    async created() {
        /*await this.$http.get(`/${this.resource}/opening_cash`)
                .then(response => {
                    this.cash = response.data.cash
                    this.open_cash = (this.cash) ? false : true
                })*/
        /*this.$eventHub.$on('openCash', () => {
                this.open_cash = false
            })*/
        
        // Verificar si se redirigió por falta de caja
        this.checkRedirectReason();
    },
    computed:{
        availableReportForSeller() {
            return this.typeUser === 'admin' ? true : (this.typeUser === 'seller' && this.configuration.available_cash_report_seller ? true : false);
        }
    },
    methods: {
        checkRedirectReason() {
            const urlParams = new URLSearchParams(window.location.search);
            const redirectReason = urlParams.get('redirect_reason');
            
            if (redirectReason === 'no_cash_sale_note') {
                this.$message({
                    message: 'Debe aperturar una caja antes de crear una nota de venta.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_document') {
                this.$message({
                    message: 'Debe aperturar una caja antes de crear un nuevo comprobante.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_pos') {
                this.$message({
                    message: 'Debe aperturar una caja antes de acceder al punto de venta.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_fast_sale') {
                this.$message({
                    message: 'Debe aperturar una caja antes de acceder a la venta rápida.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_garage') {
                this.$message({
                    message: 'Debe aperturar una caja antes de acceder a la venta rápida.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            }
        },
        clearUrlParameters() {
            const url = new URL(window.location);
            url.searchParams.delete('redirect_reason');
            window.history.replaceState({}, document.title, url.pathname);
        },
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY h:mmA")
                : null;
        },
        clickReports(row) {
            this.recordId = row.id;
            this.cashLabel = `${row.user} · Apertura ${this.formatDate(row.opening)}`;
            this.showDialogReports = true;
        },
        clickOptions(recordId) {
            this.showDialogOptions = true;
            this.recordId = recordId;
        },
        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickCloseCash(recordId) {
            this.recordId = recordId;
            const h = this.$createElement;
            this.$msgbox({
                title: "Cerrar caja chica POS",
                type: "warning",
                message: h("p", null, [
                    h(
                        "p",
                        { style: "text-align: justify; font-size:15px" },
                        "¿Está seguro de cerrar la caja?"
                    )
                ]),

                showCancelButton: true,
                confirmButtonText: "Cerrar",
                cancelButtonText: "Cancelar",
                beforeClose: (action, instance, done) => {
                    if (action === "confirm") {
                        this.createRegister(instance, done);
                    } else {
                        done();
                    }
                }
            })
                .then(action => {})
                .catch(action => {});
        },
        createRegister(instance, done) {
            instance.confirmButtonLoading = true;
            instance.confirmButtonText = "Cerrando caja...";

            this.$http
                .get(`/${this.resource}/close/${this.recordId}`)
                .then(response => {
                    if (response.data.success) {
                        this.$eventHub.$emit("reloadData");
                        this.open_cash = true;
                        this.$message.success(response.data.message);
                    } else {
                        console.log(response);
                        this.$message.success(response.data.message);
                    }
                })
                .catch(error => {
                    console.log(error);
                })
                .then(() => {
                    instance.confirmButtonLoading = false;
                    instance.confirmButtonText = "Iniciar prueba";
                    done();
                });
        },
        clickOpenPos() {
            window.open("/pos");
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickDownloadGeneral() {
            window.open(`/${this.resource}/report`, "_blank");
        },
    }
};
</script>
