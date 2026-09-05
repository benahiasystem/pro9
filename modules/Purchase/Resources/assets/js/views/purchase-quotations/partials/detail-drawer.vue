<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer purchase-quotation-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner purchase-quotation-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header purchase-quotation-detail-drawer__header">
                <div class="detail-drawer__title purchase-quotation-detail-drawer__title">
                    <h5>Detalle de la solicitud</h5>
                    <small v-if="record">{{ quotationIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar purchase-quotation-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge" :class="stateBadgeClass">
                            {{ stateLabel }}
                        </span>
                        <span v-if="hasPurchaseOrders" class="badge badge-info">OC generada</span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body purchase-quotation-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="detail-drawer__section-card purchase-quotation-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header purchase-quotation-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems solicitados en la cotización</p>
                                </div>

                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table purchase-quotation-detail-drawer__items-table mb-0">
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
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
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
                                    >
                                        <div class="detail-drawer__customer-summary">
                                            <div class="detail-drawer__customer-avatar" aria-hidden="true">{{ supplier.initials }}</div>
                                            <div class="detail-drawer__customer-identity">
                                                <strong class="detail-drawer__customer-name">{{ supplier.name }}</strong>
                                                <span v-if="supplier.documentType" class="detail-drawer__customer-badge">{{ supplier.documentType }}</span>
                                            </div>
                                        </div>

                                        <div class="detail-drawer__customer-grid">
                                            <div class="detail-drawer__customer-field">
                                                <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 8l2 0"/><path d="M15 12l2 0"/><path d="M7 16l10 0"/></svg></span>
                                                <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Documento</span><strong>{{ supplier.formatted_number || supplier.number }}</strong></div>
                                            </div>
                                            <div class="detail-drawer__customer-field">
                                                <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg></span>
                                                <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Teléfono</span><strong>{{ supplier.telephone || '—' }}</strong></div>
                                            </div>
                                            <div class="detail-drawer__customer-field">
                                                <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg></span>
                                                <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Correo</span><strong>{{ supplier.email || '—' }}</strong></div>
                                            </div>
                                            <div v-if="supplier.address" class="detail-drawer__customer-field">
                                                <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M12.783 21.326a2 2 0 0 1 -2.196 -.426l-4.244 -4.243a8 8 0 1 1 13.657 -5.62"/><path d="M15 19l2 2l4 -4"/></svg></span>
                                                <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Dirección</span><strong>{{ supplier.address }}</strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <p v-else class="text-muted small mb-0">Sin proveedores registrados.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="detail-drawer__details-section">
                                <div class="detail-drawer__details-heading">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Establecimiento, estado y origen de la solicitud</p>
                                </div>

                                <div class="detail-drawer__details-grid">
                                    <div class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Establecimiento</span><p>{{ establishmentLabel }}</p></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Fecha de emisión</span><strong>{{ issueDateLabel }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Estado</span><strong>{{ stateLabel }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Registrado por</span><strong>{{ userLabel }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Órdenes de compra</span><strong>{{ purchaseOrdersLabel }}</strong></div>
                                </div>

                                <div v-if="hasPurchaseOrders" class="detail-drawer__operation-note detail-drawer__operation-note--observation">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-triangle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4"/><path d="M10.363 3.591l-8.106 14.54a1.914 1.914 0 0 0 1.636 2.869h16.214a1.914 1.914 0 0 0 1.636 -2.869l-8.106 -14.54a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/></svg>
                                    <span>La solicitud ya tiene órdenes de compra generadas, por eso no puede editarse ni volver a generarse.</span>
                                </div>
                                <div v-else class="detail-drawer__operation-note">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-info-circle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/><path d="M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0 -18"/></svg>
                                    <span>La solicitud de cotización no afecta el stock. El inventario se actualiza recién cuando se registra la compra.</span>
                                </div>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions detail-drawer__footer--wrap purchase-quotation-detail-drawer__footer">
                    <button
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="clickDownloadPdf"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" /><path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" /><path d="M17 18h2" /><path d="M20 15h-3v6" /><path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1" /></svg>
                        Descargar PDF
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar
                    </button>
                    <button
                        v-if="canGenerate"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="$emit('generate', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304" /><path d="M9 11v-5a3 3 0 0 1 6 0v5" /></svg>
                        Generar OC
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('options', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                        Opciones
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
                const documentType =
                    supplier.identity_document_type_description
                    || supplier.identity_document_type?.description
                    || supplier.document_type
                    || this.inferDocumentType(number)
                    || null;

                const name = supplier.name || supplier.description || '—';

                return {
                    key: supplier.supplier_id || index,
                    name,
                    initials: this.buildInitials(name),
                    number: number || '—',
                    documentType,
                    email: supplier.email || null,
                    telephone: supplier.telephone || null,
                    address: supplier.address || supplier.direccion || null
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
        purchaseOrdersLabel() {
            if (!this.hasPurchaseOrders) {
                return 'Sin generar';
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
        buildInitials(name) {
            const words = String(name || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length || words[0] === '—') {
                return 'PR';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        inferDocumentType(number) {
            if (!number) {
                return null;
            }

            const digits = String(number).replace(/\D/g, '');

            if (digits.length === 11) {
                return 'RIF';
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

.purchase-quotation-detail-drawer__supplier + .purchase-quotation-detail-drawer__supplier {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid #eef2f7;
}
</style>
