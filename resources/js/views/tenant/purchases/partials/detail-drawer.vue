<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="purchase-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="purchase-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header purchase-detail-drawer__header">
                <div class="purchase-detail-drawer__title">
                    <h4>Detalle de la compra</h4>
                    <small v-if="record">{{ purchaseIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar purchase-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="purchase-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            v-if="documentTypeLabel"
                            class="badge purchase-detail-drawer__doc-badge purchase-detail-drawer__doc-badge--default"
                        >
                            {{ documentTypeLabel }}
                        </span>
                        <span class="badge" :class="stateBadgeClass">
                            {{ stateLabel }}
                        </span>
                        <span
                            v-if="paymentStateLabel"
                            class="badge"
                            :class="paymentStateBadgeClass"
                        >
                            {{ paymentStateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="purchase-detail-drawer__body">
                    <div class="purchase-detail-drawer__section-card">
                        <div class="purchase-detail-drawer__section-card-header">
                            <h5 class="section-title">Productos</h5>
                            <p class="section-subtitle">Detalle de ítems incluidos en la compra</p>
                        </div>
                        <div v-if="lineItems.length" class="table-responsive">
                            <table class="table table-sm purchase-detail-drawer__items-table mb-0">
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

                    <div class="purchase-detail-drawer__section-card">
                        <div class="purchase-detail-drawer__section-card-header">
                            <h5 class="section-title">Proveedor</h5>
                            <p class="section-subtitle">Datos del proveedor asociado a la compra</p>
                        </div>
                        <dl class="purchase-detail-drawer__list">
                            <dt>Nombre / Razón social</dt>
                            <dd>{{ supplierName }}</dd>

                            <dt>Documento</dt>
                            <dd>{{ supplierDocument }}</dd>

                            <dt v-if="supplierTelephone">Teléfono</dt>
                            <dd v-if="supplierTelephone">{{ supplierTelephone }}</dd>

                            <dt v-if="supplierEmail">Correo</dt>
                            <dd v-if="supplierEmail">{{ supplierEmail }}</dd>
                        </dl>
                    </div>

                    <div class="purchase-detail-drawer__section-card">
                        <div class="purchase-detail-drawer__section-card-header">
                            <h5 class="section-title">Información operativa</h5>
                            <p class="section-subtitle">Establecimiento, almacén y condiciones de la compra</p>
                        </div>
                        <dl class="purchase-detail-drawer__list">
                            <dt>Establecimiento</dt>
                            <dd>{{ establishmentLabel }}</dd>

                            <dt>Almacén</dt>
                            <dd>{{ warehouseLabel }}</dd>

                            <dt>Condición de pago</dt>
                            <dd>{{ paymentConditionLabel }}</dd>

                            <dt v-if="record.purchase_order">Orden de compra</dt>
                            <dd v-if="record.purchase_order">
                                {{ record.purchase_order.prefix }}-{{ record.purchase_order.id }}
                            </dd>

                            <dt v-if="guideNumbers.length">Guías</dt>
                            <dd v-if="guideNumbers.length">{{ guideNumbers.join(', ') }}</dd>
                        </dl>
                    </div>

                    <div class="purchase-detail-drawer__section-card">
                        <div class="purchase-detail-drawer__section-card-header">
                            <h5 class="section-title">Totales y pagos</h5>
                            <p class="section-subtitle">Moneda, importes gravados, saldo e historial de pagos</p>
                        </div>
                        <dl class="purchase-detail-drawer__list">
                            <dt>Moneda</dt>
                            <dd>{{ currencyLabel }}</dd>

                            <dt>Gravado</dt>
                            <dd>{{ formatMoney(record.total_taxed) }}</dd>

                            <dt>IGV</dt>
                            <dd>{{ formatMoney(record.total_igv) }}</dd>

                            <dt v-if="parseAmount(record.total_perception) > 0">Percepción</dt>
                            <dd v-if="parseAmount(record.total_perception) > 0">{{ formatMoney(record.total_perception) }}</dd>

                            <dt>Total</dt>
                            <dd class="text-primary fw-bold">{{ formatMoney(totalAmount) }}</dd>

                            <dt>Saldo</dt>
                            <dd :class="{ 'text-warning fw-bold': balanceAmount > 0, 'text-success': balanceAmount === 0 }">
                                {{ formatMoney(balanceAmount) }}
                            </dd>
                        </dl>

                        <div v-if="paymentRows.length" class="table-responsive mt-2">
                            <table class="table table-sm purchase-detail-drawer__items-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="payment in paymentRows" :key="payment.id">
                                        <td>{{ formatDisplayDate(payment.date_of_payment) }}</td>
                                        <td>
                                            {{ payment.payment_method_type_description || '—' }}
                                            <small v-if="payment.reference" class="d-block text-muted">
                                                Ref: {{ payment.reference }}
                                            </small>
                                        </td>
                                        <td class="text-end">{{ formatMoney(payment.payment, false) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-muted small mb-0">Sin pagos registrados.</p>
                    </div>
                </div>

                <div class="purchase-detail-drawer__footer">
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar compra
                    </button>
                    <button
                        v-if="canGuide"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('guide', record.id)"
                    >
                        <i class="fa fa-truck"></i> Guía
                    </button>
                    <button
                        v-if="canPayments"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="$emit('payments', record.id)"
                    >
                        <i class="fa fa-money-bill-wave"></i> Pagos
                    </button>
                    <button
                        v-if="canAnulate"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="voiding"
                        @click="clickAnulate"
                    >
                        <i class="fa fa-times-circle"></i> Anular compra
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
import { deletable } from '@mixins/deletable';

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
            default: 'purchases'
        },
        permissions: {
            type: Object,
            default: () => ({})
        },
        disableGuideBtn: {
            type: Boolean,
            default: true
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
            record: null
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
        purchaseIdentifier() {
            const number = this.record?.number || `#${this.record?.id || ''}`;
            const docType = this.documentTypeLabel;

            return docType ? `${number} · ${docType}` : number;
        },
        documentTypeLabel() {
            return this.record?.document_type_description || null;
        },
        stateLabel() {
            return this.record?.state_type_description || '—';
        },
        stateBadgeClass() {
            const stateId = String(this.record?.state_type_id || '');

            if (stateId === '11') {
                return 'badge-danger';
            }

            if (stateId === '05') {
                return 'badge-success';
            }

            if (stateId === '13' || stateId === '07') {
                return 'badge-warning';
            }

            if (stateId === '03' || stateId === '01') {
                return 'badge-primary';
            }

            return 'badge-secondary';
        },
        paymentStateLabel() {
            return this.record?.state_type_payment_description || null;
        },
        paymentStateBadgeClass() {
            return this.paymentStateLabel === 'Pagado' ? 'badge-success' : 'badge-warning';
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        supplierName() {
            return this.record?.supplier_name || this.parseSupplier(this.record?.supplier)?.name || '—';
        },
        supplierDocument() {
            const supplier = this.parseSupplier(this.record?.supplier);
            const number = supplier?.number || this.record?.supplier_number || null;
            const type =
                supplier?.identity_document_type?.description
                || supplier?.document_type
                || this.record?.supplier_identity_document_type_description
                || null;

            if (number && type) {
                return `${number} (${type})`;
            }

            return number || '—';
        },
        supplierTelephone() {
            return this.record?.supplier_telephone
                || this.parseSupplier(this.record?.supplier)?.telephone
                || null;
        },
        supplierEmail() {
            return this.record?.supplier_email
                || this.parseSupplier(this.record?.supplier)?.email
                || null;
        },
        establishmentLabel() {
            const establishment = this.record?.establishment;

            if (!establishment) {
                if (this.record?.establishment_id) {
                    return `Establecimiento #${this.record.establishment_id}`;
                }

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
            if (Array.isArray(this.record?.warehouses) && this.record.warehouses.length) {
                return this.record.warehouses.map(warehouse => warehouse.description).join(', ');
            }

            const warehouseNames = new Set();

            this.lineItems.forEach(item => {
                if (item.warehouse_description) {
                    warehouseNames.add(item.warehouse_description);
                }
            });

            if (warehouseNames.size) {
                return Array.from(warehouseNames).join(', ');
            }

            return '—';
        },
        paymentConditionLabel() {
            const condition = this.record?.payment_condition;

            if (condition?.description) {
                return condition.description;
            }

            if (condition?.name) {
                return condition.name;
            }

            if (this.record?.payment_condition_id) {
                return `Condición #${this.record.payment_condition_id}`;
            }

            return '—';
        },
        guideNumbers() {
            const guides = Array.isArray(this.record?.guides) ? this.record.guides : [];

            return guides
                .map(guide => guide?.number)
                .filter(number => number !== undefined && number !== null && String(number).trim() !== '');
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
        totalAmount() {
            let total = this.parseAmount(this.record?.total);
            const perception = this.parseAmount(this.record?.total_perception);

            if (perception > 0) {
                total += perception;
            }

            return total;
        },
        totalPaid() {
            return this.paymentRows.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
        },
        balanceAmount() {
            if (this.record?.total_canceled || this.paymentStateLabel === 'Pagado') {
                return 0;
            }

            return Math.max(0, this.totalAmount - this.totalPaid);
        },
        paymentRows() {
            return Array.isArray(this.record?.payments) ? this.record.payments : [];
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
                    key: row.id || row.key || index,
                    description: this.resolveItemDescription(row, itemData),
                    quantity,
                    unit_price: unitPrice,
                    subtotal,
                    warehouse_description: row.warehouse?.description || null
                };
            });
        },
        canEdit() {
            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(this.buildActionRow());
            }

            return Boolean(this.permissions.edit_purchase) && String(this.record?.state_type_id) !== '11';
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            return Boolean(this.permissions.annular_purchase) && String(this.record?.state_type_id) !== '11';
        },
        canGuide() {
            return !this.disableGuideBtn && String(this.record?.state_type_id) !== '11';
        },
        canPayments() {
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
                payments: this.normalizePayments(this.initialRow.payments || [])
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
                    const purchase = data?.purchase;

                    if (!purchase) {
                        throw new Error('missing purchase');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const supplier = this.parseSupplier(purchase.supplier);

                    this.record = {
                        ...snapshot,
                        id: data.id || purchase.id,
                        external_id: data.external_id || purchase.external_id,
                        number: data.number || purchase.number_full || snapshot.number,
                        date_of_issue: data.date_of_issue || purchase.date_of_issue,
                        date_of_due: purchase.date_of_due || snapshot.date_of_due,
                        state_type_id: purchase.state_type_id ?? snapshot.state_type_id,
                        state_type_description: purchase.state_type?.description ?? snapshot.state_type_description,
                        document_type_description: purchase.document_type?.description ?? snapshot.document_type_description,
                        supplier,
                        supplier_name: supplier?.name || snapshot.supplier_name,
                        supplier_number: supplier?.number || snapshot.supplier_number,
                        supplier_telephone: supplier?.telephone || snapshot.supplier_telephone,
                        supplier_email: supplier?.email || snapshot.supplier_email,
                        establishment: purchase.establishment || purchase.relation_establishment,
                        establishment_id: purchase.establishment_id,
                        payment_condition: purchase.payment_condition,
                        payment_condition_id: purchase.payment_condition_id,
                        purchase_order: purchase.purchase_order || snapshot.purchase_order,
                        guides: purchase.guides || snapshot.guides || [],
                        warehouses: snapshot.warehouses || this.extractWarehousesFromItems(purchase.items),
                        currency_type_id: purchase.currency_type_id || snapshot.currency_type_id,
                        total_taxed: purchase.total_taxed ?? snapshot.total_taxed,
                        total_igv: purchase.total_igv ?? snapshot.total_igv,
                        total_perception: purchase.total_perception ?? snapshot.total_perception,
                        total: purchase.total ?? snapshot.total,
                        total_canceled: purchase.total_canceled ?? snapshot.total_canceled,
                        state_type_payment_description: purchase.total_canceled
                            ? 'Pagado'
                            : (snapshot.state_type_payment_description || 'Pendiente de pago'),
                        items: purchase.items || snapshot.items || [],
                        payments: this.normalizePayments(purchase.purchase_payments || snapshot.payments || [])
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle de la compra.');
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
                state_type_id: this.record?.state_type_id
            };
        },
        clickAnulate() {
            if (!this.record?.id || !this.canAnulate) {
                return;
            }

            this.voiding = true;

            this.anular(`/${this.resource}/anular/${this.record.id}`)
                .then(() => {
                    this.visibleDrawer = false;
                    this.$eventHub.$emit('reloadData');
                })
                .finally(() => {
                    this.voiding = false;
                });
        },
        normalizePayments(payments) {
            if (!Array.isArray(payments)) {
                return [];
            }

            return payments.map(payment => ({
                id: payment.id,
                date_of_payment: payment.date_of_payment,
                payment: payment.payment,
                reference: payment.reference,
                payment_method_type_description: payment.payment_method_type_description
                    || payment.payment_method_type?.description
                    || null
            }));
        },
        extractWarehousesFromItems(items) {
            if (!Array.isArray(items)) {
                return [];
            }

            const warehouses = new Map();

            items.forEach(item => {
                const warehouse = item?.warehouse;
                if (warehouse?.id) {
                    warehouses.set(warehouse.id, {
                        id: warehouse.id,
                        description: warehouse.description
                    });
                }
            });

            return Array.from(warehouses.values());
        },
        parseSupplier(value) {
            if (!value) {
                return null;
            }

            if (typeof value === 'string') {
                try {
                    return JSON.parse(value);
                } catch (error) {
                    return null;
                }
            }

            return value;
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
        resolveItemDescription(row, itemData) {
            if (row.name_product_pdf) {
                return this.stripHtml(row.name_product_pdf);
            }

            return itemData?.description || itemData?.name || row.description || '—';
        },
        stripHtml(value) {
            if (!value) {
                return '—';
            }

            return String(value).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || '—';
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
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.purchase-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.purchase-detail-drawer__header {
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

.purchase-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.purchase-detail-drawer__close {
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

.purchase-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.purchase-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.purchase-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.purchase-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.purchase-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.purchase-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.purchase-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.purchase-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.purchase-detail-drawer__doc-badge {
    font-size: 11px;
    font-weight: 600;
}

.purchase-detail-drawer__doc-badge--default {
    background-color: #eef2f7;
    border: 1px solid #cbd5e1;
    color: #475569;
}

.purchase-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.purchase-detail-drawer__list {
    margin: 0 0 8px;
}

.purchase-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.purchase-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.purchase-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.purchase-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.purchase-detail-drawer__footer {
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
.purchase-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.purchase-detail-drawer .theme-sidebar-header.purchase-detail-drawer__header {
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

.purchase-detail-drawer .purchase-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
