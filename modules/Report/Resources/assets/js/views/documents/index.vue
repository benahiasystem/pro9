<template>
    <!-- ######## INICIO CAMBIO GEOPOLITICO VENEZUELA -->
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/list-reports">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-file-analytics"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path
                            d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"
                        />
                        <path d="M9 17l0 -5" />
                        <path d="M12 17l0 -1" />
                        <path d="M15 17l0 -3" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span> Consulta de Documentos </span></li>
            </ol>
        </div>
        <div class="card mb-0 pt-md-0 tab-content-default row-new">
            <div class="">
                <!-- <h3 class="my-0">Consulta de Documentos</h3> -->
                <div class="data-table-visible-columns">
                    <el-dropdown :hide-on-click="false">
                        <el-button type="secondary">
                            Mostrar columnas<i
                                class="el-icon-arrow-down el-icon--right"
                            ></i>
                        </el-button>
                        <el-dropdown-menu slot="dropdown">
                          <el-dropdown-item
                            v-for="(column, key) in filteredColumns"
                            :key="key"
                          >
                            <el-checkbox
                              v-model="columns[key].visible"
                              @change="getColumnsToShow(1)"
                            >
                              {{ column.title }}
                            </el-checkbox>
                          </el-dropdown-item>
                        </el-dropdown-menu>
                    </el-dropdown>
                </div>
            </div>
            <div class="card mb-0">
                <div class="card-body">
                    <data-table
                        :applyCustomer="true"
                        :resource="resource"
                        :visibleColumns="columns"
                        :salesColumnKeys="salesVisibleColumnKeys"
                    >
                        <tr slot="heading">
                            <th class="">#</th>
                            <th v-if="columns.user_seller.visible" class="">
                                Usuario/Vendedor
                            </th>
                            <th class="">Tipo Documento</th>
                            <th class="">Serie</th>
                            <th class="">Numero</th>
                            <!-- <th class="">Comprobante</th> -->
                            <th class="">Fecha emisión</th>
                            <th class="">Fecha vencimiento</th>
                            <th
                                v-if="columns.guides.visible"
                                class="text-end"
                            >
                                Orden de entrega
                            </th>
                            <th
                                v-if="columns.options.visible"
                                class="text-end"
                            >
                                Opciones
                            </th>
                            <th v-if="columns.doc_affect.visible">
                                Doc. Afectado
                            </th>
                            <th v-if="columns.quote.visible">Cotización</th>
                            <th v-if="columns.case.visible">Caso</th>
                            <th
                                v-if="columns.district.visible"
                                class="text-end"
                            >
                                Parroquia
                            </th>
                            <th
                                v-if="columns.department.visible"
                                class="text-end"
                            >
                                Estado
                            </th>
                            <th
                                v-if="columns.province.visible"
                                class="text-end"
                            >
                                Municipio
                            </th>
                            <th
                                v-if="columns.client_direction.visible"
                                class="text-end"
                            >
                                Direc. del cliente
                            </th>
                            <th>Cliente</th>
                            <th v-if="columns.ruc.visible" class="text-end">
                                RIF
                            </th>
                            <th v-if="columns.items.visible">Productos</th>
                            <th>Estado</th>
                            <th v-if="columns.currency_type_id.visible">
                                Moneda
                            </th>
                            <th
                                class="text-center"
                                v-if="columns.web_platforms.visible"
                            >
                                Plataforma
                            </th>
                            <th v-if="columns.purchase_order.visible">
                                Orden de compra
                            </th>
                            <th
                                v-if="columns.note_sale.visible"
                                class="text-end"
                            >
                                Nota de venta
                            </th>
                            <th
                                v-if="columns.date_note.visible"
                                class="text-end"
                            >
                                Fecha N.Venta
                            </th>
                            <th
                                v-if="columns.payment_form.visible"
                                class="text-end"
                            >
                                Forma de pago
                            </th>
                            <th
                                v-if="columns.payment_method.visible"
                                class="text-end"
                            >
                                Metodo de pago
                            </th>
                            <th v-if="columns.total_charge.visible">
                                Total Cargos
                            </th>
                            <th v-if="columns.total_exonerated.visible">
                                Total Exonerado
                            </th>
                            <th v-if="columns.total_unaffected.visible">
                                Total Inafecto
                            </th>
                            <th v-if="columns.total_free.visible">
                                Total Gratuito
                            </th>
                            <th v-if="columns.total_taxed.visible">
                                Total Gravado
                            </th>

                            <th v-if="columns.total_igv.visible" class="">
                                <!-- ########## INICIO CAMBIO IGV A IVA -->
                                Total IVA
                                <!-- ######### FIN CAMBIO IGV A IVA -->
                            </th>
                            <!-- ########## INICIO SIN DETRACCIONES E ISC -->
                            <!-- La columna Total ISC no se presenta. -->
                            <!-- ######### FIN SIN DETRACCIONES E ISC -->
                            <th v-if="columns.total.visible" class="">Total</th>

                            <template v-if="configuration.enabled_sales_agents">
                                <th v-if="columns.agent.visible">Agente</th>
                                <th v-if="columns.reference_data.visible">Datos de referencia</th>
                            </template>
                            <th v-if="columns.plate.visible">Placa</th>
                        </tr>
                        <tr slot-scope="{ index, row }">
                            <td>{{ index }}</td>
                            <td v-if="columns.user_seller.visible">
                                {{ row.user_name }}
                            </td>
                            <td>{{ row.document_type_description }}</td>
                            <td>{{ row.serie }}</td>
                            <td>{{ row.number }}</td>
                            <td>{{ formatDate(row.date_of_issue) }}</td>
                            <td>{{ formatDate(row.date_of_due) }}</td>
                            <td
                                v-if="columns.guides.visible"
                                class="text-center"
                            >
                                <span v-for="(item, i) in row.guides" :key="i">
                                    {{ item.number }} <br />
                                </span>
                            </td>
                            <td
                                v-if="columns.options.visible"
                                class="text-center"
                            >
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-info m-1__2"
                                    type="button"
                                    @click.prevent="clickOptions(row.id)"
                                >
                                    Opciones
                                </button>
                            </td>
                            <td v-if="columns.doc_affect.visible">
                                {{ row.affected_document }}
                            </td>
                            <td v-if="columns.quote.visible">
                                {{ row.quotation_number_full }}
                            </td>
                            <td v-if="columns.case.visible">
                                {{ row.sale_opportunity_number_full }}
                            </td>

                            <td v-if="columns.district.visible">
                                {{ row.district }}
                            </td>
                            <td v-if="columns.department.visible">
                                {{ row.department }}
                            </td>
                            <td v-if="columns.province.visible">
                                {{ row.province }}
                            </td>
                            <td v-if="columns.client_direction.visible">
                                {{ row.client_direction }}
                            </td>

                            <td>
                                {{ row.customer_name }}<br /><small
                                    v-text="row.customer_number"
                                ></small>
                            </td>

                            <td v-if="columns.ruc.visible">
                                {{ row.ruc }}
                            </td>

                            <td
                                v-if="columns.items.visible"
                                class="text-center"
                            >
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-primary"
                                    type="button"
                                    @click.prevent="
                                        clickViewProducts(row.items)
                                    "
                                >
                                    <i class="fa fa-eye"></i>
                                </button>
                            </td>
                            <td>{{ row.state_type_description }}</td>

                            <td v-if="columns.currency_type_id.visible">
                                {{ row.currency_type_id }}
                            </td>

                            <td v-if="columns.web_platforms.visible">
                                <template
                                    v-for="(platform, i) in row.web_platforms"
                                    v-if="row.web_platforms !== undefined"
                                >
                                    <label class="d-block" :key="i">{{
                                        platform.name
                                    }}</label>
                                </template>
                            </td>

                            <td v-if="columns.purchase_order.visible">
                                {{ row.purchase_order }}
                            </td>

                            <td v-if="columns.note_sale.visible">
                                {{ row.note_sale }}
                            </td>
                            <td v-if="columns.date_note.visible">
                                {{ formatDate(row.date_note) }}
                            </td>
                            <td v-if="columns.payment_form.visible">
                                {{ row.payment_form }}
                            </td>
                            <td v-if="columns.payment_method.visible">
                                {{ row.payment_method }}
                            </td>

                            <td v-if="columns.total_charge.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total_charge == 0
                                            ? "0.00"
                                            : "-" + row.total_charge
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total_charge
                                }}
                            </td>

                            <td v-if="columns.total_exonerated.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total_exonerated == 0
                                            ? "0.00"
                                            : "-" + row.total_exonerated
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total_exonerated
                                }}
                            </td>

                            <td v-if="columns.total_unaffected.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total_unaffected == 0
                                            ? "0.00"
                                            : "-" + row.total_unaffected
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total_unaffected
                                }}
                            </td>
                            <td v-if="columns.total_free.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total_free == 0
                                            ? "0.00"
                                            : "-" + row.total_free
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total_free
                                }}
                            </td>
                            <td v-if="columns.total_taxed.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total_taxed == 0
                                            ? "0.00"
                                            : "-" + row.total_taxed
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total_taxed
                                }}
                            </td>
                            <td v-if="columns.total_igv.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total_igv == 0
                                            ? "0.00"
                                            : "-" + row.total_igv
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total_igv
                                }}
                            </td>
                            <!-- ########## INICIO SIN DETRACCIONES E ISC -->
                            <!-- El dato ISC se conserva en la respuesta, pero no se renderiza. -->
                            <!-- ######### FIN SIN DETRACCIONES E ISC -->
                            <td v-if="columns.total.visible">
                                {{
                                    row.document_type_id == "07"
                                        ? row.total == 0
                                            ? "0.00"
                                            : "-" + row.total
                                        : row.document_type_id != "07" &&
                                          (row.state_type_id == "11" ||
                                              row.state_type_id == "09")
                                        ? "0.00"
                                        : row.total
                                }}
                            </td>

                            <template v-if="configuration.enabled_sales_agents">
                                <td v-if="columns.agent.visible">{{ row.agent_name }}</td>
                                <td v-if="columns.reference_data.visible">{{ row.reference_data }}</td>
                            </template>

                            <td v-if="columns.plate.visible">{{ row.plate_number }}</td>

                            <!-- <td>{{ (row.document_type_id == '07') ? -row.total_unaffected : ((row.document_type_id!='07' && (row.state_type_id =='11'||row.state_type_id =='09')) ? '0.00':row.total_unaffected) }}</td>
                                <td>{{ (row.document_type_id == '07') ? -row.total_free : ((row.document_type_id!='07' && (row.state_type_id =='11'||row.state_type_id =='09')) ? '0.00':row.total_free) }}</td>
                                <td>{{ (row.document_type_id == '07') ? -row.total_taxed : ((row.document_type_id!='07' && (row.state_type_id =='11'||row.state_type_id =='09')) ? '0.00':row.total_taxed) }}</td>
                                <td>{{ (row.document_type_id == '07') ? -row.total_igv : ((row.document_type_id!='07' && (row.state_type_id =='11'||row.state_type_id =='09')) ? '0.00':row.total_igv) }}</td>
                                <td>{{ (row.document_type_id == '07') ? -row.total : ((row.document_type_id!='07' && (row.state_type_id =='11'||row.state_type_id =='09')) ? '0.00':row.total) }}</td>  -->
                        </tr>
                    </data-table>
                </div>
            </div>
            <document-options
                :showDialog.sync="showDialogOptions"
                :recordId="recordId"
                :showClose="true"
                :configuration="configuration"
            ></document-options>
            <product-sale
                :records="recordsItems"
                :showDialog.sync="showDialogProducts"
            >
            </product-sale>
        </div>
    </div>
