<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="contract-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="contract-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header contract-detail-drawer__header">
                <div class="contract-detail-drawer__title">
                    <h4>Detalle del contrato</h4>
                    <small v-if="record">{{ contractIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar contract-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="contract-detail-drawer__status-bar">
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

                <div class="contract-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="contract-detail-drawer__section-card">
                                <div class="contract-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en el contrato</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm contract-detail-drawer__items-table mb-0">
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
                            <div class="contract-detail-drawer__section-card">
                                <div class="contract-detail-drawer__section-card-header">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado al contrato</p>
                                </div>
                                <dl class="contract-detail-drawer__list">
                                    <dt>Nombre / Razón social</dt>
                                    <dd>{{ customerName }}</dd>

                                    <dt>Documento</dt>
                                    <dd>{{ customerDocument }}</dd>

                                    <dt v-if="customerTelephone">Teléfono</dt>
                                    <dd v-if="customerTelephone">{{ customerTelephone }}</dd>

                                    <dt v-if="customerEmail">Correo</dt>
                                    <dd v-if="customerEmail">{{ customerEmail }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="contract-detail-drawer__section-card">
                                <div class="contract-detail-drawer__section-card-header">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Asignación comercial y origen del inventario</p>
                                </div>
                                <dl class="contract-detail-drawer__list">
                                    <dt>Vendedor</dt>
                                    <dd>{{ sellerLabel }}</dd>

                                    <dt>Establecimiento</dt>
                                    <dd>{{ establishmentLabel }}</dd>

                                    <dt>Almacén / origen</dt>
                                    <dd>{{ warehouseLabel }}</dd>

                                    <dt v-if="record.quotation_number_full">Cotización origen</dt>
                                    <dd v-if="record.quotation_number_full">{{ record.quotation_number_full }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Totales" name="totals">
                            <div class="contract-detail-drawer__section-card">
                                <div class="contract-detail-drawer__section-card-header">
                                    <h5 class="section-title">Totales</h5>
                                    <p class="section-subtitle">Moneda e importes del contrato</p>
                                </div>
                                <dl class="contract-detail-drawer__list">
                                    <dt>Moneda</dt>
                                    <dd>{{ currencyLabel }}</dd>

                                    <dt>Gravado</dt>
                                    <dd>{{ formatMoney(record.total_taxed) }}</dd>

                                    <dt>IGV</dt>
                                    <dd>{{ formatMoney(record.total_igv) }}</dd>

                                    <dt>Total</dt>
                                    <dd class="text-primary fw-bold">{{ formatMoney(record.total) }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="contract-detail-drawer__footer">
                    <button
                        v-if="canAnulate"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="voiding"
                        @click="clickAnulate"
                    >
                        <i class="fa fa-trash"></i> Anular contrato
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar contrato
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
            default: 'contracts'
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
        contractIdentifier() {
            return this.record?.number_full
                || this.record?.identifier
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
        deliveryDateLabel() {
            const date = this.record?.delivery_date;
            return date ? this.formatDisplayDate(date) : null;
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
            return this.record?.customer_telephone
                || this.record?.customer?.telephone
                || null;
        },
        customerEmail() {
            return this.record?.customer_email
                || this.record?.customer?.email
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
            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(this.buildActionRow());
            }

            return this.isEditableContractState(this.buildActionRow());
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            return String(this.record?.state_type_id) !== '11';
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
                number_full: this.initialRow.number_full,
                customer_name: this.initialRow.customer_name,
                customer_number: this.initialRow.customer_number,
                user_name: this.initialRow.user_name,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                quotation_number_full: this.initialRow.quotation_number_full
            };
        },
        loadRecord() {
            const requestedId = this.recordId;
            if (!requestedId) {
                return;
            }

            this.loading = true;

            this.$http.get(`/${this.resource}/record/${requestedId}`)
                .then(response => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    const data = response.data?.data;
                    const contract = data?.contract;
                    const customer = data?.customer;

                    if (!contract) {
                        throw new Error('missing contract');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    this.record = {
                        ...snapshot,
                        ...contract,
                        customer: customer || contract.customer || snapshot.customer,
                        number_full: data.number_full || contract.number_full || snapshot.number_full,
                        state_type_description: contract.state_type?.description
                            || snapshot.state_type_description,
                        state_type_id: contract.state_type_id ?? snapshot.state_type_id,
                        seller_name: contract.seller?.name || snapshot.user_name,
                        user_name: contract.user?.name || snapshot.user_name,
                        customer_name: customer?.name || contract.customer?.name || snapshot.customer_name,
                        customer_number: customer?.number || contract.customer?.number || snapshot.customer_number,
                        customer_telephone: data.customer_telephone || customer?.telephone || snapshot.customer_telephone,
                        customer_email: data.customer_email || customer?.email || snapshot.customer_email,
                        quotation_number_full: snapshot.quotation_number_full || contract.quotation?.number_full,
                        items: contract.items || []
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del contrato.');
                        this.visibleDrawer = false;
                    }
                })
                .finally(() => {
                    if (String(requestedId) === String(this.recordId)) {
                        this.loading = false;
                    }
                });
        },
        buildActionRow() {
            return {
                id: this.record?.id,
                state_type_id: this.record?.state_type_id,
                state_type_description: this.record?.state_type_description
                    || this.record?.state_type?.description
                    || ''
            };
        },
        isEditableContractState(row) {
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

            const date = moment(value, ['DD-MM-YYYY', 'YYYY-MM-DD', moment.ISO_8601], true);
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
.contract-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.contract-detail-drawer__header {
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

.contract-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.contract-detail-drawer__close {
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

.contract-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.contract-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.contract-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.contract-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.contract-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.contract-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.contract-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.contract-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.contract-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.contract-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.contract-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.contract-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.contract-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.contract-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.contract-detail-drawer__list {
    margin: 0 0 8px;
}

.contract-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.contract-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.contract-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.contract-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.contract-detail-drawer__footer {
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
.contract-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.contract-detail-drawer .theme-sidebar-header.contract-detail-drawer__header {
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

.contract-detail-drawer .contract-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
