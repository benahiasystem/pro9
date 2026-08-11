<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="item-set-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="item-set-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header item-set-detail-drawer__header">
                <div class="item-set-detail-drawer__title">
                    <h4>Detalle del conjunto</h4>
                    <small v-if="record">{{ packName }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar item-set-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="item-set-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge"
                            :class="record.active === false ? 'badge-danger' : 'badge-success'"
                        >
                            {{ record.active === false ? 'Inactivo' : 'Activo' }}
                        </span>
                        <small class="text-muted">{{ unitLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="item-set-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos del conjunto" name="products">
                            <div class="item-set-detail-drawer__section-card">
                                <div class="item-set-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos del conjunto</h5>
                                    <p class="section-subtitle">Ítems que componen el pack o conjunto</p>
                                </div>
                                <div v-if="componentItems.length" class="table-responsive">
                                    <table class="table table-sm item-set-detail-drawer__items-table mb-0">
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
                            <div class="item-set-detail-drawer__section-card">
                                <div class="item-set-detail-drawer__section-card-header">
                                    <h5 class="section-title">Información general</h5>
                                    <p class="section-subtitle">Datos principales del pack o conjunto</p>
                                </div>
                                <dl class="item-set-detail-drawer__list">
                                    <dt>Cód. Interno</dt>
                                    <dd>{{ record.internal_id || '—' }}</dd>

                                    <dt>Unidad</dt>
                                    <dd>{{ unitLabel }}</dd>

                                    <dt>Nombre</dt>
                                    <dd>{{ packName }}</dd>

                                    <dt>Descripción</dt>
                                    <dd>{{ descriptionText || '—' }}</dd>

                                    <dt>Precio Unitario de Venta</dt>
                                    <dd class="text-primary fw-bold">{{ salePriceLabel }}</dd>

                                    <dt>IGV</dt>
                                    <dd>{{ igvLabel }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="item-set-detail-drawer__footer">
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="deleting"
                        @click="clickDelete"
                    >
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar
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
.item-set-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.item-set-detail-drawer__header {
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

.item-set-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.item-set-detail-drawer__close {
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

.item-set-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.item-set-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-set-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.item-set-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.item-set-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.item-set-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.item-set-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.item-set-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.item-set-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.item-set-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.item-set-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.item-set-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.item-set-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.item-set-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.item-set-detail-drawer__list {
    margin: 0 0 8px;
}

.item-set-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.item-set-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.item-set-detail-drawer__list dd:last-child {
    margin-bottom: 0;
}

.item-set-detail-drawer__items-table thead th,
.item-set-detail-drawer__items-table tfoot td {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.item-set-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.item-set-detail-drawer__items-table tfoot td {
    border-top: 1px solid #e2e8f0;
    border-bottom: 0;
    padding-top: 10px;
}

.item-set-detail-drawer__footer {
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
.item-set-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.item-set-detail-drawer .theme-sidebar-header.item-set-detail-drawer__header {
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

.item-set-detail-drawer .item-set-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
