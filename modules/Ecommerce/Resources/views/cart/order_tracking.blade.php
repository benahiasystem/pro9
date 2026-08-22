@extends('ecommerce::layouts.layout_ecommerce_cart.index')

@section('content')
<style>
    /* Porto: 1rem~10px. Sin font-size: hereda body (~1.4rem ~ 14px). */
    .ot-page {
        max-width: 840px;
        margin: 32px auto 64px;
        padding: 0 18px;
    }
    .ot-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 12px 40px rgba(15, 33, 55, .08);
        border: 1px solid #eef1f4;
        padding: 34px 30px 28px;
    }
    .ot-title {
        margin: 0 0 8px;
        font-weight: 700;
        color: #0f2137;
        text-align: center;
        line-height: 1.3;
    }
    .ot-sub {
        margin: 0 0 24px;
        text-align: center;
        color: #667085;
        line-height: 1.5;
    }
    .ot-search {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
    }
    .ot-search-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .ot-search input {
        flex: 1;
        min-width: 140px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 13px 14px;
        outline: none;
    }
    .ot-search input:focus {
        border-color: var(--primary-color, #ff7a00);
        box-shadow: 0 0 0 3px rgba(255, 122, 0, .12);
    }
    .ot-search-hint {
        margin: -6px 0 0;
        color: #667085;
        line-height: 1.4;
    }
    .ot-search button {
        border: 0;
        border-radius: 10px;
        background: var(--primary-color, #ff7a00);
        color: #fff;
        font-weight: 600;
        padding: 0 20px;
        min-height: 48px;
        white-space: nowrap;
        cursor: pointer;
    }
    .ot-search button:disabled {
        opacity: .7;
        cursor: wait;
    }
    .ot-msg {
        padding: 12px 14px;
        border-radius: 10px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        margin-bottom: 16px;
        line-height: 1.45;
    }
    .ot-msg.is-error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }
    .ot-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid #eef1f4;
    }
    .ot-head .ot-number {
        font-weight: 700;
        color: #0f2137;
    }
    .ot-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 999px;
        font-weight: 700;
        color: #fff;
        background: #f59e0b;
    }
    .ot-courier-box {
        display: none;
        margin: 0 0 18px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .ot-courier-box.is-visible {
        display: block;
    }
    .ot-courier-label {
        display: block;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 4px;
    }
    .ot-courier-code {
        font-family: Consolas, Monaco, monospace;
        font-weight: 700;
        color: #0f2137;
        word-break: break-all;
    }
    .ot-section-title {
        margin: 0 0 14px;
        font-weight: 700;
        color: #0f2137;
    }
    .ot-timeline {
        list-style: none;
        margin: 0 0 26px;
        padding: 0;
        position: relative;
    }
    .ot-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #e5e7eb;
    }
    .ot-step {
        position: relative;
        display: flex;
        gap: 14px;
        padding: 0 0 18px 0;
    }
    .ot-step:last-child {
        padding-bottom: 0;
    }
    .ot-dot {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #f3f4f6;
        border: 2px solid #e5e7eb;
        color: #98a2b3;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        z-index: 1;
        font-weight: 700;
    }
    .ot-step.is-done .ot-dot {
        background: #16a34a;
        border-color: #16a34a;
        color: #fff;
    }
    .ot-step.is-current .ot-dot {
        background: var(--primary-color, #ff7a00);
        border-color: var(--primary-color, #ff7a00);
        color: #fff;
        box-shadow: 0 0 0 4px rgba(255, 122, 0, .18);
    }
    .ot-step-body {
        padding-top: 4px;
        min-width: 0;
    }
    .ot-step-title {
        font-weight: 700;
        color: #0f2137;
    }
    .ot-step.is-pending .ot-step-title {
        color: #98a2b3;
    }
    .ot-step-meta {
        color: #98a2b3;
        margin-top: 2px;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-weight: 700;
    }
    .ot-step.is-current .ot-step-meta {
        color: var(--primary-color, #ff7a00);
    }
    .ot-detail-card {
        border: 1px solid #e9edf1;
        border-radius: 14px;
        background: #fff;
        padding: 18px 16px 16px;
    }
    .ot-detail-heading {
        margin: 0 0 14px;
        font-weight: 700;
        color: #0f2137;
    }
    .ot-items {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 14px;
    }
    .ot-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 12px;
        background: #f5f6f8;
    }
    .ot-item-img {
        width: 52px;
        height: 52px;
        border-radius: 10px;
        background: #e5e7eb;
        flex-shrink: 0;
        overflow: hidden;
        display: grid;
        place-items: center;
        color: #9ca3af;
    }
    .ot-item-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ot-item-info {
        flex: 1;
        min-width: 0;
    }
    .ot-item-name {
        font-weight: 700;
        color: #0f2137;
        line-height: 1.3;
    }
    .ot-item-qty {
        margin-top: 2px;
        color: #667085;
    }
    .ot-item-amt {
        font-weight: 700;
        color: #0f2137;
        white-space: nowrap;
    }
    .ot-summary {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-top: 4px;
    }
    .ot-summary .row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        color: #52606d;
    }
    .ot-summary .row.total {
        margin-top: 6px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
        font-weight: 700;
        color: #0f2137;
    }
    .ot-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 18px;
    }
    .ot-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 48px;
        padding: 12px 16px;
        border-radius: 12px;
        border: 0;
        font-weight: 600;
        text-decoration: none !important;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    }
    .ot-btn:hover {
        transform: translateY(-1px);
        opacity: .95;
    }
    .ot-btn--whatsapp {
        background: #25d366;
        color: #fff !important;
        box-shadow: 0 8px 18px rgba(37, 211, 102, .28);
    }
    .ot-btn--store {
        background: var(--primary-color, #ff7a00);
        color: #fff !important;
        box-shadow: 0 8px 18px rgba(255, 122, 0, .24);
    }
    .ot-btn.is-disabled,
    .ot-btn:disabled {
        opacity: .55;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .ot-empty-statuses {
        color: #667085;
        margin-bottom: 16px;
    }
    @media (max-width: 575px) {
        .ot-search-row {
            flex-direction: column;
        }
        .ot-search button {
            min-height: 44px;
            width: 100%;
        }
        .ot-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ot-page">
    <div class="ot-card">
        <p class="ot-title" role="heading" aria-level="1">Estado de tu pedido</p>
        <p class="ot-sub">Consulta con tu número de pedido y DNI.</p>

        <form class="ot-search" id="ot-search-form" autocomplete="off">
            <div class="ot-search-row">
                <input
                    id="ot-pedido-input"
                    type="text"
                    name="pedido"
                    placeholder="N° de pedido (ej: 120801)"
                    value="{{ $initialPedido }}"
                    inputmode="numeric"
                >
                <input
                    id="ot-documento-input"
                    type="text"
                    name="documento"
                    placeholder="DNI / documento"
                    inputmode="numeric"
                    autocomplete="off"
                >
                <button type="submit" id="ot-search-btn">Buscar</button>
            </div>
            <p class="ot-search-hint">Usa el mismo documento con el que realizaste la compra.</p>
        </form>

        <div id="ot-feedback" class="ot-msg" style="display:none;"></div>

        <div id="ot-result" style="display:none;">
            <div class="ot-head">
                <div class="ot-number" id="ot-order-number"></div>
                <span class="ot-badge" id="ot-status-badge"></span>
            </div>

            <div class="ot-courier-box" id="ot-courier-box">
                <span class="ot-courier-label">Código de seguimiento (agencia)</span>
                <div class="ot-courier-code" id="ot-courier-code"></div>
            </div>

            <h2 class="ot-section-title">Seguimiento del pedido</h2>
            <div id="ot-timeline-empty" class="ot-empty-statuses" style="display:none;">
                Aún no hay estados de envío configurados.
            </div>
            <ul class="ot-timeline" id="ot-timeline"></ul>

            <div class="ot-detail-card">
                <h2 class="ot-detail-heading" id="ot-detail-heading">Detalle de la orden</h2>
                <div class="ot-items" id="ot-items"></div>
                <div class="ot-summary">
                    <div class="row"><span>Método de entrega</span><span id="ot-delivery"></span></div>
                    <div class="row"><span>Forma de pago</span><span id="ot-payment"></span></div>
                    <div class="row total"><span>Total</span><span id="ot-total"></span></div>
                </div>
                <div class="ot-actions">
                    @if(!empty($showWhatsapp) && !empty($whatsappPhone))
                        <a
                            id="ot-btn-whatsapp"
                            class="ot-btn ot-btn--whatsapp"
                            href="#"
                            target="_blank"
                            rel="noopener"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.5 0-9.96 4.43-9.96 9.9 0 1.75.46 3.45 1.34 4.95L2 22l5.3-1.38c1.44.78 3.06 1.19 4.74 1.19h.01c5.5 0 9.96-4.43 9.96-9.9C22.01 6.43 17.54 2 12.04 2zm5.79 14.16c-.24.68-1.41 1.25-1.95 1.33-.5.07-1.13.1-1.83-.11-.42-.13-.97-.32-1.67-.63-2.94-1.27-4.86-4.22-5.01-4.42-.15-.2-1.24-1.65-1.24-3.14 0-1.49.78-2.22 1.06-2.52.28-.3.6-.37.8-.37.2 0 .4 0 .58.01.18.01.43-.07.68.52.24.58.83 2.03.9 2.18.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.38-.44.51-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.02 1.12 1 2.07 1.31 2.37 1.46.3.15.47.12.65-.07.18-.2.75-.87.95-1.17.2-.3.4-.25.68-.15.28.1 1.78.84 2.08.99.3.15.5.22.57.35.08.12.08.71-.16 1.39z"/></svg>
                            <span>Contactar al negocio</span>
                        </a>
                    @else
                        <button type="button" class="ot-btn ot-btn--whatsapp is-disabled" disabled title="WhatsApp no configurado">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.5 0-9.96 4.43-9.96 9.9 0 1.75.46 3.45 1.34 4.95L2 22l5.3-1.38c1.44.78 3.06 1.19 4.74 1.19h.01c5.5 0 9.96-4.43 9.96-9.9C22.01 6.43 17.54 2 12.04 2zm5.79 14.16c-.24.68-1.41 1.25-1.95 1.33-.5.07-1.13.1-1.83-.11-.42-.13-.97-.32-1.67-.63-2.94-1.27-4.86-4.22-5.01-4.42-.15-.2-1.24-1.65-1.24-3.14 0-1.49.78-2.22 1.06-2.52.28-.3.6-.37.8-.37.2 0 .4 0 .58.01.18.01.43-.07.68.52.24.58.83 2.03.9 2.18.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.38-.44.51-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.02 1.12 1 2.07 1.31 2.37 1.46.3.15.47.12.65-.07.18-.2.75-.87.95-1.17.2-.3.4-.25.68-.15.28.1 1.78.84 2.08.99.3.15.5.22.57.35.08.12.08.71-.16 1.39z"/></svg>
                            <span>Contactar al negocio</span>
                        </button>
                    @endif
                    <a href="{{ $storeUrl }}" class="ot-btn ot-btn--store">
                        Volver a la tienda
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const lookupUrl = @json(route('tenant_ecommerce_order_tracking_lookup'));
    const whatsappPhone = @json($whatsappPhone ?? '');
    const showWhatsapp = @json(!empty($showWhatsapp) && !empty($whatsappPhone));
    const form = document.getElementById('ot-search-form');
    const input = document.getElementById('ot-pedido-input');
    const documentoInput = document.getElementById('ot-documento-input');
    const searchBtn = document.getElementById('ot-search-btn');
    const feedback = document.getElementById('ot-feedback');
    const result = document.getElementById('ot-result');
    const timeline = document.getElementById('ot-timeline');
    const timelineEmpty = document.getElementById('ot-timeline-empty');
    const whatsappBtn = document.getElementById('ot-btn-whatsapp');
    const courierBox = document.getElementById('ot-courier-box');
    const courierCode = document.getElementById('ot-courier-code');

    function money(amount) {
        const value = Number(amount) || 0;
        return 'Bs. ' + value.toLocaleString('es-VE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function showFeedback(message, isError) {
        feedback.style.display = 'block';
        feedback.textContent = message;
        feedback.classList.toggle('is-error', !!isError);
    }

    function hideFeedback() {
        feedback.style.display = 'none';
        feedback.textContent = '';
    }

    function setBusy(busy) {
        if (!searchBtn) return;
        searchBtn.disabled = !!busy;
        searchBtn.textContent = busy ? "Buscando\u2026" : 'Buscar';
    }

    function renderTimeline(statuses, currentId) {
        timeline.innerHTML = '';
        if (!statuses || !statuses.length) {
            timelineEmpty.style.display = 'block';
            return;
        }
        timelineEmpty.style.display = 'none';

        const currentIndex = statuses.findIndex(function (s) {
            return Number(s.id) === Number(currentId);
        });

        statuses.forEach(function (status, index) {
            const li = document.createElement('li');
            let state = 'is-pending';
            let meta = 'Pendiente';
            if (currentIndex === -1) {
                state = index === 0 ? 'is-current' : 'is-pending';
                meta = index === 0 ? 'Actual' : 'Pendiente';
            } else if (index < currentIndex) {
                state = 'is-done';
                meta = 'Completado';
            } else if (index === currentIndex) {
                state = 'is-current';
                meta = 'Actual';
            }

            li.className = 'ot-step ' + state;
            var isPaymentStep = status.kind === 'payment'
                || status.kind === 'payment_pending'
                || status.kind === 'payment_completed';
            var dotContent = state === 'is-done'
                ? "\u2713"
                : (isPaymentStep && state === 'is-current'
                    ? "\u23F1"
                    : String(index + 1));
            li.innerHTML =
                '<div class="ot-dot" aria-hidden="true">' +
                    dotContent +
                '</div>' +
                '<div class="ot-step-body">' +
                    '<div class="ot-step-title"></div>' +
                    '<div class="ot-step-meta"></div>' +
                '</div>';
            li.querySelector('.ot-step-title').textContent = status.description || 'Estado';
            li.querySelector('.ot-step-meta').textContent = meta;
            if (state === 'is-current' && status.color) {
                li.querySelector('.ot-dot').style.background = status.color;
                li.querySelector('.ot-dot').style.borderColor = status.color;
                li.querySelector('.ot-step-meta').style.color = status.color;
            }
            timeline.appendChild(li);
        });
    }

    function updateWhatsappLink(orderNumber) {
        if (!showWhatsapp || !whatsappBtn || !whatsappPhone) {
            return;
        }
        const text = 'Hola, consulto por mi pedido ' + (orderNumber || '');
        whatsappBtn.href = 'https://wa.me/' + whatsappPhone + '?text=' + encodeURIComponent(text);
    }

    function renderOrder(payload) {
        const order = payload.order || {};
        const statuses = payload.timeline_statuses || payload.shipping_statuses || [];

        document.getElementById('ot-order-number').textContent = 'Pedido ' + (order.number || '');
        document.getElementById('ot-detail-heading').textContent =
            'Detalle de la orden ' + (order.number || '');
        const badge = document.getElementById('ot-status-badge');
        badge.textContent = order.shipping_status_description || 'Pendiente';
        badge.style.background = order.shipping_status_color || '#f59e0b';

        if (order.tracking_code) {
            courierCode.textContent = order.tracking_code;
            courierBox.classList.add('is-visible');
        } else {
            courierCode.textContent = '';
            courierBox.classList.remove('is-visible');
        }

        renderTimeline(
            statuses,
            order.timeline_status_order_id || order.shipping_status_order_id
        );
        updateWhatsappLink(order.number);

        const itemsEl = document.getElementById('ot-items');
        itemsEl.innerHTML = '';
        (order.items || []).forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'ot-item';
            const qty = item.quantity || 1;
            row.innerHTML =
                '<div class="ot-item-img" aria-hidden="true"></div>' +
                '<div class="ot-item-info">' +
                    '<div class="ot-item-name"></div>' +
                    '<div class="ot-item-qty"></div>' +
                '</div>' +
                '<strong class="ot-item-amt"></strong>';

            const imgWrap = row.querySelector('.ot-item-img');
            if (item.image) {
                const img = document.createElement('img');
                img.src = item.image;
                img.alt = item.description || 'Producto';
                imgWrap.appendChild(img);
            } else {
                imgWrap.innerHTML =
                    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"/><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/></svg>';
            }

            row.querySelector('.ot-item-name').textContent = item.description || 'Producto';
            row.querySelector('.ot-item-qty').textContent = qty + (qty === 1 ? ' item' : ' items');
            row.querySelector('.ot-item-amt').textContent = money(item.total);
            itemsEl.appendChild(row);
        });
        if (!(order.items || []).length) {
            itemsEl.innerHTML = '<div class="ot-item"><div class="ot-item-info"><div class="ot-item-name">Sin detalle de productos</div></div></div>';
        }

        document.getElementById('ot-delivery').textContent = order.delivery_label || '\u2014';
        document.getElementById('ot-payment').textContent = order.payment_label || '\u2014';
        document.getElementById('ot-total').textContent = money(order.total);

        result.style.display = 'block';
    }

    async function searchPedido() {
        const value = String(input.value || '').trim();
        const documento = String(documentoInput.value || '').trim();

        if (!value) {
            result.style.display = 'none';
            showFeedback('Ingresa un número de pedido.', true);
            return;
        }

        if (!documento) {
            result.style.display = 'none';
            showFeedback('Ingresa tu DNI / documento para verificar el pedido.', true);
            return;
        }

        hideFeedback();
        setBusy(true);

        try {
            const params = new URLSearchParams();
            params.set('pedido', value);
            params.set('documento', documento);

            const url = lookupUrl + (lookupUrl.indexOf('?') >= 0 ? '&' : '?') + params.toString();
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!data.success) {
                result.style.display = 'none';
                showFeedback(data.message || 'No encontramos ese pedido o los datos no coinciden.', true);
                return;
            }
            hideFeedback();
            renderOrder(data);

            const padded = String(data.order.id).padStart(6, '0');
            input.value = padded;
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('pedido', padded);
            nextUrl.searchParams.delete('token');
            window.history.replaceState({}, '', nextUrl.toString());
        } catch (err) {
            result.style.display = 'none';
            showFeedback('No se pudo consultar el pedido. Intenta nuevamente.', true);
        } finally {
            setBusy(false);
        }
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            searchPedido();
        });
    }
})();
</script>
@endsection
