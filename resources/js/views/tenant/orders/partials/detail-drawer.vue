<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="520px"
        direction="rtl"
        custom-class="store-order-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="store-order-detail-drawer__inner">
            <div class="theme-sidebar-header store-order-detail-drawer__header">
                <div class="store-order-detail-drawer__title">
                    <h4>Detalle del pedido</h4>
                    <small v-if="record">{{ orderIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar store-order-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="store-order-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge" :class="orderStatusBadgeClass">
                            {{ orderStatusLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="store-order-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="store-order-detail-drawer__section-card">
                                <div class="store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en el pedido</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm store-order-detail-drawer__items-table mb-0">
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
                                                <td class="text-end">{{ item.priceLabel }}</td>
                                                <td class="text-end">{{ item.subtotalLabel }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin productos registrados.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Cliente" name="customer">
                            <div class="store-order-detail-drawer__section-card">
                                <div class="store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado al pedido</p>
                                </div>
                                <dl class="store-order-detail-drawer__list">
                                    <dt>Nombre / Razón social</dt>
                                    <dd>{{ record.customer || '—' }}</dd>

                                    <dt v-if="record.customer_telefono">Teléfono</dt>
                                    <dd v-if="record.customer_telefono">{{ record.customer_telefono }}</dd>

                                    <dt v-if="record.customer_email">Correo</dt>
                                    <dd v-if="record.customer_email">{{ record.customer_email }}</dd>

                                    <dt v-if="record.customer_direccion">Dirección</dt>
                                    <dd v-if="record.customer_direccion">{{ record.customer_direccion }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Resumen" name="summary">
                            <div class="store-order-detail-drawer__section-card">
                                <div class="store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Resumen</h5>
                                    <p class="section-subtitle">Información operativa y estados del pedido</p>
                                </div>
                                <dl class="store-order-detail-drawer__list">
                                    <dt>N° Pedido</dt>
                                    <dd>{{ orderIdentifier }}</dd>

                                    <dt>Fecha de emisión</dt>
                                    <dd>{{ issueDateLabel }}</dd>

                                    <dt>Medio de pago</dt>
                                    <dd>{{ record.reference_payment || '—' }}</dd>

                                    <dt>Estado de pago</dt>
                                    <dd>{{ paymentStatusLabel }}</dd>

                                    <dt>Estado de envío</dt>
                                    <dd>{{ shippingStatusLabel }}</dd>

                                    <dt>Estado de pedido</dt>
                                    <dd>{{ orderStatusLabel }}</dd>

                                    <dt v-if="documentLabel">Documento</dt>
                                    <dd v-if="documentLabel">{{ documentLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Totales" name="totals">
                            <div class="store-order-detail-drawer__section-card">
                                <div class="store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Totales</h5>
                                    <p class="section-subtitle">Importes del pedido</p>
                                </div>
                                <dl class="store-order-detail-drawer__list">
                                    <dt v-if="hasDiscount">Descuento</dt>
                                    <dd v-if="hasDiscount">{{ discountLabel }}</dd>

                                    <dt>Total</dt>
                                    <dd class="text-primary fw-bold">{{ totalLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="store-order-detail-drawer__footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="visibleDrawer = false"
                    >
                        Cerrar
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
export default {
    props: {
        showDrawer: {
            type: Boolean,
            default: false
        },
        record: {
            type: Object,
            default: null
        },
        statusOptions: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
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
        orderIdentifier() {
            if (!this.record) {
                return '';
            }
            return this.record.order_id
                || (this.record.id ? String(this.record.id).padStart(6, '0') : '');
        },
        issueDateLabel() {
            if (!this.record || !this.record.created_at) {
                return '—';
            }
            const parsed = moment(this.record.created_at);
            return parsed.isValid()
                ? parsed.format('DD-MM-YYYY h:mmA')
                : this.record.created_at;
        },
        paymentStatusLabel() {
            return this.statusLabel(this.record && this.record.payment_status_order_id);
        },
        shippingStatusLabel() {
            const id = this.record && this.record.shipping_status_order_id;
            if (!id) {
                return 'Sin envío';
            }
            return this.statusLabel(id);
        },
        orderStatusLabel() {
            return this.statusLabel(this.record && this.record.status_order_id);
        },
        orderStatusBadgeClass() {
            if (this.isVoided) {
                return 'badge-danger';
            }
            return 'badge-success';
        },
        isVoided() {
            if (!this.record || !this.statusOptions.length) {
                return false;
            }
            const ids = [
                this.record.status_order_id,
                this.record.payment_status_order_id,
                this.record.shipping_status_order_id
            ];
            return this.statusOptions.some(
                option => ids.includes(option.id) && option.action_void_order
            );
        },
        documentLabel() {
            if (!this.record) {
                return null;
            }
            if (String(this.record.document_type_id) === '80') {
                return this.record.sale_note_number_full || null;
            }
            return this.record.number_document || null;
        },
        currencySymbol() {
            const first = this.record && Array.isArray(this.record.items)
                ? this.record.items[0]
                : null;
            if (first && first.currency_type && first.currency_type.symbol) {
                return first.currency_type.symbol;
            }
            if (first && first.currency_type_id === 'USD') {
                return '$';
            }
            return 'S/';
        },
        lineItems() {
            const items = Array.isArray(this.record && this.record.items)
                ? this.record.items
                : [];

            return items.map((item, index) => {
                const quantity = Number(item.cantidad || item.quantity || 0);
                const unitPrice = Number(item.sale_unit_price || item.unit_price || 0);
                let subtotal = item.sub_total !== undefined && item.sub_total !== null
                    ? Number(item.sub_total)
                    : null;

                if (subtotal === null || Number.isNaN(subtotal)) {
                    if (item.currency_type_id === 'USD' && item.exchange_rate_sale) {
                        subtotal = quantity * Number(item.exchange_rate_sale) * unitPrice;
                    } else {
                        subtotal = quantity * unitPrice;
                    }
                }

                return {
                    key: item.id || index,
                    description: item.description || item.name || '—',
                    quantity,
                    priceLabel: `${this.itemSymbol(item)} ${unitPrice.toFixed(2)}`,
                    subtotalLabel: `${this.currencySymbol} ${Number(subtotal).toFixed(2)}`
                };
            });
        },
        hasDiscount() {
            const discount = Number(this.record && (this.record.total_discount || this.record.discount_coupont) || 0);
            return discount > 0;
        },
        discountLabel() {
            const discount = Number(this.record.total_discount || this.record.discount_coupont || 0);
            return `${this.currencySymbol} ${discount.toFixed(2)}`;
        },
        totalLabel() {
            const total = Number(this.record && this.record.total || 0);
            return `${this.currencySymbol} ${total.toFixed(2)}`;
        }
    },
    methods: {
        statusLabel(id) {
            if (!id) {
                return '—';
            }
            const status = this.statusOptions.find(option => option.id === id);
            return status && status.description ? status.description : '—';
        },
        itemSymbol(item) {
            if (item && item.currency_type && item.currency_type.symbol) {
                return item.currency_type.symbol;
            }
            return item && item.currency_type_id === 'USD' ? '$' : 'S/';
        },
        handleClosed() {
            this.activeTab = 'products';
            this.$emit('update:showDrawer', false);
        }
    }
};
</script>

<style scoped>
.store-order-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.store-order-detail-drawer__header {
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

.store-order-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.store-order-detail-drawer__close {
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

.store-order-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.store-order-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.store-order-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.store-order-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.store-order-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.store-order-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.store-order-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.store-order-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.store-order-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.store-order-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.store-order-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.store-order-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.store-order-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.store-order-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.store-order-detail-drawer__list {
    margin: 0 0 8px;
}

.store-order-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.store-order-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.store-order-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.store-order-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.store-order-detail-drawer__footer {
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
.store-order-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.store-order-detail-drawer .theme-sidebar-header.store-order-detail-drawer__header {
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

.store-order-detail-drawer .store-order-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
