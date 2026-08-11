@extends('ecommerce::layouts.layout_ecommerce_cart.index')

@push('styles')
<style>
    /* Modal Mercado Pago (SweetAlert2 v7 + Payment Brick) */
    .swal2-popup.mp-payment-swal {
        width: 560px !important;
        max-width: 92vw !important;
        max-height: 92vh;
        padding: 0 1.35rem 1.5rem !important;
        box-sizing: border-box;
        border-radius: 20px !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .22) !important;
    }

    .swal2-popup.mp-payment-swal .swal2-title {
        display: flex !important;
        align-items: center;
        align-self: stretch;
        justify-content: flex-start;
        width: calc(100% + 2.7rem);
        max-width: none;
        min-height: 72px;
        margin: 0 -1.35rem 1.25rem !important;
        padding: 14px 64px 14px 22px !important;
        box-sizing: border-box;
        border-bottom: 1px solid #e9edf1;
        background: #fff;
        text-align: left;
    }

    .swal2-popup.mp-payment-swal .gateway-payment-title-logo {
        display: block;
        width: 132px;
        height: 42px;
        object-fit: contain;
        object-position: left center;
    }

    .swal2-popup.mp-payment-swal .swal2-close {
        top: 14px !important;
        right: 16px !important;
        width: 42px;
        height: 42px;
        border-radius: 10px;
        color: #52606d !important;
        font-size: 30px !important;
        transition: background .15s, color .15s;
    }

    .swal2-popup.mp-payment-swal .swal2-close:hover {
        background: #f3f5f7;
        color: var(--primary-color) !important;
    }

    /* Mantener las alertas de pago por encima de los demás modales. */
    .swal2-container {
        z-index: 1100 !important;
    }

    .swal2-popup.mp-payment-swal .swal2-content,
    .swal2-popup.mp-payment-swal #swal2-content {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
        overflow: visible;
        text-align: left;
    }

    .mp-swal-brick {
        width: 100%;
        min-height: 0;
        text-align: left;
    }

    #mp-brick-container form > h1:first-child {
        display: none !important;
    }

    #mp-brick-container div:has(> input[type="radio"]) {
        min-height: 40px;
        margin-bottom: 9px !important;
        padding: 6px 9px !important;
        box-sizing: border-box;
        border: 1px solid #b8b8b8 !important;
    }

    #mp-brick-container form button {
        position: relative;
        width: 100% !important;
        min-height: 42px !important;
        margin-top: 20px !important;
        border-radius: 12px !important;
    }

    #mp-brick-container form button[class*="loading-"] {
        background-color: var(--primary-color) !important;
        background-image: none !important;
        color: transparent !important;
    }

    #mp-brick-container form button[class*="loading-"] > * {
        opacity: 0 !important;
        visibility: hidden !important;
    }

    #mp-brick-container form button[class*="loading-"]::after {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 38px;
        height: 16px;
        content: "";
        transform: translate(-50%, -50%);
        background-image:
            radial-gradient(circle, #fff 0 3px, transparent 4px),
            radial-gradient(circle, #fff 0 3px, transparent 4px),
            radial-gradient(circle, #fff 0 3px, transparent 4px);
        background-repeat: no-repeat;
        background-size: 8px 8px;
        animation: mpPaymentDotsWave .9s ease-in-out infinite;
    }

    @keyframes mpPaymentDotsWave {
        0%, 80%, 100% {
            background-position: 0 8px, 15px 8px, 30px 8px;
        }
        20% {
            background-position: 0 1px, 15px 8px, 30px 8px;
        }
        40% {
            background-position: 0 8px, 15px 1px, 30px 8px;
        }
        60% {
            background-position: 0 8px, 15px 8px, 30px 1px;
        }
    }

    /* Contenedor visual compartido para formularios embebidos de pasarela. */
    .gateway-payment-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        place-items: center;
        padding: 24px;
        background: rgba(15, 33, 55, .5);
        backdrop-filter: blur(4px);
    }

    .gateway-payment-overlay.is-open {
        display: grid;
    }

    .gateway-payment-dialog {
        width: min(620px, 94vw);
        max-height: 92vh;
        overflow: auto;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .25);
    }

    .gateway-payment-header {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 72px;
        padding: 14px 16px 14px 22px;
        border-bottom: 1px solid #e9edf1;
        background: #fff;
    }

    .gateway-payment-header img {
        display: block;
        width: 132px;
        height: 42px;
        object-fit: contain;
        object-position: left center;
    }

    .gateway-payment-close {
        display: grid;
        width: 42px;
        height: 42px;
        padding: 0;
        place-items: center;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #52606d;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
    }

    .gateway-payment-close:hover {
        background: #f3f5f7;
        color: var(--primary-color);
    }

        .gateway-payment-body {
        padding: 24px;
    }

    /* Overlay de carga: Yape / efectivo / transferencia / Culqi post-token.
       Por encima del iframe de Culqi Checkout v4 (usa z-index muy alto). */
    .payment-process-overlay {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: none;
        place-items: center;
        padding: 24px;
        background: rgba(15, 33, 55, .55);
        backdrop-filter: blur(4px);
    }

    .payment-process-overlay.is-open {
        display: grid;
    }

    .payment-process-dialog {
        width: min(460px, 94vw);
        padding: 44px 32px 36px;
        border-radius: 20px;
        background: #fff;
        text-align: center;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .25);
    }

    .payment-process-spinner {
        width: 58px;
        height: 58px;
        margin: 0 auto 24px;
        border: 3px solid #e9edf1;
        border-top-color: var(--primary-color, #ff7a00);
        border-radius: 50%;
        animation: paymentProcessSpin .75s linear infinite;
    }

    @keyframes paymentProcessSpin {
        to { transform: rotate(360deg); }
    }

    .payment-process-dialog h3 {
        margin: 0 0 12px;
        font-size: 1.55rem;
        font-weight: 700;
        color: #0f2137;
        line-height: 1.3;
    }

    .payment-process-dialog p {
        margin: 0;
        font-size: 1.22rem;
        line-height: 1.5;
        color: #667085;
    }

    /* Modal de éxito unificado */
    .payment-success-overlay {
        position: fixed;
        inset: 0;
        z-index: 2147483001;
        display: none;
        place-items: center;
        padding: 24px;
        background: rgba(15, 33, 55, .55);
        backdrop-filter: blur(4px);
    }

    .payment-success-overlay.is-open {
        display: grid;
    }

    .payment-success-dialog {
        width: min(480px, 94vw);
        padding: 36px 32px 32px;
        border-radius: 20px;
        background: #fff;
        text-align: center;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .25);
    }

    .payment-success-badge {
        display: grid;
        place-items: center;
        width: 76px;
        height: 76px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: #fff4eb;
        color: var(--primary-color, #ff7a00);
    }

    .payment-success-dialog h3 {
        margin: 0 0 12px;
        font-size: 1.75rem;
        font-weight: 700;
        color: #0f2137;
        line-height: 1.25;
    }

    .payment-success-dialog .payment-success-sub {
        margin: 0 0 22px;
        font-size: 1.2rem;
        line-height: 1.5;
        color: #667085;
    }

    .payment-success-summary {
        margin: 0 0 18px;
        padding: 16px 18px;
        border: 1px solid #e9edf1;
        border-radius: 12px;
        text-align: left;
        background: #fafbfc;
    }

    .payment-success-summary .psr-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
        font-size: 1.15rem;
    }

    .payment-success-summary .psr-row + .psr-row {
        border-top: 1px solid #edf1f4;
    }

    .payment-success-summary .lbl {
        color: #667085;
    }

    .payment-success-summary .val {
        font-weight: 600;
        color: #0f2137;
        text-align: right;
    }

    .payment-success-summary .psr-row--total .val {
        font-size: 1.35rem;
        color: var(--primary-color, #ff7a00);
    }

    .payment-success-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 0 0 22px;
        padding: 14px 16px;
        border-radius: 10px;
        background: #f3f5f7;
        font-size: 1.1rem;
        line-height: 1.5;
        color: #52606d;
        text-align: left;
    }

    .payment-success-note svg {
        flex-shrink: 0;
        margin-top: 2px;
        color: var(--primary-color, #ff7a00);
        width: 20px;
        height: 20px;
    }

    .payment-success-badge.is-confirm {
        background: #e8f8ef;
        color: #16a34a;
    }

    .payment-success-note.is-info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e3a5f;
    }

    .payment-success-note.is-info svg {
        color: #2563eb;
    }

    .payment-success-note.is-warn {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
    }

    .payment-success-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .payment-success-dialog .pay-btn {
        width: 100%;
        justify-content: center;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 1.12rem;
        min-height: 52px;
    }

    .payment-success-dialog .pay-btn.is-loading {
        opacity: 0.92;
        pointer-events: none;
        cursor: wait;
    }

    .payment-success-dialog .pay-btn--whatsapp {
        background: #25D366;
        border-color: #25D366;
        color: #fff;
    }

    .payment-success-dialog .pay-btn--whatsapp:hover:not(.disabled):not(:disabled) {
        background: #1ebe57;
        border-color: #1ebe57;
        color: #fff;
    }

    .payment-success-footer-hint {
        margin: 16px 0 0;
        font-size: 1.05rem;
        line-height: 1.5;
        color: #98a2b3;
    }

    .payment-success-btn-spinner {
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-top-color: #fff;
        border-radius: 50%;
        animation: paymentProcessSpin .75s linear infinite;
        flex-shrink: 0;
    }

    .checkout-methods .pay-btn + .pay-btn,
    .checkout-methods .pay-btn + a.pay-btn {
        margin-top: 10px;
    }

    .pay-btn.pay-btn--quote {
        background: #fff;
        color: var(--primary-color, #ff7a00);
        border: 1.5px solid var(--primary-color, #ff7a00);
    }

    .pay-btn.pay-btn--ghost {
        background: #fff;
        color: #52606d;
        border: 1.5px solid #d0d5dd;
    }

    .pay-btn.pay-btn--ghost:hover:not(.disabled):not(:disabled) {
        background: #f3f5f7;
        color: #0f2137;
    }

    .pay-btn.pay-btn--quote:hover:not(.disabled):not(:disabled) {
        background: #fff7ed;
        color: var(--primary-color, #ff7a00);
    }

    .pay-btn.pay-btn--quote.disabled,
    .pay-btn.pay-btn--quote:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .quotation-contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 16px;
    }
    .quotation-contact-grid .qf-full {
        grid-column: 1 / -1;
    }
    .quotation-contact-grid .input,
    .quotation-contact-grid textarea.input {
        width: 100%;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 14px;
        background: #fff;
    }
    .quotation-contact-grid textarea.input {
        resize: vertical;
        min-height: 84px;
    }
    .quotation-conditions {
        margin-top: 18px;
        padding: 14px 16px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
    }
    .quotation-conditions-title {
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 8px;
        color: #1f2937;
    }
    .quotation-conditions-list {
        margin: 0;
        padding-left: 18px;
        color: #4b5563;
        font-size: 13px;
        line-height: 1.55;
    }
    .quotation-conditions-list li + li {
        margin-top: 6px;
    }
    .quotation-summary-note {
        margin: 0 0 14px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.45;
        text-align: left;
    }
    .quotation-mode-banner {
        margin: 0 0 14px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        font-size: 13px;
        line-height: 1.45;
        text-align: left;
    }
    .quotation-mode-banner strong {
        display: block;
        margin-bottom: 2px;
    }
    .cart-summary .quotation-mode-banner {
        margin: 0 24px 4px;
    }
    .cart-summary.is-quote-summary .sum-body {
        padding-top: 10px;
    }
    .quotation-summary-meta {
        margin: 0 0 14px;
        padding: 0;
        font-size: 13.5px;
        line-height: 1.45;
        color: #6b7280;
        text-align: left;
    }
    .cart-summary.is-quote-summary .checkout-methods .pay-btn {
        width: 100%;
        justify-content: center;
    }
    .cart-summary.is-quote-summary .terms {
        align-items: flex-start;
        margin-top: 4px;
    }

    .checkout-intent-card {
        margin-top: 1rem;
        overflow: hidden;
    }
    .checkout-intent-card .card-cart-body {
        padding: 14px 18px;
    }
    .checkout-intent-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .checkout-intent-summary--in-summary {
        margin: 12px 24px 0;
        padding: 10px 12px;
        border-radius: 10px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
    }
    /* Mismo aire entre título ↔ aviso y aviso ↔ totales */
    .cart-summary .sum-head:has(+ .checkout-intent-summary--in-summary) {
        padding-bottom: 0;
    }
    .cart-summary .checkout-intent-summary--in-summary + .sum-body {
        padding-top: 12px;
    }
    .cart-summary.is-quote-summary .checkout-intent-summary--in-summary + .sum-body {
        padding-top: 12px;
    }
    .cart-summary .checkout-intent-summary--in-summary + .sum-body .table-totals > tbody > tr:first-child > td {
        padding-top: 0;
    }
    .checkout-intent-summary-text {
        font-size: 13px;
        color: #9a3412;
        line-height: 1.4;
        flex: 1 1 auto;
        text-align: left;
    }
    .checkout-intent-summary-text strong {
        color: #9a3412;
    }
    .checkout-intent-change {
        border: none;
        background: transparent;
        color: var(--primary-color, #ff7a00);
        font-size: 13px;
        font-weight: 700;
        padding: 0;
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 2px;
        flex-shrink: 0;
    }
    .checkout-intent-change:hover {
        opacity: .85;
    }

    .checkout-intent-overlay {
        position: fixed;
        inset: 0;
        z-index: 10060;
        display: none;
        place-items: center;
        padding: 24px;
        background: rgba(15, 33, 55, .55);
        backdrop-filter: blur(4px);
    }
    .checkout-intent-overlay.is-open {
        display: grid;
    }
    .checkout-intent-boot.is-open {
        /* Prioridad visual inmediata, antes de que Vue monte */
        z-index: 10080;
    }
    .checkout-intent-dialog {
        width: min(520px, 94vw);
        padding: 28px 24px 24px;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .25);
        text-align: center;
    }
    .checkout-intent-dialog h3 {
        margin: 0 0 8px;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--title-color, #0f2137);
        line-height: 1.25;
    }
    .checkout-intent-dialog .checkout-intent-sub {
        margin: 0 0 22px;
        font-size: 14px;
        line-height: 1.45;
        color: #6b7280;
    }
    .checkout-intent-dialog-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .checkout-intent-choice {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        width: 100%;
        text-align: left;
        padding: 16px 14px;
        border-radius: 14px;
        border: 1.5px solid #e5e7eb;
        background: #fff;
        color: #1f2937;
        cursor: pointer;
        transition: border-color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .checkout-intent-choice:hover {
        border-color: var(--primary-color, #ff7a00);
        background: #fff7ed;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(15, 33, 55, .06);
    }
    .checkout-intent-choice .cic-title {
        font-size: 15px;
        font-weight: 800;
        line-height: 1.2;
        color: var(--title-color, #0f2137);
    }
    .checkout-intent-choice .cic-desc {
        font-size: 12.5px;
        line-height: 1.4;
        color: #6b7280;
    }

    .checkout-panel-enter-active,
    .checkout-panel-leave-active {
        transition: opacity .28s ease, transform .28s ease;
    }
    .checkout-panel-enter,
    .checkout-panel-leave-to {
        opacity: 0;
        transform: translateY(10px);
    }

    @media (max-width: 767px) {
        .quotation-contact-grid {
            grid-template-columns: 1fr;
        }
        .checkout-intent-dialog-actions {
            grid-template-columns: 1fr;
        }
    }

    .quotation-success-overlay {
        position: fixed;
        inset: 0;
        z-index: 10070;
        display: none;
        place-items: center;
        padding: 24px;
        background: rgba(15, 33, 55, .55);
        backdrop-filter: blur(4px);
    }

    .quotation-success-overlay.is-open {
        display: grid;
    }

    .quotation-request-dialog {
        width: min(560px, 94vw);
        max-height: 92vh;
        overflow: auto;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .25);
    }

    .quotation-request-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid #e9edf1;
    }

    .quotation-request-header h3 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f2137;
    }

    .quotation-request-body {
        padding: 18px 20px 8px;
    }

    .quotation-lines {
        margin: 0 0 16px;
        border: 1px solid #e9edf1;
        border-radius: 12px;
        overflow: hidden;
        background: #fafbfc;
    }

    .quotation-lines table {
        width: 100%;
        margin: 0;
        font-size: 0.86rem;
    }

    .quotation-lines th,
    .quotation-lines td {
        padding: 10px 12px;
        border-bottom: 1px solid #edf1f4;
        vertical-align: top;
    }

    .quotation-lines th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #667085;
        background: #f3f5f7;
    }

    .quotation-lines tr:last-child td {
        border-bottom: 0;
    }

    .quotation-lines .ql-total {
        font-weight: 700;
        color: var(--primary-color, #ff7a00);
        text-align: right;
        padding: 12px;
    }

    .quotation-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .quotation-form-grid .qf-full {
        grid-column: 1 / -1;
    }

    .quotation-form-grid label {
        display: block;
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #0f2137;
    }

    .quotation-form-grid input,
    .quotation-form-grid textarea,
    .quotation-form-grid select {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 14px;
        color: #0f2137;
        background: #fff;
    }

    .quotation-form-grid textarea {
        min-height: 78px;
        resize: vertical;
    }

    .quotation-request-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 14px 20px 20px;
    }

    .quotation-request-footer .pay-btn {
        min-width: 140px;
        justify-content: center;
    }

    .quotation-success-dialog {
        width: min(440px, 94vw);
        padding: 32px 28px 28px;
        border-radius: 20px;
        background: #fff;
        text-align: center;
        box-shadow: 0 24px 70px rgba(15, 33, 55, .25);
    }

    .quotation-success-dialog h3 {
        margin: 0 0 12px;
        font-size: 1.75rem;
        font-weight: 700;
        color: #0f2137;
        line-height: 1.25;
    }

    .quotation-success-dialog .payment-success-sub {
        margin: 0 0 18px;
        color: #667085;
        font-size: 1.2rem;
        line-height: 1.5;
    }

    .quotation-success-code {
        display: inline-block;
        margin-bottom: 18px;
        padding: 8px 14px;
        border-radius: 10px;
        background: #fff4eb;
        color: var(--primary-color, #ff7a00);
        font-weight: 700;
        letter-spacing: .02em;
    }

    .quotation-success-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .quotation-success-actions .pay-btn {
        width: 100%;
        justify-content: center;
    }

    @media (max-width: 576px) {
        .quotation-form-grid {
            grid-template-columns: 1fr;
        }

        .quotation-request-footer {
            flex-direction: column-reverse;
        }

        .quotation-request-footer .pay-btn {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .payment-process-dialog,
        .payment-success-dialog {
            padding: 28px 18px 22px;
        }
    }

    #izipay-payment-host {
        width: 100%;
        min-height: 320px;
    }

    #izipay-payment-host .kr-smart-form,
    #izipay-payment-host .kr-embedded {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        box-shadow: none !important;
        font-family: inherit !important;
    }

    #izipay-payment-host .kr-payment-button {
        border-radius: 12px !important;
        background: var(--primary-color) !important;
    }

    #mp-brick-stash {
        position: fixed;
        left: -9999px;
        top: 0;
        width: 600px;
        height: 420px;
        overflow: hidden;
        visibility: hidden;
        pointer-events: none;
    }

    /* Contenedor del botón X — posicionado sobre la tarjeta blanca de Culqi */
    #culqi-js .culqi-modal-close-anchor {
        display: block;
        position: absolute;
        top: 0;
        left: 0;
        pointer-events: none;
        z-index: 1000000000001;
        opacity: 0;
        visibility: hidden;
        overflow: visible;
        transition: opacity .22s ease, visibility .22s ease;
    }

    #culqi-js .culqi-modal-close-anchor--visible {
        opacity: 1;
        visibility: visible;
    }

    #culqi-js .culqi-modal-close-btn {
        display: none;
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
        width: 32px;
        height: 32px;
        margin: 0;
        padding: 0;
        border: none;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        color: #ff6b00;
        line-height: 1;
        pointer-events: auto;
        transition: color .15s ease, opacity .22s ease;
        opacity: 0;
    }

    #culqi-js .culqi-modal-close-btn--visible {
        display: flex;
        opacity: 1;
    }

    #culqi-js .culqi-modal-close-btn:hover {
        background: transparent;
        color: #e55f00;
        box-shadow: none;
    }

    #culqi-js .culqi-modal-close-btn:focus {
        outline: none;
    }

    #culqi-js .culqi-modal-close-btn:focus-visible {
        outline: 2px solid rgba(255, 107, 0, 0.45);
        outline-offset: 2px;
    }

    #culqi-js .culqi-modal-close-btn svg {
        display: block;
        width: 22px;
        height: 22px;
        pointer-events: none;
        stroke: currentColor;
        stroke-width: 2.5;
    }

    @media (max-width: 520px) {
        #culqi-js .culqi-modal-close-btn {
            top: 12px;
            right: 12px;
            width: 30px;
            height: 30px;
        }

        #culqi-js .culqi-modal-close-btn svg {
            width: 20px;
            height: 20px;
        }
    }

    @media (max-width: 576px) {
        .swal2-popup.mp-payment-swal {
            width: 94vw !important;
            padding: 0 0.85rem 1.25rem !important;
        }

        .swal2-popup.mp-payment-swal .swal2-title {
            width: calc(100% + 1.7rem);
            margin-right: -0.85rem !important;
            margin-left: -0.85rem !important;
        }

        .gateway-payment-overlay {
            padding: 10px;
        }

        .gateway-payment-body {
            padding: 16px 12px 20px;
        }
    }

    #addressListModal .modal-content,
    #addressModal .modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 18px 40px rgba(20, 30, 45, .16);
    }

    #addressListModal .modal-content {
        display: flex;
        flex-direction: column;
        max-height: min(90vh, 640px);
    }

    #addressListModal .modal-dialog {
        max-width: 560px;
        width: calc(100% - 32px);
        margin: 1rem auto;
    }

    @media (max-width: 576px) {
        #addressListModal .modal-dialog {
            width: calc(100% - 20px);
            max-width: none;
            margin: 0.5rem auto;
        }

        #addressListModal .modal-content {
            max-height: 92vh;
            border-radius: 14px;
        }

        #addressListModal .modal-footer-wrap {
            flex-direction: column;
            gap: 8px;
            padding: 10px 14px calc(12px + env(safe-area-inset-bottom, 0px));
        }

        #addressListModal .modal-footer-wrap .pay-btn,
        #addressListModal .modal-footer-wrap .pay-btn:not(.pay-btn--ghost) {
            flex: none;
            width: 100%;
        }

        #addressListModal .addr-list-card {
            padding: 14px 12px;
            gap: 10px;
        }
    }

    #addressListModal .modal-header,
    #addressModal .modal-header {
        background: #fff;
        border-bottom: 1px solid #edf1f4;
        padding: 14px 18px;
        flex-shrink: 0;
    }

    #addressModal .modal-dialog {
        max-width: 520px;
    }

    #addressListModal .modal-title,
    #addressModal .modal-title {
        font-weight: 700;
        color: #1f2a37;
        font-size: 17px;
        margin: 0;
    }

    #addressListModal .close,
    #addressModal .close {
        font-size: 1.5rem;
        font-weight: 700;
        opacity: 0.7;
        transition: opacity 0.2s;
        padding: 0;
        margin: 0;
    }

    #addressListModal .close:hover,
    #addressModal .close:hover {
        opacity: 1;
    }

    #addressListModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 12px 16px 10px;
    }

    #addressListModal .addr-list-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: #c5ced6 transparent;
        padding-right: 2px;
    }

    #addressListModal .addr-list-scroll::-webkit-scrollbar {
        width: 6px;
    }

    #addressListModal .addr-list-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    #addressListModal .addr-list-scroll::-webkit-scrollbar-thumb {
        background: #c5ced6;
        border-radius: 999px;
    }

    #addressListModal .addr-list-scroll::-webkit-scrollbar-thumb:hover {
        background: #a8b3bd;
    }

    #addressListModal .addr-list-add-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        background: none;
        padding: 0;
        margin: 0 0 14px;
        color: var(--primary-color);
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        flex-shrink: 0;
    }

    #addressListModal .addr-list-add-link:hover {
        text-decoration: underline;
    }

    #addressListModal .addr-list-cards {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    #addressListModal .addr-list-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 14px;
        width: 100%;
        box-sizing: border-box;
        min-height: 76px;
        padding: 16px 14px;
        border: 1.5px solid #e3e8ee;
        border-radius: 14px;
        background: #fff;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
    }

    #addressListModal .addr-list-card:hover {
        border-color: hsl(var(--primary-h), var(--primary-s), 80%);
    }

    #addressListModal .addr-list-card--active {
        border-color: var(--primary-color);
        background: hsl(var(--primary-h), var(--primary-s), 98%);
        box-shadow: 0 0 0 1px hsl(var(--primary-h), var(--primary-s), 90%);
    }

    #addressListModal .addr-list-card__radio {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        margin: 0;
        accent-color: var(--primary-color);
        pointer-events: none;
    }

    #addressListModal .addr-list-card__body {
        flex: 1;
        min-width: 0;
        padding-right: 4px;
    }

    #addressListModal .addr-list-card__body strong,
    #addressListModal .addr-list-card__body .addr-list-card__primary {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: #1f2a37;
        line-height: 1.35;
        margin-bottom: 4px;
    }

    #addressListModal .addr-list-card__body span,
    #addressListModal .addr-list-card__body .addr-list-card__secondary {
        display: block;
        font-size: 13px;
        color: #6b7785;
        line-height: 1.45;
        word-break: break-word;
    }

    #addressListModal .addr-list-card__menu-wrap {
        position: relative;
        flex-shrink: 0;
        align-self: flex-start;
        margin-top: 2px;
    }

    #addressListModal .addr-list-card__menu-btn {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 8px;
        background: transparent;
        color: #667788;
        font-size: 20px;
        line-height: 1;
        cursor: pointer;
        display: grid;
        place-items: center;
    }

    #addressListModal .addr-list-card__menu-btn:hover {
        background: #f2f5f7;
        color: #1f2a37;
    }

    #addressListModal .addr-list-card__menu {
        min-width: 140px;
        background: #fff;
        border: 1px solid #e7edf2;
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(20, 30, 45, .14);
        overflow: hidden;
    }

    #addressListModal .addr-list-card__menu--floating {
        position: fixed;
        z-index: 1070;
        opacity: 0;
        pointer-events: none;
        transition: opacity .12s ease;
    }

    #addressListModal .addr-list-card__menu--floating.is-positioned {
        opacity: 1;
        pointer-events: auto;
    }

    #addressListModal .addr-list-card__menu button {
        display: block;
        width: 100%;
        border: none;
        background: #fff;
        text-align: left;
        padding: 11px 14px;
        font-size: 13px;
        color: #1f2a37;
        cursor: pointer;
    }

    #addressListModal .addr-list-card__menu button:hover {
        background: #f5f8fa;
    }

    #addressListModal .addr-list-card__menu button.danger {
        color: #d64545;
    }

    #addressListModal .addr-list-empty {
        padding: 20px 12px 8px;
        text-align: center;
        color: #8a96a3;
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
    }

    #addressListModal .addr-list-empty-hint {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #a8b3bd;
    }

    #addressListModal .modal-footer-wrap {
        display: flex;
        flex-shrink: 0;
        gap: 10px;
        padding: 12px 16px 16px;
        border-top: 1px solid #edf1f4;
        background: #fff;
    }

    #addressListModal .pay-btn--ghost {
        flex: 1;
        background: #fff;
        color: #1f2a37;
        border: 1.5px solid #dde3e9;
        box-shadow: none;
    }

    #addressListModal .pay-btn--ghost:hover {
        background: #f5f7f8;
        border-color: #c8d0d8;
    }

    #addressListModal .modal-footer-wrap .pay-btn:not(.pay-btn--ghost) {
        flex: 1.4;
    }

    #addressModal .modal-dialog {
        max-width: 520px;
    }

    #addressModal #map {
        height: 220px;
        width: 100%;
        border: none;
        border-radius: 0;
    }

    #addressModal .modal-header {
        background: #fff;
        border-bottom: 1px solid #edf1f4;
        padding: 14px 18px;
    }

    #addressModal .modal-title {
        font-weight: 700;
        color: #1f2a37;
        font-size: 17px;
        margin: 0;
    }

    #addressModal .close {
        font-size: 1.5rem;
        font-weight: 700;
        opacity: 0.7;
        transition: opacity 0.2s;
        padding: 0;
        margin: 0;
    }

    #addressModal .close:hover {
        opacity: 1;
    }

    #addressModal .modal-body {
        padding: 0;
    }

    #addressModal .addr-modal-form {
        padding: 12px 16px 0;
    }

    #addressModal .addr-modal-form .field-label {
        margin-bottom: 6px;
    }

    #addressModal .addr-modal-form .mb-2 {
        margin-bottom: 10px !important;
    }

    #addressModal .addr-map-geocoding {
        font-size: 11px;
        color: var(--primary-color);
        margin-top: 4px;
    }

    #addressModal .modal-footer-wrap {
        padding: 12px 16px 16px;
        border-top: 1px solid #edf1f4;
    }

    .btn-input-group {
        height: 26.6px !important;
        border-radius: 0px !important;
        min-width: 32px !important;
    }

    .card-body-h-auto {
        min-height: auto !important;
    }

    .collapse-arrow {
        margin-left: auto;
        transition: transform 0.25s ease;
        flex-shrink: 0;
    }

    .btn[aria-expanded="false"] .collapse-arrow {
        transform: rotate(-90deg);
    }

    /* Aceptación de términos y condiciones */
    .terms {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 14px 0 12px;
        cursor: pointer;
        user-select: none;
        line-height: 1.35;
        font-size: 13px
    }

    .terms input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .terms .box {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        border-radius: 7px;
        border: 2px solid #d5dbe0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        transition: background-color .15s ease, border-color .15s ease, transform .12s ease;
    }

    .terms .box svg {
        opacity: 0;
        transform: scale(.6);
        transition: opacity .15s ease, transform .15s ease;
    }

    .terms--checked .box svg {
        opacity: 1;
        transform: scale(1);
    }

    .terms-txt a:hover {
        text-decoration: underline;
    }

    /* Desktop - una fila */
    .items-cart {
        display: grid;
        grid-template-columns: 80px 1fr auto auto auto;
        grid-template-areas: "thumb info quantity total delete";
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .items-cart .thumb { grid-area: thumb; }
    .items-cart .info { grid-area: info; }
    .items-cart .modern-quantity-container { grid-area: quantity; }
    .items-cart .total { grid-area: total; }
    .items-cart .delete-item-btn { grid-area: delete; }

    /* Móvil - dos filas */
    @media (max-width: 576px) {
        .items-cart {
            grid-template-columns: 70px 1fr auto auto auto;
            grid-template-areas:
                "thumb info info info info"
                "thumb quantity quantity total delete";
        }
    }

    /* Guest checkout modals — removidos; se conservan estilos de formulario invitado */
    .guest-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .guest-form-grid .field-full {
        grid-column: 1 / -1;
    }

    @media (max-width: 767px) {
        .guest-form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Iconos brand al extremo derecho de cada tarjeta (diseño Fabrizio) */
    .pay-methods .pay-method {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pay-methods .pay-method input[type="radio"] {
        order: 1;
    }

    .pay-methods .pay-method-label {
        order: 2;
        flex: 1;
    }

    .pay-methods .pay-method-ic {
        order: 3;
        flex: none;
        margin-left: auto;
    }

    .pay-methods .pay-method-ic--brand {
        width: 48px;
        height: 48px;
        border-radius: 11px;
        border: 1px solid #e4e8ed;
        background: #fff;
        padding: 0;
        overflow: hidden;
        display: grid;
        place-items: center;
    }

    .pay-methods .pay-method-ic--brand img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .pay-method-panel {
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 12px;
        background: #fafafa;
    }

    .pay-method-panel p {
        font-size: 13px;
        color: #555;
        margin-bottom: 0;
        white-space: pre-line;
    }

    .pay-method-action {
        width: 100%;
        margin-top: 12px;
    }

    .checkout-hint {
        font-size: 13px;
        color: #666;
        text-align: center;
        margin: 0 0 8px;
        line-height: 1.4;
    }

    .contact-options {
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    .contact-options__intro {
        font-size: 14px;
        color: #4b5563;
        margin: 0 0 .25rem;
        line-height: 1.5;
    }

    .document-notice {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: .85rem 1rem;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.45;
        margin-top: 1rem;
    }

    .document-notice--info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .document-notice--success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .document-notice--warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }

    .document-notice--loading {
        display: none !important;
    }

    .document-notice strong {
        display: block;
        margin-bottom: .15rem;
    }

    .document-notice--plan-query-limit {
        display: none !important;
    }

    .checkout-section-enter-active {
        transition: opacity 0.35s ease, transform 0.35s ease;
        overflow: hidden;
    }

    .checkout-section-enter {
        opacity: 0;
        transform: translateY(-10px);
    }

    .contact-access-block {
        margin: 0;
    }

    .contact-access-notice {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: 1rem 1.15rem;
        border-radius: 12px;
        background: hsl(var(--primary-h, 29), var(--primary-s, 85%), 96%);
        border: 1px solid hsl(var(--primary-h, 29), var(--primary-s, 70%), 88%);
    }

    .contact-access-notice__icon {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin-top: 1px;
        color: var(--primary-color, #e67e22);
        opacity: .85;
    }

    .contact-access-notice__text {
        margin: 0;
        font-size: 14px;
        line-height: 1.65;
        color: #4b5563;
    }

    .contact-access-link {
        display: inline;
        padding: 0;
        margin: 0;
        border: none;
        background: none;
        font: inherit;
        font-weight: 600;
        color: var(--title-color, #1f2937);
        text-decoration: none;
        cursor: pointer;
        transition: color .18s ease;
    }

    .contact-access-link:hover,
    .contact-access-link:focus {
        color: var(--primary-color, #e67e22);
        outline: none;
    }

    .contact-access-link:focus-visible {
        outline: 2px solid hsl(var(--primary-h, 29), var(--primary-s, 85%), 75%);
        outline-offset: 2px;
        border-radius: 2px;
    }

    .contact-guest-form {
        animation: contactFormIn .3s ease;
    }

    @keyframes contactFormIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endpush

@section('content')

@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $ecommerceConfiguration = $configuration ?? \App\Models\Tenant\ConfigurationEcommerce::first();
    $phoneWhatsapp = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
    $showWhatsapp = ($configurationModel->enable_whatsapp ?? false) && !empty($phoneWhatsapp);
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $itemsBasePath = asset('storage/uploads/items');
    $googleMapsApiKey = app(Modules\Ecommerce\Http\Controllers\EcommerceController::class)->getGoogleMaps();
    $globalDiscountTypeId = $global_discount_type_id ?? null;

    $quoteEnabledBoot = (bool) ($quotation_enabled ?? false);
    $quoteModeBoot = $quotation_mode ?? 'quote_and_sell';
    $quoteHybridBoot = $quoteEnabledBoot && $quoteModeBoot === 'quote_and_sell';
    $quoteOnlyBoot = $quoteEnabledBoot && $quoteModeBoot === 'quote_only';
@endphp

{{-- Config mínima + overlay inmediato (antes de Vite/Vue) para evitar flickeo --}}
<script>
    window.__ecommerce_quotation_boot = {
        enabled: {!! json_encode($quoteEnabledBoot) !!},
        mode: {!! json_encode($quoteModeBoot) !!},
        hybrid: {!! json_encode($quoteHybridBoot) !!},
        quote_only: {!! json_encode($quoteOnlyBoot) !!},
        user_id: {!! json_encode(optional(Auth::guard('ecommerce')->user())->id) !!}
    };
</script>
<div
    id="checkout-intent-boot"
    class="checkout-intent-overlay checkout-intent-boot"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="checkout-intent-boot-title"
>
    <div class="checkout-intent-dialog">
        <h3 id="checkout-intent-boot-title">¿Qué desea hacer con su pedido?</h3>
        <p class="checkout-intent-sub">Elija cómo continuar. Podrá cambiar esta opción más adelante.</p>
        <div class="checkout-intent-dialog-actions">
            <button type="button" class="checkout-intent-choice" data-intent="purchase">
                <span class="cic-title">Comprar</span>
                <span class="cic-desc">Checkout con envío y métodos de pago</span>
            </button>
            <button type="button" class="checkout-intent-choice checkout-intent-choice--quote" data-intent="quote">
                <span class="cic-title">Solicitar cotización</span>
                <span class="cic-desc">Solo datos de contacto, sin pagar ahora</span>
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    var boot = window.__ecommerce_quotation_boot || {};
    var hasItems = false;
    try {
        var raw = localStorage.getItem('products_cart');
        var arr = raw ? JSON.parse(raw) : [];
        hasItems = Array.isArray(arr) && arr.length > 0;
    } catch (e) {}
    var restored = null;
    try {
        restored = sessionStorage.getItem('ecommerce_checkout_intent');
    } catch (e) {}
    var restoreQuote = restored === 'quote' && !!boot.user_id;
    var showModal = !!(boot.hybrid && hasItems && !boot.quote_only && !restoreQuote);
    window.__checkoutIntentBootState = {
        checkoutIntent: (boot.quote_only || restoreQuote) ? 'quote' : 'purchase',
        checkoutIntentChosen: !!(boot.quote_only || restoreQuote || !boot.hybrid || !hasItems),
        checkoutIntentModalVisible: showModal
    };
    if (!showModal) return;
    var el = document.getElementById('checkout-intent-boot');
    if (!el) return;
    el.style.display = 'grid';
    el.classList.add('is-open');
    el.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    el.querySelectorAll('[data-intent]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var intent = btn.getAttribute('data-intent') === 'quote' ? 'quote' : 'purchase';
            if (typeof window.__confirmCheckoutIntentBoot === 'function') {
                window.__confirmCheckoutIntentBoot(intent);
            } else {
                window.__checkoutIntentPendingChoice = intent;
            }
        });
    });
})();
</script>

<div class="row" id="app">
    <div class="col-12">
        <h2 class="my-4 mt-4" style="font-weight: 900;">@{{ isQuotationCheckout ? 'Solicitar cotización' : 'Finalizar compra' }}</h2>
    </div>
    <div class="col-md-8 mb-3">
        <div class="card card-cart">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#cartCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card cart">
                        <svg clip-rule="evenodd"
                            fill-rule="evenodd"
                            height="24"
                            stroke-linejoin="round"
                            stroke-miterlimit="2"
                            viewBox="0 0 512 512" width="24" xmlns="http://www.w3.org/2000/svg" id="fi_4893746">
                            <path d="m211.892 383.468c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm176.22 0c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm-288.464-273.226s63.534 222.705 63.534 222.705c6.591 23.103 27.703 39.034 51.727 39.034h157.478c33.502 0 61.98-24.47 67.023-57.59 4.821-31.664 11.838-77.75 17.065-112.081 2.869-18.84-2.626-37.994-15.046-52.449-12.42-14.454-30.529-22.769-49.586-22.769h-235.394l-8.72-30.567c-7.633-26.757-32.085-45.209-59.91-45.209-23.033 0-51.825 0-51.825 0-13.798 0-25 11.202-25 25s11.202 25 25 25h51.825c5.494 0 10.321 3.643 11.829 8.926zm71.066 66.85h221.129c4.482 0 8.741 1.956 11.663 5.355 2.921 3.4 4.213 7.905 3.539 12.337 0 0-17.066 112.081-17.066 112.081-1.323 8.693-8.798 15.116-17.592 15.116h-157.478c-1.693 0-3.181-1.122-3.645-2.751 0 0-40.55-142.138-40.55-142.138z"/>
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Tu carrito</span>
                    <span class="head-summary">
                        <template v-if="records.length > 0">
                            <b>@{{ records.length }} @{{ records.length === 1 ? 'producto' : 'productos' }}</b>
                            <span class="head-summary-sep" v-if="showCartPrices">·</span>
                            <span class="head-summary-amt" v-if="showCartPrices">S/ @{{ summary.total }}</span>
                        </template>
                        <span v-else class="head-summary-warn">Carrito vacío</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>

            <div id="cartCollapse" class="collapse show">
                <div class="card-cart-body">
                    <div v-if="records.length > 0">
                        <div v-for="(row, index) in records" class="items-cart" :key="row.id">
                            <div class="thumb">
                                <figure class="product-image-container m-0">
                                    <a href="#" class="product-image">
                                        <img class="image-product w-100" :src="(row.image && row.image !== 'imagen-no-disponible.jpg') ? '{{ $itemsBasePath }}' + '/' + row.image : '{{ $defaultImagePath }}'" :alt="row.description || 'Producto sin imagen'">
                                    </a>
                                </figure>
                            </div>

                            <div class="info">
                                <h5 class="product-title m-0">
                                    <a href="#">@{{ row.description }}</a>
                                </h5>
                                <span class="price text-muted" v-if="showCartPrices">
                                    @{{ row.currency_type_symbol }} @{{ row.sale_unit_price }}
                                </span>
                            </div>

                            <div class="input-group input-group-sm modern-quantity-container w-auto">
                                <div class="input-group-prepend">
                                    <button class="btn btn-outline-secondary btn-input-group" type="button" @click.stop.prevent="decrementQuantity(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /></svg>
                                    </button>
                                </div>
                                <input class="input-quantity form-control text-center" :data-product="row.id" type="number" v-model.number="row.cantidad">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-input-group" type="button" @click.stop.prevent="incrementQuantity(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                    </button>
                                </div>
                            </div>

                            <strong class="total" v-if="showCartPrices">@{{ row.currency_type_symbol }} @{{ (row.sale_unit_price * row.cantidad).toFixed(2) }}</strong>
                            <strong class="total text-muted" v-else>x @{{ row.cantidad }}</strong>

                            <button type="button" @click="deleteItem(row.id, index)" class="btn btn-sm btn-link text-muted px-0 delete-item-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            </button>

                        </div>
                    </div>
                    <div v-else class="cart-empty">
                        <span class="cart-empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.966 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z"/><path d="M9 11v-5a3 3 0 0 1 6 0v5"/></svg>
                        </span>
                        <p class="cart-empty-title">Tu carrito está vacío</p>
                        <p class="cart-empty-text">@{{ isQuotationCheckout ? 'Agrega productos para solicitar tu cotización.' : 'Agrega productos para continuar con tu compra.' }}</p>
                        <a href="/ecommerce" class="cart-empty-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Ver productos
                        </a>
                    </div>
                </div>

                <div class="card-footer card-cart-footer border-0">
                    <div v-if="showWhatsapp && records.length > 0" class="mb-3">
                        <button type="button" @click="clickConsultWhatsappCart" class="btn btn-whatsapp w-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" /><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" /></svg>
                            Consultar por WhatsApp
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <a href="/ecommerce" class="text-muted text-capitalize">
                                <i class="fa fa-arrow-left"></i>
                                Continuar Comprando
                            </a>
                        </div>
                        <div class="col-6 text-right" v-if="records.length > 0">
                            <a href="#" @click="clearShoppingCart" class="text-danger text-capitalize">Limpiar Carrito</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Banner solo cotizar (sin opción de compra) --}}
        <div class="quotation-mode-banner" v-if="quoteOnlyMode && records.length > 0" style="margin-top: 1rem;">
            <strong>Solo cotización</strong>
            Esta tienda no procesa compras en línea. Completa tus datos de contacto para solicitar tu cotización.
        </div>

        {{-- Datos de contacto: opciones iniciales o formulario de invitado --}}
        <div
            class="card card-cart contact-data-card"
            v-if="records.length > 0 && !isLoggedIn && !isQuotationCheckout && (checkoutIntentChosen || !isHybridQuotationMode)"
            id="contactDataCollapse"
        >
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#contactDataBody" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Datos de contacto</span>
                    <span class="head-summary">
                        <b v-if="guestCheckoutAccepted && guest_form.email">@{{ guest_form.email }}</b>
                        <span v-else-if="guestCheckoutAccepted" class="head-summary-warn">Completa tus datos</span>
                        <span v-else class="head-summary-warn">Elige cómo continuar</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="contactDataBody" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body">
                    {{-- Paso 1: aviso con enlaces de acceso en línea --}}
                    <div class="contact-access-block" v-if="!guestCheckoutAccepted">
                        <div class="contact-access-notice" role="status">
                            <span class="contact-access-notice__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="5" y="11" width="14" height="10" rx="2"/>
                                    <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                                </svg>
                            </span>
                            <p class="contact-access-notice__text">
                                Puedes
                                <button type="button" class="contact-access-link" @click="startGuestCheckout">comprar como invitado</button>
                                o
                                <button type="button" class="contact-access-link" @click="openLoginRegisterModal">iniciar sesión</button>
                                para guardar tus datos.
                            </p>
                        </div>
                    </div>

                    {{-- Paso 2: formulario de contacto (solo tras elegir invitado) --}}
                    <div class="contact-guest-form" v-if="guestCheckoutAccepted">
                    <p class="hint mb-3">Estos datos se usarán solo para esta compra. No se creará una cuenta ni se solicitará contraseña.</p>
                    <div class="guest-form-grid">
                        <div class="field-full">
                            <label class="field-label" for="guest_email">Correo electrónico *</label>
                            <input
                                id="guest_email"
                                type="email"
                                class="input"
                                v-model.trim="guest_form.email"
                                placeholder="tu@correo.com"
                                autocomplete="email"
                                required
                            >
                        </div>
                        <div>
                            <label class="field-label" for="guest_phone">Teléfono *</label>
                            <input
                                id="guest_phone"
                                type="tel"
                                class="input"
                                v-model.trim="guest_form.telephone"
                                placeholder="Ej: 987 654 321"
                                maxlength="15"
                                inputmode="numeric"
                                required
                            >
                        </div>
                        <div>
                            <label class="field-label" for="guest_doc_type">Tipo de documento *</label>
                            <select
                                id="guest_doc_type"
                                class="input"
                                v-model="guest_form.identity_document_type_id"
                            >
                                <option
                                    v-for="option in guestDocumentTypeOptions"
                                    :key="option.id"
                                    :value="option.id"
                                >@{{ option.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="guest_doc_number">Número de documento *</label>
                            <input
                                id="guest_doc_number"
                                type="text"
                                class="input"
                                v-model.trim="guest_form.number"
                                :maxlength="guestDocumentNumberMaxLength"
                                inputmode="numeric"
                                required
                            >
                        </div>
                        <div class="field-full">
                            <label class="field-label" for="guest_name">Nombres / Razón social *</label>
                            <input
                                id="guest_name"
                                type="text"
                                class="input"
                                v-model.trim="guest_form.name"
                                placeholder="Nombre completo o razón social"
                                required
                            >
                        </div>
                    </div>

                    <div
                        v-if="guestHighAmountIdentityNotice"
                        class="document-notice document-notice--warning mt-3"
                        role="status"
                    >
                        <span aria-hidden="true">&#9888;</span>
                        <div>@{{ guestHighAmountIdentityNotice }}</div>
                    </div>

                    <div
                        v-if="guestInvoiceNotice"
                        class="document-notice document-notice--info"
                        role="status"
                    >
                        <span aria-hidden="true">&#9432;</span>
                        <div>
                            <strong>Comprobante: @{{ guestInvoiceTypeLabel }}</strong>
                            @{{ guestInvoiceNotice }}
                        </div>
                    </div>

                    <div
                        v-if="guestDocumentStatus"
                        class="document-notice"
                        :class="{
                            'document-notice--loading': guestDocumentStatus.type === 'loading',
                            'document-notice--success': guestDocumentStatus.type === 'success',
                            'document-notice--info': guestDocumentStatus.type === 'info',
                            'document-notice--warning': guestDocumentStatus.type === 'warning',
                            'document-notice--plan-query-limit': guestDocumentStatus.message && (
                                guestDocumentStatus.message.toLowerCase().indexOf('límite de consultas') !== -1
                                || guestDocumentStatus.message.toLowerCase().indexOf('limite de consultas') !== -1
                            ),
                        }"
                        role="status"
                    >
                        <span v-if="guestDocumentStatus.type === 'info'" aria-hidden="true">&#9432;</span>
                        <span v-else-if="guestDocumentStatus.type === 'success'" aria-hidden="true">&#10003;</span>
                        <span v-else-if="guestDocumentStatus.type === 'warning'" aria-hidden="true">&#9888;</span>
                        <div>
                            <strong v-if="guestExistingCustomer">Cliente registrado</strong>
                            @{{ guestDocumentStatus.message }}
                            <button
                                v-if="guestExistingCustomer"
                                type="button"
                                class="btn btn-link p-0 align-baseline ml-1"
                                @click="openLoginRegisterModal"
                            >
                                Iniciar sesión
                            </button>
                        </div>
                    </div>

                    </div>{{-- /.contact-guest-form --}}
                </div>
            </div>
        </div>

        <transition name="checkout-panel">
        <div class="card card-cart" v-if="records.length > 0 && !isQuotationCheckout && (checkoutIntentChosen || !isHybridQuotationMode) && showCheckoutSections" key="delivery-card">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#deliveryCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#2b2b2b"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            >
                            <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" />
                            <path d="M3 9l4 0" />
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Datos de envio</span>
                    <span class="head-summary">
                        <template v-if="isPickupMode">
                            <b v-if="selectedPickupBranch">@{{ selectedPickupBranch.name }}</b>
                            <span v-else class="head-summary-warn">Elige sucursal</span>
                        </template>
                        <template v-else-if="form_contact.address || form_contact.telephone">
                            <span class="head-summary-addr" v-if="form_contact.address">@{{ form_contact.address }}</span>
                            <span class="head-summary-sep" v-if="form_contact.address && form_contact.telephone">·</span>
                            <b v-if="form_contact.telephone">@{{ form_contact.telephone }}</b>
                        </template>
                        <span v-else class="head-summary-warn">Falta completar</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="deliveryCollapse" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body ship-body">

                    {{-- Switch: Recojo en tienda (solo si está habilitado en configuración) --}}
                    <div class="pickup-switch" v-if="enableStorePickup">
                        {{-- <label class="pickup-switch-label" @click="togglePickupMode">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9 -7 9 7v11a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            Recojo en tienda
                        </label>
                        <button type="button" class="switch" :class="{ 'switch--on': isPickupMode }" @click="togglePickupMode" :aria-pressed="isPickupMode ? 'true' : 'false'" aria-label="Activar recojo en tienda">
                            <span class="switch-knob"></span>
                        </button> --}}
                        <label
                            class="option-card send-mode"
                            :class="{ 'option-card--active': !isPickupMode }"
                        >
                            <span class="icon-delivery">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentcolor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path> <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path> <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"></path> <path d="M3 9l4 0"></path></svg>
                            </span>
                            <span class="option-card-body">
                                <strong>Envío a domicilio</strong>
                                <span class="option-card-sub">Llega a tu dirección</span>
                            </span>
                            <input
                                type="radio"
                                name="delivery_mode"
                                style="margin-left: auto"
                                :checked="!isPickupMode"
                                @change="setPickupMode(false)"
                            >
                        </label>
                        <label
                            class="option-card send-mode"
                            :class="{ 'option-card--active': isPickupMode }"
                        >
                            <span class="icon-delivery">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1-5h16l1 5"></path><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"></path><path d="M3 9h18"></path><path d="M9 21v-6h6v6"></path></svg>
                            </span>
                            <span class="option-card-body">
                                <strong>Recojo en tienda</strong>
                                <span class="option-card-sub">Gratis · tú lo retiras</span>
                            </span>
                            <input
                                type="radio"
                                name="delivery_mode"
                                style="margin-left: auto"
                                :checked="isPickupMode"
                                @change="setPickupMode(true)"
                            >
                        </label>
                    </div>

                    <div class="ship-grid">
                        {{-- Columna izquierda: dirección de entrega o sucursal de recojo --}}
                        <div class="ship-col">
                            {{-- Modo recojo en tienda: radio buttons de sucursales --}}
                            <template v-if="isPickupMode">
                                <span class="field-label">Selecciona una sucursal</span>
                                <div v-if="pickupBranches.length === 0" class="ship-alert ship-alert--info">
                                    No hay sucursales de recojo configuradas.
                                </div>
                                <div v-else class="option-list">
                                    <label
                                        v-for="branch in pickupBranches"
                                        :key="branch.id"
                                        class="option-card"
                                        :class="{ 'option-card--active': selectedPickupBranch && selectedPickupBranch.id === branch.id }"
                                    >
                                        <input
                                            type="radio"
                                            :value="branch.id"
                                            :checked="selectedPickupBranch && selectedPickupBranch.id === branch.id"
                                            @change="selectPickupBranch(branch)"
                                        >
                                        <span class="option-card-body">
                                            <strong>@{{ branch.name }}</strong>
                                            <span class="option-card-sub" v-if="branch.address">@{{ branch.address }}</span>
                                        </span>
                                    </label>
                                </div>
                            </template>

                            {{-- Modo delivery normal --}}
                            <template v-else>
                                <div
                                    v-if="guestReturningAddressNotice && isGuestCheckoutActive"
                                    class="document-notice document-notice--success mb-3"
                                    role="status"
                                >
                                    <span aria-hidden="true">&#10003;</span>
                                    <div>
                                        Gracias por volver de nuevo. Tenemos tu dirección guardada; puedes confirmarla, cambiarla o actualizarla
                                    </div>
                                </div>
                                <span class="field-label">Dirección de entrega</span>
                                <button v-if="!form_contact.address" type="button" class="addr-btn" @click="openAddAddressFlow">
                                    <span class="plus">+</span>
                                    Agregar dirección
                                </button>
                                <div v-else class="addr-card">
                                    <span class="addr-card-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7 -9 13 -9 13s-9 -6 -9 -13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    </span>
                                    <div class="addr-card-info">
                                        <strong>@{{ form_contact.address }}</strong>
                                        <span class="addr-card-sub">@{{ ubigeoLabel }}</span>
                                    </div>
                                    <button type="button" class="addr-change" @click="openChangeAddressFlow">Cambiar</button>
                                </div>

                                {{-- Mensaje sin cobertura de delivery --}}
                                <div v-if="deliveryMessage != ''" class="ship-alert ship-alert--warn" role="alert">
                                    <strong>&#9888; Sin cobertura:</strong> @{{ deliveryMessage }}
                                </div>

                                {{-- Opciones de envío: mostrar cuando hay múltiples zonas disponibles --}}
                                <div v-if="availableDeliveryZones.length > 1" class="option-list mt-2">
                                    <span class="field-label">Opciones de envío</span>
                                    <label
                                        v-for="zone in availableDeliveryZones"
                                        :key="zone.id"
                                        class="option-card option-card--row"
                                        :class="{ 'option-card--active': deliveryZone && deliveryZone.id === zone.id }"
                                    >
                                        <span class="option-card-left">
                                            <input
                                                type="radio"
                                                :value="zone.id"
                                                :checked="deliveryZone && deliveryZone.id === zone.id"
                                                @change="selectDeliveryZone(zone)"
                                            >
                                            <span>@{{ zone.name }}</span>
                                        </span>
                                        <strong class="option-card-price">S/ @{{ parseFloat(zone.price).toFixed(2) }}</strong>
                                    </label>
                                </div>

                                {{-- Una sola zona disponible: mostrar informativo --}}
                                <div v-else-if="availableDeliveryZones.length === 1 && deliveryZone" class="ship-note ship-note--ok mt-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5 -5"/></svg>
                                    Envío disponible: <strong>@{{ deliveryZone.name }}</strong> &mdash; S/ @{{ parseFloat(deliveryZone.price).toFixed(2) }}
                                </div>
                            </template>
                        </div>

                        {{-- Columna derecha: teléfono de contacto --}}
                        <div class="ship-col">
                            <span class="field-label">Teléfono de contacto</span>
                            <input
                                type="tel"
                                v-model="form_contact.telephone"
                                class="input"
                                placeholder="Ej: 987 654 321"
                                maxlength="15"
                                inputmode="numeric"
                                required
                            >
                            <p class="hint">Te escribiremos por aquí para coordinar la entrega.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </transition>

        {{-- Datos de contacto (modo cotización) --}}
        <transition name="checkout-panel">
        <div class="card card-cart" v-if="records.length > 0 && isQuotationCheckout && (checkoutIntentChosen || quoteOnlyMode)" id="quotationContactCard" key="quote-contact-card">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#quotationContactCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card" v-text="isLoggedIn ? 'Detalles de la cotización' : 'Datos de contacto'"></span>
                    <span class="head-summary">
                        <b v-if="quotationContactSummary">@{{ quotationContactSummary }}</b>
                        <span v-else class="head-summary-warn">Completa tus datos</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="quotationContactCollapse" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body ship-body">
                    <div class="quotation-contact-grid">
                        {{-- Contacto solo para invitados: con sesión los datos ya vienen del cliente --}}
                        @guest('ecommerce')
                        <template v-if="!isLoggedIn">
                            <div class="qf-full">
                                <span class="field-label">Nombre</span>
                                <input type="text" class="input" v-model="quotationForm.contact_name" autocomplete="name" placeholder="Tu nombre completo">
                            </div>
                            <div>
                                <span class="field-label">Correo</span>
                                <input type="email" class="input" v-model="quotationForm.email" autocomplete="email" placeholder="correo@ejemplo.com">
                            </div>
                            <div>
                                <span class="field-label">Teléfono</span>
                                <input type="tel" class="input" v-model="quotationForm.telephone" autocomplete="tel" placeholder="Ej: 987 654 321" maxlength="15" inputmode="numeric">
                            </div>
                        </template>
                        @else
                        <div class="qf-full" v-if="isLoggedIn">
                            <p class="hint mb-2" style="margin-top:0;">
                                Usaremos los datos de tu cuenta
                                <strong v-if="user && user.email">(@{{ user.email }})</strong>
                                para registrar la cotización.
                            </p>
                        </div>
                        @endguest
                        <div class="qf-full">
                            <span class="field-label">Comentarios u observaciones</span>
                            <textarea class="input" rows="3" v-model="quotationForm.notes" placeholder="Indicaciones adicionales para tu cotización (opcional)"></textarea>
                        </div>
                    </div>

                    <div class="quotation-conditions">
                        <div class="quotation-conditions-title">Condiciones de la cotización</div>
                        <ul class="quotation-conditions-list">
                            <li>Vigencia: <strong>@{{ quotationValidityLabel }}</strong> (asignada automáticamente por la tienda).</li>
                            <li v-if="quotationTerms" style="white-space: pre-line;">@{{ quotationTerms }}</li>
                            <li v-else>Los precios y disponibilidad están referenciales hasta la confirmación comercial.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        </transition>

        @if($enable_electronic_documents)
            {{-- Modo documentos electrónicos: solo lectura, tipo inferido del número del usuario --}}
            <div class="card card-cart" v-if="isLoggedIn && !isQuotationCheckout && (checkoutIntentChosen || !isHybridQuotationMode)">
                <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#documentyCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                    <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                        <span class="icon-card">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                <path d="M9 17h6" />
                                <path d="M9 13h6" />
                            </svg>
                        </span>
                        <span class="ml-2 font-weight-bold title-card">Datos del comprobante</span>
                        <span class="head-summary">
                            <b v-if="invoiceTypeLabel">@{{ invoiceTypeLabel }}</b>
                            <template v-if="user && user.number">
                                <span class="head-summary-sep" v-if="invoiceTypeLabel">·</span>
                                <span>@{{ user.number }}</span>
                            </template>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                    </div>
                </button>
                <div id="documentyCollapse" class="collapse show">
                    <div class="card-body card-body-h-auto card-cart-body">
                        <ul class="doc-list">
                            <li><span>Cliente</span> <strong>@{{ user.name }}</strong></li>
                            <li><span>Documento</span> <strong>@{{ user.number }}</strong></li>
                            <li><span>Tipo de doc.</span> <strong>@{{ invoiceTypeLabel }}</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <transition name="checkout-panel">
        <div class="card card-cart" v-if="records.length > 0 && allowPurchase && (checkoutIntentChosen || !isHybridQuotationMode) && showCheckoutSections" key="payment-card">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#paymentCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#2b2b2d"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            >
                            <path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" />
                            <path d="M3 10l18 0" />
                            <path d="M7 15l.01 0" />
                            <path d="M11 15l2 0" />
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Método de pago</span>
                    <span class="head-summary">
                        <template v-if="!isLoggedIn">
                            <b v-if="isGuestFormReady && selectedPaymentMethod === 'culqi'">@{{ titleCulqi }}</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'cash'">@{{ cashPaymentTitle }}</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'yape'">Yape</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'transfer'">Transferencia</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'paypal'">PayPal</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'mp'">@{{ titleMp }}</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'izipay'">@{{ titleIzipay }}</b>
                            <span v-else-if="guestCheckoutAccepted" class="head-summary-warn">Completa envío y contacto</span>
                            <span v-else class="head-summary-warn">Continúa como invitado</span>
                        </template>
                        <template v-else>
                            <b v-if="selectedPaymentMethod === 'culqi'">@{{ titleCulqi }}</b>
                            <b v-else-if="selectedPaymentMethod === 'cash'">@{{ cashPaymentTitle }}</b>
                            <b v-else-if="selectedPaymentMethod === 'yape'">Yape</b>
                            <b v-else-if="selectedPaymentMethod === 'transfer'">Transferencia</b>
                            <b v-else-if="selectedPaymentMethod === 'paypal'">PayPal</b>
                            <b v-else-if="selectedPaymentMethod === 'mp'">@{{ titleMp }}</b>
                            <b v-else-if="selectedPaymentMethod === 'izipay'">@{{ titleIzipay }}</b>
                            <span v-else class="head-summary-warn">Elige un método</span>
                        </template>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="paymentCollapse" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body">
                    <div class="login-note" v-if="!isLoggedIn && guestCheckoutAccepted && !isGuestCheckoutComplete">
                        <span class="login-note-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/></svg>
                        </span>
                        <p>Completa tus datos de contacto y la información de envío para habilitar los métodos de pago.</p>
                    </div>
                    <div class="pay-methods" v-if="isLoggedIn || isGuestCheckoutComplete" role="radiogroup">

                        <label v-if="enableCulqi" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'culqi' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="culqi" autocomplete="off">
                            <span class="pay-method-ic pay-method-ic--brand">
                                <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/culqi-checkout.svg') }}?v=1" alt="Culqi">
                            </span>
                            <span class="pay-method-label">@{{ titleCulqi }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'culqi' && (!isLoggedIn || descriptionCulqi)" class="pay-method-panel">
                            <div v-if="!isSecurePage" class="ship-alert ship-alert--warn" role="alert">
                                Culqi solo funciona con HTTPS. Estás en HTTP
                                (<strong>No seguro</strong>).
                                <a :href="httpsCheckoutUrl" style="font-weight:700; text-decoration:underline;">
                                    Abrir carrito en HTTPS
                                </a>
                            </div>
                            <p v-if="descriptionCulqi">@{{ descriptionCulqi }}</p>
                            <button
                                v-if="!isLoggedIn"
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('culqi')"
                            >
                                <span v-if="processingPayment">Procesando…</span>
                                <span v-else>Pagar con @{{ titleCulqi }}</span>
                            </button>
                        </div>

                        <label v-if="enableIzipay" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'izipay' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="izipay" autocomplete="off">
                            <span class="pay-method-ic pay-method-ic--brand">
                                <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/izipay-checkout.svg') }}?v=2" alt="Izipay">
                            </span>
                            <span class="pay-method-label">@{{ titleIzipay }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'izipay' && (!isLoggedIn || descriptionIzipay)" class="pay-method-panel">
                            <p v-if="descriptionIzipay">@{{ descriptionIzipay }}</p>
                            <button
                                v-if="!isLoggedIn"
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('izipay')"
                            >
                                <span v-if="processingPayment">Procesando…</span>
                                <span v-else>Pagar con @{{ titleIzipay }}</span>
                            </button>
                        </div>

                        <label v-if="enableMp" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'mp' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="mp" autocomplete="off">
                            <span class="pay-method-ic pay-method-ic--brand">
                                <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/mercado-pago-checkout.svg') }}?v=4" alt="Mercado Pago">
                            </span>
                            <span class="pay-method-label">@{{ titleMp }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'mp' && (!isLoggedIn || descriptionMp)" class="pay-method-panel">
                            <p v-if="descriptionMp">@{{ descriptionMp }}</p>
                            <button
                                v-if="!isLoggedIn"
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('mp')"
                            >
                                <span v-if="processingPayment">Procesando…</span>
                                <span v-else>Pagar con @{{ titleMp }}</span>
                            </button>
                        </div>

                        <label v-if="enableCash && (!cashPaymentPickupOnly || isPickupMode)" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'cash' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="cash" autocomplete="off">
                            <span class="pay-method-ic pay-method-ic--brand">
                                <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/cash-delivery.svg') }}?v=1" alt="Pago contra entrega">
                            </span>
                            <span class="pay-method-label">@{{ cashPaymentTitle }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'cash' && (!isLoggedIn || cashPaymentDescription)" class="pay-method-panel">
                            <p v-if="cashPaymentDescription">@{{ cashPaymentDescription }}</p>
                            <button
                                v-if="!isLoggedIn"
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('cash')"
                            >
                                <span v-if="processingPayment">Procesando…</span>
                                <span v-else>Confirmar pedido — @{{ cashPaymentTitle }}</span>
                            </button>
                        </div>

                        <label v-if="enableYape" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'yape' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="yape" autocomplete="off">
                            <span class="pay-method-ic pay-method-ic--brand">
                                <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/yape-checkout.svg') }}?v=4" alt="Yape">
                            </span>
                            <span class="pay-method-label">Pagar con Yape</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'yape'" class="pay-method-panel">
                            <p>Escanea el código QR desde tu app de Yape.</p>
                            @if(!empty($payment_configuration->image_url_yape))
                            <div style="text-align: center; margin: 15px 0;">
                                <img src="{{ $payment_configuration->image_url_yape }}" alt="QR Yape" style="max-width: 150px; border-radius: 8px; border: 1px solid #eee;">
                            </div>
                            @endif
                            <div style="font-size: 14px; text-align: center; margin-bottom: 10px;">
                                <strong>Titular:</strong> {{ $payment_configuration->name_yape ?? 'No registrado' }}<br>
                                <strong>Teléfono:</strong> <span>{{ $payment_configuration->telephone_yape ?? 'No registrado' }}</span>
                                <button type="button" @click.prevent="copyToClipboard('{{ $payment_configuration->telephone_yape ?? '' }}')" class="btn btn-sm btn-outline-secondary" style="padding: 2px 8px; font-size: 12px; margin-left: 5px;">
                                    Copiar
                                </button>
                            </div>
                            <button
                                v-if="!isLoggedIn"
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('yape')"
                            >
                                <span v-if="processingPayment">Procesando…</span>
                                <span v-else>Confirmar pedido con Yape</span>
                            </button>
                        </div>

                        <label v-if="enableTransfer" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'transfer' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="transfer" autocomplete="off">
                            <span class="pay-method-ic pay-method-ic--brand">
                                <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/bank-transfer.svg') }}?v=1" alt="Transferencia">
                            </span>
                            <span class="pay-method-label">Transferencia bancaria</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'transfer'" class="pay-method-panel">
                            <p>Realiza el depósito en alguna de nuestras cuentas bancarias y envíanos el voucher por WhatsApp.</p>
                            @if(isset($bank_accounts) && count($bank_accounts) > 0)
                                <ul style="list-style: none; padding-left: 0; font-size: 13px; margin: 10px 0 0;">
                                @foreach($bank_accounts as $account)
                                    <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                                        <strong>Banco:</strong> {{ $account->bank->description }} ({{ $account->currency_type->symbol }})<br>
                                        <strong>Cuenta:</strong> {{ $account->number }}<br>
                                        @if($account->cci)
                                        <strong>CCI:</strong> {{ $account->cci }}
                                        @endif
                                    </li>
                                @endforeach
                                </ul>
                            @else
                                <p style="font-size: 13px; font-weight: bold; color: #d9534f; margin-top: 10px;">No hay cuentas bancarias configuradas.</p>
                            @endif
                            <button
                                v-if="!isLoggedIn"
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('transfer')"
                            >
                                <span v-if="processingPayment">Procesando…</span>
                                <span v-else>Confirmar pedido con transferencia</span>
                            </button>
                        </div>

                        @if($information->script_paypal)
                        <label class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'paypal' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="paypal" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11l1 -7h6a3 3 0 0 1 0 6h-4"/><path d="M5 20l1.5 -9h5a3 3 0 0 1 0 6h-4"/></svg>
                            </span>
                            <span class="pay-method-label">PayPal</span>
                        </label>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        </transition>
    </div><!-- End .col-lg-8 -->

    <div class="col-md-4 checkout-summary-col">
      <div class="summary-sticky">
        <div class="cart-summary" :class="{ 'is-quote-summary': isQuotationCheckout }">
            <div class="sum-head">
                <h3>@{{ isQuotationCheckout ? 'Resumen de cotización' : 'Resumen' }}</h3>
            </div>

            {{-- Solo Cotizar y vender: aviso de modo debajo del título --}}
            <div
                class="checkout-intent-summary checkout-intent-summary--in-summary"
                v-if="isHybridQuotationMode && records.length > 0 && checkoutIntentChosen && !checkoutIntentModalVisible"
            >
                <div class="checkout-intent-summary-text">
                    <template v-if="checkoutIntent === 'quote'">
                        Estás en modo <strong>Solicitar cotización</strong>.
                    </template>
                    <template v-else>
                        Estás en modo <strong>Comprar</strong>.
                    </template>
                </div>
                <button type="button" class="checkout-intent-change" @click="reopenCheckoutIntentModal">
                    Cambiar
                </button>
            </div>

            <div class="quotation-mode-banner" v-if="quoteOnlyMode && isQuotationCheckout">
                <strong>Modo: Solo cotizar</strong>
                <span>En este modo no es posible pagar en la tienda.</span>
            </div>
            <div class="sum-body">
                <table class="table table-totals" v-if="showCartPrices">
                    <tbody>
                        <tr v-if="summary.total_exonerated > 0">
                            <td>Op. exoneradas</td>
                            <td>S/ @{{ summary.total_exonerated }}</td>
                        </tr>
                        <tr v-if="summary.total_taxed > 0">
                            <td>Op. gravada</td>
                            <td>S/ @{{ summary.total_taxed }}</td>
                        </tr>
                        <tr v-if="summary.total_igv > 0">
                            <td>IGV (18%)</td>
                            <td>S/ @{{ summary.total_igv }}</td>
                        </tr>
                        <tr v-if="!isQuotationCheckout && appliedCoupon && appliedCoupon.code">
                            <td>
                                Cupón <span class="badge badge-dark">@{{ appliedCoupon.code }}</span>
                                <button class="coupon-remove" @click="removeCoupon">Eliminar</button>
                            </td>
                            <td>
                                &minus; S/ @{{ appliedCoupon.discount }}
                            </td>
                        </tr>
                        <tr v-if="!isQuotationCheckout && deliveryZone && parseFloat(deliveryZone.price) > 0">
                            <td>Envío <small class="text-muted">(@{{ deliveryZone.name }})</small></td>
                            <td>S/ @{{ summary.delivery }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td>S/ @{{summary.total}}</td>
                        </tr>
                    </tfoot>
                </table>

                <p class="quotation-summary-meta" v-if="isQuotationCheckout && !showCartPrices">
                    @{{ records.length }} producto(s) seleccionados para cotizar.
                </p>

                <!-- Coupon input and applied coupon display -->
                <div class="coupon-block" v-if="!isQuotationCheckout">
                    <div class="coupon">
                        <input v-model="couponField" type="text" class="input" placeholder="Código de cupón">
                        <button class="coupon-btn" @click="applyCoupon" :disabled="couponLoading">Aplicar</button>
                    </div>
                    <small class="coupon-msg text-danger" v-if="couponMessage">@{{ couponMessage }}</small>
                </div>

                <div class="quotation-summary-note" v-if="isQuotationCheckout">
                    <small>Vigencia automática: <strong>@{{ quotationValidityLabel }}</strong></small>
                </div>

                <label class="terms" :class="{ 'terms--checked': acceptedTerms }" id="termsLabel">
                  <input type="checkbox" id="termsCheck" v-model="acceptedTerms">
                  <span class="box"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                  <span class="terms-txt">He leído y acepto los <a href="#" data-modal-open="termsModal" @click.prevent>Términos y Condiciones</a>.</span>
                </label>
                <div class="checkout-methods">
                    <template v-if="allowPurchase">
                    <p v-if="!isLoggedIn && guestCheckoutAccepted && isGuestCheckoutComplete" class="checkout-hint">
                        Elige un método de pago y confirma con el botón Pagar.
                    </p>
                    <button
                        v-if="!isLoggedIn && !guestCheckoutAccepted"
                        type="button"
                        class="pay-btn"
                        :class="{ disabled: records.length === 0 }"
                        :disabled="records.length === 0"
                        @click="scrollToContactSection"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        Elegir forma de acceso
                    </button>
                    <button
                        v-else-if="!isLoggedIn && guestCheckoutAccepted && !isGuestCheckoutComplete"
                        type="button"
                        class="pay-btn"
                        :class="{ disabled: !acceptedTerms || records.length === 0 }"
                        :disabled="!acceptedTerms || records.length === 0"
                        @click="handleCheckoutClick"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        Completar datos
                    </button>
                    <button
                        v-else-if="(isLoggedIn || (guestCheckoutAccepted && isGuestCheckoutComplete)) && selectedPaymentMethod !== 'paypal'"
                        class="pay-btn"
                        :class="{ disabled: !acceptedTerms || processingPayment || quotationSubmitting }"
                        :disabled="!selectedPaymentMethod || !acceptedTerms || processingPayment || quotationSubmitting"
                        @click="executePayment"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        <span v-if="processingPayment">Procesando…</span>
                        <span v-else>Pagar</span>
                    </button>
                    </template>
                    <button
                        v-if="isQuotationCheckout"
                        type="button"
                        class="pay-btn pay-btn--quote"
                        :class="{ disabled: !acceptedTerms || quotationSubmitting }"
                        :disabled="!acceptedTerms || quotationSubmitting || records.length < 1"
                        @click="submitQuotationRequest"
                    >
                        <span v-if="quotationSubmitting" class="payment-success-btn-spinner" aria-hidden="true"></span>
                        <span v-if="quotationSubmitting">Enviando…</span>
                        <span v-else>Enviar cotización</span>
                    </button>
                </div><!-- End .checkout-methods -->

                <div class="trust" v-if="!isQuotationCheckout">
                    <trust-badges
                        :badges="trustBadges"
                        :enabled="trustBadgesEnabled"
                        :compact="true"
                    ></trust-badges>
                </div>
                <frequently-bought-together
                    v-if="!isQuotationCheckout && cartFbtItemIds.length"
                    :item-ids="cartFbtItemIds"
                    :limit="6"
                    title="También suelen llevarse juntos"
                    subtitle="Según pedidos reales de la tienda"
                    class="mt-3"
                ></frequently-bought-together>
                <div class="cards-row px-5" v-if="!isQuotationCheckout">
                    <img src="{{ asset('porto-ecommerce/assets/images/payments-bordered.svg') }}" alt="payment methods" class="footer-payments">
                </div>
            </div>
            <div class="secure-foot">Transacción cifrada · IGV incluido según ley peruana</div>
        </div><!-- End .cart-summary -->
      </div><!-- End .summary-sticky -->
    </div><!-- End .col-lg-4 -->

    <!-- Modal de intención: Comprar vs Solicitar cotización (modo híbrido) -->
    <div
        id="checkout-intent-overlay"
        class="checkout-intent-overlay"
        :class="{ 'is-open': checkoutIntentModalVisible }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="checkout-intent-title"
        :aria-hidden="checkoutIntentModalVisible ? 'false' : 'true'"
    >
        <div class="checkout-intent-dialog">
            <h3 id="checkout-intent-title">¿Qué desea hacer con su pedido?</h3>
            <p class="checkout-intent-sub">Elija cómo continuar. Podrá cambiar esta opción más adelante.</p>
            <div class="checkout-intent-dialog-actions">
                <button
                    type="button"
                    class="checkout-intent-choice"
                    @click="confirmCheckoutIntent('purchase')"
                >
                    <span class="cic-title">Comprar</span>
                    <span class="cic-desc">Checkout con envío y métodos de pago</span>
                </button>
                <button
                    type="button"
                    class="checkout-intent-choice checkout-intent-choice--quote"
                    @click="confirmCheckoutIntent('quote')"
                >
                    <span class="cic-title">Solicitar cotización</span>
                    <span class="cic-desc">Solo datos de contacto, sin pagar ahora</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Overlay de carga: Yape / efectivo / transferencia -->
    <div
        id="payment-loading-overlay"
        class="payment-process-overlay"
        :class="{ 'is-open': processingPayment }"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="payment-loading-title"
        aria-busy="true"
        :aria-hidden="processingPayment ? 'false' : 'true'"
    >
        <div class="payment-process-dialog">
            <div class="payment-process-spinner" aria-hidden="true"></div>
            <h3 id="payment-loading-title">@{{ paymentLoadingTitle }}</h3>
            <p>@{{ paymentLoadingText }}</p>
        </div>
    </div>

    <!-- Modal de éxito unificado (todos los métodos) -->
    <div
        id="payment-success-overlay"
        class="payment-success-overlay"
        :class="{ 'is-open': paymentSuccessVisible }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-success-title"
        :aria-hidden="paymentSuccessVisible ? 'false' : 'true'"
    >
        <div class="payment-success-dialog" v-if="successOrder">
            <div class="payment-success-badge" :class="{ 'is-confirm': isYapePaymentSuccess }" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>

            <template v-if="isYapePaymentSuccess">
                <h3 id="payment-success-title">¡Confirma tu Pago!</h3>
                <p class="payment-success-sub">Usa los datos a continuación para realizar tu pago.</p>
            </template>
            <template v-else>
                <h3 id="payment-success-title">¡Pago realizado!</h3>
                <p class="payment-success-sub">Tu pedido fue registrado con éxito.</p>
            </template>

            <div class="payment-success-summary">
                <div class="psr-row">
                    <span class="lbl">Pedido</span>
                    <span class="val">@{{ successOrderNumber }}</span>
                </div>
                <div class="psr-row" v-if="!isYapePaymentSuccess">
                    <span class="lbl">Forma de pago</span>
                    <span class="val">@{{ successPaymentLabel }}</span>
                </div>
                <div class="psr-row" v-if="!isYapePaymentSuccess">
                    <span class="lbl">Productos</span>
                    <span class="val">@{{ successItemsCount }}</span>
                </div>
                <div class="psr-row psr-row--total">
                    <span class="lbl">@{{ isYapePaymentSuccess ? 'Total a Pagar' : 'Total' }}</span>
                    <span class="val">@{{ successOrderTotal }}</span>
                </div>
            </div>

            <div class="payment-success-note" :class="{ 'is-info': isYapePaymentSuccess }" v-if="isYapePaymentSuccess">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span><strong>Importante:</strong> Por favor incluye el número de pedido <strong>@{{ successOrderNumber }}</strong> en el mensaje o nota de tu pago.</span>
            </div>
            <div class="payment-success-note" v-else>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>En breve te enviaremos un correo electrónico con los detalles de tu compra.</span>
            </div>

            <div class="payment-success-note is-warn" v-if="isYapePaymentSuccess && !canSendYapeVoucherWhatsapp">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>La tienda aún no configuró un WhatsApp. Contacta a la empresa para enviar tu comprobante de Yape.</span>
            </div>

            <div class="payment-success-actions">
                <button
                    type="button"
                    class="pay-btn pay-btn--whatsapp"
                    v-if="canSendYapeVoucherWhatsapp"
                    @click="openYapeVoucherWhatsapp"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.5 0-9.96 4.43-9.96 9.9 0 1.75.46 3.45 1.34 4.95L2 22l5.3-1.38c1.44.78 3.06 1.19 4.74 1.19h.01c5.5 0 9.96-4.43 9.96-9.9C22.01 6.43 17.54 2 12.04 2zm5.79 14.16c-.24.68-1.41 1.25-1.95 1.33-.5.07-1.13.1-1.83-.11-.42-.13-.97-.32-1.67-.63-2.94-1.27-4.86-4.22-5.01-4.42-.15-.2-1.24-1.65-1.24-3.14 0-1.49.78-2.22 1.06-2.52.28-.3.6-.37.8-.37.2 0 .4 0 .58.01.18.01.43-.07.68.52.24.58.83 2.03.9 2.18.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.38-.44.51-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.02 1.12 1 2.07 1.31 2.37 1.46.3.15.47.12.65-.07.18-.2.75-.87.95-1.17.2-.3.4-.25.68-.15.28.1 1.78.84 2.08.99.3.15.5.22.57.35.08.12.08.71-.16 1.39z"/></svg>
                    <span>Enviar Comprobante al WhatsApp de la empresa</span>
                </button>

                <button
                    type="button"
                    class="pay-btn"
                    :class="{ 'pay-btn--ghost': isYapePaymentSuccess, 'is-loading': paymentSuccessRedirecting, disabled: paymentSuccessRedirecting }"
                    :disabled="paymentSuccessRedirecting"
                    @click="confirmPurchaseSuccess"
                >
                    <span v-if="paymentSuccessRedirecting" class="payment-success-btn-spinner" aria-hidden="true"></span>
                    <span v-if="paymentSuccessRedirecting">Redirigiendo…</span>
                    <span v-else-if="isYapePaymentSuccess">Ver Estado del Pedido</span>
                    <span v-else>Continuar</span>
                </button>
            </div>

            <p class="payment-success-footer-hint" v-if="isYapePaymentSuccess">
                Al enviar el comprobante, procesaremos tu pedido lo antes posible.
            </p>
        </div>
    </div>

    <!-- Modal éxito cotización -->
    <div
        id="quotation-success-overlay"
        class="quotation-success-overlay"
        :class="{ 'is-open': quotationSuccessVisible }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="quotation-success-title"
        :aria-hidden="quotationSuccessVisible ? 'false' : 'true'"
    >
        <div class="quotation-success-dialog" v-if="quotationResult">
            <div class="payment-success-badge" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 id="quotation-success-title">¡Cotización enviada!</h3>
            <p class="payment-success-sub">@{{ displayedQuotationSuccessMessage }}</p>
            <div class="quotation-success-code">@{{ quotationResult.code || quotationResult.number_full }}</div>
            <div class="quotation-success-actions">
                <button type="button" class="pay-btn pay-btn--ghost" @click="openQuotationPdf" v-if="quotationResult.print_url && showCartPrices">
                    Ver PDF
                </button>
                <button
                    type="button"
                    class="pay-btn"
                    :class="{ 'is-loading': quotationSuccessRedirecting }"
                    :disabled="quotationSuccessRedirecting"
                    @click="confirmQuotationSuccess"
                >
                    <span v-if="quotationSuccessRedirecting" class="payment-success-btn-spinner" aria-hidden="true"></span>
                    <span v-if="quotationSuccessRedirecting">Redirigiendo…</span>
                    <span v-else-if="quotationResult.is_guest">Continuar</span>
                    <span v-else>Ver mis cotizaciones</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Mis Direcciones -->
    <div class="modal fade" id="addressListModal" tabindex="-1" role="dialog" aria-labelledby="addressListModalLabel" aria-hidden="true" @click="closeAddressListMenu">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header h-auto">
                    <h3 class="modal-title" id="addressListModalLabel">Mis direcciones</h3>
                    <button type="button" class="close" @click="closeAddressListModal()" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <button type="button" class="addr-list-add-link" @click="openAddressMapModal('add', null, true)">
                        <span>+</span> Agregar nueva dirección
                    </button>

                    <div class="addr-list-scroll" @scroll="closeAddressListMenu">
                        <p v-if="userAddresses.length === 0" class="addr-list-empty">
                            No tienes direcciones guardadas.
                            <span class="addr-list-empty-hint">Haz clic en «+ Agregar nueva dirección» para registrar una.</span>
                        </p>

                        <ul v-else class="addr-list-cards">
                            <li
                                v-for="(item, index) in userAddresses"
                                :key="item.id || index"
                                class="addr-list-card"
                                :class="{ 'addr-list-card--active': selectedAddressId === item.id }"
                                @click="selectAddressInList(item.id)"
                            >
                                <input
                                    type="radio"
                                    class="addr-list-card__radio"
                                    name="savedAddress"
                                    :value="item.id"
                                    :checked="selectedAddressId === item.id"
                                    tabindex="-1"
                                    aria-hidden="true"
                                >
                                <div class="addr-list-card__body">
                                    <strong v-if="getAddressStreet(item)" class="addr-list-card__primary">@{{ getAddressStreet(item) }}</strong>
                                    <span v-if="getAddressSecondaryLine(item)" class="addr-list-card__secondary">@{{ getAddressSecondaryLine(item) }}</span>
                                </div>
                                <div class="addr-list-card__menu-wrap" @click.stop>
                                    <button
                                        type="button"
                                        class="addr-list-card__menu-btn"
                                        aria-label="Opciones"
                                        @click.stop="toggleAddressListMenu(item.id, $event)"
                                    >&#8942;</button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="modal-footer-wrap">
                    <button type="button" class="pay-btn pay-btn--ghost" @click="closeAddressListModal()">Cancelar</button>
                    <button
                        v-if="userAddresses.length > 0"
                        type="button"
                        class="pay-btn"
                        :disabled="!selectedAddressId"
                        @click="confirmChooseAddress()"
                    >Elegir dirección</button>
                </div>
            </div>
        </div>

        <div
            v-if="addressListMenuOpen"
            ref="addressListFloatingMenu"
            class="addr-list-card__menu addr-list-card__menu--floating"
            :class="{ 'is-positioned': addressListMenuPositioned }"
            :style="addressListMenuStyle"
            role="menu"
            @click.stop
        >
            <button type="button" role="menuitem" @click="editSavedAddress(getAddressListMenuItem())">Editar</button>
            <button type="button" role="menuitem" class="danger" @click="deleteSavedAddress(getAddressListMenuItem())">Eliminar</button>
        </div>
    </div>

    <!-- Modal de Dirección (mapa) -->
    <div class="modal fade" id="addressModal" tabindex="-1" role="dialog" aria-labelledby="addressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header h-auto">
                    <h3 class="modal-title">@{{ addressModalMode === 'edit' ? 'Editar dirección' : 'Agregar dirección' }}</h3>
                    <button type="button" class="close" @click="closeAddressModal()" aria-label="Close">
                        <span aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                        </span>
                    </button>
                </div>

                <div class="modal-body">
                    @if(!empty($googleMapsApiKey))
                        <div id="map"></div>
                    @endif

                    <div class="addr-modal-form">
                        @if(empty($googleMapsApiKey))
                            <div class="form-row mb-2">
                                <div class="col-12 col-md-4 mb-2 mb-md-0">
                                    <label class="field-label" for="department">Departamento</label>
                                    <select v-model="selectedDepartment" @change="updateProvinces" name="department" id="department" class="input">
                                        <option value="">Seleccione departamento</option>
                                        <option v-for="department in departments" :key="department.value" :value="department.value">
                                            @{{ department.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4 mb-2 mb-md-0">
                                    <label class="field-label" for="province">Provincia</label>
                                    <select v-model="selectedProvince" @change="updateDistricts" name="province" id="province" class="input">
                                        <option value="">Seleccione provincia</option>
                                        <option v-for="province in provinces" :key="province.value" :value="province.value">
                                            @{{ province.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="field-label" for="district">Distrito</label>
                                    <select v-model="selectedDistrict" @change="checkDeliveryZone" name="district" id="district" class="input">
                                        <option value="">Seleccione distrito</option>
                                        <option v-for="district in districts" :key="district.value" :value="district.value">
                                            @{{ district.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="mb-2">
                            <label class="field-label">Dirección</label>
                            <div class="position-relative">
                                <input
                                    v-model="addressModal.address"
                                    @input="onAddressInputChange"
                                    @keydown.down.prevent="moveSuggestion(1)"
                                    @keydown.up.prevent="moveSuggestion(-1)"
                                    @keydown.enter.prevent="selectHighlighted"
                                    @keydown.esc="clearSuggestions"
                                    id="addressAutocompleteInput"
                                    type="text"
                                    class="input"
                                    placeholder="Ingrese su dirección completa"
                                    autocomplete="off">

                                <ul v-if="addressSuggestions.length > 0" class="addr-suggestions">
                                    <li v-for="(suggestion, i) in addressSuggestions"
                                        :key="i"
                                        @mousedown.prevent="selectSuggestionFromList(suggestion)"
                                        class="addr-suggestion"
                                        :class="{ 'addr-suggestion--active': highlightedIndex === i }">
                                        <span class="font-weight-bold">@{{ suggestion.mainText }}</span>
                                        <span class="d-block text-muted small">@{{ suggestion.secondaryText }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p v-if="isGeocodingAddress" class="addr-map-geocoding mb-0">Actualizando dirección…</p>
                        </div>

                        <div class="mb-0">
                            <label class="field-label">Referencias</label>
                            <textarea v-model="addressModal.reference" class="input" rows="2" placeholder="Ej: Al costado del parque, frente a la iglesia"></textarea>
                        </div>
                    </div>
                </div>

                <div v-if="deliveryMessage" class="ship-alert ship-alert--warn mx-3 mb-2">
                    <strong>&#9888; Sin cobertura:</strong> @{{ deliveryMessage }}
                </div>

                <div class="modal-footer-wrap">
                    <button type="button" class="pay-btn" @click="confirmAddress()">
                        @{{ addressModalMode === 'edit' ? 'Guardar dirección' : 'Continuar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modal reutilizable: Términos y Condiciones ===== --}}
    <div class="app-modal" id="termsModal" role="dialog" aria-modal="true" aria-labelledby="termsModalTitle" aria-hidden="true">
        <div class="app-modal__dialog">
            <div class="app-modal__header">
                <span class="app-modal__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 9h1"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
                </span>
                <h3 class="app-modal__title" id="termsModalTitle">Términos y Condiciones</h3>
                <button type="button" class="app-modal__close" data-modal-close aria-label="Cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="app-modal__body">
                @if(!empty($configuration->terms_conditions))
                    <div class="app-modal__prose">{!! $configuration->terms_conditions !!}</div>
                @else
                    <p class="app-modal__prose text-muted">No se han definido términos y condiciones.</p>
                @endif
            </div>
            <div class="app-modal__footer">
                <button type="button" class="pay-btn second-btn" data-modal-close>Cerrar</button>
                <button type="button" class="pay-btn" data-modal-accept="termsCheck" data-modal-close>Acepto los términos</button>
            </div>
        </div>
    </div>

    <!-- ===== Overlay de carga mientras se procesa el pago ===== -->
    <div class="purchase-overlay purchase-overlay--show" v-if="processingPayment">
        <div class="purchase-loading" role="status" aria-live="polite">
            <span class="purchase-spinner" aria-hidden="true"></span>
            <h3>@{{ paymentLoadingTitle }}</h3>
            <p>@{{ paymentLoadingText }}</p>
        </div>
    </div>

    <!-- ===== Confirmación de compra (post-pago) ===== -->
    {{-- Modal unificado: payment-success-overlay. Este bloque legacy se desactiva para no duplicar. --}}
    <div class="purchase-overlay" v-if="false"></div>

</div><!-- End #app -->

@if(auth('ecommerce')->check() && $information->script_paypal)
<div id="paypal-widget-container" style="display:none;">
    {!!html_entity_decode($information->script_paypal)!!}
</div>
@endif

<!-- DOM Containers for MP and Izipay (fuera de #app para evitar conflicto con Vue) -->
<div id="mp-brick-stash" aria-hidden="true">
    <div id="mp-brick-container" class="mp-swal-brick"></div>
</div>
<div id="izipay-payment-modal" class="gateway-payment-overlay" aria-hidden="true">
    <section class="gateway-payment-dialog" role="dialog" aria-modal="true" aria-label="Pago con Izipay">
        <header class="gateway-payment-header">
            <img src="{{ asset('porto-ecommerce/assets/images/payment-gateways/izipay-official.svg') }}?v=5" alt="Izipay">
            <button type="button" id="izipay-payment-close" class="gateway-payment-close" aria-label="Cerrar">&times;</button>
        </header>
        <div class="gateway-payment-body">
            <div id="izipay-payment-host" class="kr-izipay-container-inner" style="display:none"></div>
        </div>
    </section>
</div>

<input type="hidden" id="total_amount" data-total="0.0">

@endsection

@push('scripts')
<!-- Configuration globals para cart app -->
@php
    $ecommerceAuthUser = Auth::guard('ecommerce')->user();
    // Payload mínimo: evitar serializar el modelo completo (puede romper el JS del config).
    $ecommerceUserPayload = $ecommerceAuthUser ? [
        'id' => $ecommerceAuthUser->id,
        'name' => $ecommerceAuthUser->name,
        'email' => $ecommerceAuthUser->email,
        'telephone' => $ecommerceAuthUser->telephone,
        'address' => $ecommerceAuthUser->address,
        'identity_document_type_id' => $ecommerceAuthUser->identity_document_type_id,
        'number' => $ecommerceAuthUser->number,
    ] : null;
@endphp
<script>
    window.__ecommerce_config = {
        phone_whatsapp: {!! json_encode($phoneWhatsapp ?? '') !!},
        enable_whatsapp: {!! json_encode($showWhatsapp ?? false) !!},
        global_discount_type: {!! json_encode($global_discount_type ?? []) !!},
        user: {!! json_encode($ecommerceUserPayload) !!},
        userAddress: {!! json_encode($userAddress ?? null) !!},
        userAddresses: {!! json_encode($userAddresses ?? []) !!},
        enable_electronic_documents: {!! json_encode($enable_electronic_documents ?? false) !!},
        enable_store_pickup: {!! json_encode($enable_store_pickup ?? false) !!},
        quotation_enabled: {!! json_encode($quotation_enabled ?? false) !!},
        quotation_mode: {!! json_encode($quotation_mode ?? 'quote_and_sell') !!},
        quotation_show_prices: {!! json_encode($quotation_show_prices ?? true) !!},
        quotation_success_message: {!! json_encode($quotation_success_message ?? 'Registramos tu solicitud. Nuestro equipo la revisará a la brevedad.') !!},
        quotation_validity_days: {!! json_encode($quotation_validity_days ?? 7) !!},
        quotation_terms: {!! json_encode($quotation_terms ?? '') !!},
        pickup_branches: {!! json_encode($pickup_branches ?? []) !!},
        enable_yape: {!! json_encode($enable_yape ?? false) !!},
        enable_transfer: {!! json_encode($enable_transfer ?? false) !!},
        enable_cash: {!! json_encode(isset($configuration->preferences['enable_cash']) && $configuration->preferences['enable_cash'] == 1) !!},
        cash_payment_title: {!! json_encode($configuration->preferences['cash_title'] ?? 'Pago contra entrega') !!},
        cash_payment_description: {!! json_encode($configuration->preferences['cash_description'] ?? '') !!},
        cash_payment_pickup_only: {!! json_encode(isset($configuration->preferences['cash_pickup_only']) && $configuration->preferences['cash_pickup_only'] == 1) !!},
        enable_izipay: {!! json_encode($payment_configuration->enabled_izipay ?? false) !!},
        public_key_izipay: {!! json_encode($payment_configuration->publickey_izipay ?? '') !!},
        title_izipay: {!! json_encode($preferences['title_izipay'] ?? 'Pago con Izipay') !!},
        description_izipay: {!! json_encode($preferences['description_izipay'] ?? '') !!},
        enable_mp: {!! json_encode($payment_configuration->enabled_mp ?? false) !!},
        public_key_mp: {!! json_encode($payment_configuration->public_key_mp ?? '') !!},
        title_mp: {!! json_encode($preferences['title_mp'] ?? 'Mercado Pago') !!},
        description_mp: {!! json_encode($preferences['description_mp'] ?? '') !!},
        enable_culqi: {!! json_encode($payment_configuration->enabled_culqi ?? false) !!},
        title_culqi: {!! json_encode($preferences['title_culqi'] ?? 'Pago con Tarjeta (Culqi)') !!},
        description_culqi: {!! json_encode($preferences['description_culqi'] ?? '') !!},
    };

    window.__routes = {
        payment_cash: '{{ route("tenant_ecommerce_payment_cash") }}',
        user_data: '{{ route("tenant_ecommerce_user_data") }}',
        shipping_address: '{{ route("tenant_ecommerce_shipping_address") }}',
        shipping_addresses: '{{ route("tenant_ecommerce_shipping_addresses") }}',
        shipping_address_delete: '{{ route("tenant_ecommerce_shipping_address_delete") }}',
        locations: '{{ route("get_location_cascade") }}',
        home: '{{ route("tenant.ecommerce.index") }}',
        culqi: '{{ route("tenant_ecommerce_culqui") }}',
        izipay_payment: '{{ route("tenant_ecommerce_izipay") }}',
        izipay_transaction: '{{ route("tenant_ecommerce_izipay_transaction") }}',
        mercadopago_payment: '{{ route("tenant_ecommerce_mp") }}',
        thank_you: '{{ route("tenant_ecommerce_thank_you", ["external_id" => "EXTERNAL_ID"]) }}',
        order_tracking: '{{ route("tenant_ecommerce_order_tracking") }}',
        quotation_store: '{{ route("tenant_ecommerce_quotation_store") }}',
        quotation_list: '{{ route("tenant_ecommerce_quotation_list") }}',
        login: '{{ route("tenant_ecommerce_login") }}',
        search_document: '{{ url("ecommerce/search-document") }}',
    };
</script>

<script>
    (function () {
        function openModal(modal) {
            if (!modal) return;
            modal.classList.add('app-modal--open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modal) {
            if (!modal) return;
            modal.classList.remove('app-modal--open');
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.app-modal--open')) {
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('click', function (e) {
            // Abrir
            var opener = e.target.closest('[data-modal-open]');
            if (opener) {
                e.preventDefault();
                openModal(document.getElementById(opener.getAttribute('data-modal-open')));
                return;
            }

            // Aceptar: marca un checkbox antes de cerrar (dispara change para Vue/listeners)
            var accepter = e.target.closest('[data-modal-accept]');
            if (accepter) {
                var check = document.getElementById(accepter.getAttribute('data-modal-accept'));
                if (check && !check.checked) {
                    check.checked = true;
                    check.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            // Cerrar: botón con [data-modal-close] o clic en el overlay
            if (e.target.closest('[data-modal-close]')) {
                closeModal(e.target.closest('.app-modal'));
                return;
            }
            if (e.target.classList.contains('app-modal')) {
                closeModal(e.target);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(document.querySelector('.app-modal--open'));
            }
        });
    })();
</script>

@vite('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js')
<script>
    window.__socialProofBoot = {
        trustBadgesEnabled: {{ ($trustBadgesEnabled ?? true) ? 'true' : 'false' }},
        trustBadges: @json($trustBadges ?? [])
    };
</script>

<script>
(function () {
    /**
     * Credenciales desde PaymentConfiguration (string plano vía json_encode).
     * Culqi Checkout v4 cifra el payload con RSA-OAEP (window.crypto.subtle).
     * En http://*.test no hay Secure Context → subtle es undefined → ENCRYPT ERROR
     * si se envían xculqirsaid/rsapublickey. Solo activamos RSA con subtle + PEM válidos.
     */
    function normalizeCulqiPublicKey(raw) {
        return String(raw || '')
            .trim()
            .replace(/^["']+|["']+$/g, '');
    }

    function normalizeCulqiRsaPublicKey(raw) {
        let key = String(raw || '').trim();
        if (!key) {
            return '';
        }

        key = key
            .replace(/\\r\\n/g, '\n')
            .replace(/\\n/g, '\n')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n');

        if (!/BEGIN PUBLIC KEY/i.test(key)) {
            return '';
        }

        if (!key.includes('\n')) {
            key = key
                .replace(/-----BEGIN PUBLIC KEY-----\s*/i, '-----BEGIN PUBLIC KEY-----\n')
                .replace(/\s*-----END PUBLIC KEY-----/i, '\n-----END PUBLIC KEY-----');
        }

        const match = key.match(/-----BEGIN PUBLIC KEY-----\n([\s\S]+?)\n-----END PUBLIC KEY-----/i);
        if (!match) {
            return key;
        }

        const body = match[1].replace(/\s+/g, '');
        if (!body) {
            return '';
        }

        const wrapped = body.match(/.{1,64}/g).join('\n');
        return '-----BEGIN PUBLIC KEY-----\n' + wrapped + '\n-----END PUBLIC KEY-----';
    }

    function canUseCulqiRsaEncryption() {
        return !!(window.isSecureContext && window.crypto && window.crypto.subtle);
    }

    const culqiPublicKey = normalizeCulqiPublicKey({!! json_encode($payment_configuration->publickey_culqi ?? '') !!});
    const culqiRsaId = String({!! json_encode($payment_configuration->idrsa_culqi ?? '') !!} || '').trim();
    const culqiRsaPublicKey = normalizeCulqiRsaPublicKey({!! json_encode($payment_configuration->rsa_culqi ?? '') !!});
    let culqiReady = false;
    let culqiReadyPromise = null;
    let culqiCloseMountTimer = null;
    let culqiClosePositionTimer = null;
    let culqiCloseResizeHandler = null;
    let culqiEscapeHandler = null;

    function getCulqiErrorMessage(error) {
        if (!error) return '';
        if (error.type === 'error_encrypt') {
            return 'No se pudo encriptar la tarjeta. En entorno local use HTTPS (p. ej. https://tienda.pro9.test) o verifique RSA Key / ID RSA en Configuración de pagos.';
        }
        return error.user_message || error.merchant_message || '';
    }

    window.mostrarMensaje = function (title, text, type) {
        if (typeof app_cart !== 'undefined' && typeof app_cart.showSwalMessage === 'function') {
            app_cart.showSwalMessage(title, text, type || 'info');
            return;
        }
        if (typeof swal !== 'undefined') {
            swal(title, text, type || 'info');
        }
    };

    function waitForCulqi(maxAttempts, intervalMs) {
        maxAttempts = maxAttempts || 50;
        intervalMs = intervalMs || 100;

        return new Promise(function (resolve, reject) {
            let attempts = 0;

            function check() {
                if (typeof window.Culqi !== 'undefined') {
                    resolve(window.Culqi);
                    return;
                }

                attempts += 1;
                if (attempts >= maxAttempts) {
                    reject(new Error('Culqi SDK no cargó'));
                    return;
                }

                setTimeout(check, intervalMs);
            }

            check();
        });
    }

    function initCulqi(Culqi) {
        if (!culqiPublicKey) {
            jQuery('.culqi').hide();
            return;
        }

        // Asignación explícita como string plano (pk_test_... / pk_live_...).
        Culqi.publicKey = culqiPublicKey;

        const ecommercePrimaryCssColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--primary-color').trim() || '#ff7a00';
        const ecommerceColorProbe = document.createElement('span');
        ecommerceColorProbe.style.color = ecommercePrimaryCssColor;
        document.body.appendChild(ecommerceColorProbe);
        const ecommercePrimaryRgb = getComputedStyle(ecommerceColorProbe).color;
        ecommerceColorProbe.remove();
        const ecommercePrimaryColor = '#' + (ecommercePrimaryRgb.match(/\d+/g) || [255, 122, 0])
            .slice(0, 3)
            .map(function (value) { return Number(value).toString(16).padStart(2, '0'); })
            .join('');

        Culqi.options({
            lang: 'es',
            installments: true,
            paymentMethods: {
                tarjeta: true,
                yape: true,
                bancaMovil: true,
                agente: true,
            },
            style: {
                bannerColor: '#ffffff',
                buttonBackground: ecommercePrimaryColor,
                menuColor: ecommercePrimaryColor,
                linksColor: ecommercePrimaryColor,
                buttonText: 'Pagar',
                buttonTextColor: '#ffffff',
                priceColor: ecommercePrimaryColor
            }
        });
    }

    function ensureCulqiReady() {
        if (culqiReady && typeof window.Culqi !== 'undefined') {
            return Promise.resolve(window.Culqi);
        }

        if (!culqiReadyPromise) {
            culqiReadyPromise = waitForCulqi().then(function (Culqi) {
                initCulqi(Culqi);
                culqiReady = true;
                return Culqi;
            });
        }

        return culqiReadyPromise;
    }

    function teardownCulqiCloseButton() {
        const root = findCulqiModalRoot();
        if (root) {
            const anchor = root.querySelector('#culqi-modal-close-anchor');
            if (anchor) {
                anchor.remove();
            }
        }

        const iframe = findCulqiIframe();
        if (iframe) {
            delete iframe.dataset.culqiCloseBound;
            delete iframe.dataset.culqiLoadDetectedAt;
        }
    }

    function createCulqiCloseButton() {
        const root = findCulqiModalRoot();
        if (!root) {
            return null;
        }

        teardownCulqiCloseButton();

        const anchor = document.createElement('div');
        anchor.id = 'culqi-modal-close-anchor';
        anchor.className = 'culqi-modal-close-anchor';
        anchor.setAttribute('aria-hidden', 'true');

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'culqi-modal-close-btn';
        btn.className = 'culqi-modal-close-btn';
        btn.setAttribute('aria-label', 'Cerrar formulario de pago');
        btn.title = 'Cerrar';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>';
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeCulqiModal();
        });

        anchor.appendChild(btn);
        root.appendChild(anchor);

        return { anchor: anchor, btn: btn };
    }

    function findCulqiIframe() {
        const root = findCulqiModalRoot();
        if (!root) {
            return null;
        }

        return root.querySelector('#culqi_checkout_frame')
            || root.querySelector('iframe.culqi_checkout')
            || root.querySelector('iframe');
    }

    /**
     * Dimensiones de la tarjeta blanca del checkout Culqi v4.
     * El iframe ocupa todo el viewport; la tarjeta blanca va centrada dentro.
     */
    function getCulqiFormCardRect() {
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        let cardWidth;
        let cardHeight;
        let top;
        let left;

        if (vw <= 520) {
            cardWidth = vw - 24;
            cardHeight = vh - 24;
            left = 12;
            top = 12;
        } else if (vw <= 768) {
            cardWidth = Math.min(420, vw - 32);
            cardHeight = Math.min(620, vh - 40);
            left = (vw - cardWidth) / 2;
            top = Math.max(16, (vh - cardHeight) / 2);
        } else {
            cardWidth = 420;
            cardHeight = Math.min(580, vh - 48);
            left = (vw - cardWidth) / 2;
            top = Math.max(20, (vh - cardHeight) / 2);
        }

        return {
            top: top,
            left: left,
            right: left + cardWidth,
            bottom: top + cardHeight,
            width: cardWidth,
            height: cardHeight,
        };
    }

    function positionCulqiCloseAnchor(anchor) {
        const card = getCulqiFormCardRect();

        if (!anchor || !card || card.width <= 0 || card.height <= 0) {
            return false;
        }

        anchor.style.top = Math.round(card.top) + 'px';
        anchor.style.left = Math.round(card.left) + 'px';
        anchor.style.width = Math.round(card.width) + 'px';
        anchor.style.height = Math.round(card.height) + 'px';

        return true;
    }

    function positionCulqiCloseButton(anchor, btn) {
        if (!anchor || !btn) {
            return false;
        }

        return positionCulqiCloseAnchor(anchor);
    }

    function findCulqiModalRoot() {
        return document.getElementById('culqi-js');
    }

    function mountCulqiCloseButton() {
        const root = findCulqiModalRoot();
        if (!root || !isCulqiOverlayVisible() || !isCulqiCheckoutReady()) {
            return false;
        }

        const elements = createCulqiCloseButton();
        if (!elements) {
            return false;
        }

        const anchor = elements.anchor;
        const btn = elements.btn;

        if (!positionCulqiCloseButton(anchor, btn)) {
            teardownCulqiCloseButton();
            return false;
        }

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                anchor.classList.add('culqi-modal-close-anchor--visible');
                btn.classList.add('culqi-modal-close-btn--visible');
            });
        });

        return true;
    }

    function bindCulqiEscapeClose() {
        if (culqiEscapeHandler) {
            return;
        }

        culqiEscapeHandler = function (e) {
            if (e.key === 'Escape') {
                closeCulqiModal();
            }
        };
        document.addEventListener('keydown', culqiEscapeHandler);
    }

    function unbindCulqiEscapeClose() {
        if (!culqiEscapeHandler) {
            return;
        }
        document.removeEventListener('keydown', culqiEscapeHandler);
        culqiEscapeHandler = null;
    }

    function isCulqiOverlayVisible() {
        const root = findCulqiModalRoot();
        if (!root) {
            return false;
        }

        const style = window.getComputedStyle(root);
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) {
            return false;
        }

        const rect = root.getBoundingClientRect();
        return rect.width > 50 && rect.height > 50;
    }

    function isCulqiCheckoutReady() {
        if (!isCulqiOverlayVisible()) {
            return false;
        }

        const iframe = findCulqiIframe();
        if (!iframe || !iframe.getAttribute('src')) {
            return false;
        }

        const rect = iframe.getBoundingClientRect();
        return rect.width > 200 && rect.height > 200;
    }

    function waitForCulqiFormReady(callback) {
        let finished = false;
        let contentReadyTriggered = false;
        let iframeLoadFired = false;
        let iframeDetectedAt = null;
        const startedAt = Date.now();
        const maxWaitMs = 15000;
        const minWaitAfterIframeMs = 900;
        const paintSettleMs = 700;

        function cleanupPollTimer() {
            if (culqiCloseMountTimer) {
                clearInterval(culqiCloseMountTimer);
                culqiCloseMountTimer = null;
            }
        }

        function finish(isReady) {
            if (finished) {
                return;
            }
            finished = true;
            cleanupPollTimer();
            callback(isReady);
        }

        function revealAfterPaintSettle() {
            if (contentReadyTriggered) {
                return;
            }
            contentReadyTriggered = true;

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    setTimeout(function () {
                        finish(isCulqiOverlayVisible() && isCulqiCheckoutReady());
                    }, paintSettleMs);
                });
            });
        }

        function markIframeContentReady() {
            if (!iframeLoadFired) {
                iframeLoadFired = true;
            }

            if (!iframeDetectedAt) {
                return;
            }

            const elapsed = Date.now() - iframeDetectedAt;
            if (elapsed >= minWaitAfterIframeMs) {
                revealAfterPaintSettle();
            }
        }

        function bindIframeLoad(iframe) {
            if (!iframe || iframe.dataset.culqiCloseBound === '1') {
                return;
            }

            iframe.dataset.culqiCloseBound = '1';
            iframe.addEventListener('load', function () {
                iframeLoadFired = true;
                markIframeContentReady();
            }, { once: true });
        }

        function pollCheckoutReady() {
            if (finished) {
                return;
            }

            if (Date.now() - startedAt > maxWaitMs) {
                finish(false);
                return;
            }

            if (!isCulqiOverlayVisible()) {
                return;
            }

            const iframe = findCulqiIframe();
            if (!iframe || !iframe.getAttribute('src')) {
                return;
            }

            if (!iframeDetectedAt) {
                iframeDetectedAt = Date.now();
                iframe.dataset.culqiLoadDetectedAt = String(iframeDetectedAt);
            }

            bindIframeLoad(iframe);

            if (!iframeLoadFired && iframeDetectedAt) {
                const elapsed = Date.now() - iframeDetectedAt;
                if (elapsed >= minWaitAfterIframeMs + 400) {
                    iframeLoadFired = true;
                }
            }

            if (!iframeLoadFired) {
                return;
            }

            markIframeContentReady();
        }

        pollCheckoutReady();
        culqiCloseMountTimer = setInterval(pollCheckoutReady, 120);
    }

    function revealCulqiCloseButton() {
        if (!mountCulqiCloseButton()) {
            return false;
        }

        bindCulqiEscapeClose();

        if (!culqiClosePositionTimer) {
            culqiCloseResizeHandler = function () {
                const root = findCulqiModalRoot();
                const anchor = root ? root.querySelector('#culqi-modal-close-anchor') : null;
                const btn = anchor ? anchor.querySelector('#culqi-modal-close-btn') : null;
                if (anchor && btn && btn.classList.contains('culqi-modal-close-btn--visible')) {
                    positionCulqiCloseButton(anchor, btn);
                }
            };

            window.addEventListener('resize', culqiCloseResizeHandler);
            culqiClosePositionTimer = setInterval(culqiCloseResizeHandler, 250);
        }

        return true;
    }

    function showCulqiCloseButton() {
        hideCulqiCloseButton();

        waitForCulqiFormReady(function (isReady) {
            if (!isReady) {
                return;
            }
            revealCulqiCloseButton();
        });
    }

    function hideCulqiCloseButton() {
        teardownCulqiCloseButton();

        if (culqiCloseMountTimer) {
            clearInterval(culqiCloseMountTimer);
            culqiCloseMountTimer = null;
        }

        if (culqiClosePositionTimer) {
            clearInterval(culqiClosePositionTimer);
            culqiClosePositionTimer = null;
        }

        if (culqiCloseResizeHandler) {
            window.removeEventListener('resize', culqiCloseResizeHandler);
            culqiCloseResizeHandler = null;
        }

        unbindCulqiEscapeClose();
    }

    function cleanupCulqiDomFallback() {
        const root = findCulqiModalRoot();
        if (root) {
            root.style.display = 'none';
            return;
        }

        document.querySelectorAll('iframe[src*="culqi"], [id*="culqi"], [class*="culqi"]').forEach(function (el) {
            if (el.id === 'culqi-modal-close-btn') {
                return;
            }

            const overlay = el.closest('[class*="culqi"]') || el;
            if (overlay && overlay !== document.body && overlay.id !== 'culqi-modal-close-btn') {
                overlay.style.display = 'none';
            }
        });
    }

    function dismissCulqiCheckout(options) {
        const opts = options && typeof options === 'object' ? options : {};
        hideCulqiCloseButton();

        try {
            if (typeof window.Culqi !== 'undefined' && typeof window.Culqi.close === 'function') {
                window.Culqi.close();
            }
        } catch (e) { /* ignore */ }

        cleanupCulqiDomFallback();

        if (!opts.keepBodyLocked) {
            document.body.style.overflow = '';
        }
    }

    function closeCulqiModal(options) {
        const opts = options && typeof options === 'object' ? options : {};
        dismissCulqiCheckout({ keepBodyLocked: opts.keepLoading === true });

        if (opts.keepLoading) {
            return;
        }

        if (typeof app_cart !== 'undefined') {
            app_cart.hidePaymentLoading();
        } else {
            document.body.style.overflow = '';
        }
    }

    window.closeCulqiModal = closeCulqiModal;
    window.dismissCulqiCheckout = dismissCulqiCheckout;

    async function askedDocument(order) {
        app_cart.order_generated = order
        $('#modal_ask_document').modal('show')
    }

    async function execCulqi() {
        if (!culqiPublicKey) {
            window.mostrarMensaje('Pago con tarjeta', 'El pago con tarjeta aún no está configurado. Elija otro método de pago.', 'warning');
            return;
        }

        let Culqi;
        try {
            Culqi = await ensureCulqiReady();
        } catch (e) {
            if (typeof app_cart !== 'undefined') {
                app_cart.hidePaymentLoading();
            }
            window.mostrarMensaje('Culqi', 'No se pudo cargar el formulario de Culqi. Recargue la página e intente de nuevo.', 'error');
            return;
        }

        const precio = Math.round(Number(jQuery('#total_amount').data('total')) * 100);
        if (precio <= 0) {
            window.mostrarMensaje('Monto inválido', 'El monto del pedido debe ser mayor a cero.', 'warning');
            return;
        }

        // Reaplicar publicKey justo antes de abrir el checkout (evita pérdida de contexto).
        Culqi.publicKey = culqiPublicKey;

        const settings = {
            title: 'Productos Ecommerce',
            currency: 'PEN',
            description: 'Compras Ecommerce Facturador Pro',
            amount: precio,
        };

        // RSA solo si hay Web Crypto (HTTPS / localhost). En http://*.test se omite a propósito
        // para tokenizar sin ENCRYPT ERROR; el canal a secure.culqi.com sigue siendo HTTPS.
        if (canUseCulqiRsaEncryption() && culqiRsaId && culqiRsaPublicKey) {
            settings.xculqirsaid = culqiRsaId;
            settings.rsapublickey = culqiRsaPublicKey;
        } else if (culqiRsaId || culqiRsaPublicKey) {
            console.warn(
                '[Culqi] RSA omitido: se requiere Secure Context (HTTPS) y PEM válido. ' +
                'Tokenización continúa sin cifrado RSA local.'
            );
        }

        Culqi.settings(settings);
        Culqi.open();
        showCulqiCloseButton();
    }


    async function culqi() {
        if (!window.Culqi || !window.Culqi.token) {
            const message = getCulqiErrorMessage(window.Culqi && window.Culqi.error) || 'Pago no realizado';
            closeCulqiModal();
            window.mostrarMensaje('Pago no realizado', message, 'error');
            return;
        }

        // Token generado: cerrar checkout Culqi de inmediato y pasar al overlay de carga del banco.
        dismissCulqiCheckout({ keepBodyLocked: true });

        if (typeof app_cart === 'undefined') {
            window.mostrarMensaje('Pago no realizado', 'No se pudo preparar el pedido. Recargue la página e intente de nuevo.', 'error');
            return;
        }

        app_cart.showCulqiBankLoading();

        try {
            if (typeof app_cart.ensureGuestFormDocument === 'function') {
                app_cart.ensureGuestFormDocument();
            }

            const formpayment = await app_cart.getFormPaymentCash();
            const customer = typeof app_cart.buildBackendPaymentCustomer === 'function'
                ? app_cart.buildBackendPaymentCustomer()
                : (formpayment.customer || {});

            if (formpayment.purchase) {
                formpayment.purchase = typeof app_cart.syncPurchaseCustomerData === 'function'
                    ? app_cart.syncPurchaseCustomerData(formpayment.purchase, customer)
                    : formpayment.purchase;
            }

            const totalAmount = Number(app_cart.summary?.total ?? jQuery('#total_amount').data('total') ?? 0);
            const precio = Math.round(totalAmount * 100);
            const precio_culqi = Number(totalAmount.toFixed(2));
            const culqiToken = window.Culqi.token;
            const culqiMetadata = culqiToken.metadata || {};

            const payload = Object.assign({}, formpayment, {
                producto: formpayment.producto || 'Compras Ecommerce Facturador Pro',
                precio: precio,
                precio_culqi: precio_culqi,
                customer: customer,
                token: culqiToken.id,
                email: culqiToken.email || customer.correo_electronico || app_cart.guest_form?.email || '',
                installments: culqiMetadata.installments ?? 0,
                reference_payment: 'culqi',
                shipping_address: formpayment.shipping_address || app_cart.buildShippingAddress?.() || '',
            });

            const response = await axios.post(
                window.__routes?.culqi || "{{ route('tenant_ecommerce_culqui') }}",
                payload,
                app_cart.getHeaderConfig()
            );

            if (response.data && response.data.success === true) {
                dismissCulqiCheckout({ keepBodyLocked: true });
                app_cart.saveContactDataUser();
                app_cart.showPurchaseSuccess(response.data.order);
                return;
            }

            dismissCulqiCheckout({ keepBodyLocked: true });
            app_cart.hidePaymentLoading();
            window.mostrarMensaje(
                'Pago no realizado',
                (response.data && response.data.message) || 'Sucedió algo inesperado.',
                'error'
            );
        } catch (error) {
            console.log(error);
            dismissCulqiCheckout({ keepBodyLocked: true });
            app_cart.hidePaymentLoading();

            let message = 'Ocurrió un error al procesar el pago.';
            const responseData = error.response && error.response.data;

            if (responseData && responseData.message) {
                message = responseData.message;
            } else if (responseData && responseData.errors) {
                message = Object.values(responseData.errors).flat().join(' ');
            }

            window.mostrarMensaje('Pago no realizado', message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (culqiPublicKey) {
            ensureCulqiReady().catch(function () {
                console.warn('Culqi v4 no disponible al cargar la página.');
            });
        }
    });

    window.execCulqi = execCulqi;
    window.culqi = culqi;
})();

    function getCustomer() {
        let user = JSON.parse('{!! json_encode( Auth::guard("ecommerce")->user() ) !!}')
        return {
            "codigo_tipo_documento_identidad": "0",
            "numero_documento": "0",
            "apellidos_y_nombres_o_razon_social": user.name,
            "codigo_pais": "PE",
            "ubigeo": "150101",
            "direccion": app_cart.user.address,
            "correo_electronico": user.email,
            "telefono": app_cart.user.telephone
        }
    }

    function getItems() {
        return app_cart.records
    }

    function isNumberKey(evt) {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode != 46 && charCode > 31 &&
            (charCode < 48 || charCode > 57))
            return false;
        return true;
    }

</script>

<script src="{{ route('google_maps_script') }}"></script>

@endpush
