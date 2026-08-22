{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
@extends('ecommerce::layouts.layout_ecommerce_cart.index')

@section('content')
<style>
    /* Porto: 1rem≈10px. Sin font-size: hereda body (~1.4rem ≈ 14px). */
    .ty-token-box {
        margin: 16px 0 4px;
        padding: 14px 14px 12px;
        border: 1px dashed #d0d5dd;
        border-radius: 12px;
        background: #f8fafc;
        text-align: left;
    }
    .ty-token-box .ty-token-label {
        display: block;
        font-weight: 700;
        color: #667085;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 8px;
    }
    .ty-token-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .ty-token-row input {
        flex: 1;
        min-width: 0;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 12px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-weight: 600;
        color: #0f2137;
        background: #fff;
    }
    .ty-token-copy {
        border: 0;
        border-radius: 8px;
        background: var(--primary-color, #ff7a00);
        color: #fff;
        font-weight: 700;
        padding: 10px 14px;
        white-space: nowrap;
        cursor: pointer;
    }
    .ty-token-copy.is-copied {
        background: #16a34a;
    }
    .ty-token-hint {
        margin: 8px 0 0;
        color: #667085;
        line-height: 1.45;
    }
</style>

<div id="app">
    <div class="thankyou-page">
        <div class="ty-card">
            <div class="ty-badge"><div class="ring">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline class="ty-check" points="20 6 9 17 4 12"/></svg>
            </div></div>
            <h1>¡Gracias por tu compra!</h1>
            <p class="ty-sub">Tu pedido fue registrado con éxito. Te enviaremos los detalles y coordinaremos la entrega contigo.</p>
            <div class="ty-order"><span>N° de pedido:</span> <b>#{{ $order->publicNumber() }}</b></div>

            <div class="ty-token-box">
                <span class="ty-token-label">Número de pedido</span>
                <div class="ty-token-row">
                    <input
                        id="ty-token-input"
                        type="text"
                        readonly
                        value="{{ $order->publicNumber() }}"
                        aria-label="Número de pedido"
                    >
                    <button type="button" class="ty-token-copy" id="ty-token-copy">Copiar</button>
                </div>
                <p class="ty-token-hint">Guárdalo: para consultar el estado en Seguimiento necesitas este N° de pedido y tu DNI.</p>
            </div>

            <div class="ty-info">
                <div class="ti-row"><span class="lbl">Método de entrega</span><span class="val">{{ $deliveryLabel }}</span></div>
                <div class="ti-row"><span class="lbl">Forma de pago</span><span class="val">{{ $paymentLabel }}</span></div>
                <div class="ti-row"><span class="lbl">Productos</span><span class="val">{{ $itemsCount }}</span></div>
                <div class="ti-row ti-total"><span class="lbl">Total</span><span class="val">Bs. {{ number_format($order->total, 2) }}</span></div>
            </div>
            <div class="ty-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>@if($isPickup)Acércate a la sucursal elegida para recoger tu pedido. Te avisaremos cuando esté listo.@else Coordinaremos la entrega contigo. Te contactaremos por el teléfono registrado para concretar tu pedido.@endif</span>
            </div>
            <div class="ty-btns">
                <a href="{{ route('tenant_ecommerce_order_tracking', ['pedido' => $order->publicNumber()]) }}" class="pay-btn second-btn">Ver estado del pedido</a>
                <a href="{{ route('tenant_order_list') }}" class="pay-btn second-btn">Ver mis pedidos</a>
                <a href="{{ route('tenant.ecommerce.index') }}" class="pay-btn">Seguir comprando</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('ty-token-input');
    var btn = document.getElementById('ty-token-copy');
    if (!input || !btn) return;

    btn.addEventListener('click', function () {
        var value = String(input.value || '').trim();
        if (!value) return;

        var done = function () {
            btn.textContent = '¡Copiado!';
            btn.classList.add('is-copied');
            setTimeout(function () {
                btn.textContent = 'Copiar';
                btn.classList.remove('is-copied');
            }, 1800);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function () {
                input.select();
                document.execCommand('copy');
                done();
            });
            return;
        }

        input.select();
        document.execCommand('copy');
        done();
    });
})();
</script>

@endsection

{{-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## --}}
