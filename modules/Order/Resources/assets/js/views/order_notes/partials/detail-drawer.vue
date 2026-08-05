<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="520px"
        direction="rtl"
        custom-class="order-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="order-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header order-detail-drawer__header">
                <div class="order-detail-drawer__title">
                    <h4>Detalle del pedido</h4>
                    <small v-if="record">{{ orderIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar order-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="order-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge"
                            :class="stateBadgeClass"
                        >
                            {{ stateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                        <small v-if="deliveryDateLabel" class="text-muted">· Entrega: {{ deliveryDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="order-detail-drawer__body">
                    <div class="order-detail-drawer__section-card">
                        <div class="order-detail-drawer__section-card-header">
                            <h5 class="section-title">Cliente</h5>
                            <p class="section-subtitle">Datos del cliente asociado al pedido</p>
                        </div>
                        <dl class="order-detail-drawer__list">
                            <dt>Nombre / Razón social</dt>
                            <dd>{{ customerName }}</dd>

                            <dt>Documento</dt>
                            <dd>{{ customerDocument }}</dd>

                            <dt v-if="customerTelephone">Teléfono</dt>
                            <dd v-if="customerTelephone">{{ customerTelephone }}</dd>

                            <dt v-if="customerEmail">Correo</dt>
                            <dd v-if="customerEmail">{{ customerEmail }}</dd>

                            <dt v-if="shippingAddress">Dirección de envío</dt>
                            <dd v-if="shippingAddress">{{ shippingAddress }}</dd>
                        </dl>
                    </div>

                    <div class="order-detail-drawer__section-card">
                        <div class="order-detail-drawer__section-card-header">
                            <h5 class="section-title">Información operativa</h5>
                            <p class="section-subtitle">Asignación comercial y origen del inventario</p>
                        </div>
                        <dl class="order-detail-drawer__list">
                            <dt>Vendedor</dt>
                            <dd>{{ sellerLabel }}</dd>

                            <dt>Establecimiento</dt>
                            <dd>{{ establishmentLabel }}</dd>

                            <dt>Almacén / origen</dt>
                            <dd>{{ warehouseLabel }}</dd>

                            <dt v-if="record.observation">Observaciones</dt>
                            <dd v-if="record.observation">{{ record.observation }}</dd>
                        </dl>
                    </div>

                    <div class="order-detail-drawer__section-card">
                        <div class="order-detail-drawer__section-card-header">
                            <h5 class="section-title">Pagos y totales</h5>
                            <p class="section-subtitle">Condiciones de pago e importes del pedido</p>
                        </div>
                        <dl class="order-detail-drawer__list">
                            <dt>Moneda</dt>
                            <dd>{{ currencyLabel }}</dd>

                            <dt>Condición de pago</dt>
                            <dd>{{ paymentConditionLabel }}</dd>

                            <dt>Gravado</dt>
                            <dd>{{ formatMoney(record.total_taxed) }}</dd>

                            <dt>IGV</dt>
                            <dd>{{ formatMoney(record.total_igv) }}</dd>

                            <dt>Saldo</dt>
                            <dd :class="{ 'text-danger fw-bold': balanceAmount > 0 }">{{ formatMoney(balanceAmount) }}</dd>

                            <dt>Total</dt>
                            <dd class="text-primary fw-bold">{{ formatMoney(record.total) }}</dd>
                        </dl>
                    </div>

                    <div class="order-detail-drawer__section-card">
                        <div class="order-detail-drawer__section-card-header">
                            <h5 class="section-title">Productos</h5>
                            <p class="section-subtitle">Detalle de ítems incluidos en el pedido</p>
                        </div>
                        <div v-if="lineItems.length" class="table-responsive">
                            <table class="table table-sm order-detail-drawer__items-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end">P. unit.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in lineItems" :key="item.key || index">
                                        <td>{{ item.description }}</td>
                                        <td class="text-center">{{ item.quantity }}</td>
                                        <td class="text-end">{{ formatMoney(item.unit_price, false) }}</td>
                                        <td class="text-end">{{ formatMoney(item.subtotal, false) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-muted small mb-0">Sin productos registrados.</p>
                    </div>
                </div>

                <div class="order-detail-drawer__footer">
                    <button
                        v-if="canAnulate"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="voiding"
                        @click="clickAnulate"
                    >
                        <i class="fa fa-trash"></i> Anular pedido
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar pedido
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
import { deletable } from '@mixins/deletable';

const EDITABLE_STATE_IDS = ['01'];

export default {
    mixins: [deletable],
    props: {
        showDrawer: {
            type: Boolean,
            default: false
        },
        recordId: {
            type: [Number, String],
            default: null
        },
        initialRow: {
            type: Object,
            default: null
        },
        resource: {
            type: String,
            default: 'order-notes'
        },
        canEditRow: {
            type: Function,
            default: null
        },
        canAnulateRow: {
            type: Function,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            voiding: false,
            record: null,
            paymentMethodTypes: [],
            tablesLoaded: false
        };
    },
    computed: {
        visibleDrawer: {
            get() {
                return this.showDrawer;
            },
            set(value) {
                this.$emit('update:showDrawer', value);
            }
        },
        orderIdentifier() {
            return this.record?.identifier
                || (this.record?.prefix && this.record?.id ? `${this.record.prefix}-${this.record.id}` : null)
                || `#${this.record?.id || ''}`;
        },
        stateLabel() {
            return this.record?.state_type_description
                || this.record?.state_type?.description
                || '—';
        },
        stateBadgeClass() {
            if (String(this.record?.state_type_id) === '11') {
                return 'badge-danger';
            }

            return 'badge-success';
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        deliveryDateLabel() {
            const date = this.record?.delivery_date;
            if (!date) {
                return null;
            }

            return this.formatDisplayDate(date);
        },
        customerName() {
            return this.record?.customer?.name
                || this.record?.customer_name
                || '—';
        },
        customerDocument() {
            const customer = this.record?.customer;
            if (customer?.identity_document_type?.description && customer?.number) {
                return `${customer.identity_document_type.description} ${customer.number}`;
            }

            if (customer?.number) {
                return customer.number;
            }

            return this.record?.customer_number || '—';
        },
        customerTelephone() {
            return this.record?.customer?.telephone
                || this.record?.customer_telephone
                || null;
        },
        customerEmail() {
            return this.record?.customer?.email
                || this.record?.customer_email
                || null;
        },
        shippingAddress() {
            return this.record?.shipping_address || null;
        },
        sellerLabel() {
            return this.record?.user?.name
                || this.record?.user_name
                || '—';
        },
        establishmentLabel() {
            const establishment = this.record?.establishment;
            if (!establishment) {
                return '—';
            }

            if (establishment.description) {
                return establishment.description;
            }

            const parts = [establishment.code, establishment.address].filter(Boolean);
            return parts.length ? parts.join(' — ') : '—';
        },
        warehouseLabel() {
            const warehouseIds = new Set();

            this.lineItems.forEach(item => {
                if (item.warehouse_id) {
                    warehouseIds.add(String(item.warehouse_id));
                }
            });

            if (!warehouseIds.size) {
                return this.establishmentLabel !== '—' ? this.establishmentLabel : '—';
            }

            if (warehouseIds.size === 1) {
                return `Almacén #${Array.from(warehouseIds)[0]}`;
            }

            return `${warehouseIds.size} almacenes`;
        },
        currencyLabel() {
            const currencyId = this.record?.currency_type_id;
            if (!currencyId) {
                return '—';
            }

            const symbol = currencyId === 'PEN' ? 'S/' : (currencyId === 'USD' ? '$' : currencyId);
            return `${currencyId} (${symbol})`;
        },
        currencySymbol() {
            return this.record?.currency_type_id === 'USD' ? '$' : 'S/';
        },
        paymentConditionLabel() {
            if (this.record?.payment_method_type?.description) {
                return this.record.payment_method_type.description;
            }

            const paymentType = this.paymentMethodTypes.find(
                option => String(option.id) === String(this.record?.payment_method_type_id)
            );

            return paymentType ? paymentType.description : '—';
        },
        balanceAmount() {
            const total = this.parseAmount(this.record?.total);
            return Math.max(total - this.totalPayments, 0);
        },
        totalPayments() {
            let payments = 0;

            (this.record?.documents || []).forEach(doc => {
                payments += Number(doc.total_payments || 0);
            });

            (this.record?.sale_notes || []).forEach(note => {
                payments += Number(note.total_payments || 0);
            });

            return payments;
        },
        lineItems() {
            const items = Array.isArray(this.record?.items) ? this.record.items : [];

            return items.map((row, index) => {
                const itemData = this.parseItemData(row.item);
                const quantity = Number(row.quantity || 0);
                const unitPrice = Number(row.unit_price || 0);
                const subtotal = row.total !== undefined && row.total !== null
                    ? Number(row.total)
                    : quantity * unitPrice;

                return {
                    key: row.id || index,
                    description: itemData?.description || itemData?.name || '—',
                    quantity,
                    unit_price: unitPrice,
                    subtotal,
                    warehouse_id: row.warehouse_id || itemData?.warehouse_id || null
                };
            });
        },
        canEdit() {
            const row = this.buildActionRow();

            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(row);
            }

            return this.isEditableOrderState(row) && !this.hasAssociatedVouchers(row);
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            const row = this.buildActionRow();
            if (String(row.state_type_id) === '11') {
                return false;
            }

            return row.documents.length === 0;
        }
    },
    watch: {
        showDrawer(value) {
            if (value && this.recordId) {
                this.openDrawer();
            }
        },
        recordId(value) {
            if (this.showDrawer && value) {
                this.openDrawer();
            }
        }
    },
    methods: {
        openDrawer() {
            this.applyInitialSnapshot();
            this.loadRecord();
        },
        applyInitialSnapshot() {
            if (!this.initialRow || String(this.initialRow.id) !== String(this.recordId)) {
                this.record = null;
                return;
            }

            this.record = {
                ...this.initialRow,
                identifier: this.initialRow.identifier,
                customer_name: this.initialRow.customer_name,
                customer_number: this.initialRow.customer_number,
                user_name: this.initialRow.user_name,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                documents: this.initialRow.documents || [],
                sale_notes: this.initialRow.sale_notes || [],
                btn_generate: this.initialRow.btn_generate
            };
        },
        loadRecord() {
            const requestedId = this.recordId;
            if (!requestedId) {
                return;
            }

            this.loading = true;

            Promise.all([
                this.ensureTables(),
                this.$http.get(`/${this.resource}/record/${requestedId}`)
            ])
                .then(([, response]) => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    const orderNote = response.data?.data?.order_note || response.data?.order_note;
                    if (!orderNote) {
                        throw new Error('missing order note');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    this.record = {
                        ...snapshot,
                        ...orderNote,
                        identifier: orderNote.prefix && orderNote.id
                            ? `${orderNote.prefix}-${orderNote.id}`
                            : (snapshot.identifier || orderNote.identifier),
                        state_type_description: orderNote.state_type?.description
                            || snapshot.state_type_description,
                        state_type_id: orderNote.state_type_id ?? snapshot.state_type_id,
                        user_name: orderNote.user?.name || snapshot.user_name,
                        customer_name: orderNote.customer?.name || snapshot.customer_name,
                        customer_number: orderNote.customer?.number || snapshot.customer_number,
                        documents: snapshot.documents || orderNote.documents || [],
                        sale_notes: snapshot.sale_notes || orderNote.sale_notes || [],
                        btn_generate: snapshot.btn_generate !== undefined
                            ? snapshot.btn_generate
                            : orderNote.btn_generate
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del pedido.');
                        this.visibleDrawer = false;
                    }
                })
                .finally(() => {
                    if (String(requestedId) === String(this.recordId)) {
                        this.loading = false;
                    }
                });
        },
        ensureTables() {
            if (this.tablesLoaded) {
                return Promise.resolve();
            }

            return this.$http
                .get(`/${this.resource}/tables`)
                .then(response => {
                    this.paymentMethodTypes = response.data?.payment_method_types || [];
                    this.tablesLoaded = true;
                })
                .catch(() => {
                    this.paymentMethodTypes = [];
                    this.tablesLoaded = true;
                });
        },
        buildActionRow() {
            return {
                id: this.record?.id,
                state_type_id: this.record?.state_type_id,
                state_type_description: this.record?.state_type_description
                    || this.record?.state_type?.description
                    || '',
                documents: this.record?.documents || [],
                sale_notes: this.record?.sale_notes || [],
                btn_generate: this.record?.btn_generate
            };
        },
        isEditableOrderState(row) {
            if (!row) {
                return false;
            }

            if (String(row.state_type_id) === '11') {
                return false;
            }

            if (EDITABLE_STATE_IDS.includes(String(row.state_type_id))) {
                return true;
            }

            const description = String(
                row.state_type_description || row.state_type?.description || ''
            ).toLowerCase();

            return description.includes('registrado') || description.includes('pendiente');
        },
        hasAssociatedVouchers(row) {
            if (!row) {
                return true;
            }

            if (row.btn_generate === false) {
                return true;
            }

            return (row.documents && row.documents.length > 0)
                || (row.sale_notes && row.sale_notes.length > 0);
        },
        clickAnulate() {
            if (!this.record?.id || !this.canAnulate) {
                return;
            }

            this.voiding = true;

            this.voided(`/${this.resource}/voided/${this.record.id}`)
                .then(() => {
                    this.visibleDrawer = false;
                    this.$eventHub.$emit('reloadData');
                })
                .finally(() => {
                    this.voiding = false;
                });
        },
        parseItemData(item) {
            if (!item) {
                return null;
            }

            if (typeof item === 'string') {
                try {
                    return JSON.parse(item);
                } catch (error) {
                    return { description: item };
                }
            }

            return item;
        },
        parseAmount(value) {
            if (value === undefined || value === null || value === '') {
                return 0;
            }

            if (typeof value === 'number') {
                return value;
            }

            return Number(String(value).replace(/,/g, '').trim()) || 0;
        },
        formatMoney(value, withSymbol = true) {
            const amount = this.parseAmount(value);
            const formatted = amount.toLocaleString('es-PE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            return withSymbol ? `${this.currencySymbol} ${formatted}` : formatted;
        },
        formatDisplayDate(value) {
            if (!value) {
                return '—';
            }

            const date = moment(value);
            return date.isValid() ? date.format('DD-MM-YYYY') : value;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.voiding = false;
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.order-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.order-detail-drawer__header {
    flex-shrink: 0;
    width: 100%;
    min-height: 56px;
    box-sizing: border-box;
    overflow: hidden;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    direction: ltr;
}

.order-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.order-detail-drawer__close {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    min-width: 32px;
    margin: 0;
    padding: 0;
    line-height: 1;
    border-radius: 6px;
    align-self: center;
}

.order-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.order-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.order-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.order-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.order-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.order-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.order-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.order-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.order-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.order-detail-drawer__list {
    margin: 0 0 8px;
}

.order-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.order-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.order-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.order-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.order-detail-drawer__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid #ebeef5;
    background: #fff;
}

.section-title {
    font-size: 1.05rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #1f3a8a;
    margin-bottom: 0.2rem;
}

.section-subtitle {
    color: #6b7280;
    font-size: 0.86rem;
    margin-bottom: 0.5rem;
}
</style>

<style>
.order-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.order-detail-drawer .theme-sidebar-header.order-detail-drawer__header {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    box-sizing: border-box;
    overflow: hidden;
    padding: 14px 16px;
    gap: 12px;
    direction: ltr;
}

.order-detail-drawer .order-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
