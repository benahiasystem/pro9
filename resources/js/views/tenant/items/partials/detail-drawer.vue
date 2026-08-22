<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer item-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner item-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header item-detail-drawer__header">
                <div class="detail-drawer__title item-detail-drawer__title">
                    <h5>{{ entityLabel }}</h5>
                    <small v-if="record">{{ record.description }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar item-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span v-if="activeLabel" class="badge" :class="activeBadgeClass">{{ activeLabel }}</span>
                        <span v-if="stockLabel" class="badge" :class="stockBadgeClass">{{ stockLabel }}</span>
                        <small class="text-muted">{{ salePriceLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body item-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Información básica" name="basic">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Información básica</h5>
                                    <p class="section-subtitle">Datos principales del {{ entityShortLabel }}</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div
                                        class="detail-drawer__customer-avatar item-detail-drawer__avatar"
                                        :class="{ 'item-detail-drawer__avatar--image': record.image_url }"
                                        aria-hidden="true"
                                    >
                                        <img
                                            v-if="record.image_url"
                                            :src="record.image_url"
                                            :alt="record.description"
                                            class="item-detail-drawer__image"
                                        />
                                        <template v-else>{{ itemInitials }}</template>
                                    </div>
                                    <div class="detail-drawer__customer-identity">
                                        <strong class="detail-drawer__customer-name">{{ record.description || '—' }}</strong>
                                        <span class="detail-drawer__customer-badge">{{ unitTypeLabel }}</span>
                                    </div>
                                </div>

                                <div class="detail-drawer__details-grid item-detail-drawer__grid">
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Cód. interno</span><strong>{{ record.internal_id || '—' }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Tipo de unidad</span><strong>{{ unitTypeLabel }}</strong></div>
                                    <div v-if="record.model" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Modelo</span><strong>{{ record.model }}</strong></div>
                                    <div v-if="record.barcode" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Código de barras</span><strong>{{ record.barcode }}</strong></div>
                                    <div class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Descripción</span><p>{{ descriptionText || '—' }}</p></div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Categorías / Marcas" name="classification">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Categorías / Marcas</h5>
                                    <p class="section-subtitle">Clasificación comercial del {{ entityShortLabel }}</p>
                                </div>

                                <div class="detail-drawer__customer-grid">
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-category"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4h6v6h-6z"/><path d="M14 4h6v6h-6z"/><path d="M4 14h6v6h-6z"/><path d="M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Categoría</span><strong>{{ categoryLabel }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-tag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Marca</span><strong>{{ brandLabel }}</strong></div>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane v-if="showStockTab" label="Stock" name="stock">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Stock</h5>
                                    <p class="section-subtitle">Existencias disponibles por almacén</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-package"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5v9l-8 4.5l-8 -4.5v-9z"/><path d="M12 12l8 -4.5"/><path d="M12 12v9"/><path d="M12 12l-8 -4.5"/><path d="M16 5.25l-8 4.5"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Stock actual</span><strong>{{ formatStock(record.stock) }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-square-rounded"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 8v4"/><path d="M12 16h.01"/><path d="M3 12c0 -7.2 1.8 -9 9 -9s9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9z"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Stock mínimo</span><strong>{{ formatStock(record.stock_min) }}</strong></div>
                                    </div>
                                </div>

                                <div v-if="warehouseRows.length" class="table-responsive mt-3">
                                    <table class="table table-sm detail-drawer__items-table item-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Almacén</th>
                                                <th class="text-end">Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(warehouse, index) in warehouseRows" :key="index">
                                                <td>{{ warehouse.description }}</td>
                                                <td class="text-end">{{ formatStock(warehouse.stock) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-if="isLowStock" class="detail-drawer__operation-note detail-drawer__operation-note--observation">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-triangle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4"/><path d="M10.363 3.591l-8.106 14.54a1.914 1.914 0 0 0 1.636 2.869h16.214a1.914 1.914 0 0 0 1.636 -2.869l-8.106 -14.54a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/></svg>
                                    <span>El stock actual está por debajo del stock mínimo configurado.</span>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Precios y costos" name="prices">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Precios y costos</h5>
                                    <p class="section-subtitle">Valores de venta y adquisición</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-tag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Precio de venta</span><strong>{{ salePriceLabel }}</strong></div>
                                    </div>
                                    <div v-if="showPurchasePrices" class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Precio de compra</span><strong>{{ purchasePriceLabel }}</strong></div>
                                    </div>
                                    <div v-else class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Moneda</span><strong>{{ currencySymbol }}</strong></div>
                                    </div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-percentage"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M7 7m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M6 18l12 -12"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Incluye IGV (venta)</span><strong>{{ hasIgvLabel }}</strong>
                                    </div>
                                    <div v-if="showPurchasePrices" class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Incluye IGV (compra)</span><strong>{{ purchaseHasIgvLabel }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Moneda</span><strong>{{ currencySymbol }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-total"><span>Precio venta con IGV</span><strong><small>{{ currencySymbol }}</small> {{ priceWithIgvAmount }}</strong></div>
                                </div>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions item-detail-drawer__footer">
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="deleting"
                        @click="clickDelete"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                        Eliminar {{ entityShortLabel }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar {{ entityShortLabel }}
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
const AFFECTATION_IGV_TYPES_EXONERATED = ['20', '21', '30', '31', '32', '33', '34', '35', '36', '37'];

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
        type: {
            type: String,
            default: ''
        },
        typeUser: {
            type: String,
            default: ''
        },
        resource: {
            type: String,
            default: 'items'
        }
    },
    data() {
        return {
            loading: false,
            toggling: false,
            deleting: false,
            record: null,
            unitTypes: [],
            categories: [],
            brands: [],
            currencyTypes: [],
            tablesLoaded: false,
            tablesLoading: false,
            activeTab: 'basic'
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
        entityLabel() {
            return this.type === 'ZZ' ? 'Detalle del servicio' : 'Detalle del producto';
        },
        entityShortLabel() {
            return this.type === 'ZZ' ? 'servicio' : 'producto';
        },
        isService() {
            return this.type === 'ZZ' || this.record?.unit_type_id === 'ZZ';
        },
        itemInitials() {
            const words = String(this.record?.description || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length) {
                return this.isService ? 'SV' : 'PR';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        activeLabel() {
            if (typeof this.record?.active !== 'boolean') {
                return null;
            }

            return this.record.active ? 'Activo' : 'Inhabilitado';
        },
        activeBadgeClass() {
            return this.record?.active ? 'badge-success' : 'badge-danger';
        },
        stockValue() {
            if (this.isService || this.record?.stock === undefined || this.record?.stock === null) {
                return null;
            }

            return Number(this.record.stock) || 0;
        },
        stockMinValue() {
            return Number(this.record?.stock_min || 0);
        },
        isLowStock() {
            return this.stockValue !== null && this.stockValue < this.stockMinValue;
        },
        stockLabel() {
            if (this.stockValue === null) {
                return null;
            }

            return `Stock: ${this.formatStock(this.stockValue)}`;
        },
        stockBadgeClass() {
            return this.isLowStock ? 'badge-danger' : 'badge-info';
        },
        warehouseRows() {
            const warehouses = Array.isArray(this.record?.warehouses) ? this.record.warehouses : [];

            return warehouses.map(warehouse => ({
                description: warehouse.warehouse_description || warehouse.description || '—',
                stock: warehouse.stock
            }));
        },
        showStockTab() {
            return this.stockValue !== null;
        },
        descriptionText() {
            if (!this.record) {
                return '';
            }

            return this.stripHtml(this.record.name);
        },
        unitTypeLabel() {
            if (!this.record) {
                return '—';
            }

            if (this.record.unit_type_text) {
                return this.record.unit_type_text;
            }

            const unit = this.unitTypes.find(option => option.id === this.record.unit_type_id);
            if (unit) {
                return `${unit.id} — ${unit.description}`;
            }

            return this.record.unit_type_id || '—';
        },
        categoryLabel() {
            if (!this.record) {
                return '—';
            }

            if (this.record.category_description) {
                return this.record.category_description;
            }

            const category = this.categories.find(option => option.id === this.record.category_id);
            return category ? category.name : '—';
        },
        brandLabel() {
            if (!this.record) {
                return '—';
            }

            if (this.record.brand) {
                return this.record.brand;
            }

            const brand = this.brands.find(option => option.id === this.record.brand_id);
            return brand ? brand.name : '—';
        },
        currencySymbol() {
            if (!this.record) {
                return 'Bs.';
            }

            if (this.record.currency_type_symbol) {
                return this.record.currency_type_symbol;
            }

            const currency = this.currencyTypes.find(option => option.id === this.record.currency_type_id);
            return currency ? currency.symbol : 'Bs.';
        },
        showPurchasePrices() {
            return this.typeUser !== 'seller';
        },
        hasIgvLabel() {
            return this.resolveIgvDescription(
                this.record?.has_igv,
                this.record?.sale_affectation_igv_type_id,
                this.record?.has_igv_description
            );
        },
        purchaseHasIgvLabel() {
            return this.resolveIgvDescription(
                this.record?.purchase_has_igv,
                this.record?.purchase_affectation_igv_type_id,
                this.record?.purchase_has_igv_description
            );
        },
        salePriceLabel() {
            if (!this.record) {
                return '—';
            }

            if (typeof this.record.sale_unit_price === 'string' && this.record.sale_unit_price.includes(this.currencySymbol)) {
                return this.record.sale_unit_price;
            }

            return this.formatPrice(this.record.sale_unit_price, this.currencySymbol);
        },
        purchasePriceLabel() {
            if (!this.record) {
                return '—';
            }

            if (typeof this.record.purchase_unit_price === 'string' && this.record.purchase_unit_price.includes(this.currencySymbol)) {
                return this.record.purchase_unit_price;
            }

            return this.formatPrice(this.record.purchase_unit_price, this.currencySymbol);
        },
        salePriceWithIgvLabel() {
            if (!this.record?.sale_unit_price_with_igv) {
                return null;
            }

            return this.record.sale_unit_price_with_igv;
        },
        priceWithIgvAmount() {
            const amount = this.parseAmount(
                this.record?.sale_unit_price_with_igv ?? this.record?.sale_unit_price
            );

            if (amount === null) {
                return '—';
            }

            return amount.toLocaleString('es-VE', {
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
            this.activeTab = 'basic';
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

            Promise.all([
                this.ensureTables(),
                this.$http.get(`/${this.resource}/record/${requestedId}`)
            ])
                .then(([, response]) => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    const data = response.data.data || response.data;
                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    this.record = {
                        ...snapshot,
                        ...data,
                        active: snapshot.active !== undefined ? snapshot.active : data.active
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error(`No se pudo cargar el detalle del ${this.entityShortLabel}.`);
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

            if (this.tablesLoading) {
                return new Promise(resolve => {
                    const interval = setInterval(() => {
                        if (this.tablesLoaded) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 50);
                });
            }

            this.tablesLoading = true;

            return this.$http
                .get(`/${this.resource}/tables`)
                .then(response => {
                    const data = response.data || {};
                    this.unitTypes = data.unit_types || [];
                    this.categories = data.categories || [];
                    this.brands = data.brands || [];
                    this.currencyTypes = data.currency_types || [];
                    this.tablesLoaded = true;
                })
                .catch(() => {
                    this.unitTypes = [];
                    this.categories = [];
                    this.brands = [];
                    this.currencyTypes = [];
                    this.tablesLoaded = true;
                })
                .finally(() => {
                    this.tablesLoading = false;
                });
        },
        toggleActive(value) {
            const previous = !value;

            const applyChange = () => {
                this.toggling = true;
                const url = value
                    ? `/${this.resource}/enable/${this.record.id}`
                    : `/${this.resource}/disable/${this.record.id}`;

                this.$http
                    .get(url)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message);
                            this.$eventHub.$emit('reloadData');
                            return;
                        }

                        this.record.active = previous;
                        this.$message.error(response.data.message || 'No se pudo actualizar el estado.');
                    })
                    .catch(() => {
                        this.record.active = previous;
                        this.$message.error('No se pudo actualizar el estado.');
                    })
                    .finally(() => {
                        this.toggling = false;
                    });
            };

            if (!value) {
                this.$confirm(
                    `¿Desea inhabilitar este ${this.entityShortLabel}?`,
                    'Inhabilitar',
                    {
                        confirmButtonText: 'Inhabilitar',
                        cancelButtonText: 'Cancelar',
                        type: 'warning'
                    }
                )
                    .then(() => applyChange())
                    .catch(() => {
                        this.record.active = previous;
                    });
                return;
            }

            applyChange();
        },
        clickDelete() {
            if (!this.record?.id) {
                return;
            }

            this.deleting = true;

            this.$confirm('¿Desea eliminar el registro?', 'Eliminar', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            })
                .then(() => this.$http.delete(`/${this.resource}/${this.record.id}`))
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.visibleDrawer = false;
                        this.$eventHub.$emit('reloadData');
                        return;
                    }

                    this.$message.error(response.data.message);
                })
                .catch(error => {
                    if (error === 'cancel' || error === 'close') {
                        return;
                    }

                    if (error.response?.status === 500) {
                        this.$message.error('Error al intentar eliminar');
                    }
                })
                .finally(() => {
                    this.deleting = false;
                });
        },
        resolveIgvDescription(hasIgv, affectationTypeId, fallbackLabel) {
            if (fallbackLabel) {
                return fallbackLabel;
            }

            if (AFFECTATION_IGV_TYPES_EXONERATED.includes(affectationTypeId)) {
                return 'No';
            }

            return hasIgv ? 'Sí' : 'No';
        },
        parseAmount(value) {
            if (value === undefined || value === null || value === '') {
                return null;
            }

            if (typeof value === 'number') {
                return value;
            }

            const cleaned = String(value).replace(/[^\d.,-]/g, '').replace(/,/g, '');
            const amount = Number(cleaned);

            return Number.isFinite(amount) ? amount : null;
        },
        formatStock(value) {
            const amount = Number(value || 0);

            return amount.toLocaleString('es-VE', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            });
        },
        formatPrice(value, symbol = 'Bs.') {
            const amount = Number(value || 0);

            return `${symbol} ${amount.toLocaleString('es-VE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        },
        stripHtml(html) {
            if (!html) {
                return '';
            }

            return String(html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.toggling = false;
            this.deleting = false;
            this.activeTab = 'basic';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>

.item-detail-drawer__avatar--image {
    overflow: hidden;
    border: 1px solid #dfe7ee;
    background: #fff;
}

.item-detail-drawer__image {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.item-detail-drawer__grid {
    margin-top: 16px;
}
</style>
