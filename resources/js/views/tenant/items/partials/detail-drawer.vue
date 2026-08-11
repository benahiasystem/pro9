<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="480px"
        direction="rtl"
        custom-class="item-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="item-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header item-detail-drawer__header">
                <div class="item-detail-drawer__title">
                    <h4>{{ entityLabel }}</h4>
                    <small v-if="record">{{ record.description }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar item-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="item-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Información básica" name="basic">
                            <div class="item-detail-drawer__section-card">
                                <div class="item-detail-drawer__section-card-header">
                                    <h5 class="section-title">Información básica</h5>
                                    <p class="section-subtitle">Datos principales del {{ entityShortLabel }}</p>
                                </div>
                                <div v-if="record.image_url" class="item-detail-drawer__image-wrap mb-3">
                                    <img
                                        :src="record.image_url"
                                        :alt="record.description"
                                        class="item-detail-drawer__image"
                                    />
                                </div>
                                <dl class="item-detail-drawer__list">
                                    <dt>Nombre</dt>
                                    <dd>{{ record.description || '—' }}</dd>

                                    <dt>Cód. interno</dt>
                                    <dd>{{ record.internal_id || '—' }}</dd>

                                    <dt>Descripción</dt>
                                    <dd>{{ descriptionText || '—' }}</dd>

                                    <dt>Tipo de unidad</dt>
                                    <dd>{{ unitTypeLabel }}</dd>

                                    <dt v-if="record.model">Modelo</dt>
                                    <dd v-if="record.model">{{ record.model }}</dd>

                                    <dt v-if="record.barcode">Código de barras</dt>
                                    <dd v-if="record.barcode">{{ record.barcode }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Categorías / Marcas" name="classification">
                            <div class="item-detail-drawer__section-card">
                                <div class="item-detail-drawer__section-card-header">
                                    <h5 class="section-title">Categorías / Marcas</h5>
                                    <p class="section-subtitle">Clasificación comercial del {{ entityShortLabel }}</p>
                                </div>
                                <dl class="item-detail-drawer__list">
                                    <dt>Categoría</dt>
                                    <dd>{{ categoryLabel }}</dd>

                                    <dt>Marca</dt>
                                    <dd>{{ brandLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Precios y costos" name="prices">
                            <div class="item-detail-drawer__section-card">
                                <div class="item-detail-drawer__section-card-header">
                                    <h5 class="section-title">Precios y costos</h5>
                                    <p class="section-subtitle">Valores de venta y adquisición</p>
                                </div>
                                <dl class="item-detail-drawer__list">
                                    <dt>Precio unitario (venta)</dt>
                                    <dd class="text-primary">{{ salePriceLabel }}</dd>

                                    <dt>Incluye IGV (venta)</dt>
                                    <dd>{{ hasIgvLabel }}</dd>

                                    <dt v-if="showPurchasePrices">Precio unitario (compra)</dt>
                                    <dd v-if="showPurchasePrices">{{ purchasePriceLabel }}</dd>

                                    <dt v-if="showPurchasePrices">Incluye IGV (compra)</dt>
                                    <dd v-if="showPurchasePrices">{{ purchaseHasIgvLabel }}</dd>

                                    <dt v-if="salePriceWithIgvLabel">Precio venta con IGV</dt>
                                    <dd v-if="salePriceWithIgvLabel">{{ salePriceWithIgvLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="item-detail-drawer__footer">
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="deleting"
                        @click="clickDelete"
                    >
                        <i class="fa fa-trash"></i> Eliminar {{ entityShortLabel }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar {{ entityShortLabel }}
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
                return 'S/';
            }

            if (this.record.currency_type_symbol) {
                return this.record.currency_type_symbol;
            }

            const currency = this.currencyTypes.find(option => option.id === this.record.currency_type_id);
            return currency ? currency.symbol : 'S/';
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
        formatPrice(value, symbol = 'S/') {
            const amount = Number(value || 0);

            return `${symbol} ${amount.toLocaleString('es-PE', {
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
.item-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.item-detail-drawer__header {
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

.item-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.item-detail-drawer__close {
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

.item-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.item-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.item-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.item-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.item-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.item-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.item-detail-drawer__section-card .item-detail-drawer__list dd:last-child {
    margin-bottom: 0;
}

.item-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.item-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.item-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.item-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.item-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.item-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.item-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.item-detail-drawer__list {
    margin: 0 0 8px;
}

.item-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.item-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.item-detail-drawer__image-wrap {
    display: flex;
    justify-content: center;
}

.item-detail-drawer__image {
    width: 96px;
    height: 96px;
    object-fit: contain;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
}

.item-detail-drawer__footer {
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
.item-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.item-detail-drawer .theme-sidebar-header.item-detail-drawer__header {
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

.item-detail-drawer .item-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
