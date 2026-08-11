<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="sale-note-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="sale-note-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header sale-note-detail-drawer__header">
                <div class="sale-note-detail-drawer__title">
                    <h4>Detalle de la nota de venta</h4>
                    <small v-if="record">{{ saleNoteIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar sale-note-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="sale-note-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge"
                            :class="stateBadgeClass"
                        >
                            {{ stateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="sale-note-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="sale-note-detail-drawer__section-card">
                                <div class="sale-note-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en la nota de venta</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm sale-note-detail-drawer__items-table mb-0">
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
                        </el-tab-pane>

                        <el-tab-pane label="Cliente" name="customer">
                            <div class="sale-note-detail-drawer__section-card">
                                <div class="sale-note-detail-drawer__section-card-header">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado a la nota de venta</p>
                                </div>
                                <dl class="sale-note-detail-drawer__list">
                                    <dt>Nombre / Razón social</dt>
                                    <dd>{{ customerName }}</dd>

                                    <dt>Documento</dt>
                                    <dd>{{ customerDocument }}</dd>

                                    <dt v-if="customerTelephone">Teléfono</dt>
                                    <dd v-if="customerTelephone">{{ customerTelephone }}</dd>

                                    <dt v-if="customerEmail">Correo</dt>
                                    <dd v-if="customerEmail">{{ customerEmail }}</dd>

                                    <dt v-if="customerAddress">Dirección</dt>
                                    <dd v-if="customerAddress">{{ customerAddress }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="sale-note-detail-drawer__section-card">
                                <div class="sale-note-detail-drawer__section-card-header">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Asignación comercial y origen del inventario</p>
                                </div>
                                <dl class="sale-note-detail-drawer__list">
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
                        </el-tab-pane>

                        <el-tab-pane label="Pagos y totales" name="totals">
                            <div class="sale-note-detail-drawer__section-card">
                                <div class="sale-note-detail-drawer__section-card-header">
                                    <h5 class="section-title">Pagos y totales</h5>
                                    <p class="section-subtitle">Condiciones de pago e importes de la nota de venta</p>
                                </div>
                                <dl class="sale-note-detail-drawer__list">
                                    <dt>Condición de pago</dt>
                                    <dd>{{ paymentConditionLabel }}</dd>

                                    <dt>Moneda</dt>
                                    <dd>{{ currencyLabel }}</dd>

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
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="sale-note-detail-drawer__footer">
                    <button
                        v-if="canAnulate"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="voiding"
                        @click="clickAnulate"
                    >
                        <i class="fa fa-trash"></i> Anular nota de venta
                    </button>
                    <button
                        v-if="canPrint"
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="$emit('print', record.id)"
                    >
                        <i class="fa fa-print"></i> Imprimir
                    </button>
                    <button
                        v-if="canGenerateDocument"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('generate-document', record.id)"
                    >
                        <i class="fa fa-file-text"></i> Generar comprobante
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar nota de venta
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
import { deletable } from '@mixins/deletable';

const PAYMENT_CONDITIONS = {
    '01': 'Contado',
    '02': 'Crédito',
    '03': 'Crédito con cuotas'
};

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
            default: 'sale-notes'
        },
        canEditRow: {
            type: Function,
            default: null
        },
        canAnulateRow: {
            type: Function,
            default: null
        },
        canGenerateRow: {
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
            tablesLoaded: false,
            activeTab: 'products'
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
        saleNoteIdentifier() {
            return this.record?.full_number
                || this.record?.identifier
                || (this.record?.serie && this.record?.number
                    ? `${this.record.serie}-${this.record.number}`
                    : null)
                || `#${this.record?.id || ''}`;
        },
        stateLabel() {
            return this.record?.state_type_description
                || this.record?.state_type?.description
                || '—';
        },
        stateBadgeClass() {
            const stateId = String(this.record?.state_type_id || '');

            if (stateId === '11' || stateId === '09') {
                return 'badge-danger';
            }

            if (stateId === '05') {
                return 'badge-success';
            }

            if (stateId === '07' || stateId === '13') {
                return 'badge-warning';
            }

            if (stateId === '03') {
                return 'badge-primary';
            }

            return 'badge-info';
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        customerName() {
            return this.record?.customer?.name
                || this.record?.customer_name
                || '—';
        },
        customerDocument() {
            const customer = this.record?.customer;
            const number = customer?.number || this.record?.customer_number || null;
            const type =
                customer?.identity_document_type?.description
                || customer?.document_type
                || this.record?.customer_identity_document_type_description
                || null;

            if (number && type) {
                return `${number} (${type})`;
            }

            if (number) {
                return number;
            }

            return '—';
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
        customerAddress() {
            const customer = this.record?.customer;
            return customer?.address
                || customer?.direccion
                || this.record?.consigned_address
                || null;
        },
        sellerLabel() {
            return this.record?.seller?.name
                || this.record?.seller_name
                || this.record?.user?.name
                || this.record?.user_name
                || '—';
        },
        establishmentLabel() {
            const establishment = this.record?.establishment;
            if (!establishment) {
                return '—';
            }

            if (typeof establishment === 'string') {
                try {
                    const parsed = JSON.parse(establishment);
                    return parsed.description || parsed.address || '—';
                } catch (error) {
                    return establishment;
                }
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
            const paymentConditionId = this.record?.payment_condition_id;
            if (paymentConditionId && PAYMENT_CONDITIONS[paymentConditionId]) {
                return PAYMENT_CONDITIONS[paymentConditionId];
            }

            if (this.record?.payment_method_type?.description) {
                return this.record.payment_method_type.description;
            }

            const paymentType = this.paymentMethodTypes.find(
                option => String(option.id) === String(this.record?.payment_method_type_id)
            );

            return paymentType ? paymentType.description : '—';
        },
        balanceAmount() {
            if (this.record?.total_pending_paid !== undefined && this.record?.total_pending_paid !== null) {
                return this.parseAmount(this.record.total_pending_paid);
            }

            const total = this.parseAmount(this.record?.total);
            return Math.max(total - this.totalPayments, 0);
        },
        totalPayments() {
            if (this.record?.total_paid !== undefined && this.record?.total_paid !== null) {
                return this.parseAmount(this.record.total_paid);
            }

            const payments = Array.isArray(this.record?.payments) ? this.record.payments : [];
            return payments.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
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
                    description: itemData?.description || itemData?.name || row.description || '—',
                    quantity,
                    unit_price: unitPrice,
                    subtotal,
                    warehouse_id: row.warehouse_id || itemData?.warehouse_id || null
                };
            });
        },
        canEdit() {
            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(this.buildActionRow());
            }

            const row = this.buildActionRow();
            return row.btn_generate && String(row.state_type_id) !== '11';
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            return String(this.record?.state_type_id) !== '11';
        },
        canPrint() {
            return String(this.record?.state_type_id) !== '11';
        },
        canGenerateDocument() {
            if (typeof this.canGenerateRow === 'function') {
                return this.canGenerateRow(this.buildActionRow());
            }

            const row = this.buildActionRow();
            return !row.changed && String(row.state_type_id) !== '11';
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
            this.activeTab = 'products';
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
                full_number: this.initialRow.full_number,
                identifier: this.initialRow.identifier,
                customer_name: this.initialRow.customer_name,
                customer_number: this.initialRow.customer_number,
                customer_telephone: this.initialRow.customer_telephone,
                customer_email: this.initialRow.customer_email,
                seller_name: this.initialRow.seller_name,
                user_name: this.initialRow.user_name,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                total_paid: this.initialRow.total_paid,
                total_pending_paid: this.initialRow.total_pending_paid,
                documents: this.initialRow.documents || [],
                btn_generate: this.initialRow.btn_generate,
                changed: this.initialRow.changed
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

                    const data = response.data?.data;
                    if (!data) {
                        throw new Error('missing sale note');
                    }

                    const saleNote = data.sale_note || data;
                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const customer = saleNote.customer || data.customer || snapshot.customer;

                    this.record = {
                        ...snapshot,
                        ...data,
                        ...saleNote,
                        customer,
                        full_number: data.full_number || saleNote.full_number || snapshot.full_number,
                        identifier: saleNote.identifier || data.identifier || snapshot.identifier,
                        state_type_description: saleNote.state_type?.description
                            || data.state_type_description
                            || snapshot.state_type_description,
                        state_type_id: saleNote.state_type_id ?? data.state_type_id ?? snapshot.state_type_id,
                        seller_name: data.seller_name || saleNote.seller?.name || snapshot.seller_name,
                        user_name: data.user_name || saleNote.user?.name || snapshot.user_name,
                        customer_name: data.customer_name || customer?.name || snapshot.customer_name,
                        customer_number: data.customer_number || customer?.number || snapshot.customer_number,
                        customer_telephone: data.customer_telephone || customer?.telephone || snapshot.customer_telephone,
                        customer_email: data.customer_email || customer?.email || snapshot.customer_email,
                        total_paid: data.total_paid ?? snapshot.total_paid,
                        total_pending_paid: data.total_pending_paid ?? snapshot.total_pending_paid,
                        payments: data.payments || saleNote.payments || [],
                        items: saleNote.items || data.items || [],
                        documents: data.documents || snapshot.documents || [],
                        btn_generate: data.btn_generate !== undefined
                            ? data.btn_generate
                            : snapshot.btn_generate,
                        changed: data.changed !== undefined
                            ? data.changed
                            : snapshot.changed,
                        establishment: saleNote.establishment || data.establishment,
                        payment_condition_id: saleNote.payment_condition_id || data.payment_condition_id,
                        payment_method_type_id: saleNote.payment_method_type_id || data.payment_method_type_id,
                        payment_method_type: saleNote.payment_method_type || data.payment_method_type,
                        observation: saleNote.observation || data.observation
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle de la nota de venta.');
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
                btn_generate: this.record?.btn_generate,
                changed: this.record?.changed
            };
        },
        clickAnulate() {
            if (!this.record?.id || !this.canAnulate) {
                return;
            }

            this.voiding = true;

            this.anular(`/${this.resource}/anulate/${this.record.id}`)
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
            this.activeTab = 'products';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.sale-note-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.sale-note-detail-drawer__header {
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

.sale-note-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.sale-note-detail-drawer__close {
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

.sale-note-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.sale-note-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sale-note-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.sale-note-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.sale-note-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.sale-note-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.sale-note-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.sale-note-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.sale-note-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.sale-note-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.sale-note-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.sale-note-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.sale-note-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.sale-note-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.sale-note-detail-drawer__list {
    margin: 0 0 8px;
}

.sale-note-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.sale-note-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.sale-note-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.sale-note-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.sale-note-detail-drawer__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
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
.sale-note-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.sale-note-detail-drawer .theme-sidebar-header.sale-note-detail-drawer__header {
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

.sale-note-detail-drawer .sale-note-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
