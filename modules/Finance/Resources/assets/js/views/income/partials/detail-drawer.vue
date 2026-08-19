<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer income-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner income-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header income-detail-drawer__header">
                <div class="detail-drawer__title income-detail-drawer__title">
                    <h5>Detalle del ingreso</h5>
                    <small v-if="record" class="income-detail-drawer__identifier">{{ incomeIdentifier }}</small>
                    <div v-if="record" class="detail-drawer__header-meta income-detail-drawer__header-meta">
                        <span
                            class="badge detail-drawer__state-badge income-detail-drawer__state-badge"
                            :class="stateBadgeClass"
                        >
                            {{ stateLabel }}
                        </span>
                        <small class="detail-drawer__header-date income-detail-drawer__header-date text-dark">{{ issueDateLabel }}</small>
                    </div>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar income-detail-drawer__status-bar">
                    <small class="text-muted">{{ reasonLabel }}</small>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body income-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Detalles / Ítems" name="items">
                            <div class="detail-drawer__section-card income-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header income-detail-drawer__section-card-header">
                                    <h5 class="section-title">Detalles / Ítems</h5>
                                    <p class="section-subtitle">Desglose adicional del ingreso</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table income-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Descripción</th>
                                                <th class="text-end">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in lineItems" :key="item.key || index">
                                                <td>{{ item.description }}</td>
                                                <td class="text-end">{{ formatMoney(item.total, false) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin ítems adicionales registrados.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="detail-drawer__section-card income-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header income-detail-drawer__section-card-header">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Motivo, moneda, tipo de cambio y distribución del ingreso</p>
                                </div>
                                <dl class="detail-drawer__list income-detail-drawer__list">
                                    <dt>Motivo del ingreso</dt>
                                    <dd>{{ reasonLabel }}</dd>

                                    <dt>Tipo comprobante</dt>
                                    <dd>{{ documentTypeLabel }}</dd>

                                    <dt>Moneda</dt>
                                    <dd>{{ currencyLabel }}</dd>

                                    <dt>Tipo de cambio</dt>
                                    <dd>{{ exchangeRateLabel }}</dd>
                                </dl>

                                <div v-if="distributionRows.length" class="table-responsive mt-2">
                                    <table class="table table-sm detail-drawer__items-table income-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Método</th>
                                                <th>Destino</th>
                                                <th>Referencia</th>
                                                <th class="text-end">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in distributionRows" :key="row.id">
                                                <td>{{ row.payment_method_type_description || '—' }}</td>
                                                <td>{{ row.destination_description || '—' }}</td>
                                                <td>{{ row.reference || '—' }}</td>
                                                <td class="text-end">{{ formatMoney(row.payment, false) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin distribución registrada.</p>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--stacked income-detail-drawer__footer">
                    <div class="detail-drawer__actions income-detail-drawer__actions">
                        <button
                            type="button"
                            class="btn btn-outline-info btn-sm"
                            @click="clickPrint"
                        >
                            <i class="fa fa-print"></i> Imprimir
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            @click="$emit('payments', record.id)"
                        >
                            <i class="fa fa-search"></i> Dist. ingreso
                        </button>
                        <button
                            v-if="canVoid"
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="voiding"
                            @click="clickVoid"
                        >
                            <i class="fa fa-trash"></i> Anular ingreso
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
            default: 'finances/income'
        }
    },
    data() {
        return {
            loading: false,
            voiding: false,
            record: null,
            activeTab: 'items'
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
        incomeIdentifier() {
            const number = this.record?.number || `#${this.record?.id || ''}`;
            const docType = this.documentTypeLabel;

            return docType && docType !== '—' ? `${number} · ${docType}` : number;
        },
        documentTypeLabel() {
            return this.record?.income_type_description
                || this.record?.income_type?.description
                || '—';
        },
        reasonLabel() {
            return this.record?.income_reason_description
                || this.record?.income_reason?.description
                || '—';
        },
        stateLabel() {
            return this.record?.state_type_description
                || this.record?.state_type?.description
                || '—';
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
        distributionRows() {
            return Array.isArray(this.record?.payments) ? this.record.payments : [];
        },
        lineItems() {
            const items = Array.isArray(this.record?.items) ? this.record.items : [];

            return items.map((row, index) => ({
                key: row.id || index,
                description: row.description || '—',
                total: this.parseAmount(row.total)
            }));
        },
        canVoid() {
            return String(this.record?.state_type_id) === '05';
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

            this.record = {
                ...this.initialRow,
                number: this.initialRow.number,
                customer_name: this.initialRow.customer_name,
                income_type_description: this.initialRow.income_type_description,
                income_reason_description: this.initialRow.income_reason_description,
                state_type_id: this.initialRow.state_type_id,
                currency_type_id: this.initialRow.currency_type_id,
                total: this.initialRow.total,
                payments: [],
                items: []
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
                    const income = data?.income;

                    if (!income && !data) {
                        throw new Error('missing income');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    this.record = {
                        ...snapshot,
                        id: data.id || income?.id,
                        external_id: data.external_id || income?.external_id,
                        number: data.number || income?.number || snapshot.number,
                        date_of_issue: data.date_of_issue || income?.date_of_issue,
                        state_type_id: income?.state_type_id ?? data.state_type_id ?? snapshot.state_type_id,
                        state_type_description: income?.state_type?.description
                            || snapshot.state_type_description,
                        state_type: income?.state_type,
                        income_type_description: income?.income_type?.description
                            || snapshot.income_type_description,
                        income_reason_description: income?.income_reason?.description
                            || snapshot.income_reason_description,
                        income_type: income?.income_type,
                        income_reason: income?.income_reason,
                        customer_name: data.customer_name || income?.customer || snapshot.customer_name,
                        currency_type_id: income?.currency_type_id || snapshot.currency_type_id,
                        exchange_rate_sale: income?.exchange_rate_sale,
                        total: income?.total ?? snapshot.total,
                        payments: this.normalizePayments(data.payments || income?.payments || []),
                        items: income?.items || []
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del ingreso.');
                        this.visibleDrawer = false;
                    }
                })
                .finally(() => {
                    if (String(requestedId) === String(this.recordId)) {
                        this.loading = false;
                    }
                });
        },
        clickPrint() {
            if (!this.record?.external_id) {
                return;
            }

            window.open(`/${this.resource}/print/${this.record.external_id}`, '_blank');
        },
        clickVoid() {
            if (!this.record?.id || !this.canVoid) {
                return;
            }

            this.voiding = true;

            this.voided(`/${this.resource}/voided/${this.record.id}`)
                .then(() => {
                    this.visibleDrawer = false;
                    this.$eventHub.$emit('reloadData');
                })
                .finally(() => {
                    this.voiding = false;
                });
        },
        normalizePayments(payments) {
            if (!Array.isArray(payments)) {
                return [];
            }

            return payments
                .filter(payment => payment.id)
                .map(payment => ({
                    id: payment.id,
                    payment_method_type_description: payment.payment_method_type_description
                        || payment.payment_method_type?.description
                        || null,
                    destination_description: payment.destination_description,
                    reference: payment.reference,
                    payment: payment.payment
                }));
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

            const date = moment(value, ['DD/MM/YYYY', 'DD-MM-YYYY', 'YYYY-MM-DD', moment.ISO_8601], true);
            return date.isValid() ? date.format('DD-MM-YYYY') : value;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.voiding = false;
            this.activeTab = 'items';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>
