<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="520px"
        direction="rtl"
        custom-class="detail-drawer store-order-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner store-order-detail-drawer__inner">
            <div class="theme-sidebar-header detail-drawer__header store-order-detail-drawer__header">
                <div class="detail-drawer__title store-order-detail-drawer__title">
                    <h5>Detalle del pedido</h5>
                    <small v-if="record">{{ orderIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar store-order-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge" :class="orderStatusBadgeClass">
                            {{ orderStatusLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body store-order-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="detail-drawer__section-card store-order-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en el pedido</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table store-order-detail-drawer__items-table mb-0">
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
                            <div class="detail-drawer__section-card store-order-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado al pedido</p>
                                </div>
                                <dl class="detail-drawer__list store-order-detail-drawer__list">
                                    <dt>Nombre / Razón social</dt>
                                    <dd>{{ record.customer || '—' }}</dd>

                                    <dt v-if="customerDocumentLabel">Documento</dt>
                                    <dd v-if="customerDocumentLabel">{{ customerDocumentLabel }}</dd>

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
                            <div class="detail-drawer__section-card store-order-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Resumen</h5>
                                    <p class="section-subtitle">Información operativa y estados del pedido</p>
                                </div>
                                <dl class="detail-drawer__list store-order-detail-drawer__list">
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
                            <div class="detail-drawer__section-card store-order-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header store-order-detail-drawer__section-card-header">
                                    <h5 class="section-title">Totales</h5>
                                    <p class="section-subtitle">Importes del pedido</p>
                                </div>
                                <dl class="detail-drawer__list store-order-detail-drawer__list">
                                    <dt v-if="hasDiscount">Descuento</dt>
                                    <dd v-if="hasDiscount">{{ discountLabel }}</dd>

                                    <dt>Total</dt>
                                    <dd class="text-primary fw-bold">{{ totalLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions store-order-detail-drawer__footer">
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
        customerDocumentLabel() {
            if (!this.record) {
                return null;
            }

            const number = String(this.record.customer_document_number || '').trim();
            if (!number || number === '0') {
                return null;
            }

            const type = String(this.record.customer_document_type || '').trim();
            return type ? `${number} (${type})` : number;
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
            return 'Bs.';
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
            return item && item.currency_type_id === 'USD' ? '$' : 'Bs.';
        },
        handleClosed() {
            this.activeTab = 'products';
            this.$emit('update:showDrawer', false);
        }
    }
};
</script>
