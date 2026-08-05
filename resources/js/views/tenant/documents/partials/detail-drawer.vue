<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="document-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="document-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header document-detail-drawer__header">
                <div class="document-detail-drawer__title">
                    <h4>Detalle del comprobante</h4>
                    <small v-if="record">{{ documentIdentifier }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar document-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div class="document-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge document-detail-drawer__doc-badge"
                            :class="documentTypeBadgeClass"
                        >
                            {{ documentTypeLabel }}
                        </span>
                        <span class="badge" :class="stateBadgeClass">
                            {{ stateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="document-detail-drawer__body">
                    <div class="document-detail-drawer__section-card">
                        <div class="document-detail-drawer__section-card-header">
                            <h5 class="section-title">Cliente</h5>
                            <p class="section-subtitle">Datos del receptor del comprobante fiscal</p>
                        </div>
                        <dl class="document-detail-drawer__list">
                            <dt>Nombre / Razón social</dt>
                            <dd>{{ customerName }}</dd>

                            <dt>Documento</dt>
                            <dd>{{ customerDocument }}</dd>

                            <dt v-if="customerAddress">Dirección fiscal</dt>
                            <dd v-if="customerAddress">{{ customerAddress }}</dd>
                        </dl>
                    </div>

                    <div class="document-detail-drawer__section-card">
                        <div class="document-detail-drawer__section-card-header">
                            <h5 class="section-title">Información operativa</h5>
                            <p class="section-subtitle">Asignación comercial y condiciones de emisión</p>
                        </div>
                        <dl class="document-detail-drawer__list">
                            <dt>Vendedor</dt>
                            <dd>{{ sellerLabel }}</dd>

                            <dt>Establecimiento</dt>
                            <dd>{{ establishmentLabel }}</dd>

                            <dt>Moneda</dt>
                            <dd>{{ currencyLabel }}</dd>

                            <dt>Tipo de cambio</dt>
                            <dd>{{ exchangeRateLabel }}</dd>
                        </dl>
                    </div>

                    <div class="document-detail-drawer__section-card">
                        <div class="document-detail-drawer__section-card-header">
                            <h5 class="section-title">Totales y pagos</h5>
                            <p class="section-subtitle">Importes gravados, IGV, saldo y pagos registrados</p>
                        </div>
                        <dl class="document-detail-drawer__list">
                            <dt>Gravado</dt>
                            <dd>{{ formatMoney(record.total_taxed) }}</dd>

                            <dt>IGV</dt>
                            <dd>{{ formatMoney(record.total_igv) }}</dd>

                            <dt>Total</dt>
                            <dd class="text-primary fw-bold">{{ formatMoney(record.total) }}</dd>

                            <dt>Saldo</dt>
                            <dd :class="{ 'text-warning fw-bold': balanceAmount > 0, 'text-success': balanceAmount === 0 }">
                                {{ formatMoney(balanceAmount) }}
                            </dd>
                        </dl>

                        <div v-if="paymentRows.length" class="table-responsive mt-2">
                            <table class="table table-sm document-detail-drawer__items-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="payment in paymentRows" :key="payment.id">
                                        <td>{{ payment.date_of_payment }}</td>
                                        <td>
                                            {{ payment.payment_method_type_description || '—' }}
                                            <small v-if="payment.destination_description" class="d-block text-muted">
                                                {{ payment.destination_description }}
                                            </small>
                                        </td>
                                        <td class="text-end">{{ formatMoney(payment.payment, false) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-muted small mb-0">Sin pagos registrados.</p>
                    </div>

                    <div class="document-detail-drawer__section-card">
                        <div class="document-detail-drawer__section-card-header">
                            <h5 class="section-title">Productos / Servicios</h5>
                            <p class="section-subtitle">Detalle de ítems incluidos en el comprobante</p>
                        </div>
                        <div v-if="lineItems.length" class="table-responsive">
                            <table class="table table-sm document-detail-drawer__items-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
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
                        <p v-else class="text-muted small mb-0">Sin ítems registrados.</p>
                    </div>
                </div>

                <div class="document-detail-drawer__footer">
                    <button
                        v-if="record.has_pdf && record.download_pdf"
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="clickDownload(record.download_pdf)"
                    >
                        <i class="fa fa-file-pdf"></i> PDF
                    </button>
                    <button
                        v-if="record.has_xml && record.download_xml"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="clickDownload(record.download_xml)"
                    >
                        <i class="fa fa-file-code"></i> XML
                    </button>
                    <button
                        v-if="record.has_cdr && record.download_cdr"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="clickDownload(record.download_cdr)"
                    >
                        <i class="fa fa-file-archive"></i> CDR
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="$emit('payments', record.id)"
                    >
                        <i class="fa fa-money-bill-wave"></i> Pagos
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('options', record.id)"
                    >
                        <i class="fa fa-cog"></i> Opciones
                    </button>
                    <button
                        v-if="canVoid"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        @click="$emit('voided', record.id)"
                    >
                        <i class="fa fa-times-circle"></i> Anular
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
            default: 'documents'
        }
    },
    data() {
        return {
            loading: false,
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
        documentIdentifier() {
            return this.record?.number
                || this.record?.number_full
                || `#${this.record?.id || ''}`;
        },
        documentTypeLabel() {
            return this.record?.document_type_description || 'Comprobante';
        },
        documentTypeBadgeClass() {
            const typeId = String(this.record?.document_type_id || '');

            if (typeId === '01') {
                return 'document-detail-drawer__doc-badge--invoice';
            }

            if (typeId === '03') {
                return 'document-detail-drawer__doc-badge--ticket';
            }

            if (typeId === '07' || typeId === '08') {
                return 'document-detail-drawer__doc-badge--note';
            }

            return 'document-detail-drawer__doc-badge--default';
        },
        stateLabel() {
            return this.record?.state_type_description || '—';
        },
        stateBadgeClass() {
            const stateId = String(this.record?.state_type_id || '');

            if (stateId === '11') {
                return 'badge-danger';
            }

            if (stateId === '05') {
                return 'badge-success';
            }

            if (stateId === '13' || stateId === '07') {
                return 'badge-warning';
            }

            if (stateId === '03') {
                return 'badge-primary';
            }

            if (stateId === '09') {
                return 'badge-dark';
            }

            return 'badge-secondary';
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        customerName() {
            return this.record?.customer_name || '—';
        },
        customerDocument() {
            const docType = this.record?.customer_identity_document_type_description;
            const number = this.record?.customer_number;

            if (docType && number) {
                return `${docType}: ${number}`;
            }

            return number || '—';
        },
        customerAddress() {
            return this.record?.customer_address || null;
        },
        sellerLabel() {
            return this.record?.seller_name
                || this.record?.user_name
                || '—';
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
        balanceAmount() {
            return this.parseAmount(this.record?.balance);
        },
        paymentRows() {
            return Array.isArray(this.record?.payments) ? this.record.payments : [];
        },
        lineItems() {
            const items = Array.isArray(this.record?.items) ? this.record.items : [];

            return items.map((row, index) => {
                const quantity = Number(row.quantity || 0);
                const unitPrice = Number(row.unit_price || 0);
                const subtotal = row.total !== undefined && row.total !== null
                    ? Number(row.total)
                    : quantity * unitPrice;

                return {
                    key: row.id || index,
                    description: row.description || '—',
                    quantity,
                    unit_price: unitPrice,
                    subtotal
                };
            });
        },
        canVoid() {
            return Boolean(this.record?.btn_voided);
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

                    const data = response.data?.data;
                    if (!data) {
                        throw new Error('missing document');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    this.record = {
                        ...snapshot,
                        ...data,
                        number: data.number || snapshot.number,
                        state_type_description: data.state_type_description || snapshot.state_type_description,
                        state_type_id: data.state_type_id ?? snapshot.state_type_id,
                        customer_name: data.customer_name || snapshot.customer_name,
                        customer_number: data.customer_number || snapshot.customer_number,
                        customer_identity_document_type_description: data.customer_identity_document_type_description
                            || snapshot.customer_identity_document_type_description,
                        customer_address: data.customer_address || snapshot.customer_address,
                        seller_name: data.seller_name || snapshot.seller_name,
                        user_name: data.user_name || snapshot.user_name,
                        currency_type_id: data.currency_type_id || snapshot.currency_type_id,
                        exchange_rate_sale: data.exchange_rate_sale ?? snapshot.exchange_rate_sale,
                        total_taxed: data.total_taxed ?? snapshot.total_taxed,
                        total_igv: data.total_igv ?? snapshot.total_igv,
                        total: data.total ?? snapshot.total,
                        balance: data.balance ?? snapshot.balance,
                        items: data.items || [],
                        payments: data.payments || [],
                        has_xml: data.has_xml ?? snapshot.has_xml,
                        has_pdf: data.has_pdf ?? snapshot.has_pdf,
                        has_cdr: data.has_cdr ?? snapshot.has_cdr,
                        download_xml: data.download_xml || snapshot.download_xml,
                        download_pdf: data.download_pdf || snapshot.download_pdf,
                        download_cdr: data.download_cdr || snapshot.download_cdr,
                        btn_voided: data.btn_voided ?? snapshot.btn_voided,
                        document_type_id: data.document_type_id || snapshot.document_type_id,
                        document_type_description: data.document_type_description || snapshot.document_type_description
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del comprobante.');
                        this.visibleDrawer = false;
                    }
                })
                .finally(() => {
                    if (String(requestedId) === String(this.recordId)) {
                        this.loading = false;
                    }
                });
        },
        clickDownload(url) {
            if (!url) {
                return;
            }

            window.open(url, '_blank');
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
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>
.document-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.document-detail-drawer__header {
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

.document-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.document-detail-drawer__close {
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

.document-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.document-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.document-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.document-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.document-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.document-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.document-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.document-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

.document-detail-drawer__doc-badge {
    font-size: 11px;
    font-weight: 600;
}

.document-detail-drawer__doc-badge--invoice {
    background-color: color-mix(in srgb, var(--warning) 10%, #ffffff) !important;
    border: 1px solid var(--warning);
    color: var(--warning);
}

.document-detail-drawer__doc-badge--ticket {
    background-color: color-mix(in srgb, var(--success) 10%, #ffffff) !important;
    border: 1px solid var(--success);
    color: var(--success);
}

.document-detail-drawer__doc-badge--note {
    background-color: color-mix(in srgb, var(--info) 10%, #ffffff) !important;
    border: 1px solid var(--info);
    color: var(--info);
}

.document-detail-drawer__doc-badge--default {
    background-color: #eef2f7;
    border: 1px solid #cbd5e1;
    color: #475569;
}

.document-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.document-detail-drawer__list {
    margin: 0 0 8px;
}

.document-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.document-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
}

.document-detail-drawer__items-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.document-detail-drawer__items-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    font-size: 13px;
}

.document-detail-drawer__footer {
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
.document-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.document-detail-drawer .theme-sidebar-header.document-detail-drawer__header {
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

.document-detail-drawer .document-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
