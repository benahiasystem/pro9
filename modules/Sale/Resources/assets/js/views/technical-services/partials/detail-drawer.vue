<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="technical-service-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="technical-service-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header technical-service-detail-drawer__header">
                <div class="technical-service-detail-drawer__title">
                    <h4>Detalle del servicio</h4>
                    <small v-if="record">{{ serviceIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar technical-service-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="technical-service-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            v-for="badge in serviceTypeBadges"
                            :key="badge.key"
                            class="badge"
                            :class="badge.className"
                        >
                            {{ badge.label }}
                        </span>
                        <span v-if="stateLabel" class="badge badge-info">{{ stateLabel }}</span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                        <small v-if="serialNumberLabel" class="text-muted">· Serie: {{ serialNumberLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="technical-service-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Piezas" name="pieces">
                            <div class="technical-service-detail-drawer__section-card">
                                <div class="technical-service-detail-drawer__section-card-header">
                                    <h5 class="section-title">Piezas</h5>
                                    <p class="section-subtitle">Repuestos e ítems asociados al servicio</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm technical-service-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-start">Producto</th>
                                                <th class="text-center">Cant.</th>
                                                <th class="text-end">P. unit.</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in lineItems" :key="item.key || index">
                                                <td class="text-start">{{ item.description }}</td>
                                                <td class="text-center">{{ item.quantity }}</td>
                                                <td class="text-end">{{ formatMoney(item.unit_price, false) }}</td>
                                                <td class="text-end">{{ formatMoney(item.subtotal, false) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin piezas registradas.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Cliente" name="customer">
                            <div class="technical-service-detail-drawer__section-card">
                                <div class="technical-service-detail-drawer__section-card-header">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado al servicio</p>
                                </div>
                                <dl class="technical-service-detail-drawer__list">
                                    <dt>Nombre / Razón social</dt>
                                    <dd>{{ customerName }}</dd>

                                    <dt>Documento</dt>
                                    <dd>{{ customerDocument }}</dd>

                                    <dt>Celular</dt>
                                    <dd>{{ customerCellphone }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Costos y totales" name="totals">
                            <div class="technical-service-detail-drawer__section-card">
                                <div class="technical-service-detail-drawer__section-card-header">
                                    <h5 class="section-title">Costos y totales</h5>
                                    <p class="section-subtitle">Importes del servicio técnico</p>
                                </div>
                                <dl class="technical-service-detail-drawer__list">
                                    <dt>Moneda</dt>
                                    <dd>{{ currencyLabel }}</dd>

                                    <dt>Costo S.</dt>
                                    <dd>{{ formatMoney(record.cost) }}</dd>

                                    <dt>Costo P.</dt>
                                    <dd>{{ formatMoney(record.total) }}</dd>

                                    <dt v-if="record.prepayment">Pago adelantado</dt>
                                    <dd v-if="record.prepayment">{{ formatMoney(record.prepayment) }}</dd>

                                    <dt>Saldo</dt>
                                    <dd :class="{ 'text-danger fw-bold': balanceAmount > 0 }">{{ formatMoney(balanceAmount) }}</dd>

                                    <dt>Total</dt>
                                    <dd class="text-primary fw-bold">{{ formatMoney(sumTotalAmount) }}</dd>

                                    <dt v-if="record.number_document_sale_note">Comprobante</dt>
                                    <dd v-if="record.number_document_sale_note">{{ record.number_document_sale_note }}</dd>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Detalles adicionales" name="additional">
                            <div class="technical-service-detail-drawer__section-card">
                                <div class="technical-service-detail-drawer__section-card-header">
                                    <h5 class="section-title">Detalles adicionales</h5>
                                    <p class="section-subtitle">Descripción, fallas reportadas y diagnóstico</p>
                                </div>
                                <dl v-if="hasAdditionalDetails" class="technical-service-detail-drawer__list">
                                    <dt v-if="record.description">Descripción</dt>
                                    <dd v-if="record.description" class="text-pre-wrap">{{ record.description }}</dd>

                                    <dt v-if="record.reason">Motivo de ingreso</dt>
                                    <dd v-if="record.reason" class="text-pre-wrap">{{ record.reason }}</dd>

                                    <dt v-if="record.state">Estado del equipo</dt>
                                    <dd v-if="record.state" class="text-pre-wrap">{{ record.state }}</dd>

                                    <dt v-if="record.brand">Marca</dt>
                                    <dd v-if="record.brand">{{ record.brand }}</dd>

                                    <dt v-if="record.equipment">Equipo</dt>
                                    <dd v-if="record.equipment">{{ record.equipment }}</dd>

                                    <dt v-if="record.activities">Actividades realizadas</dt>
                                    <dd v-if="record.activities" class="text-pre-wrap">{{ record.activities }}</dd>

                                    <dt v-if="importantNotes.length">Notas importantes</dt>
                                    <dd v-if="importantNotes.length">
                                        <ul class="technical-service-detail-drawer__notes mb-0 ps-3">
                                            <li v-for="(note, index) in importantNotes" :key="index">{{ note }}</li>
                                        </ul>
                                    </dd>
                                </dl>
                                <p v-else class="text-muted small mb-0">Sin detalles adicionales.</p>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="technical-service-detail-drawer__footer">
                    <button
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="clickPrint"
                    >
                        <i class="fa fa-file-pdf"></i> Ver PDF
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('payments', record.id)"
                    >
                        <i class="fa fa-money-bill"></i> Pagos
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar servicio
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
            default: 'technical-services'
        },
        canEditRow: {
            type: Function,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            record: null,
            activeTab: 'pieces'
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
        serviceIdentifier() {
            const parts = [`Servicio #${this.record?.id || ''}`];

            if (this.record?.serial_number) {
                parts.push(`Serie ${this.record.serial_number}`);
            }

            if (this.issueDateLabel && this.issueDateLabel !== '—') {
                parts.push(this.issueDateLabel);
            }

            return parts.join(' · ');
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        serialNumberLabel() {
            return this.record?.serial_number || null;
        },
        stateLabel() {
            const state = this.record?.state;
            if (!state || typeof state !== 'string') {
                return null;
            }

            const trimmed = state.trim();
            return trimmed.length <= 48 ? trimmed : `${trimmed.slice(0, 48)}…`;
        },
        serviceTypeBadges() {
            const badges = [];

            if (this.record?.repair) {
                badges.push({ key: 'repair', label: 'Reparación', className: 'badge-primary' });
            }
            if (this.record?.warranty) {
                badges.push({ key: 'warranty', label: 'Garantía', className: 'badge-success' });
            }
            if (this.record?.maintenance) {
                badges.push({ key: 'maintenance', label: 'Mantenimiento', className: 'badge-warning' });
            }
            if (this.record?.diagnosis) {
                badges.push({ key: 'diagnosis', label: 'Diagnóstico', className: 'badge-info' });
            }

            return badges;
        },
        customerName() {
            return this.record?.customer?.name
                || this.record?.customer_name
                || '—';
        },
        customerDocument() {
            const customer = this.parseCustomer(this.record?.customer);
            const number = customer?.number || this.record?.customer_number || null;
            const type =
                customer?.identity_document_type?.description
                || customer?.document_type
                || customer?.identity_document_type_id
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
        customerCellphone() {
            return this.record?.cellphone || '—';
        },
        currencyLabel() {
            const currencyId = this.record?.currency_type_id;
            if (!currencyId) {
                return 'PEN (S/)';
            }

            const symbol = currencyId === 'PEN' ? 'S/' : (currencyId === 'USD' ? '$' : currencyId);
            return `${currencyId} (${symbol})`;
        },
        currencySymbol() {
            return this.record?.currency_type_id === 'USD' ? '$' : 'S/';
        },
        sumTotalAmount() {
            if (this.record?.sum_total !== undefined && this.record?.sum_total !== null) {
                return this.parseAmount(this.record.sum_total);
            }

            return this.parseAmount(this.record?.cost) + this.parseAmount(this.record?.total);
        },
        balanceAmount() {
            return this.parseAmount(this.record?.balance);
        },
        importantNotes() {
            const notes = this.record?.important_note;

            if (!notes) {
                return [];
            }

            if (Array.isArray(notes)) {
                return notes.filter(note => note !== null && note !== undefined && String(note).trim() !== '');
            }

            if (typeof notes === 'string') {
                try {
                    const parsed = JSON.parse(notes);
                    return Array.isArray(parsed)
                        ? parsed.filter(note => note !== null && note !== undefined && String(note).trim() !== '')
                        : [notes];
                } catch (error) {
                    return notes.trim() ? [notes] : [];
                }
            }

            return [];
        },
        hasAdditionalDetails() {
            return Boolean(
                this.record?.description
                || this.record?.reason
                || this.record?.state
                || this.record?.brand
                || this.record?.equipment
                || this.record?.activities
                || this.importantNotes.length
            );
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
                    description: row.description || itemData?.description || itemData?.name || '—',
                    quantity,
                    unit_price: unitPrice,
                    subtotal
                };
            });
        },
        canEdit() {
            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(this.buildActionRow());
            }

            return !this.record?.has_document_sale_note;
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
            this.activeTab = 'pieces';
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
                customer_name: this.initialRow.customer_name,
                customer_number: this.initialRow.customer_number,
                serial_number: this.initialRow.serial_number,
                date_of_issue: this.initialRow.date_of_issue,
                cost: this.initialRow.cost,
                total: this.initialRow.total,
                sum_total: this.initialRow.sum_total,
                balance: this.initialRow.balance,
                cellphone: this.initialRow.cellphone,
                has_document_sale_note: this.initialRow.has_document_sale_note,
                number_document_sale_note: this.initialRow.number_document_sale_note
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

                    if (!data || !data.id) {
                        throw new Error('missing technical service');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const customer = this.parseCustomer(data.customer);

                    this.record = {
                        ...snapshot,
                        ...data,
                        customer: customer || snapshot.customer,
                        customer_name: data.customer_name || customer?.name || snapshot.customer_name,
                        customer_number: data.customer_number || customer?.number || snapshot.customer_number,
                        sum_total: data.sum_total ?? snapshot.sum_total,
                        balance: data.balance ?? snapshot.balance,
                        items: data.items || []
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del servicio técnico.');
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
                has_document_sale_note: this.record?.has_document_sale_note
            };
        },
        clickPrint() {
            if (!this.record?.id) {
                return;
            }

            window.open(`/${this.resource}/print/${this.record.id}/a4`, '_blank');
        },
        parseCustomer(value) {
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
            this.activeTab = 'pieces';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.technical-service-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.technical-service-detail-drawer__header {
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

.technical-service-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.technical-service-detail-drawer__close {
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

.technical-service-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.technical-service-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.technical-service-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.technical-service-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.technical-service-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.technical-service-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.technical-service-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.technical-service-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.technical-service-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.technical-service-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.technical-service-detail-drawer__body >>> .el-tabs__nav-wrap::after {
    height: 1px;
    background-color: #ebeef5;
}

.technical-service-detail-drawer__body >>> .el-tabs__item {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.technical-service-detail-drawer__body >>> .el-tabs__item.is-active {
    color: #1f3a8a;
}

.technical-service-detail-drawer__body >>> .el-tabs__active-bar {
    background-color: #1f3a8a;
}

.technical-service-detail-drawer__list {
    margin: 0 0 8px;
}

.technical-service-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.technical-service-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.technical-service-detail-drawer__list dd.text-pre-wrap {
    white-space: pre-wrap;
}

.technical-service-detail-drawer__notes li {
    margin-bottom: 4px;
}

.technical-service-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 10px;
    vertical-align: middle;
}

.technical-service-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
    padding: 8px 10px;
}

.technical-service-detail-drawer__footer {
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
.technical-service-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.technical-service-detail-drawer .theme-sidebar-header.technical-service-detail-drawer__header {
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

.technical-service-detail-drawer .technical-service-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
