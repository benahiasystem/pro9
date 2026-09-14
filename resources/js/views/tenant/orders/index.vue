<template>
    <div class="orders" v-loading="loading_submit">
        <div class="page-header pe-0">
            <h2>
                <a href="/orders">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 17h-11v-14h-2" />
                        <path d="M6 5l14 1l-1 7h-13" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Pedidos</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <button
                    class="btn btn-custom btn-sm mt-2 me-4"
                    @click="showStatusModal = true"
                    title="Gestionar estados"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/>
                        <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/>
                    </svg>
                    Gestionar estados
                </button>
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource" :status-options="orderOptions">
                    <tr slot="heading" width="100%">
                        <!-- <th>#</th> -->
                        <th># Pedido</th>
                        <th>Cliente</th>
                        <th class="text-end">Total</th>
                        <th>Fecha Emision</th>
                        <th>Medio Pago</th>
                        <th>Estado de pago</th>
                        <th>Estado de envío</th>
                        <th>Estado de pedido</th>
                        <th class="text-center">Documento</th>
                        <th class="text-end">Opciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row }" :class="{ 'order-voided-row': isVoided(row) }">
                        <!-- <td>{{ index }}</td> -->
                        <td>
                            <a href="#" @click.prevent="openDetail(row)" class="text-primary">
                                {{ row.order_id }}
                            </a>
                        </td>
                        <td>
                            <span class="d-inline-flex align-items-center gap-1">
                                <el-tooltip
                                    :content="row.is_guest ? 'Compra como invitado' : 'Cliente autenticado'"
                                    placement="top"
                                >
                                    <i
                                        class="fas fa-user"
                                        :style="{ color: row.is_guest ? '#9ca3af' : '#2563eb' }"
                                        aria-hidden="true"
                                    ></i>
                                </el-tooltip>
                                <span
                                    class="order-customer-link"
                                    role="button"
                                    tabindex="0"
                                    @click="openDetail(row)"
                                    @keyup.enter.prevent="openDetail(row)"
                                >{{ row.customer }}</span>
                            </span>
                        </td>
                        <td class="text-end">Bs. {{ row.total }}</td>
                        <td>{{ formatDate(row.created_at) }}</td>
                        <td>{{ row.reference_payment }}</td>
                        <td>
                            <div
                                class="status-select-wrap"
                                :class="{ 'has-color': statusColor(row.payment_status_order_id) }"
                                :style="selectVars(row.payment_status_order_id)"
                            >
                                <span
                                    v-if="statusColor(row.payment_status_order_id)"
                                    class="status-dot status-dot--inside"
                                    :style="{ background: statusColor(row.payment_status_order_id) }"
                                ></span>
                                <el-select
                                    v-model="row.payment_status_order_id"
                                    placeholder="Estado de pago"
                                    :value="row.payment_status_order_id"
                                    :disabled="isVoided(row)"
                                    @change="updateStatus(row, 'payment_status_order_id')"
                                >
                                    <el-option
                                        v-for="item in paymentOptions"
                                        :key="item.id"
                                        :label="item.description"
                                        :value="item.id"
                                    >
                                        <span class="status-dot" :style="{ background: item.color || '#909399' }"></span>
                                        <span>{{ item.description }}</span>
                                    </el-option>
                                </el-select>
                            </div>
                        </td>
                        <td>
                            <div class="shipping-tracking-cell">
                                <div
                                    class="status-select-wrap"
                                    :class="{ 'has-color': statusColor(row.shipping_status_order_id) }"
                                    :style="selectVars(row.shipping_status_order_id)"
                                >
                                    <span
                                        v-if="statusColor(row.shipping_status_order_id)"
                                        class="status-dot status-dot--inside"
                                        :style="{ background: statusColor(row.shipping_status_order_id) }"
                                    ></span>
                                    <el-select
                                        v-model="row.shipping_status_order_id"
                                        placeholder="Estado de envío"
                                        :value="row.shipping_status_order_id"
                                        :disabled="isVoided(row)"
                                        @change="updateStatus(row, 'shipping_status_order_id')"
                                    >
                                        <el-option
                                            v-for="item in shippingOptions"
                                            :key="item.id"
                                            :label="item.description"
                                            :value="item.id"
                                        >
                                            <span class="status-dot" :style="{ background: item.color || '#909399' }"></span>
                                            <span>{{ item.description }}</span>
                                        </el-option>
                                    </el-select>
                                </div>
                                <button
                                    type="button"
                                    class="tracking-code-trigger"
                                    :class="{
                                        'has-code': !!row.tracking_code,
                                        'is-disabled': isVoided(row),
                                    }"
                                    :disabled="isVoided(row)"
                                    :title="row.tracking_code || 'Agregar código de seguimiento'"
                                    @click.prevent="openTrackingModal(row)"
                                >
                                    <i class="el-icon-truck tracking-code-trigger__icon"></i>
                                    <span class="tracking-code-trigger__text">
                                        {{ row.tracking_code || 'Código de seguimiento' }}
                                    </span>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div
                                class="status-select-wrap"
                                :class="{ 'has-color': statusColor(row.status_order_id) }"
                                :style="selectVars(row.status_order_id)"
                            >
                                <span
                                    v-if="statusColor(row.status_order_id)"
                                    class="status-dot status-dot--inside"
                                    :style="{ background: statusColor(row.status_order_id) }"
                                ></span>
                                <el-select
                                    v-model="row.status_order_id"
                                    placeholder="Estado de pedido"
                                    :value="row.status_order_id"
                                    :disabled="isVoided(row)"
                                    @change="updateStatus(row, 'status_order_id')"
                                >
                                    <el-option
                                        v-for="item in orderOptions"
                                        :key="item.id"
                                        :label="item.description"
                                        :value="item.id"
                                    >
                                        <span class="status-dot" :style="{ background: item.color || '#909399' }"></span>
                                        <span>{{ item.description }}</span>
                                    </el-option>
                                </el-select>
                            </div>
                        </td>
                        <td class="text-center">
                            <template v-if="row.document_type_id == '80'">
                                {{ row.sale_note_number_full }}
                            </template>
                            <template v-else>
                                {{ row.number_document }}
                            </template>
                        </td>
                        <td class="text-end">
                            <el-tag v-if="isVoided(row)" type="danger" size="small" effect="plain">
                                Anulado
                            </el-tag>
                            <div v-else class="d-inline-flex align-items-center justify-content-end gap-1">
                                <template v-if="row.document_type_id == '80'">
                                    <el-button
                                        type="primary"
                                        size="mini"
                                        icon="el-icon-tickets"
                                        title="Opciones de nota de venta"
                                        @click.prevent="openSaleNoteOptions(row)"
                                    ></el-button>
                                </template>
                                <template v-else>
                                    <el-button
                                        type="primary"
                                        size="mini"
                                        icon="el-icon-tickets"
                                        title="Opciones de comprobante"
                                        @click.prevent="openDocumentOptions(row)"
                                    ></el-button>
                                </template>
                                <el-button
                                    v-if="canGenerateGuide(row)"
                                    type="default"
                                    size="mini"
                                    icon="el-icon-truck"
                                    title="Generar orden de entrega"
                                    @click.prevent="goToGuide(row)"
                                ></el-button>
                            </div>
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>

        <el-dialog
            title="Stock en almacén"
            width="40%"
            :visible="showDialog"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            append-to-body
            :show-close="false"
        >
            <div class="form-body">
                <div class="row">
                    <div class="col-lg-12 col-md-12 table-responsive">
                        <table width="100%" class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Almacén</th>
                                </tr>
                            </thead>
                            <tbody
                                v-for="(rowProduct,
                                indexProduct) in totalProduct"
                                :key="indexProduct"
                                width="100%"
                            >
                                <tr>
                                    <td>
                                        {{ record.items[indexProduct].name }}
                                    </td>
                                    <td>
                                        <el-select
                                            v-model="form[rowProduct]"
                                            placeholder="Almacenes"
                                        >
                                            <el-option
                                                v-if="
                                                    rowProduct === item.item_id
                                                "
                                                v-for="item in warehouses"
                                                :key="item.id"
                                                :label="
                                                    item.warehouse +
                                                        ' - ' +
                                                        'Stock -> ' +
                                                        Math.trunc(item.stock)
                                                "
                                                :value="item.id"
                                                :disabled="
                                                    optionDisable(
                                                        item.item_id,
                                                        item.stock
                                                    )
                                                "
                                            ></el-option>
                                        </el-select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2">
                <el-button class="second-buton" @click="close"
                    >Cerrar</el-button
                >
                <el-button type="primary" @click="save">Guardar</el-button>
            </div>
        </el-dialog>

        <el-dialog
            title="Código de Seguimiento de Courier"
            width="460px"
            :visible.sync="showTrackingModal"
            :close-on-click-modal="false"
            append-to-body
            @close="closeTrackingModal"
        >
            <div class="tracking-modal-body">
                <label class="tracking-modal-label">Código de orden de entrega / tracking</label>
                <div class="tracking-modal-input-row">
                    <el-input
                        v-model="trackingForm.code"
                        placeholder="Ej: OLVA-123456 / SHALOM-ABC"
                        maxlength="120"
                        clearable
                        @keyup.enter.native="saveTrackingFromModal"
                    ></el-input>
                    <el-button
                        type="danger"
                        plain
                        icon="el-icon-delete"
                        title="Limpiar código"
                        :disabled="!String(trackingForm.code || '').trim()"
                        @click.prevent="clearTrackingDraft"
                    ></el-button>
                </div>
                <p class="tracking-modal-hint">
                    Úsalo para registrar el código de agencias externas (Olva, Shalom, Marvisur, etc.).
                </p>
            </div>
            <div slot="footer" class="form-actions text-end">
                <el-button class="second-buton" @click="closeTrackingModal">Cancelar</el-button>
                <el-button
                    type="primary"
                    :loading="trackingForm.saving"
                    @click="saveTrackingFromModal"
                >Guardar</el-button>
            </div>
        </el-dialog>

        <options-form
            :showDialog.sync="showDialogOptions"
            :recordId="documentNewId"
            :statusDocument="statusDocument"
            :resource="resource_options"
        ></options-form>

        <document-form
            :order_id="order_id"
            :user="user"
            :document_types="document_types"
            ref="document_form"
        >
        </document-form>

        <sale-note-form
            :showDialog.sync="showDialogSaleNote"
            :orderId="order_id"
            :dataSaleNote="dataSaleNote"
            :statusField="statusField"
            :statusValue="record ? record[statusField] : null"
        >
        </sale-note-form>
        <status-order-modal
            :showDialog.sync="showStatusModal"
            :options="options"
        ></status-order-modal>
        <order-detail-drawer
            :showDrawer.sync="showOrderDrawer"
            :record="selectedOrder"
            :statusOptions="options"
        ></order-detail-drawer>
    </div>
