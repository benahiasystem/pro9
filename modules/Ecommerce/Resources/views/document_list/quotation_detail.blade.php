<style>
#quotationDetailModal .modal-content {
    border: none;
    border-radius: 14px;
    overflow: hidden;
}

#quotationDetailModal .modal-header {
    background: #fafbfc;
    border-bottom: 1px solid #eef0f2;
    padding: 20px 28px;
}

#quotationDetailModal .modal-title {
    font-weight: 700;
    font-size: 18px;
    color: #1a1a1a;
}

#quotationDetailModal .modal-body {
    padding: 28px;
}

#quotationDetailModal .info-card {
    background: #fafbfc;
    border: 1px solid #eef0f2;
    border-radius: 10px;
    padding: 18px 20px;
    height: 100%;
}

#quotationDetailModal .info-card h6 {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #8a8f98;
    margin-bottom: 14px;
}

#quotationDetailModal .info-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 14px;
    border-bottom: 1px solid #f0f1f3;
}
#quotationDetailModal .info-row:last-child { border-bottom: none; }
#quotationDetailModal .info-row .label { color: #6b7280; }
#quotationDetailModal .info-row .value { font-weight: 600; color: #1a1a1a; text-align: right; max-width: 65%; }

#quotationDetailModal .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    background: #fff4eb;
    color: var(--primary-color, #ff7a00);
}
#quotationDetailModal .status-badge.is-voided {
    background: #f3f5f7;
    color: #667085;
}
#quotationDetailModal .status-badge.is-expired {
    background: #fef3c7;
    color: #92400e;
}

#quotationDetailModal .products-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #8a8f98;
    margin: 28px 0 14px;
}

#quotationDetailModal .product-table {
    border: 1px solid #eef0f2;
    border-radius: 10px;
    overflow: hidden;
}
#quotationDetailModal .product-table thead th {
    background: #fafbfc;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #8a8f98;
    border-bottom: 1px solid #eef0f2;
    padding: 12px 16px;
}
#quotationDetailModal .product-table td {
    padding: 12px 16px;
    vertical-align: middle;
    border-top: 1px solid #f5f6f8;
}
#quotationDetailModal .product-thumb {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #eef0f2;
}
#quotationDetailModal .product-name {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

#quotationDetailModal .total-box {
    margin-top: 20px;
    padding: 16px 20px;
    background: #fafbfc;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
#quotationDetailModal .total-box .label {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6b7280;
}
#quotationDetailModal .total-box .amount {
    font-size: 22px;
    font-weight: 800;
    color: #15803d;
}
#quotationDetailModal .modal-footer-actions {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
<script type="text/x-template" id="quotation-detail-template">
<div class="modal fade" id="quotationDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content" v-if="record">

            <div class="modal-header">
                <h5 class="modal-title">
                    Cotización @{{ record.code }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" @click="close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="info-card">
                            <h6>Cliente</h6>
                            <div class="info-row">
                                <span class="label">Nombre</span>
                                <span class="value">@{{ record.customer_name || record.contact || '—' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="label">Teléfono</span>
                                <span class="value">@{{ record.customer_telephone || record.phone || '—' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="label">Email</span>
                                <span class="value">@{{ record.customer_email || '—' }}</span>
                            </div>
                            <div class="info-row" v-if="record.customer_address">
                                <span class="label">Dirección</span>
                                <span class="value">@{{ record.customer_address }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Cotización</h6>
                            <div class="info-row">
                                <span class="label">Fecha</span>
                                <span class="value">@{{ record.date_of_issue || '—' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="label">Vigencia</span>
                                <span class="value">@{{ record.date_of_due || '—' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="label">Estado</span>
                                <span class="value">
                                    <span
                                        class="status-badge"
                                        :class="{
                                            'is-voided': record.state_type_id === '11',
                                            'is-expired': record.is_expired
                                        }"
                                    >
                                        @{{ record.state_type_description }}
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="products-title">Productos</div>

                <table class="table product-table mb-0">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th width="70" class="text-center">Cant.</th>
                            <th width="110" class="text-right" v-if="showPrices">Precio</th>
                            <th width="110" class="text-right" v-if="showPrices">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in record.items" :key="index">
                            <td>
                                <div class="d-flex align-items-center">
                                    <img :src="'/storage/uploads/items/' + item.image" class="product-thumb mr-3" alt="">
                                    <span class="product-name">@{{ item.description }}</span>
                                </div>
                            </td>
                            <td class="text-center">@{{ item.quantity }}</td>
                            <td class="text-right" v-if="showPrices">
                                @{{ item.currency_symbol }} @{{ Number(item.unit_price).toFixed(2) }}
                            </td>
                            <td class="text-right" v-if="showPrices">
                                @{{ item.currency_symbol }} @{{ Number(item.total).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="!record.items || record.items.length === 0">
                            <td :colspan="showPrices ? 4 : 2" class="text-center text-muted py-4">Sin productos</td>
                        </tr>
                    </tbody>
                </table>

                <div class="total-box" v-if="showPrices">
                    <span class="label">Total</span>
                    <span class="amount">
                        @{{ record.currency_symbol }} @{{ Number(record.total).toFixed(2) }}
                    </span>
                </div>

                <div class="modal-footer-actions" v-if="record.print_url && showPrices">
                    <a :href="record.print_url" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                        Ver PDF
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
</script>
