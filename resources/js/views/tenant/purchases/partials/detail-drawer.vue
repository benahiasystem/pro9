<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer purchase-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner purchase-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header purchase-detail-drawer__header">
                <div class="detail-drawer__title purchase-detail-drawer__title">
                    <h5>Detalle de la compra</h5>
                    <small v-if="record">{{ purchaseIdentifier }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar purchase-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                            v-if="documentTypeLabel"
                            class="badge detail-drawer__doc-badge purchase-detail-drawer__doc-badge detail-drawer__doc-badge--default purchase-detail-drawer__doc-badge--default"
                        >
                            {{ documentTypeLabel }}
                        </span>
                        <span class="badge" :class="stateBadgeClass">
                            {{ stateLabel }}
                        </span>
                        <span
                            v-if="paymentStateLabel"
                            class="badge"
                            :class="paymentStateBadgeClass"
                        >
                            {{ paymentStateLabel }}
                        </span>
                        <small class="text-muted">{{ issueDateLabel }}</small>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div class="detail-drawer__body purchase-detail-drawer__body">
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="Productos" name="products">
                            <div class="detail-drawer__section-card purchase-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header purchase-detail-drawer__section-card-header">
                                    <h5 class="section-title">Productos</h5>
                                    <p class="section-subtitle">Detalle de ítems incluidos en la compra</p>
                                </div>
                                <div v-if="lineItems.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table purchase-detail-drawer__items-table mb-0">
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

                        <el-tab-pane label="Proveedor" name="supplier">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Proveedor</h5>
                                    <p class="section-subtitle">Datos del proveedor asociado a la compra</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div class="detail-drawer__customer-avatar" aria-hidden="true">{{ supplierInitials }}</div>
                                    <div class="detail-drawer__customer-identity">
                                        <strong class="detail-drawer__customer-name">{{ supplierName }}</strong>
                                        <span v-if="supplierDocumentTypeLabel" class="detail-drawer__customer-badge">{{ supplierDocumentTypeLabel }}</span>
                                    </div>
                                </div>

                                <div class="detail-drawer__customer-grid">
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 8l2 0"/><path d="M15 12l2 0"/><path d="M7 16l10 0"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Documento</span><strong>{{ supplierDocumentNumber }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Teléfono</span><strong>{{ supplierTelephone || '—' }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Correo</span><strong>{{ supplierEmail || '—' }}</strong></div>
                                    </div>
                                    <div class="detail-drawer__customer-field">
                                        <span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M12.783 21.326a2 2 0 0 1 -2.196 -.426l-4.244 -4.243a8 8 0 1 1 13.657 -5.62"/><path d="M15 19l2 2l4 -4"/></svg></span>
                                        <div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Dirección</span><strong>{{ supplierAddress }}</strong></div>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Información operativa" name="operational">
                            <div class="detail-drawer__operation-section">
                                <div class="detail-drawer__operation-heading">
                                    <h5 class="section-title">Información operativa</h5>
                                    <p class="section-subtitle">Establecimiento, almacén y condiciones de la compra</p>
                                </div>

                                <div class="detail-drawer__operation-flow">
                                    <div class="detail-drawer__operation-card"><span class="detail-drawer__operation-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-store"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0"/><path d="M5 21v-14l8 -4v18"/><path d="M19 21v-10l-6 -4"/><path d="M9 9l0 .01"/><path d="M9 12l0 .01"/><path d="M9 15l0 .01"/><path d="M9 18l0 .01"/></svg></span><div class="detail-drawer__operation-card-content"><span class="detail-drawer__operation-label">Establecimiento</span><strong>{{ establishmentDisplayLabel }}</strong></div></div>
                                    <span class="detail-drawer__operation-arrow" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M13 18l6 -6"/><path d="M13 6l6 6"/></svg></span>
                                    <div class="detail-drawer__operation-card"><span class="detail-drawer__operation-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-warehouse"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21v-13l9 -4l9 4v13"/><path d="M13 13h4v8h-10v-6h6"/><path d="M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3"/></svg></span><div class="detail-drawer__operation-card-content"><span class="detail-drawer__operation-label">Almacén / destino</span><strong>{{ warehouseLabel }}</strong></div></div>
                                </div>

                                <div class="detail-drawer__operation-note"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-info-circle" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/><path d="M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0 -18"/></svg><span>{{ inventoryMovementNote }}</span></div>
                                <div class="detail-drawer__operation-note detail-drawer__operation-note--observation"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M3 10l18 0"/><path d="M7 15l.01 0"/><path d="M11 15l2 0"/></svg><span>Condición de pago: {{ paymentConditionLabel }}</span></div>
                                <div v-if="record.purchase_order" class="detail-drawer__operation-note"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-description" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 17h6"/><path d="M9 13h6"/></svg><span>Orden de compra: {{ record.purchase_order.prefix }}-{{ record.purchase_order.id }}</span></div>
                                <div v-if="guideNumbers.length" class="detail-drawer__operation-note"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-truck-delivery" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 17a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 17a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M5 17h-2v-4m-1 -5h11v9m-4 0h6m4 0h2v-6l-3 -5h-5"/><path d="M3 9l4 0"/></svg><span>Órdenes de entrega asociadas: {{ guideNumbers.join(', ') }}</span></div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Totales y pagos" name="totals">
                            <div class="detail-drawer__totals-section">
                                <div class="detail-drawer__totals-heading">
                                    <h5 class="section-title">Totales y pagos</h5>
                                    <p class="section-subtitle">Moneda, importes gravados, saldo e historial de pagos</p>
                                </div>

                                <div class="detail-drawer__totals-summary">
                                    <div class="detail-drawer__totals-summary-card"><span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-coin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-6 0a6 3 0 1 0 12 0a6 3 0 1 0 -12 0"/><path d="M6 6v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/><path d="M6 12v6c0 1.657 2.686 3 6 3s6 -1.343 6 -3v-6"/></svg></span><div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Moneda</span><strong>{{ currencyLabel }}</strong></div></div>
                                    <div class="detail-drawer__totals-summary-card"><span class="detail-drawer__totals-summary-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 9m0 2a2 2 0 1 0 0 4a2 2 0 0 0 0 -4"/><path d="M17 9m0 2a2 2 0 1 0 0 4a2 2 0 0 0 0 -4"/><path d="M14 15a2 2 0 0 0 -4 0"/><path d="M6 6h12a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-8a2 2 0 0 1 2 -2"/></svg></span><div class="detail-drawer__totals-summary-content"><span class="detail-drawer__totals-label">Pagado</span><strong>{{ formatMoney(totalPaid) }}</strong></div></div>
                                </div>

                                <div class="detail-drawer__totals-card">
                                    <div class="detail-drawer__totals-row"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/></svg></span><span class="detail-drawer__totals-row-label">Op. gravada</span><strong>{{ formatMoney(record.total_taxed) }}</strong></div>
                                    <div class="detail-drawer__totals-row"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-percentage"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M7 7m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M6 18l12 -12"/></svg></span><span class="detail-drawer__totals-row-label">IGV</span><strong>{{ formatMoney(record.total_igv) }}</strong></div>
                                    <div v-if="perceptionAmount > 0" class="detail-drawer__totals-row"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg></span><span class="detail-drawer__totals-row-label">Percepción</span><strong>{{ formatMoney(perceptionAmount) }}</strong></div>
                                    <div class="detail-drawer__totals-row detail-drawer__totals-row--balance"><span class="detail-drawer__totals-row-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/><path d="M20 12v4h-4a2 2 0 0 1 0 -4z"/></svg></span><span class="detail-drawer__totals-row-label">Saldo pendiente</span><span class="detail-drawer__totals-status" :class="balanceIsPaid ? 'detail-drawer__totals-status--paid' : 'detail-drawer__totals-status--pending'"><svg v-if="balanceIsPaid" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>{{ balanceStatusLabel }}</span></div>
                                    <div class="detail-drawer__totals-total"><span>Total</span><strong><small>{{ currencySymbol }}</small> {{ formatMoney(totalAmount, false) }}</strong></div>
                                </div>

                                <div class="detail-drawer__section-card mt-3 mb-0">
                                    <div class="detail-drawer__section-card-header"><h5 class="section-title">Pagos registrados</h5><p class="section-subtitle">Detalle de fechas, métodos y referencias</p></div>
                                    <div v-if="paymentRows.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table purchase-detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Método</th>
                                                <th class="text-end">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="payment in paymentRows" :key="payment.id">
                                                <td>{{ formatDisplayDate(payment.date_of_payment) }}</td>
                                                <td>
                                                    {{ payment.payment_method_type_description || '—' }}
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

                <div class="detail-drawer__footer detail-drawer__footer--actions detail-drawer__footer--wrap purchase-detail-drawer__footer">
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar compra
                    </button>
                    <button
                        v-if="canGuide"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        @click="$emit('guide', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-truck-delivery" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" /><path d="M3 9l4 0" /></svg>
                        Orden de entrega
                    </button>
                    <button
                        v-if="canPayments"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="$emit('payments', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash-banknote" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M3 8a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -8" /><path d="M18 12h.01" /><path d="M6 12h.01" /></svg>Pagos
                    </button>
                    <button
                        v-if="canAnulate"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="voiding"
                        @click="clickAnulate"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-x" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M10 10l4 4m0 -4l-4 4" /></svg>
                        Anular compra
                    </button>
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
            default: 'purchases'
        },
        permissions: {
            type: Object,
            default: () => ({})
        },
        disableGuideBtn: {
            type: Boolean,
            default: true
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
        purchaseIdentifier() {
            const number = this.record?.number || `#${this.record?.id || ''}`;
            const docType = this.documentTypeLabel;

            return docType ? `${number} · ${docType}` : number;
        },
        documentTypeLabel() {
            return this.record?.document_type_description || null;
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

            if (stateId === '03' || stateId === '01') {
                return 'badge-primary';
            }

            return 'badge-secondary';
        },
        paymentStateLabel() {
            return this.record?.state_type_payment_description || null;
        },
        paymentStateBadgeClass() {
            return this.paymentStateLabel === 'Pagado' ? 'badge-success' : 'badge-warning';
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
            const label = values.find(value => this.hasDisplayValue(value));

            if (label) {
                return label;
            }

            return this.record?.establishment_id
                ? `Establecimiento #${this.record.establishment_id}`
                : '—';
        },
        establishmentDisplayLabel() {
            const establishment = this.establishmentData;
            if (!establishment) {
                return this.establishmentLabel;
            }

            const name = [establishment.description, establishment.address]
                .find(value => this.hasDisplayValue(value));

            return [establishment.code, name]
                .filter(value => this.hasDisplayValue(value))
                .join(' — ') || this.establishmentLabel;
        },
        warehouseLabel() {
            if (Array.isArray(this.record?.warehouses) && this.record.warehouses.length) {
                const descriptions = this.record.warehouses
                    .map(warehouse => warehouse.description)
                    .filter(value => this.hasDisplayValue(value));

                if (descriptions.length) {
                    return descriptions.join(', ');
                }
            }

            const warehouseNames = new Set();

            this.lineItems.forEach(item => {
                if (item.warehouse_description) {
                    warehouseNames.add(item.warehouse_description);
                }
            });

            if (warehouseNames.size) {
                return Array.from(warehouseNames).join(', ');
            }

            const warehouseIds = new Set(
                this.lineItems
                    .map(item => item.warehouse_id)
                    .filter(Boolean)
                    .map(String)
            );

            if (warehouseIds.size === 1) {
                return `Almacén #${Array.from(warehouseIds)[0]}`;
            }

            if (warehouseIds.size > 1) {
                return `${warehouseIds.size} almacenes asignados`;
            }

            return 'Almacén predeterminado';
        },
        inventoryMovementNote() {
            if (String(this.record?.state_type_id || '') === '11') {
                return 'Esta compra fue anulada. El sistema registró la salida de las cantidades ingresadas y el movimiento correspondiente en kardex.';
            }

            return `Al registrar esta compra, el sistema ingresó las cantidades a ${this.warehouseLabel.toLowerCase()} y generó los movimientos de kardex correspondientes.`;
        },
        paymentConditionLabel() {
            const condition = this.record?.payment_condition;

            if (condition?.description) {
                return condition.description;
            }

            if (condition?.name) {
                return condition.name;
            }

            if (this.record?.payment_condition_id) {
                return `Condición #${this.record.payment_condition_id}`;
            }

            return '—';
        },
        guideNumbers() {
            const guides = Array.isArray(this.record?.guides) ? this.record.guides : [];

            return guides
                .map(guide => guide?.number)
                .filter(number => number !== undefined && number !== null && String(number).trim() !== '');
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
        totalAmount() {
            let total = this.parseAmount(this.record?.total);
            const perception = this.parseAmount(this.record?.total_perception);

            if (perception > 0) {
                total += perception;
            }

            return total;
        },
        perceptionAmount() {
            return this.parseAmount(this.record?.total_perception);
        },
        totalPaid() {
            return this.paymentRows.reduce((sum, payment) => sum + this.parseAmount(payment.payment), 0);
        },
        balanceAmount() {
            if (this.record?.total_canceled || this.paymentStateLabel === 'Pagado') {
                return 0;
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
        paymentRows() {
            return Array.isArray(this.record?.payments) ? this.record.payments : [];
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
                    key: row.id || row.key || index,
                    description: this.resolveItemDescription(row, itemData),
                    quantity,
                    unit_price: unitPrice,
                    subtotal,
                    warehouse_id: row.warehouse_id || itemData?.warehouse_id || null,
                    warehouse_description: row.warehouse?.description || null
                };
            });
        },
        canEdit() {
            if (typeof this.canEditRow === 'function') {
                return this.canEditRow(this.buildActionRow());
            }

            return Boolean(this.permissions.edit_purchase) && String(this.record?.state_type_id) !== '11';
        },
        canAnulate() {
            if (typeof this.canAnulateRow === 'function') {
                return this.canAnulateRow(this.buildActionRow());
            }

            return Boolean(this.permissions.annular_purchase) && String(this.record?.state_type_id) !== '11';
        },
        canGuide() {
            return !this.disableGuideBtn && String(this.record?.state_type_id) !== '11';
        },
        canPayments() {
            return String(this.record?.state_type_id) !== '11';
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
                payments: this.normalizePayments(this.initialRow.payments || [])
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
                    const purchase = data?.purchase;

                    if (!purchase) {
                        throw new Error('missing purchase');
                    }

                    const snapshot = this.initialRow && String(this.initialRow.id) === String(requestedId)
                        ? this.initialRow
                        : {};

                    const supplier = this.parseSupplier(purchase.supplier);

                    this.record = {
                        ...snapshot,
                        id: data.id || purchase.id,
                        external_id: data.external_id || purchase.external_id,
                        number: data.number || purchase.number_full || snapshot.number,
                        date_of_issue: data.date_of_issue || purchase.date_of_issue,
                        date_of_due: purchase.date_of_due || snapshot.date_of_due,
                        state_type_id: purchase.state_type_id ?? snapshot.state_type_id,
                        state_type_description: purchase.state_type?.description ?? snapshot.state_type_description,
                        document_type_description: purchase.document_type?.description ?? snapshot.document_type_description,
                        supplier,
                        supplier_name: supplier?.name || snapshot.supplier_name,
                        supplier_number: supplier?.number || snapshot.supplier_number,
                        supplier_telephone: supplier?.telephone || snapshot.supplier_telephone,
                        supplier_email: supplier?.email || snapshot.supplier_email,
                        establishment: purchase.establishment || purchase.relation_establishment,
                        establishment_id: purchase.establishment_id,
                        payment_condition: purchase.payment_condition,
                        payment_condition_id: purchase.payment_condition_id,
                        purchase_order: purchase.purchase_order || snapshot.purchase_order,
                        guides: purchase.guides || snapshot.guides || [],
                        warehouses: snapshot.warehouses || this.extractWarehousesFromItems(purchase.items),
                        currency_type_id: purchase.currency_type_id || snapshot.currency_type_id,
                        total_taxed: purchase.total_taxed ?? snapshot.total_taxed,
                        total_igv: purchase.total_igv ?? snapshot.total_igv,
                        total_perception: purchase.total_perception ?? snapshot.total_perception,
                        total: purchase.total ?? snapshot.total,
                        total_canceled: purchase.total_canceled ?? snapshot.total_canceled,
                        state_type_payment_description: purchase.total_canceled
                            ? 'Pagado'
                            : (snapshot.state_type_payment_description || 'Pendiente de pago'),
                        items: purchase.items || snapshot.items || [],
                        payments: this.normalizePayments(purchase.purchase_payments || snapshot.payments || [])
                    };
                })
                .catch(() => {
                    if (String(requestedId) !== String(this.recordId)) {
                        return;
                    }

                    if (!this.record) {
                        this.$message.error('No se pudo cargar el detalle de la compra.');
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
                state_type_id: this.record?.state_type_id
            };
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
        normalizePayments(payments) {
            if (!Array.isArray(payments)) {
                return [];
            }

            return payments.map(payment => ({
                id: payment.id,
                date_of_payment: payment.date_of_payment,
                payment: payment.payment,
                reference: payment.reference,
                payment_method_type_description: payment.payment_method_type_description
                    || payment.payment_method_type?.description
                    || null
            }));
        },
        extractWarehousesFromItems(items) {
            if (!Array.isArray(items)) {
                return [];
            }

            const warehouses = new Map();

            items.forEach(item => {
                const warehouse = item?.warehouse;
                if (warehouse?.id) {
                    warehouses.set(warehouse.id, {
                        id: warehouse.id,
                        description: warehouse.description
                    });
                }
            });

            return Array.from(warehouses.values());
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
        resolveItemDescription(row, itemData) {
            if (row.name_product_pdf) {
                return this.stripHtml(row.name_product_pdf);
            }

            return itemData?.description || itemData?.name || row.description || '—';
        },
        stripHtml(value) {
            if (!value) {
                return '—';
            }

            return String(value).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || '—';
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
            this.voiding = false;
            this.activeTab = 'products';
            this.$emit('update:initialRow', null);
        }
    }
};
</script>
