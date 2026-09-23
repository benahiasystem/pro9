<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer sale-note-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner sale-note-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header sale-note-detail-drawer__header">
                <div class="detail-drawer__title sale-note-detail-drawer__title">
                    <h5>Detalle de la nota de venta</h5>
                    <small v-if="record">{{ saleNoteIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar sale-note-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            class="badge"
                            :class="stateBadgeClass"
                        >
                            {{ stateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body sale-note-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="detail-drawer__section-card sale-note-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header sale-note-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en la nota de venta</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table sale-note-detail-drawer__items-table mb-0">
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
                        </el-tab-pane>

                        <el-tab-pane label="Cliente" name="customer">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Cliente</h5>
                                    <p class="section-subtitle">Datos del cliente asociado a la nota de venta</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div class="detail-drawer__customer-avatar" aria-hidden="true">
                                        {{ customerInitials }}
                                    </div>
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
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 8l2 0"/><path d="M15 12l2 0"/><path d="M7 16l10 0"/></svg>
                                        </span>
                                        <div class="detail-drawer__customer-field-content">
                                            <span class="detail-drawer__customer-field-label">Documento</span>
                                            <strong>{{ customerDocumentNumber }}</strong>
                                        </div>
                                    </div>

                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg>
                                        </span>
                                        <div class="detail-drawer__customer-field-content">
                                            <span class="detail-drawer__customer-field-label">Teléfono</span>
                                            <strong>{{ customerTelephone || '—' }}</strong>
                                        </div>
                                    </div>

                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg>
                                        </span>
                                        <div class="detail-drawer__customer-field-content">
                                            <span class="detail-drawer__customer-field-label">Correo</span>
                                            <strong>{{ customerEmail || '—' }}</strong>
                                        </div>
                                    </div>

                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M12.783 21.326a2 2 0 0 1 -2.196 -.426l-4.244 -4.243a8 8 0 1 1 13.657 -5.62"/><path d="M15 19l2 2l4 -4"/></svg>
                                        </span>
                                        <div class="detail-drawer__customer-field-content">
                                            <span class="detail-drawer__customer-field-label">Dirección</span>
                                            <strong>{{ customerAddress || '—' }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="detail-drawer__operation-section">
                                <div class="detail-drawer__operation-heading">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Asignación comercial y origen del inventario</p>
                                </div>

                                <div class="detail-drawer__operation-seller">
                                    <div class="detail-drawer__operation-avatar" aria-hidden="true">
                                        {{ sellerInitials }}
                                    </div>
                                    <div class="detail-drawer__operation-seller-content">
                                        <span class="detail-drawer__operation-label">Vendedor</span>
                                        <strong>{{ sellerLabel }}</strong>
                                    </div>
                                    <span v-if="sellerAccountLabel" class="detail-drawer__operation-badge">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3c3.866 0 7 1.79 7 4v5c0 4.418 -3.134 8 -7 9c-3.866 -1 -7 -4.582 -7 -9v-5c0 -2.21 3.134 -4 7 -4z"/></svg>
                                        {{ sellerAccountLabel }}
                                    </span>
                                </div>

                                <div class="detail-drawer__operation-flow">
                                    <div class="detail-drawer__operation-card">
                                        <span class="detail-drawer__operation-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-store"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0"/><path d="M5 21v-14l8 -4v18"/><path d="M19 21v-10l-6 -4"/><path d="M9 9l0 .01"/><path d="M9 12l0 .01"/><path d="M9 15l0 .01"/><path d="M9 18l0 .01"/></svg>
                                        </span>
                                        <div class="detail-drawer__operation-card-content">
                                            <span class="detail-drawer__operation-label">Establecimiento</span>
                                            <strong>{{ establishmentDisplayLabel }}</strong>
                                        </div>
                                    </div>

                                    <span class="detail-drawer__operation-arrow" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M13 18l6 -6"/><path d="M13 6l6 6"/></svg>
                                    </span>

                                    <div class="detail-drawer__operation-card">
                                        <span class="detail-drawer__operation-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-warehouse"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21v-13l9 -4l9 4v13"/><path d="M13 13h4v8h-10v-6h6"/><path d="M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3"/></svg>
                                        </span>
                                        <div class="detail-drawer__operation-card-content">
                                            <span class="detail-drawer__operation-label">Almacén / origen</span>
                                            <strong>{{ warehouseLabel }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-drawer__operation-note">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-info-circle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/><path d="M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0 -18"/></svg>
                                    <span>{{ stockOriginNote }}</span>
                                </div>

                                <div v-if="record.observation" class="detail-drawer__operation-note detail-drawer__operation-note--observation">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h8"/><path d="M8 13h6"/><path d="M9 18h-3a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-3l-3 3z"/></svg>
                                    <span>{{ record.observation }}</span>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Pagos y totales" name="totals">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Pagos y totales</h5>
                                    <p class="section-subtitle">Condiciones de pago e importes de la nota de venta</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-currency-dollar"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16.7 8a3 3 0 0 0 -2.7 -2h-4a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-4a3 3 0 0 1 -2.7 -2"/><path d="M12 3v3m0 12v3"/></svg>
                                        </span>
                                        <div class="detail-drawer__totals-summary-content">
                                            <span class="detail-drawer__totals-label">Condición de pago</span>
                                            <strong>{{ paymentConditionLabel }}</strong>
                                        </div>
                                    </div>

                                    <div class="detail-drawer__totals-summary-card">
                                        <span class="detail-drawer__totals-summary-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg>
                                        </span>
                                        <div class="detail-drawer__totals-summary-content">
                                            <span class="detail-drawer__totals-label">Moneda</span>
                                            <strong>{{ currencyLabel }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/><path d="M13 15l2 0"/></svg>
                                        </span>
                                        <span class="detail-drawer__totals-row-label">Op. gravada</span>
                                        <strong>{{ formatMoney(record.total_taxed) }}</strong>
                                    </div>

                                    <div class="detail-drawer__totals-row">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-percentage"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M7 7m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M6 18l12 -12"/></svg>
                                        </span>
                                        <span class="detail-drawer__totals-row-label">IGV ({{ igvPercentage }}%)</span>
                                        <strong>{{ formatMoney(record.total_igv) }}</strong>
                                    </div>

                                    <div class="detail-drawer__totals-row detail-drawer__totals-row--balance">
                                        <span class="detail-drawer__totals-row-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
                                        </span>
                                        <span class="detail-drawer__totals-row-label">Saldo pendiente</span>
                                        <span
                                            class="detail-drawer__totals-status"
                                            :class="balanceIsPaid ? 'detail-drawer__totals-status--paid' : 'detail-drawer__totals-status--pending'"
                                        >
                                            <svg v-if="balanceIsPaid" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>
                                            {{ balanceStatusLabel }}
                                        </span>
                                    </div>

                                    <div class="detail-drawer__totals-total">
                                        <span>Total</span>
                                        <strong><small>{{ currencySymbol }}</small> {{ formatMoney(record.total, false) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions detail-drawer__footer--wrap sale-note-detail-drawer__footer">
                    <button
                        v-if="canAnulate"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="voiding"
                        @click="clickAnulate"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                        Anular nota de venta
                    </button>
                    <button
                        v-if="canPrint"
                        type="button"
                        class="btn btn-outline-info btn-sm"
                        @click="$emit('print', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-printer" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" /><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" /><path d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" /></svg>
                        Imprimir
                    </button>
                    <button
                        v-if="canGenerateDocument"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('generate-document', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-invoice" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M19 12v7a1.78 1.78 0 0 1 -3.1 1.4a1.65 1.65 0 0 0 -2.6 0a1.65 1.65 0 0 1 -2.6 0a1.65 1.65 0 0 0 -2.6 0a1.78 1.78 0 0 1 -3.1 -1.4v-14a2 2 0 0 1 2 -2h7l5 5v4.25" /></svg>
                        Generar comprobante
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar nota de venta
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
import { deletable } from '@mixins/deletable';

const PAYMENT_CONDITIONS = {
    '01': 'Contado',
    '02': 'Crédito',
    '03': 'Crédito con cuotas'
};

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
            default: 'sale-notes'
        },
        canEditRow: {
            type: Function,
            default: null
        },
        canAnulateRow: {
            type: Function,
            default: null
        },
        canGenerateRow: {
            type: Function,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            voiding: false,
            record: null,
            paymentMethodTypes: [],
            tablesLoaded: false,
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
        saleNoteIdentifier() {
            return this.record?.full_number
                || this.record?.identifier
                || (this.record?.serie && this.record?.number
                    ? `${this.record.serie}-${this.record.number}`
                    : null)
                || `#${this.record?.id || ''}`;
        },
        stateLabel() {
            return this.record?.state_type_description
                || this.record?.state_type?.description
                || '—';
        },
        stateBadgeClass() {
            const stateId = String(this.record?.state_type_id || '');

            if (stateId === '11' || stateId === '09') {
                return 'badge-danger';
            }

            if (stateId === '05') {
                return 'badge-success';
            }

            if (stateId === '07' || stateId === '13') {
                return 'badge-warning';
            }

            if (stateId === '03') {
                return 'badge-primary';
            }

            return 'badge-info';
        },
        issueDateLabel() {
            return this.formatDisplayDate(this.record?.date_of_issue);
        },
        customerName() {
            return this.record?.customer?.name
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
            const customer = this.record?.customer;
            return customer?.number
                || this.record?.customer_number
                || '—';
        },
        customerDocumentTypeLabel() {
            const customer = this.record?.customer;
            return customer?.identity_document_type?.description
                || customer?.document_type
                || this.record?.customer_identity_document_type_description
                || null;
        },
        customerDocumentTypeIsWarning() {
            const customer = this.record?.customer;
            const typeId = customer?.identity_document_type_id
                || customer?.identity_document_type?.id
                || this.record?.customer_identity_document_type_id;
            const label = String(this.customerDocumentTypeLabel || '').toLowerCase();

            return String(typeId) === '0' || label.includes('no domiciliado');
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
            const customer = this.record?.customer;
            return customer?.address
                || customer?.direccion
                || this.record?.consigned_address
                || null;
        },
        sellerLabel() {
            return this.record?.seller?.name
                || this.record?.seller_name
                || this.record?.user?.name
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
            const seller = this.record?.seller || this.record?.user || {};
            const role = String(seller.role || seller.type || '').toLowerCase();
            const name = String(this.sellerLabel || '').toLowerCase();
            const isMainAccount = seller.is_admin
                || seller.is_main
                || role.includes('admin')
                || name.includes('administrador');

            return isMainAccount ? 'Cuenta principal' : null;
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
        warehouseLabel() {
            const warehouseIds = new Set();

            this.lineItems.forEach(item => {
                if (item.warehouse_id) {
                    warehouseIds.add(String(item.warehouse_id));
                }
            });

            if (!warehouseIds.size) {
                return 'Almacén predeterminado';
            }

            if (warehouseIds.size === 1) {
                return `Almacén #${Array.from(warehouseIds)[0]}`;
            }

            return `${warehouseIds.size} almacenes asignados`;
        },
        stockOriginNote() {
            if (String(this.record?.state_type_id || '') === '11') {
                return 'Esta nota fue anulada. El sistema registró en kardex los movimientos de reversión de sus productos.';
            }

            if (this.record?.order_note_id) {
                return 'El stock ya fue descontado por el pedido de origen. Esta nota de venta no realizó un segundo descuento.';
            }

            const warehouseIds = new Set(
                this.lineItems
                    .map(item => item.warehouse_id)
                    .filter(Boolean)
                    .map(String)
            );
            const inventoryOrigin = warehouseIds.size
                ? this.warehouseLabel.toLowerCase()
                : 'el almacén predeterminado del establecimiento';

            return `Al registrar esta nota de venta, el sistema descontó las cantidades de ${inventoryOrigin} y generó los movimientos de kardex correspondientes.`;
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
        igvPercentage() {
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            const percentage = this.record?.percentage_igv
                ?? this.record?.igv_percentage
                ?? 16;

            return Number(percentage) || 16;
            // ######### FIN CAMBIO AFECTACIÓN IVA
        },
        paymentConditionLabel() {
            const paymentConditionId = this.record?.payment_condition_id;
            if (paymentConditionId && PAYMENT_CONDITIONS[paymentConditionId]) {
                return PAYMENT_CONDITIONS[paymentConditionId];
            }

            if (this.record?.payment_method_type?.description) {
                return this.record.payment_method_type.description;
            }

            const paymentType = this.paymentMethodTypes.find(
                option => String(option.id) === String(this.record?.payment_method_type_id)
            );

            return paymentType ? paymentType.description : '—';
        },
        balanceAmount() {
            if (this.record?.total_pending_paid !== undefined && this.record?.total_pending_paid !== null) {
                return this.parseAmount(this.record.total_pending_paid);
            }

            const total = this.parseAmount(this.record?.total);
            return Math.max(total - this.totalPayments, 0);
        },
        balanceIsPaid() {
            return this.balanceAmount < 0.01;
        },
        balanceStatusLabel() {
            const status = this.balanceIsPaid ? 'Pagado' : 'Pendiente';
            return `${this.formatMoney(this.balanceAmount)} · ${status}`;
        },
        totalPayments() {
            if (this.record?.total_paid !== undefined && this.record?.total_paid !== null) {
                return this.parseAmount(this.record.total_paid);
            }

            const payments = Array.isArray(this.record?.payments) ? this.record.payments : [];
            return payments.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
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
                    description: itemData?.description || itemData?.name || row.description || '—',
                    quantity,
                    unit_price: unitPrice,
                    subtotal,
                    warehouse_id: row.warehouse_id || itemData?.warehouse_id || null
                };
            });
        },
        canEdit() {
            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(this.buildActionRow());
            }

            const row = this.buildActionRow();
            return row.btn_generate && String(row.state_type_id) !== '11';
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            return String(this.record?.state_type_id) !== '11';
        },
        canPrint() {
            return String(this.record?.state_type_id) !== '11';
        },
        canGenerateDocument() {
            if (typeof this.canGenerateRow === 'function') {
                return this.canGenerateRow(this.buildActionRow());
            }

            const row = this.buildActionRow();
            return !row.changed && String(row.state_type_id) !== '11';
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

            this.record = {
                ...this.initialRow,
                full_number: this.initialRow.full_number,
                identifier: this.initialRow.identifier,
                customer_name: this.initialRow.customer_name,
                customer_number: this.initialRow.customer_number,
                customer_telephone: this.initialRow.customer_telephone,
                customer_email: this.initialRow.customer_email,
                seller_name: this.initialRow.seller_name,
                user_name: this.initialRow.user_name,
                state_type_description: this.initialRow.state_type_description,
                state_type_id: this.initialRow.state_type_id,
                total_paid: this.initialRow.total_paid,
                total_pending_paid: this.initialRow.total_pending_paid,
                documents: this.initialRow.documents || [],
                btn_generate: this.initialRow.btn_generate,
                changed: this.initialRow.changed
            };
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

                    const data = response.data?.data;
                    if (!data) {
                        throw new Error('missing sale note');
                    }

                    const saleNote = data.sale_note || data;
                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const customer = saleNote.customer || data.customer || snapshot.customer;

                    this.record = {
                        ...snapshot,
                        ...data,
                        ...saleNote,
                        customer,
                        full_number: data.full_number || saleNote.full_number || snapshot.full_number,
                        identifier: saleNote.identifier || data.identifier || snapshot.identifier,
                        state_type_description: saleNote.state_type?.description
                            || data.state_type_description
                            || snapshot.state_type_description,
                        state_type_id: saleNote.state_type_id ?? data.state_type_id ?? snapshot.state_type_id,
                        seller_name: data.seller_name || saleNote.seller?.name || snapshot.seller_name,
                        user_name: data.user_name || saleNote.user?.name || snapshot.user_name,
                        customer_name: data.customer_name || customer?.name || snapshot.customer_name,
                        customer_number: data.customer_number || customer?.number || snapshot.customer_number,
                        customer_telephone: data.customer_telephone || customer?.telephone || snapshot.customer_telephone,
                        customer_email: data.customer_email || customer?.email || snapshot.customer_email,
                        total_paid: data.total_paid ?? snapshot.total_paid,
                        total_pending_paid: data.total_pending_paid ?? snapshot.total_pending_paid,
                        payments: data.payments || saleNote.payments || [],
                        items: saleNote.items || data.items || [],
                        documents: data.documents || snapshot.documents || [],
                        btn_generate: data.btn_generate !== undefined
                            ? data.btn_generate
                            : snapshot.btn_generate,
                        changed: data.changed !== undefined
                            ? data.changed
                            : snapshot.changed,
                        establishment: saleNote.establishment || data.establishment,
                        payment_condition_id: saleNote.payment_condition_id || data.payment_condition_id,
                        payment_method_type_id: saleNote.payment_method_type_id || data.payment_method_type_id,
                        payment_method_type: saleNote.payment_method_type || data.payment_method_type,
                        observation: saleNote.observation || data.observation
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle de la nota de venta.');
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

            return this.$http
                .get(`/${this.resource}/tables`)
                .then(response => {
                    this.paymentMethodTypes = response.data?.payment_method_types || [];
                    this.tablesLoaded = true;
                })
                .catch(() => {
                    this.paymentMethodTypes = [];
                    this.tablesLoaded = true;
                });
        },
        buildActionRow() {
            return {
                id: this.record?.id,
                state_type_id: this.record?.state_type_id,
                state_type_description: this.record?.state_type_description
                    || this.record?.state_type?.description
                    || '',
                documents: this.record?.documents || [],
                btn_generate: this.record?.btn_generate,
                changed: this.record?.changed
            };
        },
        clickAnulate() {
            if (!this.record?.id || !this.canAnulate) {
                return;
            }

            this.voiding = true;

            this.anular(`/${this.resource}/anulate/${this.record.id}`, 'post')
                .then(success => {
                    if (!success) return;
                    this.visibleDrawer = false;
                    this.$eventHub.$emit('reloadData');
                })
                .finally(() => {
                    this.voiding = false;
                });
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

            const date = moment(value);
            return date.isValid() ? date.format('DD-MM-YYYY') : value;
        },
        handleClosed() {
            this.record = null;
            this.loading = false;
            this.voiding = false;
            this.activeTab = 'products';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>
