<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer technical-service-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner technical-service-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header technical-service-detail-drawer__header">
                <div class="detail-drawer__title technical-service-detail-drawer__title">
                    <h5>Detalle del servicio</h5>
                    <small v-if="record">{{ serviceIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar technical-service-detail-drawer__status-bar">
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

                <div class="detail-drawer__body technical-service-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Piezas" name="pieces">
                            <div class="detail-drawer__section-card technical-service-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header technical-service-detail-drawer__section-card-header">
                                    <h5 class="section-title">Piezas</h5>
                                    <p class="section-subtitle">Repuestos e ítems asociados al servicio</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table technical-service-detail-drawer__items-table mb-0">
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
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado al servicio</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div class="detail-drawer__customer-avatar" aria-hidden="true">{{ customerInitials }}</div>
                                    <div class="detail-drawer__customer-identity">
                                        <strong class="detail-drawer__customer-name">{{ customerName }}</strong>
                                        <span
                                            v-if="customerDocumentTypeLabel"
                                            class="detail-drawer__customer-badge"
                                            :class="{ 'detail-drawer__customer-badge--warning': customerDocumentTypeIsWarning }"
                                        >
                                            <svg v-if="customerDocumentTypeIsWarning" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-triangle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4"/><path d="M10.363 3.591l-8.106 14.54a1.914 1.914 0 0 0 1.636 2.869h16.214a1.914 1.914 0 0 0 1.636 -2.869l-8.106 -14.54a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/></svg>
                                            {{ customerDocumentTypeLabel }}
                                        </span>
                                    </div>
                                </div>

                                <div class="detail-drawer__customer-grid">
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 8l2 0"/><path d="M15 12l2 0"/><path d="M7 16l10 0"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Documento</span><strong>{{ customerDocumentNumber }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Celular</span><strong>{{ customerCellphone }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Correo</span><strong>{{ customerEmail }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M12.783 21.326a2 2 0 0 1 -2.196 -.426l-4.244 -4.243a8 8 0 1 1 13.657 -5.62"/><path d="M15 19l2 2l4 -4"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Dirección</span><strong>{{ customerAddress }}</strong></div>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Costos y totales" name="totals">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Costos y totales</h5>
                                    <p class="section-subtitle">Importes del servicio técnico</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-briefcase-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 9a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9" /><path d="M8 7v-2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v2" /></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Servicio</span><strong>{{ formatMoney(record.cost) }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-package"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5v9l-8 4.5l-8 -4.5v-9z"/><path d="M12 12l8 -4.5"/><path d="M12 12v9"/><path d="M12 12l-8 -4.5"/><path d="M16 5.25l-8 4.5"/></svg></span>
                                        <div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Piezas</span><strong>{{ formatMoney(record.total) }}</strong></div>
                                    </div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Moneda</span><strong>{{ currencyLabel }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash-banknote"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M3 8a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -8" /><path d="M18 12h.01" /><path d="M6 12h.01" /></svg></span>
                                        <span class="detail-drawer__totals-row-label">Pagos registrados</span><strong>{{ formatMoney(totalPayments) }}</strong>
                                    </div>
                                    <div v-if="prepaymentAmount > 0" class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Adelanto declarado</span><strong>{{ formatMoney(prepaymentAmount) }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-row detail-drawer__totals-row--balance">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/><path d="M20 12v4h-4a2 2 0 0 1 0 -4z"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Saldo pendiente</span>
                                        <span class="detail-drawer__totals-status" :class="balanceIsPaid ? 'detail-drawer__totals-status--paid' : 'detail-drawer__totals-status--pending'">
                                            <svg v-if="balanceIsPaid" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>
                                            {{ balanceStatusLabel }}
                                        </span>
                                    </div>
                                    <div v-if="record.number_document_sale_note" class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-invoice"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 7l1 0"/><path d="M9 13l6 0"/><path d="M13 17l2 0"/></svg></span>
                                        <span class="detail-drawer__totals-row-label">Comprobante asociado</span><strong>{{ record.number_document_sale_note }}</strong>
                                    </div>
                                    <div class="detail-drawer__totals-total"><span>Total</span><strong><small>{{ currencySymbol }}</small> {{ formatMoney(sumTotalAmount, false) }}</strong></div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Detalles adicionales" name="additional">
                            <div class="detail-drawer__details-section">
                                <div class="detail-drawer__details-heading">
                                    <h5 class="section-title">Detalles adicionales</h5>
                                    <p class="section-subtitle">Descripción, fallas reportadas y diagnóstico</p>
                                </div>
                                <div v-if="hasAdditionalDetails" class="detail-drawer__details-grid">
                                    <div v-if="record.equipment" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Equipo</span><strong>{{ record.equipment }}</strong></div>
                                    <div v-if="record.brand" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Marca</span><strong>{{ record.brand }}</strong></div>
                                    <div v-if="record.description" class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Descripción</span><p>{{ record.description }}</p></div>
                                    <div v-if="record.reason" class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Motivo de ingreso</span><p>{{ record.reason }}</p></div>
                                    <div v-if="record.state" class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Estado del equipo</span><p>{{ record.state }}</p></div>
                                    <div v-if="record.activities" class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Actividades realizadas</span><p>{{ record.activities }}</p></div>
                                    <div v-if="importantNotes.length" class="detail-drawer__details-card detail-drawer__details-card--wide detail-drawer__details-card--notice">
                                        <span class="detail-drawer__details-label">Notas importantes</span>
                                        <ul class="detail-drawer__details-notes"><li v-for="(note, index) in importantNotes" :key="index">{{ note }}</li></ul>
                                    </div>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin detalles adicionales.</p>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions detail-drawer__footer--wrap technical-service-detail-drawer__footer">
                    <button
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="clickPrint"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" /><path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" /><path d="M17 18h2" /><path d="M20 15h-3v6" /><path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1" /></svg>
                        Ver PDF
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('payments', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash-banknote" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M3 8a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -8" /><path d="M18 12h.01" /><path d="M6 12h.01" /></svg>
                        Pagos
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar servicio
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
        customerData() {
            return this.parseCustomer(this.record?.customer) || {};
        },
        customerName() {
            return this.customerData.name
                || this.record?.customer_name
                || '—';
        },
        customerInitials() {
            const words = String(this.customerName || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length) {
                return 'CL';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        customerDocumentNumber() {
            return this.customerData.number
                || this.record?.customer_number
                || '—';
        },
        customerDocumentTypeLabel() {
            return this.customerData.identity_document_type?.description
                || this.customerData.document_type
                || this.record?.customer_identity_document_type_description
                || null;
        },
        customerDocumentTypeIsWarning() {
            const typeId = this.customerData.identity_document_type_id
                || this.customerData.identity_document_type?.id
                || this.record?.customer_identity_document_type_id;
            const label = String(this.customerDocumentTypeLabel || '').toLowerCase();

            return String(typeId) === '0' || label.includes('no domiciliado');
        },
        customerCellphone() {
            return this.record?.cellphone
                || this.customerData.telephone
                || this.customerData.cellphone
                || '—';
        },
        customerEmail() {
            return this.customerData.email || '—';
        },
        customerAddress() {
            return this.customerData.address
                || this.customerData.direccion
                || '—';
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
            if (this.record?.balance !== undefined && this.record?.balance !== null) {
                return Math.max(this.parseAmount(this.record.balance), 0);
            }

            return Math.max(this.sumTotalAmount - this.totalPayments, 0);
        },
        totalPayments() {
            const payments = Array.isArray(this.record?.payments) ? this.record.payments : [];
            return payments.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
        },
        prepaymentAmount() {
            return this.parseAmount(this.record?.prepayment);
        },
        balanceIsPaid() {
            return this.balanceAmount < 0.01;
        },
        balanceStatusLabel() {
            const status = this.balanceIsPaid ? 'Pagado' : 'Pendiente';
            return `${this.formatMoney(this.balanceAmount)} · ${status}`;
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