</template>

    <!-- ######## FIN CAMBIO GEOPOLITICO VENEZUELA -->
<script>
// ######## INICIO SCRIPT GEOPOLITICO VENEZUELA
import DataTable from "@componentsModuleReport/DataTableReportsDocuments.vue";
import DocumentOptions from "@views/documents/partials/options.vue";
import ProductSale from "./partials/product_sale.vue";

export default {
    props: ["configuration"],
    components: { DataTable, DocumentOptions, ProductSale },
    data() {
        return {
            showDialogOptions: false,
            recordId: null,
            resource: "reports/sales",
            form: {},
            columns: {
                guides: {
                    title: "Órdenes de entrega",
                    visible: false
                },
                options: {
                    title: "Opciones",
                    visible: false
                },
                web_platforms: {
                    title: "Plataformas web",
                    visible: false
                },
                // ########## INICIO SIN DETRACCIONES E ISC
                // Total ISC no se ofrece como columna configurable.
                // ######### FIN SIN DETRACCIONES E ISC
                total_charge: {
                    title: "Total Cargos",
                    visible: false
                },
                district: {
                    title: "Parroquia",
                    visible: false
                },
                department: {
                    title: "Estado",
                    visible: false
                },
                province: {
                    title: "Municipio",
                    visible: false
                },
                client_direction: {
                    title: "Direccion del cliente",
                    visible: false
                },
                ruc: {
                    title: "RIF",
                    visible: false
                },
                note_sale: {
                    title: "Nota de venta",
                    visible: false
                },
                date_note: {
                    title: "Fecha de N.Venta",
                    visible: false
                },
                payment_form: {
                    title: "Forma de pago",
                    visible: false
                },
                payment_method: {
                    title: "Metodo de pago",
                    visible: false
                },
                purchase_order: {
                    title: "Orden de compra",
                    visible: true
                },
                total_exonerated: {
                    title: "Total Exonerado",
                    visible: true
                },
                total_unaffected: {
                    title: "Total Inafecto",
                    visible: true
                },
                total_free: {
                    title: "Total Gratuito",
                    visible: true
                },
                total_taxed: {
                    title: "Total Gravado",
                    visible: true
                },
                total_igv: {
                    // ########## INICIO CAMBIO IGV A IVA
                    title: "Total IVA",
                    // ######### FIN CAMBIO IGV A IVA
                    visible: true
                },
                // ########## INICIO SIN DETRACCIONES E ISC
                // Total ISC no se ofrece como columna configurable.
                // ######### FIN SIN DETRACCIONES E ISC
                total: {
                    title: "Total",
                    visible: true
                },
                user_seller: {
                    title: "Usuario/Vendedor",
                    visible: true
                },
                doc_affect: {
                    title: "Doc. Afectado",
                    visible: true
                },
                quote: {
                    title: "Cotizacion",
                    visible: true
                },
                case: {
                    title: "Caso",
                    visible: true
                },
                items: {
                    title: "Productos",
                    visible: true
                },
                currency_type_id: {
                    title: "Moneda",
                    visible: true
                },
                plate: {
                    title: "Placa",
                    visible: false
                },
                agent: {
                    title: "Agente",
                    visible: false
                },
                reference_data: {
                    title: "Datos de referencia",
                    visible: false
                }

            },
            showDialogProducts: false,
            recordsItems: []
        };
    },
    created() {
        this.getColumnsToShow();
    },
    computed: {
      filteredColumns() {
        return Object.fromEntries(
          Object.entries(this.columns).filter(([key, column]) => {
            if ((key === 'agent' || key === 'reference_data') && !this.configuration.enabled_sales_agents) {
              return false;
            }
            return true;
          })
        );
      },
      // ########## INICIO CAMBIO IGV A IVA
      salesVisibleColumnKeys() {
        const orderedColumns = [
          { key: "index", visible: true },
          { key: "user_seller", visible: this.columns.user_seller.visible },
          { key: "document_type", visible: true },
          { key: "series", visible: true },
          { key: "number", visible: true },
          { key: "date_of_issue", visible: true },
          { key: "date_of_due", visible: true },
          { key: "guides", visible: this.columns.guides.visible },
          { key: "options", visible: this.columns.options.visible },
          { key: "doc_affect", visible: this.columns.doc_affect.visible },
          { key: "quote", visible: this.columns.quote.visible },
          { key: "case", visible: this.columns.case.visible },
          { key: "district", visible: this.columns.district.visible },
          { key: "department", visible: this.columns.department.visible },
          { key: "province", visible: this.columns.province.visible },
          { key: "client_direction", visible: this.columns.client_direction.visible },
          { key: "customer", visible: true },
          { key: "ruc", visible: this.columns.ruc.visible },
          { key: "items", visible: this.columns.items.visible },
          { key: "state", visible: true },
          { key: "currency_type_id", visible: this.columns.currency_type_id.visible },
          { key: "web_platforms", visible: this.columns.web_platforms.visible },
          { key: "purchase_order", visible: this.columns.purchase_order.visible },
          { key: "note_sale", visible: this.columns.note_sale.visible },
          { key: "date_note", visible: this.columns.date_note.visible },
          { key: "payment_form", visible: this.columns.payment_form.visible },
          { key: "payment_method", visible: this.columns.payment_method.visible },
          { key: "total_charge", visible: this.columns.total_charge.visible },
          { key: "total_exonerated", visible: this.columns.total_exonerated.visible },
          { key: "total_unaffected", visible: this.columns.total_unaffected.visible },
          { key: "total_free", visible: this.columns.total_free.visible },
          { key: "total_taxed", visible: this.columns.total_taxed.visible },
          { key: "total_igv", visible: this.columns.total_igv.visible },
          { key: "total", visible: this.columns.total.visible },
          {
            key: "agent",
            visible: Boolean(this.configuration.enabled_sales_agents && this.columns.agent.visible)
          },
          {
            key: "reference_data",
            visible: Boolean(this.configuration.enabled_sales_agents && this.columns.reference_data.visible)
          },
          { key: "plate", visible: this.columns.plate.visible }
        ];

        return orderedColumns.filter(column => column.visible).map(column => column.key);
      }
      // ######### FIN CAMBIO IGV A IVA
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY")
                : null;
        },
        clickOptions(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptions = true;
        },
        clickViewProducts(items = []) {
            this.recordsItems = items;
            this.showDialogProducts = true;
        },
        getColumnsToShow(updated) {
            this.$http
                .post("/validate_columns", {
                    columns: this.columns,
                    report: "documents_report_index", // Nombre del reporte.
                    updated: updated !== undefined
                })
                .then(response => {
                    if (updated === undefined) {
                        let currentCols = response.data.columns;
                        if (currentCols && typeof currentCols === "object") {
                            // ########## INICIO SIN DETRACCIONES E ISC
                            // Fusionar preferencias históricas sin reintroducir ISC ni perder columnas actuales.
                            this.columns = Object.keys(this.columns).reduce((columns, key) => {
                                columns[key] = currentCols[key]
                                    ? { ...this.columns[key], ...currentCols[key] }
                                    : this.columns[key];
                                return columns;
                            }, {});
                            // ######### FIN SIN DETRACCIONES E ISC
                        }
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        }
    }
};
// ######## FIN SCRIPT GEOPOLITICO VENEZUELA
</script>
