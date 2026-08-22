<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer expense-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner expense-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header expense-detail-drawer__header">
                <div class="detail-drawer__title expense-detail-drawer__title">
                    <h5>Detalle del gasto</h5>
                    <small v-if="record" class="expense-detail-drawer__identifier">{{ expenseIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar expense-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge" :class="stateBadgeClass">{{ stateLabel }}</span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body expense-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Detalles / Ítems" name="items">
                            <div class="detail-drawer__section-card expense-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header expense-detail-drawer__section-card-header">
                                    <h5 class="section-title">Detalles / Ítems</h5>
                                    <p class="section-subtitle">Desglose adicional del gasto</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table expense-detail-drawer__items-table mb-0">
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

                        <el-tab-pane label="Proveedor" name="supplier">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Proveedor</h5>
                                    <p class="section-subtitle">Datos del proveedor asociado al gasto</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div class="detail-drawer__customer-avatar" aria-hidden="true">{{ supplierInitials }}</div>
                                    <div class="detail-drawer__customer-identity">
                                        <strong class="detail-drawer__customer-name">{{ supplierName }}</strong>
                                        <span v-if="supplierDocumentTypeLabel" class="detail-drawer__customer-badge">{{ supplierDocumentTypeLabel }}</span>
                                    </div>
                                </div>

                                <div class="detail-drawer__customer-grid">
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 8l2 0"/><path d="M15 12l2 0"/><path d="M7 16l10 0"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Documento</span><strong>{{ supplierDocumentNumber }}</strong></div></div>
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Teléfono</span><strong>{{ supplierTelephone || '—' }}</strong></div></div>
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Correo</span><strong>{{ supplierEmail || '—' }}</strong></div></div>
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M12.783 21.326a2 2 0 0 1 -2.196 -.426l-4.244 -4.243a8 8 0 1 1 13.657 -5.62"/><path d="M15 19l2 2l4 -4"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Dirección</span><strong>{{ supplierAddress }}</strong></div></div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="detail-drawer__operation-section">
                                <div class="detail-drawer__operation-heading">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Motivo, moneda, tipo de cambio y distribución del gasto</p>
                                </div>

                                <div class="detail-drawer__operation-flow">
                                    <div class="detail-drawer__operation-card"><span class="detail-drawer__operation-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-category"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4h6v6h-6z"/><path d="M14 4h6v6h-6z"/><path d="M4 14h6v6h-6z"/><path d="M17 14l3 3l-3 3l-3 -3z"/></svg></span><div class="detail-drawer__operation-card-content"><span class="detail-drawer__operation-label">Motivo del gasto</span><strong>{{ reasonLabel }}</strong></div></div>
                                    <span class="detail-drawer__operation-arrow" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M13 18l6 -6"/><path d="M13 6l6 6"/></svg></span>
                                    <div class="detail-drawer__operation-card"><span class="detail-drawer__operation-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-invoice"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 7l1 0"/><path d="M9 13l6 0"/><path d="M13 17l2 0"/></svg></span><div class="detail-drawer__operation-card-content"><span class="detail-drawer__operation-label">Tipo de comprobante</span><strong>{{ documentTypeLabel || '—' }}</strong></div></div>
                                </div>

                                <div class="detail-drawer__operation-note detail-drawer__operation-note--observation"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coins" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 14c0 1.657 2.686 3 6 3s6 -1.343 6 -3s-2.686 -3 -6 -3s-6 1.343 -6 3z"/><path d="M9 14v4c0 1.656 2.686 3 6 3s6 -1.344 6 -3v-4"/><path d="M3 6c0 1.072 1.144 2.062 3 2.598s4.144 .536 6 0c1.856 -.536 3 -1.526 3 -2.598c0 -1.072 -1.144 -2.062 -3 -2.598s-4.144 -.536 -6 0c-1.856 .536 -3 1.526 -3 2.598z"/><path d="M3 6v4c0 .888 .772 1.716 2 2.282"/><path d="M15 6v2"/></svg><span>{{ issuanceCurrencyLabel }}</span></div>

                                <div class="detail-drawer__section-card mt-3 mb-0">
                                    <div class="detail-drawer__section-card-header"><h5 class="section-title">Distribución del gasto</h5><p class="section-subtitle">Métodos, destinos y referencias registrados</p></div>
                                    <div v-if="distributionRows.length" class="table-responsive">
                                        <table class="table table-sm detail-drawer__items-table expense-detail-drawer__items-table mb-0">
                                            <thead><tr><th>Método</th><th>Destino</th><th>Referencia</th><th class="text-end">Monto</th></tr></thead>
                                            <tbody><tr v-for="row in distributionRows" :key="row.id"><td>{{ row.expense_method_type_description || '—' }}</td><td>{{ row.destination_description || '—' }}</td><td>{{ row.reference || '—' }}</td><td class="text-end">{{ formatMoney(row.payment, false) }}</td></tr></tbody>
                                        </table>
                                    </div>
                                    <p v-else class="text-muted small mb-0">Sin distribución registrada.</p>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Totales y pagos" name="totals">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Totales y pagos</h5>
                                    <p class="section-subtitle">Importe total, saldo pendiente e historial de pagos</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card"><span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span><div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Moneda</span><strong>{{ currencyLabel }}</strong></div></div>
                                    <div class="detail-drawer__totals-summary-card"><span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 9m0 2a2 2 0 1 0 0 4a2 2 0 0 0 0 -4"/><path d="M17 9m0 2a2 2 0 1 0 0 4a2 2 0 0 0 0 -4"/><path d="M14 15a2 2 0 0 0 -4 0"/><path d="M6 6h12a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-8a2 2 0 0 1 2 -2"/></svg></span><div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Pagado</span><strong>{{ formatMoney(totalPaid) }}</strong></div></div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row detail-drawer__totals-row--balance"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/><path d="M20 12v4h-4a2 2 0 0 1 0 -4z"/></svg></span><span class="detail-drawer__totals-row-label">Saldo pendiente</span><span class="detail-drawer__totals-status" :class="balanceIsPaid ? 'detail-drawer__totals-status--paid' : 'detail-drawer__totals-status--pending'"><svg v-if="balanceIsPaid" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>{{ balanceStatusLabel }}</span></div>
                                    <div class="detail-drawer__totals-total"><span>Total</span><strong><small>{{ currencySymbol }}</small> {{ formatMoney(totalAmount, false) }}</strong></div>
                                </div>

                                <div class="detail-drawer__section-card mt-3 mb-0">
                                    <div class="detail-drawer__section-card-header"><h5 class="section-title">Pagos registrados</h5><p class="section-subtitle">Detalle de fechas, métodos y destinos</p></div>
                                    <div v-if="paymentRows.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table expense-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Método</th>
                                                <th>Destino</th>
                                                <th class="text-end">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="payment in paymentRows" :key="payment.id">
                                                <td>{{ formatDisplayDate(payment.date_of_payment) }}</td>
                                                <td>{{ payment.expense_method_type_description || '—' }}</td>
                                                <td>
                                                    {{ payment.destination_description || '—' }}
                                                    <small v-if="payment.reference" class="d-block text-muted">
                                                        Ref: {{ payment.reference }}
                                                    </small>
                                                </td>
                                                <td class="text-end">{{ formatMoney(payment.payment, false) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>
                                    <p v-else class="text-muted small mb-0">Sin pagos registrados.</p>
                                </div>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--stacked expense-detail-drawer__footer">
                    <div class="detail-drawer__actions expense-detail-drawer__actions">
                        <button
                            type="button"
                            class="btn btn-outline-info btn-sm"
                            @click="clickPrint"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-printer" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" /><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" /><path d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" /></svg>
                            Imprimir
                        </button>
                        <button
                            v-if="canEdit"
                            type="button"
                            class="btn btn-custom btn-sm"
                            @click="$emit('edit', record.id)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                            Editar gasto
                        </button>
                        <button
                            v-if="canPayments"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            @click="$emit('payments', record.id)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash-banknote" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M3 8a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -8" /><path d="M18 12h.01" /><path d="M6 12h.01" /></svg>
                            Pagos
                        </button>
                        <button
                            v-if="canVoid"
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="voiding"
                            @click="clickVoid"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            Eliminar gasto
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
            default: 'expenses'
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
        expenseIdentifier() {
            const number = this.record?.number || `#${this.record?.id || ''}`;
            const docType = this.documentTypeLabel;

            return docType && docType !== '—' ? `${number} · ${docType}` : number;
        },
        documentTypeLabel() {
            return this.record?.expense_type_description
                || this.record?.expense_type?.description
                || null;
        },
        reasonLabel() {
            return this.record?.expense_reason_description
                || this.record?.expense_reason?.description
                || '—';
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
        supplierData() {
            return this.parseSupplier(this.record?.supplier) || {};
        },
        supplierName() {
            return this.record?.supplier_name || this.supplierData.name || '—';
        },
        supplierInitials() {
            const words = String(this.supplierName || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length) {
                return 'PR';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        supplierDocumentNumber() {
            return this.supplierData.number || this.record?.supplier_number || '—';
        },
        supplierDocumentTypeLabel() {
            return this.supplierData.identity_document_type?.description
                || this.supplierData.document_type
                || this.supplierData.identity_document_type_code
                || this.record?.supplier_identity_document_type_description
                || null;
        },
        supplierTelephone() {
            return this.record?.supplier_telephone
                || this.supplierData.telephone
                || null;
        },
        supplierEmail() {
            return this.record?.supplier_email
                || this.supplierData.email
                || null;
        },
        supplierAddress() {
            return this.supplierData.address
                || this.supplierData.direccion
                || '—';
        },
        currencyLabel() {
            const currencyId = this.record?.currency_type_id;
            if (!currencyId) {
                return '—';
            }

            const symbol = currencyId === 'VES' ? 'Bs.' : (currencyId === 'USD' ? '$' : currencyId);
            return `${currencyId} (${symbol})`;
        },
        currencySymbol() {
            return this.record?.currency_type_id === 'USD' ? '$' : 'Bs.';
        },
        exchangeRateLabel() {
            if (this.record?.exchange_rate_sale === undefined || this.record?.exchange_rate_sale === null) {
                return '—';
            }

            return Number(this.record.exchange_rate_sale).toLocaleString('es-VE', {
                minimumFractionDigits: 3,
                maximumFractionDigits: 4
            });
        },
        issuanceCurrencyLabel() {
            return this.exchangeRateLabel === '—'
                ? this.currencyLabel
                : `${this.currencyLabel} · T.C. ${this.exchangeRateLabel}`;
        },
        totalAmount() {
            return this.parseAmount(this.record?.total);
        },
        totalPaid() {
            if (this.record?.total_paid !== undefined && this.record?.total_paid !== null) {
                return this.parseAmount(this.record.total_paid);
            }

            return this.paymentRows.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
        },
        balanceAmount() {
            if (this.record?.total_difference !== undefined && this.record?.total_difference !== null) {
                return Math.max(0, this.parseAmount(this.record.total_difference));
            }

            return Math.max(0, this.totalAmount - this.totalPaid);
        },
        balanceIsPaid() {
            return this.balanceAmount < 0.01;
        },
        balanceStatusLabel() {
            const status = this.balanceIsPaid ? 'Pagado' : 'Pendiente';
            return `${this.formatMoney(this.balanceAmount)} · ${status}`;
        },
        distributionRows() {
            return Array.isArray(this.record?.distribution) ? this.record.distribution : [];
        },
        paymentRows() {
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
        canEdit() {
            return String(this.record?.state_type_id) !== '11';
        },
        canPayments() {
            return String(this.record?.state_type_id) !== '11';
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
            this.activeTab = 'items';
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
                supplier_name: this.initialRow.supplier_name,
                supplier_number: this.initialRow.supplier_number,
                expense_type_description: this.initialRow.expense_type_description,
                expense_reason_description: this.initialRow.expense_reason_description,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                currency_type_id: this.initialRow.currency_type_id,
                total: this.initialRow.total,
                distribution: [],
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

            Promise.all([
                this.$http.get(`/${this.resource}/record/${requestedId}`),
                this.$http.get(`/expense-payments/expense/${requestedId}`),
                this.$http.get(`/expense-payments/records/${requestedId}`)
            ])
                .then(([recordResponse, summaryResponse, paymentsResponse]) => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    const data = recordResponse.data?.data;
                    const expense = data?.expense;

                    if (!expense) {
                        throw new Error('missing expense');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const supplier = this.parseSupplier(expense.supplier);

                    this.record = {
                        ...snapshot,
                        id: data.id || expense.id,
                        external_id: data.external_id || expense.external_id,
                        number: data.number || expense.number || snapshot.number,
                        date_of_issue: data.date_of_issue || expense.date_of_issue,
                        state_type_id: expense.state_type_id ?? snapshot.state_type_id,
                        state_type_description: expense.state_type?.description ?? snapshot.state_type_description,
                        expense_type_description: expense.expense_type?.description ?? snapshot.expense_type_description,
                        expense_reason_description: expense.expense_reason?.description ?? snapshot.expense_reason_description,
                        expense_type: expense.expense_type,
                        expense_reason: expense.expense_reason,
                        supplier,
                        supplier_name: supplier?.name || snapshot.supplier_name,
                        supplier_number: supplier?.number || snapshot.supplier_number,
                        supplier_telephone: supplier?.telephone || null,
                        supplier_email: supplier?.email || null,
                        currency_type_id: expense.currency_type_id || snapshot.currency_type_id,
                        exchange_rate_sale: expense.exchange_rate_sale,
                        total: expense.total ?? snapshot.total,
                        total_paid: summaryResponse.data?.total_paid,
                        total_difference: summaryResponse.data?.total_difference,
                        distribution: this.normalizeDistribution(data.payments || []),
                        payments: this.normalizePayments(paymentsResponse.data?.data || expense.payments || []),
                        items: expense.items || []
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle del gasto.');
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

            this.voided(`/${this.resource}/${this.record.id}/voided`)
                .then(() => {
                    this.visibleDrawer = false;
                    this.$eventHub.$emit('reloadData');
                })
                .finally(() => {
                    this.voiding = false;
                });
        },
        normalizeDistribution(rows) {
            if (!Array.isArray(rows)) {
                return [];
            }

            return rows
                .filter(row => row.id)
                .map(row => ({
                    id: row.id,
                    expense_method_type_description: row.expense_method_type_description,
                    destination_description: row.destination_description,
                    reference: row.reference,
                    payment: row.payment
                }));
        },
        normalizePayments(payments) {
            if (!Array.isArray(payments)) {
                return [];
            }

            return payments
                .filter(payment => payment.id)
                .map(payment => ({
                    id: payment.id,
                    date_of_payment: payment.date_of_payment,
                    expense_method_type_description: payment.expense_method_type_description
                        || payment.expense_method_type?.description
                        || null,
                    destination_description: payment.destination_description,
                    reference: payment.reference,
                    payment: payment.payment
                }));
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
            const formatted = amount.toLocaleString('es-VE', {
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
