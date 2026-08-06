@extends('ecommerce::layouts.layout_ecommerce_cart.index')

@section('content')
<style>
    .ot-page {
        max-width: 720px;
        margin: 28px auto 60px;
        padding: 0 16px;
    }
    .ot-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 12px 40px rgba(15, 33, 55, .08);
        border: 1px solid #eef1f4;
        padding: 28px 24px 24px;
    }
    .ot-title {
        margin: 0 0 6px;
        font-size: 1.55rem;
        font-weight: 700;
        color: #0f2137;
        text-align: center;
    }
    .ot-sub {
        margin: 0 0 22px;
        text-align: center;
        color: #667085;
        font-size: .95rem;
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
        padding: 12px 14px;
        font-size: 15px;
        outline: none;
    }
    .ot-search input:focus {
        border-color: var(--primary-color, #ff7a00);
        box-shadow: 0 0 0 3px rgba(255, 122, 0, .12);
    }
    .ot-search-hint {
        margin: -6px 0 0;
        font-size: .82rem;
        color: #667085;
    }
    .ot-mode-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 14px;
    }
    .ot-mode-tab {
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #344054;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: .86rem;
        font-weight: 700;
        cursor: pointer;
    }
    .ot-mode-tab.is-active {
        border-color: var(--primary-color, #ff7a00);
        background: rgba(255, 122, 0, .08);
        color: var(--primary-color, #ff7a00);
    }
    .ot-search-panel[hidden] {
        display: none !important;
    }
    .ot-search button {
        border: 0;
        border-radius: 10px;
        background: var(--primary-color, #ff7a00);
        color: #fff;
        font-weight: 700;
        padding: 0 18px;
        min-height: 46px;
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
        font-size: .9rem;
        margin-bottom: 16px;
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
        font-size: 1.05rem;
    }
    .ot-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 700;
        color: #fff;
        background: #f59e0b;
    }
    .ot-section-title {
        margin: 0 0 14px;
        font-size: .95rem;
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
        font-size: 12px;
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
        font-size: .95rem;
    }
    .ot-step.is-pending .ot-step-title {
        color: #98a2b3;
    }
    .ot-step-meta {
        font-size: .75rem;
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
        font-size: 1rem;
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
        font-size: .95rem;
        line-height: 1.3;
    }
    .ot-item-qty {
        margin-top: 2px;
        font-size: .82rem;
        color: #667085;
    }
    .ot-item-amt {
        font-weight: 700;
        color: #0f2137;
        font-size: .95rem;
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
        font-size: .92rem;
        color: #52606d;
    }
    .ot-summary .row.total {
        margin-top: 6px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
        font-weight: 700;
        color: #0f2137;
        font-size: 1.05rem;
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
        font-weight: 700;
        font-size: .95rem;
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
        font-size: .9rem;
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
        <h1 class="ot-title">Estado de tu pedido</h1>
        <p class="ot-sub">Elige cómo buscar: con tu código de seguimiento o con el N° de pedido.</p>

        <div class="ot-mode-tabs" role="tablist">
            <button type="button" class="ot-mode-tab is-active" data-ot-mode="token" id="ot-tab-token">Código de seguimiento</button>
            <button type="button" class="ot-mode-tab" data-ot-mode="datos" id="ot-tab-datos">N° + correo o DNI</button>
        </div>

        <form class="ot-search ot-search-panel" id="ot-search-token-form" autocomplete="off" data-ot-panel="token">
            <div class="ot-search-row">
                <input
                    id="ot-token-input"
                    type="text"
                    name="token"
                    placeholder="Pega tu código de seguimiento"
                    value="{{ $initialToken ?? '' }}"
                    autocomplete="off"
                    spellcheck="false"
                >
                <button type="submit" id="ot-search-token-btn">Buscar</button>
            </div>
            <p class="ot-search-hint">Es el código que aparece al finalizar tu compra y también te llega por correo (también está en el link de gracias).</p>
        </form>

        <form class="ot-search ot-search-panel" id="ot-search-datos-form" autocomplete="off" data-ot-panel="datos" hidden>
            <div class="ot-search-row">
                <input
                    id="ot-pedido-input"
                    type="text"
                    name="pedido"
                    placeholder="N° de pedido (ej: 000192)"
                    value="{{ $initialPedido }}"
                    inputmode="numeric"
                >
                <button type="submit" id="ot-search-datos-btn">Buscar</button>
            </div>
            <div class="ot-search-row">
                <input
                    id="ot-email-input"
                    type="email"
                    name="email"
                    placeholder="Correo del comprador"
                    autocomplete="email"
                >
                <input
                    id="ot-documento-input"
                    type="text"
                    name="documento"
                    placeholder="DNI / documento"
                    inputmode="numeric"
                    autocomplete="off"
                >
            </div>
            <p class="ot-search-hint">Basta con correo o DNI (uno de los dos), además del N° de pedido.</p>
        </form>

        <div id="ot-feedback" class="ot-msg" style="display:none;"></div>

        <div id="ot-result" style="display:none;">
            <div class="ot-head">
                <div class="ot-number" id="ot-order-number"></div>
                <span class="ot-badge" id="ot-status-badge"></span>
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
    const tokenForm = document.getElementById('ot-search-token-form');
    const datosForm = document.getElementById('ot-search-datos-form');
    const input = document.getElementById('ot-pedido-input');
    const tokenInput = document.getElementById('ot-token-input');
    const emailInput = document.getElementById('ot-email-input');
    const documentoInput = document.getElementById('ot-documento-input');
    const tokenBtn = document.getElementById('ot-search-token-btn');
    const datosBtn = document.getElementById('ot-search-datos-btn');
    const feedback = document.getElementById('ot-feedback');
    const result = document.getElementById('ot-result');
    const timeline = document.getElementById('ot-timeline');
    const timelineEmpty = document.getElementById('ot-timeline-empty');
    const whatsappBtn = document.getElementById('ot-btn-whatsapp');
    const initialToken = String(@json($initialToken ?? '') || '').trim();
    const tabs = document.querySelectorAll('.ot-mode-tab');

    function setMode(mode) {
        const isToken = mode === 'token';
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-ot-mode') === mode);
        });
        if (tokenForm) tokenForm.hidden = !isToken;
        if (datosForm) datosForm.hidden = isToken;
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setMode(tab.getAttribute('data-ot-mode') || 'token');
        });
    });

    function money(amount) {
        const value = Number(amount) || 0;
        return 'S/ ' + value.toLocaleString('es-PE', {
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

    function setBusy(btn, busy, idleLabel) {
        if (!btn) return;
        btn.disabled = !!busy;
        btn.textContent = busy ? 'Buscando…' : idleLabel;
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
                ? '✓'
                : (isPaymentStep && state === 'is-current'
                    ? '⏱'
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

        document.getElementById('ot-delivery').textContent = order.delivery_label || '—';
        document.getElementById('ot-payment').textContent = order.payment_label || '—';
        document.getElementById('ot-total').textContent = money(order.total);

        result.style.display = 'block';
    }

    async function searchPedido(options) {
        options = options || {};
        const value = String(options.pedido != null ? options.pedido : '').trim();
        const token = String(options.token != null ? options.token : '').trim();
        const email = String(options.email != null ? options.email : '').trim();
        const documento = String(options.documento != null ? options.documento : '').trim();
        const activeBtn = options.mode === 'token' ? tokenBtn : datosBtn;

        if (!token && !value) {
            result.style.display = 'none';
            showFeedback(options.mode === 'token'
                ? 'Pega tu código de seguimiento.'
                : 'Ingresa un número de pedido.', true);
            return;
        }

        if (!token && !email && !documento) {
            result.style.display = 'none';
            showFeedback('Ingresa tu correo o DNI para verificar el pedido.', true);
            return;
        }

        hideFeedback();
        setBusy(activeBtn, true, 'Buscar');

        try {
            const params = new URLSearchParams();
            if (value) params.set('pedido', value);
            if (token) params.set('token', token);
            if (email) params.set('email', email);
            if (documento) params.set('documento', documento);

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
            const nextToken = String(data.order.token || token || '').trim();
            if (tokenInput && nextToken) {
                tokenInput.value = nextToken;
            }
            if (input) {
                input.value = padded;
            }
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('pedido', padded);
            if (nextToken) {
                nextUrl.searchParams.set('token', nextToken);
            } else {
                nextUrl.searchParams.delete('token');
            }
            window.history.replaceState({}, '', nextUrl.toString());
        } catch (err) {
            result.style.display = 'none';
            showFeedback('No se pudo consultar el pedido. Intenta nuevamente.', true);
        } finally {
            setBusy(activeBtn, false, 'Buscar');
        }
    }

    if (tokenForm) {
        tokenForm.addEventListener('submit', function (e) {
            e.preventDefault();
            searchPedido({
                mode: 'token',
                token: tokenInput.value,
                pedido: '',
                email: '',
                documento: '',
            });
        });
    }

    if (datosForm) {
        datosForm.addEventListener('submit', function (e) {
            e.preventDefault();
            searchPedido({
                mode: 'datos',
                pedido: input.value,
                token: '',
                email: emailInput.value,
                documento: documentoInput.value,
            });
        });
    }

    // Autoload solo con token (link thank you). Con solo ?pedido= esperar verificación.
    if (initialToken) {
        setMode('token');
        searchPedido({
            mode: 'token',
            pedido: input ? input.value : '',
            token: initialToken,
        });
    } else if (input && String(input.value || '').trim()) {
        setMode('datos');
    } else {
        setMode('token');
    }
})();
</script>
@endsection
