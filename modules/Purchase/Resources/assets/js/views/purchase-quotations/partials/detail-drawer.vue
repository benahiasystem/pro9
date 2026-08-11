<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="purchase-quotation-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="purchase-quotation-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header purchase-quotation-detail-drawer__header">
                <div class="purchase-quotation-detail-drawer__title">
                    <h4>Detalle de la solicitud</h4>
                    <small v-if="record">{{ quotationIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar purchase-quotation-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="purchase-quotation-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge" :class="stateBadgeClass">
                            {{ stateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="purchase-quotation-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="purchase-quotation-detail-drawer__section-card">
                                <div class="purchase-quotation-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems solicitados en la cotización</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm purchase-quotation-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th class="text-center">Unidad</th>
                                                <th class="text-end">Cant.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in lineItems" :key="item.key || index">
                                                <td>{{ item.description }}</td>
                                                <td class="text-center">{{ item.unit }}</td>
                                                <td class="text-end">{{ item.quantity }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin productos registrados.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane
                            :label="supplierRows.length > 1 ? 'Proveedores' : 'Proveedor'"
                            name="supplier"
                        >
                            <div class="purchase-quotation-detail-drawer__section-card">
                                <div class="purchase-quotation-detail-drawer__section-card-header">
                                    <h5 class="section-title">Proveedor{{ supplierRows.length > 1 ? 'es' : '' }}</h5>
                                    <p class="section-subtitle">
                                        Datos del{{ supplierRows.length > 1 ? 's' : '' }} proveedor{{ supplierRows.length > 1 ? 'es' : '' }} asociado{{ supplierRows.length > 1 ? 's' : '' }} a la solicitud
                                    </p>
                                </div>

                                <template v-if="supplierRows.length">
                                    <div
                                        v-for="(supplier, index) in supplierRows"
                                        :key="supplier.key || index"
                                        class="purchase-quotation-detail-drawer__supplier"
                                        :class="{ 'purchase-quotation-detail-drawer__supplier--bordered': index > 0 }"
                                    >
                                        <dl class="purchase-quotation-detail-drawer__list mb-0">
                                            <dt>Nombre / Razón social</dt>
                                            <dd>{{ supplier.name }}</dd>

                                            <dt>Documento</dt>
                                            <dd>{{ supplier.document }}</dd>

                                            <dt v-if="supplier.email">Correo</dt>
                                            <dd v-if="supplier.email">{{ supplier.email }}</dd>

                                            <dt v-if="supplier.telephone">Teléfono</dt>
                                            <dd v-if="supplier.telephone">{{ supplier.telephone }}</dd>
                                        </dl>
                                    </div>
                                </template>
                                <p v-else class="text-muted small mb-0">Sin proveedores registrados.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="purchase-quotation-detail-drawer__section-card">
                                <div class="purchase-quotation-detail-drawer__section-card-header">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Establecimiento, estado y origen de la solicitud</p>
                                </div>
                                <dl class="purchase-quotation-detail-drawer__list">
                                    <dt>Establecimiento</dt>
                                    <dd>{{ establishmentLabel }}</dd>

                                    <dt>Registrado por</dt>
                                    <dd>{{ userLabel }}</dd>

                                    <dt>Estado</dt>
                                    <dd>{{ stateLabel }}</dd>

                                    <dt v-if="hasPurchaseOrdersLabel">Órdenes de compra</dt>
                                    <dd v-if="hasPurchaseOrdersLabel">{{ hasPurchaseOrdersLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="purchase-quotation-detail-drawer__footer">
                    <button
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="clickDownloadPdf"
                    >
                        <i class="fa fa-file-pdf"></i> Descargar PDF
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar
                    </button>
                    <button
                        v-if="canGenerate"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="$emit('generate', record.id)"
                    >
                        <i class="fa fa-shopping-bag"></i> Generar OC
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('options', record.id)"
                    >
                        <i class="fa fa-cog"></i> Opciones
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
            default: 'purchase-quotations'
        }
    },
    data() {
        return {
            loading: false,
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
        quotationIdentifier() {
            return this.record?.identifier
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
        lineItems() {
            const items = Array.isArray(this.record?.items) ? this.record.items : [];

            return items.map((row, index) => {
                const itemData = this.parseItemData(row.item);

                return {
                    key: row.id || index,
                    description: itemData?.description || itemData?.name || '—',
                    unit: itemData?.unit_type_id || itemData?.unit_type?.description || '—',
                    quantity: Number(row.quantity || 0)
                };
            });
        },
        supplierRows() {
            return this.normalizeSuppliers(this.record?.suppliers).map((supplier, index) => {
                const number = supplier.number || null;
                const type =
                    supplier.identity_document_type_description
                    || supplier.identity_document_type?.description
                    || supplier.document_type
                    || this.inferDocumentType(number)
                    || null;

                let document = '—';
                if (number && type) {
                    document = `${number} (${type})`;
                } else if (number) {
                    document = number;
                }

                return {
                    key: supplier.supplier_id || index,
                    name: supplier.name || supplier.description || '—',
                    document,
                    email: supplier.email || null,
                    telephone: supplier.telephone || null
                };
            });
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
        userLabel() {
            return this.record?.user?.name
                || this.record?.user_name
                || '—';
        },
        hasPurchaseOrders() {
            if (typeof this.record?.has_purchase_orders === 'boolean') {
                return this.record.has_purchase_orders;
            }

            if (Array.isArray(this.record?.purchase_orders)) {
                return this.record.purchase_orders.length > 0;
            }

            return false;
        },
        hasPurchaseOrdersLabel() {
            if (!this.hasPurchaseOrders) {
                return null;
            }

            const count = Array.isArray(this.record?.purchase_orders)
                ? this.record.purchase_orders.length
                : null;

            return count ? `${count} generada${count === 1 ? '' : 's'}` : 'Sí';
        },
        canEdit() {
            return !this.hasPurchaseOrders;
        },
        canGenerate() {
            return !this.hasPurchaseOrders;
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
                identifier: this.initialRow.identifier,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                has_purchase_orders: this.initialRow.has_purchase_orders
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
                    const purchaseQuotation = data?.purchase_quotation;

                    if (!purchaseQuotation) {
                        throw new Error('missing purchase quotation');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const suppliers = data?.suppliers
                        || purchaseQuotation.suppliers
                        || snapshot.suppliers
                        || [];

                    this.record = {
                        ...snapshot,
                        ...purchaseQuotation,
                        id: data.id || purchaseQuotation.id,
                        external_id: data.external_id || purchaseQuotation.external_id,
                        identifier: data.identifier || purchaseQuotation.identifier || snapshot.identifier,
                        date_of_issue: data.date_of_issue || purchaseQuotation.date_of_issue,
                        state_type_id: purchaseQuotation.state_type_id ?? snapshot.state_type_id,
                        state_type_description: purchaseQuotation.state_type?.description
                            || snapshot.state_type_description,
                        state_type: purchaseQuotation.state_type || snapshot.state_type,
                        suppliers,
                        items: purchaseQuotation.items || [],
                        establishment: purchaseQuotation.establishment || snapshot.establishment,
                        user: purchaseQuotation.user || snapshot.user,
                        user_name: purchaseQuotation.user?.name || snapshot.user_name,
                        has_purchase_orders: typeof data.has_purchase_orders === 'boolean'
                            ? data.has_purchase_orders
                            : (
                                Array.isArray(purchaseQuotation.purchase_orders)
                                    ? purchaseQuotation.purchase_orders.length > 0
                                    : Boolean(snapshot.has_purchase_orders)
                            ),
                        purchase_orders: purchaseQuotation.purchase_orders || snapshot.purchase_orders || []
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle de la solicitud de cotización.');
                        this.visibleDrawer = false;
                    }
                })
                .finally(() => {
                    if (String(requestedId) === String(this.recordId)) {
                        this.loading = false;
                    }
                });
        },
        clickDownloadPdf() {
            if (!this.record?.external_id) {
                return;
            }

            window.open(`/${this.resource}/download/${this.record.external_id}`, '_blank');
        },
        normalizeSuppliers(value) {
            if (!value) {
                return [];
            }

            if (Array.isArray(value)) {
                return value.filter(Boolean);
            }

            if (typeof value === 'object') {
                return Object.values(value).filter(Boolean);
            }

            if (typeof value === 'string') {
                try {
                    return this.normalizeSuppliers(JSON.parse(value));
                } catch (error) {
                    return [];
                }
            }

            return [];
        },
        inferDocumentType(number) {
            if (!number) {
                return null;
            }

            const digits = String(number).replace(/\D/g, '');

            if (digits.length === 11) {
                return 'RUC';
            }

            if (digits.length === 8) {
                return 'DNI';
            }

            return null;
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
        formatDisplayDate(value) {
            if (!value || value === '-') {
                return '—';
            }

            const date = moment(value, ['DD-MM-YYYY', 'YYYY-MM-DD', moment.ISO_8601], true);
            return date.isValid() ? date.format('DD-MM-YYYY') : value;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.activeTab = 'products';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.purchase-quotation-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.purchase-quotation-detail-drawer__header {
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

.purchase-quotation-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.purchase-quotation-detail-drawer__close {
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

.purchase-quotation-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.purchase-quotation-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.purchase-quotation-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.purchase-quotation-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.purchase-quotation-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.purchase-quotation-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.purchase-quotation-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.purchase-quotation-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.purchase-quotation-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.purchase-quotation-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.purchase-quotation-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.purchase-quotation-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.purchase-quotation-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.purchase-quotation-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.purchase-quotation-detail-drawer__list {
    margin: 0 0 8px;
}

.purchase-quotation-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.purchase-quotation-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.purchase-quotation-detail-drawer__list dd:last-child {
    margin-bottom: 0;
}

.purchase-quotation-detail-drawer__supplier--bordered {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #eef2f7;
}

.purchase-quotation-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.purchase-quotation-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.purchase-quotation-detail-drawer__footer {
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
.purchase-quotation-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.purchase-quotation-detail-drawer .theme-sidebar-header.purchase-quotation-detail-drawer__header {
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

.purchase-quotation-detail-drawer .purchase-quotation-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
