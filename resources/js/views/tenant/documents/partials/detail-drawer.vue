<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer document-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner document-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header document-detail-drawer__header">
                <div class="detail-drawer__title document-detail-drawer__title">
                    <h5>Detalle del comprobante</h5>
                    <small v-if="record">{{ documentIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar document-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge detail-drawer__doc-badge document-detail-drawer__doc-badge"
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

                <div class="detail-drawer__body document-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos / Servicios" name="products">
                            <div class="detail-drawer__section-card document-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header document-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos / Servicios</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en el comprobante</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table document-detail-drawer__items-table mb-0">
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
                        </el-tab-pane>

                        <el-tab-pane label="Cliente" name="customer">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del receptor del comprobante fiscal</p>
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
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Teléfono</span><strong>{{ customerTelephone || '—' }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Correo</span><strong>{{ customerEmail || '—' }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M12.783 21.326a2 2 0 0 1 -2.196 -.426l-4.244 -4.243a8 8 0 1 1 13.657 -5.62"/><path d="M15 19l2 2l4 -4"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Dirección fiscal</span><strong>{{ customerAddress || '—' }}</strong></div>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="detail-drawer__operation-section">
                                <div class="detail-drawer__operation-heading">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Asignación comercial y condiciones de emisión</p>
                                </div>

                                <div class="detail-drawer__operation-seller">
                                    <div class="detail-drawer__operation-avatar" aria-hidden="true">{{ sellerInitials }}</div>
                                    <div class="detail-drawer__operation-seller-content"><span class="detail-drawer__operation-label">Vendedor</span><strong>{{ sellerLabel }}</strong></div>
                                    <span v-if="sellerAccountLabel" class="detail-drawer__operation-badge"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3c3.866 0 7 1.79 7 4v5c0 4.418 -3.134 8 -7 9c-3.866 -1 -7 -4.582 -7 -9v-5c0 -2.21 3.134 -4 7 -4z"/></svg>{{ sellerAccountLabel }}</span>
                                </div>

                                <div class="detail-drawer__operation-flow">
                                    <div class="detail-drawer__operation-card">
                                        <span class="detail-drawer__operation-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-store"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0"/><path d="M5 21v-14l8 -4v18"/><path d="M19 21v-10l-6 -4"/><path d="M9 9l0 .01"/><path d="M9 12l0 .01"/><path d="M9 15l0 .01"/><path d="M9 18l0 .01"/></svg></span>
                                        <div class="detail-drawer__operation-card-content"><span class="detail-drawer__operation-label">Establecimiento</span><strong>{{ establishmentDisplayLabel }}</strong></div>
                                    </div>
                                    <span class="detail-drawer__operation-arrow" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M13 18l6 -6"/><path d="M13 6l6 6"/></svg></span>
                                    <div class="detail-drawer__operation-card">
                                        <span class="detail-drawer__operation-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coins"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 14c0 1.657 2.686 3 6 3s6 -1.343 6 -3s-2.686 -3 -6 -3s-6 1.343 -6 3z"/><path d="M9 14v4c0 1.656 2.686 3 6 3s6 -1.344 6 -3v-4"/><path d="M3 6c0 1.072 1.144 2.062 3 2.598s4.144 .536 6 0c1.856 -.536 3 -1.526 3 -2.598c0 -1.072 -1.144 -2.062 -3 -2.598s-4.144 -.536 -6 0c-1.856 .536 -3 1.526 -3 2.598z"/><path d="M3 6v4c0 .888 .772 1.716 2 2.282"/><path d="M3 10v4c0 .888 .772 1.716 2 2.282"/><path d="M3 14v4c0 .888 .772 1.716 2 2.282"/><path d="M15 6v2"/></svg></span>
                                        <div class="detail-drawer__operation-card-content"><span class="detail-drawer__operation-label">Moneda / T. cambio</span><strong>{{ issuanceCurrencyLabel }}</strong></div>
                                    </div>
                                </div>

                                <div class="detail-drawer__operation-note"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-event" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M16 3l0 4"/><path d="M8 3l0 4"/><path d="M4 11l16 0"/><path d="M8 15h2v2h-2z"/></svg><span>Fecha de emisión: {{ issueDateLabel }}</span></div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Totales y pagos" name="totals">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Totales y pagos</h5>
                                    <p class="section-subtitle">Importes gravados, IGV, saldo y pagos registrados</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card"><span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span><div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Moneda</span><strong>{{ currencyLabel }}</strong></div></div>
                                    <div class="detail-drawer__totals-summary-card"><span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 9m0 2a2 2 0 1 0 0 4a2 2 0 0 0 0 -4"/><path d="M17 9m0 2a2 2 0 1 0 0 4a2 2 0 0 0 0 -4"/><path d="M14 15a2 2 0 0 0 -4 0"/><path d="M6 6h12a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-8a2 2 0 0 1 2 -2"/></svg></span><div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Pagado</span><strong>{{ formatMoney(totalPayments) }}</strong></div></div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/></svg></span><span class="detail-drawer__totals-row-label">Op. gravada</span><strong>{{ formatMoney(record.total_taxed) }}</strong></div>
                                    <div class="detail-drawer__totals-row"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-percentage"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M7 7m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M6 18l12 -12"/></svg></span><span class="detail-drawer__totals-row-label">IGV</span><strong>{{ formatMoney(record.total_igv) }}</strong></div>
                                    <div class="detail-drawer__totals-row detail-drawer__totals-row--balance"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/><path d="M20 12v4h-4a2 2 0 0 1 0 -4z"/></svg></span><span class="detail-drawer__totals-row-label">Saldo pendiente</span><span class="detail-drawer__totals-status" :class="balanceIsPaid ? 'detail-drawer__totals-status--paid' : 'detail-drawer__totals-status--pending'"><svg v-if="balanceIsPaid" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>{{ balanceStatusLabel }}</span></div>
                                    <div class="detail-drawer__totals-total"><span>Total</span><strong><small>{{ currencySymbol }}</small> {{ formatMoney(record.total, false) }}</strong></div>
                                </div>

                                <div class="detail-drawer__section-card mt-3 mb-0">
                                    <div class="detail-drawer__section-card-header"><h5 class="section-title">Pagos registrados</h5><p class="section-subtitle">Detalle de fechas, métodos y destinos</p></div>
                                    <div v-if="paymentRows.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table document-detail-drawer__items-table mb-0">
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
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions detail-drawer__footer--wrap document-detail-drawer__footer">
                    <button
                        v-if="record.has_pdf && record.download_pdf"
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="clickDownload(record.download_pdf)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" /><path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" /><path d="M17 18h2" /><path d="M20 15h-3v6" /><path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1" /></svg>
                        PDF
                    </button>
                    <!-- ########## INICIO CAMBIO SIN XML CDR SUNAT -->
                    <!-- El detalle conserva PDF, pero no expone XML ni CDR. -->
                    <!-- ######### FIN CAMBIO SIN XML CDR SUNAT -->
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="$emit('payments', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash-banknote" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M3 8a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -8" /><path d="M18 12h.01" /><path d="M6 12h.01" /></svg>
                        Pagos
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('options', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                        Opciones
                    </button>
                    <button
                        v-if="canVoid"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        @click="$emit('voided', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-x" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M10 10l4 4m0 -4l-4 4" /></svg>
                        Anular
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

            return 'detail-drawer__doc-badge--default document-detail-drawer__doc-badge--default';
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
            return this.record?.customer_number || '—';
        },
        customerDocumentTypeLabel() {
            return this.record?.customer_identity_document_type_description || null;
        },
        customerDocumentTypeIsWarning() {
            return String(this.customerDocumentTypeLabel || '').toLowerCase().includes('no domiciliado');
        },
        customerTelephone() {
            return this.record?.customer?.telephone
                || this.record?.customer_telephone
                || null;
        },
        customerEmail() {
            return this.record?.customer?.email
                || this.record?.customer_email
                || null;
        },
        customerAddress() {
            return this.record?.customer_address || null;
        },
        sellerLabel() {
            return this.record?.seller_name
                || this.record?.user_name
                || '—';
        },
        sellerInitials() {
            const words = String(this.sellerLabel || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length) {
                return 'VE';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        sellerAccountLabel() {
            const seller = String(this.sellerLabel || '').toLowerCase();
            return seller.includes('administrador') || seller.includes('admin')
                ? 'Cuenta principal'
                : null;
        },
        establishmentData() {
            const establishment = this.record?.establishment;

            if (!establishment) {
                return null;
            }

            if (typeof establishment === 'string') {
                try {
                    return JSON.parse(establishment);
                } catch (error) {
                    return { description: establishment };
                }
            }

            return establishment;
        },
        establishmentLabel() {
            const establishment = this.establishmentData;
            const values = [establishment?.description, establishment?.address];
            return values.find(value => this.hasDisplayValue(value)) || '—';
        },
        establishmentDisplayLabel() {
            const establishment = this.establishmentData;
            if (!establishment) {
                return '—';
            }

            const name = [establishment.description, establishment.address]
                .find(value => this.hasDisplayValue(value));

            return [establishment.code, name]
                .filter(value => this.hasDisplayValue(value))
                .join(' — ') || '—';
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
        balanceAmount() {
            return Math.max(this.parseAmount(this.record?.balance), 0);
        },
        totalPayments() {
            if (this.record?.total_paid !== undefined && this.record?.total_paid !== null) {
                return this.parseAmount(this.record.total_paid);
            }

            return this.paymentRows.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
        },
        balanceIsPaid() {
            return this.balanceAmount < 0.01;
        },
        balanceStatusLabel() {
            const status = this.balanceIsPaid ? 'Pagado' : 'Pendiente';
            return `${this.formatMoney(this.balanceAmount)} · ${status}`;
        },
        paymentRows() {
            return Array.isArray(this.record?.payments) ? this.record.payments : [];
        },
        lineItems() {
            const items = Array.isArray(this.record?.items) ? this.record.items : [];

            return items.map((row, index) => {
                const itemData = this.parseItemData(row.item);
                const quantity = Number(row.quantity || 0);
                const unitPrice = Number(row.unit_price ?? itemData?.sale_unit_price ?? 0);
                const subtotal = row.total !== undefined && row.total !== null
                    ? Number(row.total)
                    : quantity * unitPrice;

                return {
                    key: row.id || index,
                    description: row.description
                        || itemData?.description
                        || itemData?.name
                        || row.name_product_pdf
                        || '—',
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
        hasDisplayValue(value) {
            const normalized = String(value ?? '').trim();
            return normalized !== '' && normalized !== '-' && normalized !== '—';
        },
        openDrawer() {
            this.activeTab = 'products';
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
                .then(async (response) => {
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

                    let items = Array.isArray(data.items) ? data.items : [];
                    if (!items.length) {
                        items = await this.fetchDocumentItemsFallback(requestedId);
                    }

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
                        customer_telephone: data.customer_telephone || snapshot.customer_telephone,
                        customer_email: data.customer_email || snapshot.customer_email,
                        customer_address: data.customer_address || snapshot.customer_address,
                        seller_name: data.seller_name || snapshot.seller_name,
                        user_name: data.user_name || snapshot.user_name,
                        currency_type_id: data.currency_type_id || snapshot.currency_type_id,
                        exchange_rate_sale: data.exchange_rate_sale ?? snapshot.exchange_rate_sale,
                        total_taxed: data.total_taxed ?? snapshot.total_taxed,
                        total_igv: data.total_igv ?? snapshot.total_igv,
                        total: data.total ?? snapshot.total,
                        balance: data.balance ?? snapshot.balance,
                        items,
                        payments: Array.isArray(data.payments) ? data.payments : [],
                        has_pdf: data.has_pdf ?? snapshot.has_pdf,
                        download_pdf: data.download_pdf || snapshot.download_pdf,
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
        normalizeItems(items) {
            if (!items) {
                return [];
            }

            if (Array.isArray(items)) {
                return items;
            }

            if (typeof items === 'object') {
                return Object.values(items);
            }

            return [];
        },
        extractItemsFromPayload(payload) {
            if (!payload || typeof payload !== 'object') {
                return [];
            }

            const candidates = [
                payload.items,
                payload.details,
                payload.document_items,
                payload.items_document,
                payload.document?.items,
                payload.data?.items
            ];

            for (let i = 0; i < candidates.length; i += 1) {
                const normalized = this.normalizeItems(candidates[i]);
                if (normalized.length) {
                    return normalized;
                }
            }

            return [];
        },
        fetchDocumentItemsFallback(documentId) {
            return this.$http.get(`/documents/${documentId}/show`)
                .then((response) => {
                    const raw = response.data?.data || response.data;
                    return this.extractItemsFromPayload(raw);
                })
                .catch(() => []);
        },
        parseItemData(raw) {
            if (!raw) {
                return null;
            }

            if (typeof raw === 'object') {
                return raw;
            }

            if (typeof raw === 'string') {
                try {
                    return JSON.parse(raw);
                } catch (error) {
                    return null;
                }
            }

            return null;
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
            if (!value) {
                return '—';
            }

            const date = moment(value, ['DD-MM-YYYY', 'YYYY-MM-DD', moment.ISO_8601], true);
            return date.isValid() ? date.format('DD-MM-YYYY') : value;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.activeTab = 'products';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>

<style scoped>

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
</style>
