<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer item-set-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner item-set-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header item-set-detail-drawer__header">
                <div class="detail-drawer__title item-set-detail-drawer__title">
                    <h5>Detalle del conjunto</h5>
                    <small v-if="record">{{ packName }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar item-set-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge"
                            :class="record.active === false ? 'badge-danger' : 'badge-success'"
                        >
                            {{ record.active === false ? 'Inactivo' : 'Activo' }}
                        </span>
                        <span v-if="componentItems.length" class="badge badge-info">
                            {{ componentItems.length }} producto{{ componentItems.length === 1 ? '' : 's' }}
                        </span>
                        <small class="text-muted">{{ salePriceLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body item-set-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="detail-drawer__section-card item-set-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header item-set-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos del conjunto</h5>
                                    <p class="section-subtitle">Ítems que componen el pack o conjunto</p>
                                </div>
                                <div v-if="componentItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table item-set-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th class="text-end">P. unit.</th>
                                                <th class="text-center">Cant.</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in componentItems" :key="item.key || index">
                                                <td>{{ item.description }}</td>
                                                <td class="text-end">{{ formatMoney(item.sale_unit_price, false) }}</td>
                                                <td class="text-center">{{ item.quantity }}</td>
                                                <td class="text-end">{{ formatMoney(item.total, false) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot v-if="componentItems.length">
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Total componentes</td>
                                                <td class="text-end fw-bold text-primary">{{ formatMoney(componentsTotal) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin productos registrados en el conjunto.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información general" name="general">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Información general</h5>
                                    <p class="section-subtitle">Datos principales del pack o conjunto</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div
                                        class="detail-drawer__customer-avatar item-set-detail-drawer__avatar"
                                        :class="{ 'item-set-detail-drawer__avatar--image': record.image_url }"
                                        aria-hidden="true"
                                    >
                                        <img
                                            v-if="record.image_url"
                                            :src="record.image_url"
                                            :alt="packName"
                                            class="item-set-detail-drawer__image"
                                        />
                                        <template v-else>{{ packInitials }}</template>
                                    </div>
                                    <div class="detail-drawer__customer-identity">
                                        <strong class="detail-drawer__customer-name">{{ packName }}</strong>
                                        <span class="detail-drawer__customer-badge">{{ unitLabel }}</span>
                                    </div>
                                </div>

                                <div class="detail-drawer__details-grid item-set-detail-drawer__grid">
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Cód. interno</span><strong>{{ record.internal_id || '—' }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Unidad</span><strong>{{ unitLabel }}</strong></div>
                                    <div v-if="categoryLabel" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Categoría</span><strong>{{ categoryLabel }}</strong></div>
                                    <div v-if="brandLabel" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Marca</span><strong>{{ brandLabel }}</strong></div>
                                    <div class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Descripción</span><p>{{ descriptionText || '—' }}</p></div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Precios" name="prices">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Precios</h5>
                                    <p class="section-subtitle">Precio del conjunto frente al valor de sus componentes</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-tag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Precio del conjunto</span><strong>{{ salePriceLabel }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-stack-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 4l-8 4l8 4l8 -4l-8 -4"/><path d="M4 12l8 4l8 -4"/><path d="M4 16l8 4l8 -4"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Suma de componentes</span><strong>{{ formatMoney(componentsTotal) }}</strong></div>
                                    </div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-percentage"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M7 7m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M6 18l12 -12"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Incluye IGV</span><strong>{{ igvLabel }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Moneda</span><strong>{{ currencySymbol }}</strong>
                                    </div>
                                    <div v-if="componentsTotal > 0" class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrows-exchange"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10h14l-4 -4"/><path d="M17 14h-14l4 4"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Diferencia vs. componentes</span><strong>{{ priceDifferenceLabel }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-total"><span>Precio con IGV</span><strong><small>{{ currencySymbol }}</small> {{ priceWithIgvAmount }}</strong></div>
                                </div>

                                <div v-if="componentsTotal > 0" class="detail-drawer__operation-note">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-info-circle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/><path d="M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0 -18"/></svg>
                                    <span>{{ priceComparisonNote }}</span>
                                </div>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions detail-drawer__footer--wrap item-set-detail-drawer__footer">
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="deleting"
                        @click="clickDelete"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                        Eliminar
                    </button>
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar
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
            default: 'item-sets'
        },
        typeUser: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            loading: false,
            deleting: false,
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
        packName() {
            return this.record?.description || '—';
        },
        packInitials() {
            const words = String(this.record?.description || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length) {
                return 'PK';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        categoryLabel() {
            return this.record?.category_description || null;
        },
        brandLabel() {
            return this.record?.brand || null;
        },
        descriptionText() {
            if (!this.record) {
                return '';
            }

            return this.stripHtml(this.record.name || '');
        },
        unitLabel() {
            return this.record?.unit_type_id || '—';
        },
        currencySymbol() {
            if (this.record?.currency_type_symbol) {
                return this.record.currency_type_symbol;
            }

            return this.record?.currency_type_id === 'USD' ? '$' : 'S/';
        },
        salePriceLabel() {
            if (!this.record) {
                return '—';
            }

            if (
                typeof this.record.sale_unit_price === 'string'
                && this.record.sale_unit_price.includes(this.currencySymbol)
            ) {
                return this.record.sale_unit_price;
            }

            return this.formatMoney(this.record.sale_unit_price);
        },
        igvLabel() {
            if (this.record?.has_igv_description) {
                return this.record.has_igv_description;
            }

            if (typeof this.record?.has_igv === 'boolean') {
                return this.record.has_igv ? 'Sí' : 'No';
            }

            return '—';
        },
        componentItems() {
            const items = Array.isArray(this.record?.individual_items)
                ? this.record.individual_items
                : [];

            return items.map((row, index) => {
                const quantity = Number(row.quantity || 0);
                const unitPrice = Number(row.sale_unit_price || 0);

                return {
                    key: row.id || row.individual_item_id || index,
                    description: row.full_description || row.description || '—',
                    sale_unit_price: unitPrice,
                    quantity,
                    total: quantity * unitPrice
                };
            });
        },
        componentsTotal() {
            return this.componentItems.reduce((sum, item) => sum + Number(item.total || 0), 0);
        },
        salePriceAmount() {
            return this.parseAmount(this.record?.sale_unit_price);
        },
        priceDifference() {
            return this.salePriceAmount - this.componentsTotal;
        },
        priceDifferenceLabel() {
            const difference = this.priceDifference;
            const sign = difference > 0 ? '+' : (difference < 0 ? '-' : '');

            return `${sign}${this.formatMoney(Math.abs(difference))}`;
        },
        priceComparisonNote() {
            const difference = this.priceDifference;

            if (!difference) {
                return 'El precio del conjunto coincide con la suma de sus componentes.';
            }

            const percentage = this.componentsTotal
                ? Math.abs((difference / this.componentsTotal) * 100).toFixed(1)
                : null;

            const direction = difference < 0 ? 'menor' : 'mayor';
            const detail = percentage ? ` (${percentage}%)` : '';

            return `El precio del conjunto es ${direction} que la suma de sus componentes${detail}.`;
        },
        priceWithIgvAmount() {
            const amount = this.parseAmount(
                this.record?.sale_unit_price_with_igv || this.record?.sale_unit_price
            );

            return amount.toLocaleString('es-PE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
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

            this.record = { ...this.initialRow };
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

                    const data = response.data?.data || response.data || {};
                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    this.record = {
                        ...snapshot,
                        ...data,
                        active: snapshot.active !== undefined ? snapshot.active : data.active,
                        has_igv_description: snapshot.has_igv_description || data.has_igv_description,
                        individual_items: Array.isArray(data.individual_items)
                            ? data.individual_items
                            : (snapshot.individual_items || [])
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del conjunto.');
                        this.visibleDrawer = false;
                    }
                })
                .finally(() => {
                    if (String(requestedId) === String(this.recordId)) {
                        this.loading = false;
                    }
                });
        },
        clickDelete() {
            if (!this.record?.id || this.typeUser !== 'admin') {
                return;
            }

            this.$confirm(
                '¿Está seguro de eliminar este producto compuesto?',
                'Eliminar',
                {
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }
            )
                .then(() => {
                    this.deleting = true;

                    this.$http.delete(`/${this.resource}/${this.record.id}`)
                        .then(response => {
                            if (response.data?.success) {
                                this.$message.success(response.data.message || 'Eliminado correctamente');
                                this.visibleDrawer = false;
                                this.$eventHub.$emit('reloadData');
                                return;
                            }

                            this.$message.error(response.data?.message || 'No se pudo eliminar el conjunto.');
                        })
                        .catch(error => {
                            const message = error.response?.data?.message
                                || 'No se pudo eliminar el conjunto.';
                            this.$message.error(message);
                        })
                        .finally(() => {
                            this.deleting = false;
                        });
                })
                .catch(() => {});
        },
        stripHtml(value) {
            if (!value) {
                return '';
            }

            const el = document.createElement('div');
            el.innerHTML = String(value);
            return (el.textContent || el.innerText || '').trim();
        },
        parseAmount(value) {
            if (value === undefined || value === null || value === '') {
                return 0;
            }

            if (typeof value === 'number') {
                return value;
            }

            return Number(String(value).replace(/,/g, '').replace(/[^\d.-]/g, '').trim()) || 0;
        },
        formatMoney(value, withSymbol = true) {
            const amount = this.parseAmount(value);
            const formatted = amount.toLocaleString('es-PE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            return withSymbol ? `${this.currencySymbol} ${formatted}` : formatted;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.deleting = false;
            this.activeTab = 'products';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>

.item-set-detail-drawer__items-table thead th,
.item-set-detail-drawer__items-table tfoot td {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.item-set-detail-drawer__items-table tfoot td {
    border-top: 1px solid #e2e8f0;
    border-bottom: 0;
    padding-top: 10px;
}

.item-set-detail-drawer__avatar--image {
    overflow: hidden;
    border: 1px solid #dfe7ee;
    background: #fff;
}

.item-set-detail-drawer__image {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.item-set-detail-drawer__grid {
    margin-top: 16px;
}
</style>
