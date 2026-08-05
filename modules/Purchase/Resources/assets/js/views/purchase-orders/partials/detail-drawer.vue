<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="purchase-order-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="purchase-order-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header purchase-order-detail-drawer__header">
                <div class="purchase-order-detail-drawer__title">
                    <h4>Detalle de la orden de compra</h4>
                    <small v-if="record" class="purchase-order-detail-drawer__identifier">{{ orderIdentifier }}</small>
                    <div v-if="record" class="purchase-order-detail-drawer__header-meta">
                        <span
                            class="badge purchase-order-detail-drawer__state-badge"
                            :class="stateBadgeClass"
                        >
                            {{ stateLabel }}
                        </span>
                        <small class="purchase-order-detail-drawer__header-date text-dark">{{ issueDateLabel }}</small>
                    </div>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar purchase-order-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="purchase-order-detail-drawer__status-bar">
                    <small class="text-muted">Orden de compra</small>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="purchase-order-detail-drawer__body">
                    <div class="purchase-order-detail-drawer__section-card">
                        <div class="purchase-order-detail-drawer__section-card-header">
                            <h5 class="section-title">Proveedor</h5>
                            <p class="section-subtitle">Datos del proveedor asociado a la orden</p>
                        </div>
                        <dl class="purchase-order-detail-drawer__list">
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

                    <div class="purchase-order-detail-drawer__section-card">
                        <div class="purchase-order-detail-drawer__section-card-header">
                            <h5 class="section-title">Información operativa</h5>
                            <p class="section-subtitle">Vigencia, moneda y tipo de cambio</p>
                        </div>
                        <dl class="purchase-order-detail-drawer__list">
                            <dt>Fecha de vencimiento</dt>
                            <dd>{{ dueDateLabel }}</dd>

                            <dt>Moneda</dt>
                            <dd>{{ currencyLabel }}</dd>

                            <dt>Tipo de cambio</dt>
                            <dd>{{ exchangeRateLabel }}</dd>

                            <dt v-if="record.sale_opportunity_number_full">O. Venta</dt>
                            <dd v-if="record.sale_opportunity_number_full">{{ record.sale_opportunity_number_full }}</dd>

                            <dt v-if="record.observation">Observaciones</dt>
                            <dd v-if="record.observation" class="text-pre-wrap">{{ record.observation }}</dd>
                        </dl>
                    </div>

                    <div class="purchase-order-detail-drawer__section-card">
                        <div class="purchase-order-detail-drawer__section-card-header">
                            <h5 class="section-title">Totales</h5>
                            <p class="section-subtitle">Importes gravados, IGV y total de la orden</p>
                        </div>
                        <dl class="purchase-order-detail-drawer__list">
                            <dt>Gravado</dt>
                            <dd>{{ formatMoney(record.total_taxed) }}</dd>

                            <dt>IGV</dt>
                            <dd>{{ formatMoney(record.total_igv) }}</dd>

                            <dt>Total</dt>
                            <dd class="text-primary fw-bold">{{ formatMoney(record.total) }}</dd>
                        </dl>
                    </div>

                    <div class="purchase-order-detail-drawer__section-card">
                        <div class="purchase-order-detail-drawer__section-card-header">
                            <h5 class="section-title">Productos</h5>
                            <p class="section-subtitle">Detalle de ítems solicitados en la orden</p>
                        </div>
                        <div v-if="lineItems.length" class="table-responsive">
                            <table class="table table-sm purchase-order-detail-drawer__items-table mb-0">
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
                </div>

                <div class="purchase-order-detail-drawer__footer">
                    <div class="purchase-order-detail-drawer__email-row">
                        <el-input
                            v-model="emailToSend"
                            size="small"
                            placeholder="Correo del proveedor"
                        >
                            <el-button
                                slot="append"
                                icon="el-icon-message"
                                :loading="sendingEmail"
                                @click="clickSendEmail"
                            >
                                Enviar
                            </el-button>
                        </el-input>
                    </div>

                    <div class="purchase-order-detail-drawer__actions">
                        <button
                            type="button"
                            class="btn btn-outline-info btn-sm"
                            @click="clickPrint"
                        >
                            <i class="fa fa-file-pdf"></i> Imprimir A4
                        </button>
                        <button
                            v-if="record.upload_filename"
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            @click="clickDownloadAttached"
                        >
                            <i class="fa fa-download"></i> Descargar archivo
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
                            <i class="fa fa-shopping-bag"></i> Generar compra
                        </button>
                        <button
                            v-if="canAnulate"
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="voiding"
                            @click="clickAnulate"
                        >
                            <i class="fa fa-times-circle"></i> Anular
                        </button>
                    </div>
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
            default: 'purchase-orders'
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
            sendingEmail: false,
            emailToSend: '',
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
        orderIdentifier() {
            return this.record?.number
                || this.record?.number_full
                || `#${this.record?.id || ''}`;
        },
        stateLabel() {
            return this.record?.state_type_description || '—';
        },
        stateBadgeClass() {
            const stateId = String(this.record?.state_type_id || '');

            if (stateId === '11') {
                return 'bg-danger text-white';
            }

            if (stateId === '13') {
                return 'bg-warning text-dark';
            }

            if (stateId === '03') {
                return 'bg-info text-white';
            }

            if (stateId === '05') {
                return 'bg-success text-white';
            }

            if (stateId === '09') {
                return 'bg-dark text-white';
            }

            return 'bg-secondary text-white';
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        dueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_due);
        },
        supplierName() {
            return this.record?.supplier_name
                || this.parseSupplier(this.record?.supplier)?.name
                || '—';
        },
        supplierDocument() {
            const supplier = this.parseSupplier(this.record?.supplier);
            const number = supplier?.number || this.record?.supplier_number;

            if (supplier?.identity_document_type?.description && number) {
                return `${supplier.identity_document_type.description} ${number}`;
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
        exchangeRateLabel() {
            if (this.record?.exchange_rate_sale === undefined || this.record?.exchange_rate_sale === null) {
                return '—';
            }

            return Number(this.record.exchange_rate_sale).toLocaleString('es-PE', {
                minimumFractionDigits: 3,
                maximumFractionDigits: 4
            });
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
                    description: itemData?.description || itemData?.name || '—',
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

            return Boolean(this.record?.show_actions_row);
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            return Boolean(this.record?.show_actions_row);
        },
        canGenerate() {
            return Boolean(this.record?.show_actions_row);
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
        },
        supplierEmail(value) {
            if (value && !this.emailToSend) {
                this.emailToSend = value;
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
                this.emailToSend = '';
                return;
            }

            this.record = {
                ...this.initialRow,
                number: this.initialRow.number,
                supplier_name: this.initialRow.supplier_name,
                supplier_number: this.initialRow.supplier_number,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                show_actions_row: this.initialRow.show_actions_row
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
                    const purchaseOrder = data?.purchase_order;

                    if (!purchaseOrder) {
                        throw new Error('missing purchase order');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const supplier = this.parseSupplier(purchaseOrder.supplier);

                    this.record = {
                        ...snapshot,
                        id: data.id || purchaseOrder.id,
                        external_id: data.external_id || purchaseOrder.external_id,
                        number: data.number_full || snapshot.number,
                        number_full: data.number_full || snapshot.number,
                        upload_filename: data.upload_filename || purchaseOrder.upload_filename,
                        date_of_issue: data.date_of_issue || purchaseOrder.date_of_issue,
                        date_of_due: purchaseOrder.date_of_due || snapshot.date_of_due,
                        state_type_id: purchaseOrder.state_type_id ?? snapshot.state_type_id,
                        state_type_description: purchaseOrder.state_type?.description ?? snapshot.state_type_description,
                        supplier,
                        supplier_name: supplier?.name || snapshot.supplier_name,
                        supplier_number: supplier?.number || snapshot.supplier_number,
                        supplier_telephone: supplier?.telephone || null,
                        supplier_email: supplier?.email || null,
                        currency_type_id: purchaseOrder.currency_type_id || snapshot.currency_type_id,
                        exchange_rate_sale: purchaseOrder.exchange_rate_sale,
                        total_taxed: purchaseOrder.total_taxed ?? snapshot.total_taxed,
                        total_igv: purchaseOrder.total_igv ?? snapshot.total_igv,
                        total: purchaseOrder.total ?? snapshot.total,
                        sale_opportunity_number_full: snapshot.sale_opportunity_number_full
                            || purchaseOrder.sale_opportunity?.number_full
                            || '',
                        observation: purchaseOrder.observation || null,
                        show_actions_row: snapshot.show_actions_row,
                        items: purchaseOrder.items || []
                    };

                    if (this.supplierEmail && !this.emailToSend) {
                        this.emailToSend = this.supplierEmail;
                    }
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle de la orden de compra.');
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
                state_type_id: this.record?.state_type_id,
                show_actions_row: this.record?.show_actions_row
            };
        },
        clickPrint() {
            if (!this.record?.external_id) {
                return;
            }

            window.open(`/${this.resource}/print/${this.record.external_id}/a4`, '_blank');
        },
        clickDownloadAttached() {
            if (!this.record?.external_id) {
                return;
            }

            window.open(`/${this.resource}/download-attached/${this.record.external_id}`, '_blank');
        },
        clickSendEmail() {
            if (!this.record?.id) {
                return;
            }

            if (!this.emailToSend || !String(this.emailToSend).trim()) {
                this.$message.warning('Ingrese un correo electrónico válido.');
                return;
            }

            this.sendingEmail = true;

            this.$http.post(`/${this.resource}/email`, {
                customer_email: this.emailToSend,
                id: this.record.id
            })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success('El correo fue enviado satisfactoriamente');
                    } else {
                        this.$message.error('Error al enviar el correo');
                    }
                })
                .catch(error => {
                    if (error.response?.status === 422 && error.response.data?.errors) {
                        this.$message.error('Verifique el correo ingresado.');
                    } else {
                        this.$message.error(error.response?.data?.message || 'Error al enviar el correo');
                    }
                })
                .finally(() => {
                    this.sendingEmail = false;
                });
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
            if (!value || value === '-') {
                return '—';
            }

            const date = moment(value, ['DD-MM-YYYY', 'YYYY-MM-DD', moment.ISO_8601], true);
            return date.isValid() ? date.format('DD-MM-YYYY') : value;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.voiding = false;
            this.sendingEmail = false;
            this.emailToSend = '';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.purchase-order-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.purchase-order-detail-drawer__header {
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

.purchase-order-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.purchase-order-detail-drawer__close {
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

.purchase-order-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.purchase-order-detail-drawer__title small.purchase-order-detail-drawer__identifier {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.purchase-order-detail-drawer__header-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.purchase-order-detail-drawer__header-date {
    color: #334155 !important;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.2;
}

.purchase-order-detail-drawer__state-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    padding: 0.35em 0.65em;
    border-radius: 999px;
    vertical-align: middle;
}

.purchase-order-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.purchase-order-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.purchase-order-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.purchase-order-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.purchase-order-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.purchase-order-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.purchase-order-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.purchase-order-detail-drawer__list {
    margin: 0 0 8px;
}

.purchase-order-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.purchase-order-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.purchase-order-detail-drawer__list dd.text-pre-wrap {
    white-space: pre-wrap;
}

.purchase-order-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.purchase-order-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.purchase-order-detail-drawer__footer {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #ebeef5;
    background: #fff;
}

.purchase-order-detail-drawer__email-row {
    width: 100%;
}

.purchase-order-detail-drawer__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
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
.purchase-order-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.purchase-order-detail-drawer .theme-sidebar-header.purchase-order-detail-drawer__header {
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

.purchase-order-detail-drawer .purchase-order-detail-drawer__header-date {
    color: #334155 !important;
}

.purchase-order-detail-drawer .purchase-order-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