</template>
<style scoped>
.order-customer-link {
    color: inherit;
    cursor: pointer;
    font-weight: inherit;
    text-decoration: underline;
}

.order-customer-link:hover,
.order-customer-link:focus {
    color: inherit;
    font-weight: inherit;
    text-decoration: underline;
    outline: none;
}
</style>
<style>
/* Pedido anulado: texto en rojo en toda la fila (patrón consistente con anulaciones) */
.order-voided-row td,
.order-voided-row td a,
.order-voided-row td .order-customer-link {
    color: #c0392b !important;
}
.shipping-tracking-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 260px;
}
.shipping-tracking-cell .status-select-wrap {
    flex: 1 1 55%;
    min-width: 0;
}
.tracking-code-trigger {
    flex: 1 1 45%;
    min-width: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 32px;
    padding: 4px 10px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: #fff;
    color: #909399;
    font-size: 12px;
    line-height: 1.2;
    text-align: left;
    cursor: pointer;
    transition: border-color .15s ease, color .15s ease, background-color .15s ease;
}
.tracking-code-trigger:hover:not(.is-disabled) {
    border-color: #409eff;
    color: #409eff;
}
.tracking-code-trigger.has-code {
    color: #303133;
    border-color: #c0c4cc;
    background: #f5f7fa;
}
.tracking-code-trigger.has-code:hover:not(.is-disabled) {
    border-color: #409eff;
    color: #303133;
    background: #ecf5ff;
}
.tracking-code-trigger.is-disabled,
.tracking-code-trigger:disabled {
    opacity: .55;
    cursor: not-allowed;
}
.tracking-code-trigger__icon {
    flex-shrink: 0;
    font-size: 14px;
}
.tracking-code-trigger__text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tracking-modal-body {
    padding-top: 4px;
}
.tracking-modal-label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #606266;
}
.tracking-modal-input-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.tracking-modal-input-row .el-input {
    flex: 1;
}
.tracking-modal-hint {
    margin: 10px 0 0;
    font-size: 12px;
    color: #909399;
    line-height: 1.4;
}
/* Estado con color: se pinta el propio select (borde, fondo, texto) con el punto dentro */
.status-select-wrap {
    position: relative;
    width: 100%;
}
.status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
/* Punto dentro del select, sobre el input, a la izquierda del texto */
.status-dot--inside {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    pointer-events: none;
}
/* Pinta el input del select con el color del estado. !important para ganarle al skin del tenant */
.status-select-wrap.has-color .el-input__inner {
    border: none !important;
    background-color: color-mix(in srgb, var(--st-color) 12%, #fff) !important;
    color: color-mix(in srgb, var(--st-color) 80%, #000) !important;
    font-weight: 600;
    padding-left: 30px !important; /* espacio para el punto interno */
}
.status-select-wrap.has-color .el-input__inner::placeholder {
    color: color-mix(in srgb, var(--st-color) 62%, #000) !important;
}
.status-select-wrap.has-color .el-select__caret {
    color: var(--st-color) !important;
}
/* Punto dentro de las opciones del desplegable (se teletransporta al body) */
.el-select-dropdown__item .status-dot {
    margin-right: 8px;
    vertical-align: middle;
}

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
import queryString from "query-string";
import OptionsForm from "../pos/partials/options.vue";
import DocumentForm from "./partials/document_form.vue";
import SaleNoteForm from "./partials/sale_note_form.vue";
import StatusOrderModal from "./partials/status_order_modal.vue";
import OrderDetailDrawer from "./partials/detail-drawer.vue";

export default {
    props: ["user"],

    components: { DataTable, OptionsForm, DocumentForm, SaleNoteForm, StatusOrderModal, OrderDetailDrawer },
    data() {
        return {
            showStatusModal: false,
            showDialog: false,
            showImportDialog: false,
            showImageDetail: false,
            resource: "orders",
            recordId: null,
            options: [],
            warehouses: [],
            estableciment_id: "",
            totalProduct: [], // items_id
            showDialog: false,
            form: [],
            record: "", // record orders
            stocks: "",
            showDialogOptions: false,
            documentNewId: null,
            statusDocument: {},
            resource_options: null,
            loading_submit: false,
            document_types: [],
            order_id: null,
            dataSaleNote: {},
            showDialogSaleNote: false,
            statusFilter: null,
            statusField: 'status_order_id',
            showOrderDrawer: false,
            selectedOrder: null,
            showTrackingModal: false,
            trackingForm: {
                orderId: null,
                code: '',
                saving: false,
            },
            trackingTargetRow: null,
        };
    },
    async created() {
        this.$http.get(`/statusOrder/records`).then(response => {
            this.options = response.data;
        });
        this.events();
        this.$eventHub.$on('statusesUpdated', () => {
            this.loadStatuses()
        })
    },
    computed: {
        paymentOptions() {
            return this.options.filter(o => o.is_payment_status);
        },
        orderOptions() {
            return this.options.filter(o => o.is_order_status);
        },
        shippingOptions() {
            return this.options.filter(o => o.is_shipping_status);
        }
    },
    methods: {
        openDetail(row) {
            this.selectedOrder = row;
            this.showOrderDrawer = true;
        },
        loadStatuses() {
            this.$http.get(`/statusOrder/records`).then(response => {
                this.options = response.data;
            });
        },
        // Devuelve el color hex del estado con ese id (o '' si no tiene)
        statusColor(id) {
            if (!id) return '';
            const s = this.options.find(o => o.id === id);
            return s && s.color ? s.color : '';
        },
        // Variable CSS con el color del estado, para pintar el select (borde, fondo, texto)
        selectVars(id) {
            const c = this.statusColor(id);
            return c ? { '--st-color': c } : {};
        },
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY h:mmA")
                : null;
        },
        clickOptions(recordId) {
            this.documentNewId = recordId;
            this.statusDocument.send = "";
            this.resource_options = "sale-notes";
            this.showDialogOptions = true;
        },
        openSaleNoteOptions(row) {
            if (!row.sale_note_id) {
                return this.$message.warning('Este pedido aún no tiene nota de venta. Cambia el estado del pedido para generarla.');
            }

            this.clickOptions(row.sale_note_id);
        },
        openDocumentOptions(row) {
            if (!row.document_external_id) {
                return this.$message.warning('Este pedido aún no tiene comprobante electrónico. Cambia el estado del pedido para generarlo.');
            }

            this.clickDownload(row.document_external_id);
        },
        // El pedido está anulado si alguno de sus estados actuales tiene "Anular pedido".
        isVoided(row) {
            const ids = [
                row.status_order_id,
                row.payment_status_order_id,
                row.shipping_status_order_id,
            ];
            return this.options.some(o => ids.includes(o.id) && o.action_void_order);
        },
        // La orden de entrega se arma sobre la nota de venta: basta con que exista para permitirla.
        canGenerateGuide(row) {
            return !!row.sale_note_id;
        },
        goToGuide(row) {
            window.location.href = `/dispatches/create_new/sale_note/${row.sale_note_id}`;
        },
        async clickDownload(row) {
            await this.$http
                .get(`/documents/search/externalId/${row}`)
                .then(response => {
                    this.documentNewId = response.data.id;
                });
            this.statusDocument.send = "";
            this.resource_options = "documents";
            this.showDialogOptions = true;
        },
        subtotal(item) {
            var subtotal;
            if (item.currency_type_id === "USD") {
                subtotal = Number(
                    item.cantidad *
                        item.exchange_rate_sale *
                        parseFloat(item.sale_unit_price)
                ).toFixed(2);
                if (isNaN(subtotal)) {
                    return "-";
                } else {
                    return subtotal;
                }
            } else {
                return parseFloat(item.cantidad * item.sale_unit_price);
            }
        },
        optionDisable(product, stock) {
            for (var i = 0; i < this.record.items.length; i++) {
                if (product === this.record.items[i].id) {
                    return stock >= this.record.items[i].cantidad
                        ? false
                        : true;
                }
            }
        },
        openDialogSaleNote(sale_note) {
            this.dataSaleNote = sale_note;
            this.showDialogSaleNote = true;
        },
        openTrackingModal(row) {
            if (!row || !row.id || this.isVoided(row)) {
                return;
            }
            this.trackingTargetRow = row;
            this.trackingForm = {
                orderId: row.id,
                code: String(row.tracking_code || ''),
                saving: false,
            };
            this.showTrackingModal = true;
        },
        closeTrackingModal() {
            this.showTrackingModal = false;
            this.trackingTargetRow = null;
            this.trackingForm = {
                orderId: null,
                code: '',
                saving: false,
            };
        },
        clearTrackingDraft() {
            this.trackingForm.code = '';
        },
        async saveTrackingFromModal() {
            if (!this.trackingForm.orderId) {
                return;
            }
            const next = String(this.trackingForm.code || '').trim();
            this.trackingForm.saving = true;
            try {
                const response = await this.$http.post(`/orders/tracking-code`, {
                    id: this.trackingForm.orderId,
                    tracking_code: next,
                });
                if (response.data && response.data.success === false) {
                    this.$message.error(response.data.message || 'No se pudo guardar el código');
                    return;
                }
                const saved = response.data.tracking_code || null;
                if (this.trackingTargetRow) {
                    this.$set(this.trackingTargetRow, 'tracking_code', saved);
                }
                this.$message.success(response.data.message || 'Código de seguimiento guardado');
                this.closeTrackingModal();
            } catch (e) {
                this.$message.error('Error al guardar el código de seguimiento');
            } finally {
                this.trackingForm.saving = false;
            }
        },
        async updateStatus(record, field = 'status_order_id') {
            this.record = record;
            this.statusField = field;

            // Obtener el objeto de estado completo desde las opciones cargadas
            const selectedStatus = this.options.find(o => o.id === record[field])

            if (selectedStatus && selectedStatus.action_void_order) {
                this.$confirm(
                    'Se anulará el pedido y se revertirá el stock (y la nota de venta si existe). Esta acción no se puede deshacer.',
                    'Anular pedido',
                    { confirmButtonText: 'Anular', cancelButtonText: 'Cancelar', type: 'warning' }
                ).then(() => {
                    this.saveUpdateStatus();
                    this.$eventHub.$emit('reloadData');
                }).catch(() => {
                    // Cancelado: revertir el estado visual al valor de BD
                    this.$eventHub.$emit('reloadData');
                });
                return;
            } else if (selectedStatus && selectedStatus.action_generate_document) {
                // Antes se abría document_form sin persistir el estado → el comprobante
                // podía crearse y el pago quedaba en "Pendiente". El backend ya actualiza
                // el estado y genera el comprobante (OrderDocumentFromStatusService).
                this.order_id = record.id;
                this.saveUpdateStatus();
            } else if (selectedStatus && selectedStatus.action_discount_stock) {
                // Si la orden ya tiene el flag de stock descontado, no continuar
                if (record.stock_discounted) {
                    this.$message.success('El stock ya fue descontado para esta orden');
                    return;
                }
                this.totalProduct = await this.products(record);
                await this.$http
                    .post(`/orders/warehouse`, { item_id: this.totalProduct })
                    .then(response => {
                        this.warehouses = response.data.data;
                        this.showDialog = true;
                    });
                return;
            } else {
                this.saveUpdateStatus();
            }
        },
        async saveUpdateStatus() {
            // Capturar el estado seleccionado ANTES de hacer la petición,
            // para saber si tiene action_generate_document activo.
            const selectedStatus = this.options.find(o => o.id === this.record[this.statusField]);

            this.loading_submit = true;

            try {
                const response = await this.$http.post(`/statusOrder/update`, {
                    record: this.record,
                    field: this.statusField
                });

                if (response.data.type === 'error') {
                    this.$message.error(response.data.message);
                    return;
                }

                if (response.data.type === 'warning') {
                    this.$message.warning(response.data.message);
                    this.$eventHub.$emit('reloadData');
                    return;
                }

                this.$message.success(response.data.message);

                // Actualizar la fila al instante (sin esperar el reload de la tabla).
                if (response.data.number_document) {
                    this.record.number_document = response.data.number_document;
                }
                if (response.data.document_external_id) {
                    this.record.document_external_id = response.data.document_external_id;
                }
                if (response.data.sale_note_id) {
                    this.record.sale_note_id = response.data.sale_note_id;
                }
                if (response.data.sale_note_number_full) {
                    this.record.sale_note_number_full = response.data.sale_note_number_full;
                }

                this.$eventHub.$emit('reloadData');

                // Abrir la vista del comprobante recién generado (NV o CPE).
                if (selectedStatus && selectedStatus.action_generate_document) {
                    if (response.data.sale_note_id) {
                        this.documentNewId = response.data.sale_note_id;
                        this.statusDocument.send = '';
                        this.resource_options = 'sale-notes';
                        this.showDialogOptions = true;
                    } else if (response.data.document_id) {
                        this.documentNewId = response.data.document_id;
                        this.statusDocument.send = '';
                        this.resource_options = 'documents';
                        this.showDialogOptions = true;
                    } else if (response.data.document_external_id) {
                        await this.clickDownload(response.data.document_external_id);
                    }
                }
            } catch (error) {
                console.error(error);
                this.$message.error('Ocurrió un error al actualizar el estado.');
            } finally {
                this.loading_submit = false;
            }
        },
        async save() {
            var save = [];

            for (var i = 0; i < this.record.items.length; i++) {
                if (this.totalProduct[i] === this.record.items[i].id) {
                    save.push({
                        id: this.form[this.totalProduct[i]],
                        cantidad: this.record.items[i].cantidad
                    });
                }
            }

            await this.$http
                .post(`/statusOrder/update`, {
                    record: this.record,
                    discount: save,
                    field: this.statusField
                })
                .then(response => {
                    // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
                    if (response.data.type === 'warning' || response.data.type === 'error') {
                        this.$message.warning(response.data.message);
                        return;
                    }
                    this.record.stock_discounted = true;
                    // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
                    this.$message.success(response.data.message);
                    this.$eventHub.$emit('reloadData');
                    this.close();
                })
                .catch(error => { this.$message.error((error.response && error.response.data && error.response.data.message) || 'No se pudo reservar el inventario del pedido.'); });
        },
        close() {
            this.form = [];
            this.showDialog = false;
            this.recoard = "";
        },
        products(products) {
            let listProduct = [];

            for (var i = 0; i <= products.items.length - 1; i++) {
                listProduct.push(products.items[i].id);
            }
            return listProduct;
        },
        async events() {
            await this.$eventHub.$on("cancelSale", () => {
                this.showDialogOptions = false;
            });
        },

        getHeaderConfig() {
            let token = this.user.api_token;
            let httpConfig = {
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${token}`
                }
            };
            return httpConfig;
        }
    }
};
</script>
