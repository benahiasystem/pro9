// Cart Application - Ecommerce Module
// Main Vue instance for shopping cart detail page

import TrustBadges from './components/TrustBadges.vue';
import FrequentlyBoughtTogether from './components/FrequentlyBoughtTogether.vue';

let izipaySdkLoadPromise = null;
let izipayLoadedPublicKey = null;
const IZIPAY_KR_MAIN_SRC = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js';
const IZIPAY_KR_CSS_HREF = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css';
const IZIPAY_KR_EXT_SRC = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.js';
const MP_BRICK_HOST_ID = 'mp-brick-container';
const MP_LOGO_SRC = '/porto-ecommerce/assets/images/payment-gateways/mercado-pago-official.svg?v=2';

function parseEcommerceConfigBool(value, defaultValue = true) {
    if (value === undefined || value === null || value === '') {
        return defaultValue;
    }
    if (value === true || value === 1 || value === '1' || value === 'true' || value === 'on' || value === 'yes') {
        return true;
    }
    if (value === false || value === 0 || value === '0' || value === 'false' || value === 'off' || value === 'no') {
        return false;
    }
    return !!value;
}

/**
 * Estado inicial del modal de intención (síncrono, sin API).
 * Usa config inyectada por Blade + localStorage del carrito.
 */
function resolveCheckoutIntentBootState() {
    const fromWindow = window.__checkoutIntentBootState;
    if (fromWindow && typeof fromWindow === 'object') {
        return {
            checkoutIntent: fromWindow.checkoutIntent === 'quote' ? 'quote' : 'purchase',
            checkoutIntentChosen: !!fromWindow.checkoutIntentChosen,
            checkoutIntentModalVisible: !!fromWindow.checkoutIntentModalVisible,
        };
    }

    const boot = window.__ecommerce_quotation_boot || {};
    const cfg = window.__ecommerce_config || {};
    const enabled = typeof boot.enabled === 'boolean'
        ? boot.enabled
        : parseEcommerceConfigBool(cfg.quotation_enabled, false);
    const mode = boot.mode || cfg.quotation_mode || 'quote_and_sell';
    const quoteOnly = enabled && mode === 'quote_only';
    const hybrid = enabled && mode === 'quote_and_sell';
    const userId = boot.user_id || (cfg.user && cfg.user.id) || null;

    let hasItems = false;
    try {
        const raw = localStorage.getItem('products_cart');
        const arr = raw ? JSON.parse(raw) : [];
        hasItems = Array.isArray(arr) && arr.length > 0;
    } catch (e) { /* ignore */ }

    let restored = null;
    try {
        restored = sessionStorage.getItem('ecommerce_checkout_intent');
    } catch (e) { /* ignore */ }
    const restoreQuote = restored === 'quote' && !!userId;

    if (!enabled) {
        return { checkoutIntent: 'purchase', checkoutIntentChosen: true, checkoutIntentModalVisible: false };
    }
    if (quoteOnly) {
        return { checkoutIntent: 'quote', checkoutIntentChosen: true, checkoutIntentModalVisible: false };
    }
    if (restoreQuote) {
        return { checkoutIntent: 'quote', checkoutIntentChosen: true, checkoutIntentModalVisible: false };
    }
    if (hybrid && hasItems) {
        return { checkoutIntent: 'purchase', checkoutIntentChosen: false, checkoutIntentModalVisible: true };
    }
    return { checkoutIntent: 'purchase', checkoutIntentChosen: true, checkoutIntentModalVisible: false };
}

function dismissCheckoutIntentBootOverlay() {
    const el = document.getElementById('checkout-intent-boot');
    if (!el) return;
    el.classList.remove('is-open');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
}

const __checkoutIntentBoot = resolveCheckoutIntentBootState();

var app_cart = new Vue({
    el: '#app',
    components: {
        TrustBadges,
        FrequentlyBoughtTogether,
    },
    data: {
        form_contact: {
            address:   '',
            telephone:   '',
            receiver_name: '',
            receiver_telephone: '',
        },
        deliveryContactOverride: false,
        addressModal: {
            address: '',
            reference: '',
            latitude: -12.046374,
            longitude: -77.042793,
            preventSearch: false
        },
        addressModalMode: 'add',
        editingAddressId: null,
        addressMapReturnToList: false,
        userAddresses: window.__ecommerce_config?.userAddresses || [],
        selectedAddressId: null,
        addressListMenuOpen: null,
        addressListMenuStyle: {},
        addressListMenuPlacement: 'bottom',
        addressListMenuPositioned: false,
        addressListMenuAnchorEvent: null,
        map: null,
        marker: null,
        geocoder: null,
        mapListeners: [],
        mapGeocodeEnabled: false,
        mapGeocodeRequestId: 0,
        isGeocodingAddress: false,
        lastGeocodedLat: null,
        lastGeocodedLng: null,
        addressSearchTimeout: null,
        payment_cash: {
            amount: '',
            clicked: false
        },
        response_search: {},
        text_search: '',
        loading_search: false,
        identity_document_types: [{
            id: '1',
            description: 'Cédula'
        }, {
            id: '6',
            description: 'RIF'
        }],
        formIdentity: {
            identity_document_type_id: ''
        },
        records: [],
        records_old: [],
        couponField: '',
        couponMessage: null,
        couponSuccessMessage: null,
        couponLoading: false,
        appliedCoupon: null,
        appliedCampaignDiscount: 0,
        campaignSavings: 0,
        appliedCampaigns: [],
        campaignValidationTimer: null,
        campaignExpirationInterval: null,
        order_generated: {},
        summary: {
            subtotal: '0.0',
            tax: '0.0',
            total: '0.0'
        },
        aux_totals: {},
        form_document: {},
        user: (window.__ecommerce_config && window.__ecommerce_config.user) || {},
        typeDocumentSelected: '',
        response_order_total:0,
        errors: {},
        exchange_rate_sale: '',
        typeDocuments: '',
        typeDocumentList: [],
        numberDocument: '',
        phone_whatsapp: window.__ecommerce_config?.phone_whatsapp || '',
        enable_whatsapp: window.__ecommerce_config?.enable_whatsapp || false,
        global_discount_type: window.__ecommerce_config?.global_discount_type || {},
        all_identity_document_types : [
            {id: '1', name: 'Cédula'},
            {id: '6', name: 'RIF'},
            {id: '0', name: 'Otro documento'},
            {id: '4', name: 'Carnet de extranjería'},
            {id: '7', name: 'Pasaporte'},
        ],
        addressSuggestions: [],
        departments: [],
        provinces: [],
        districts: [],
        selectedDepartment: '',
        selectedProvince: '',
        selectedDistrict: '',
        highlightedIndex: -1,
        addressSearchTimeout: null,
        // Zona de delivery seleccionada (usada en cálculos y documento)
        deliveryZone: null,
        // Todas las zonas que hacen match con la dirección del cliente
        availableDeliveryZones: [],
        deliveryMessage: '',
        // Primera dirección guardada del cliente (cargada desde el servidor)
        userDefaultAddress: window.__ecommerce_config?.userAddress || null,
        // Método de pago seleccionado: 'culqi' | 'cash' | null
        selectedPaymentMethod: null,

        // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
        // Controla si se emiten facturas electrónicas o notas de venta
        enable_electronic_documents: window.__ecommerce_config?.enable_electronic_documents || false,
        // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
        // Recojo en tienda
        enableStorePickup: window.__ecommerce_config?.enable_store_pickup || false,
        // Cotizaciones en tienda virtual
        quotationEnabled: parseEcommerceConfigBool(window.__ecommerce_config?.quotation_enabled, false),
        quotationMode: window.__ecommerce_config?.quotation_mode || 'quote_and_sell',
        quotationShowPrices: parseEcommerceConfigBool(window.__ecommerce_config?.quotation_show_prices, true),
        quotationSuccessMessage: window.__ecommerce_config?.quotation_success_message
            || 'Registramos tu solicitud. Nuestro equipo la revisará a la brevedad.',
        quotationValidityDays: Number(window.__ecommerce_config?.quotation_validity_days) || 7,
        quotationTerms: window.__ecommerce_config?.quotation_terms || '',
        // Intención del checkout en modo híbrido: 'purchase' | 'quote'
        // Inicializado en sincronía (Blade + localStorage) para modal instantáneo.
        checkoutIntent: __checkoutIntentBoot.checkoutIntent,
        checkoutIntentModalVisible: __checkoutIntentBoot.checkoutIntentModalVisible,
        checkoutIntentChosen: __checkoutIntentBoot.checkoutIntentChosen,
        pickupBranches: window.__ecommerce_config?.pickup_branches || [],
        selectedPickupBranch: null,
        isPickupMode: false,
        pickupBranchQuery: '',
        // Métodos de pago adicionales
        enableCash: window.__ecommerce_config?.enable_cash || false,
        cashPaymentTitle: window.__ecommerce_config?.cash_payment_title || 'Pago contra entrega',
        cashPaymentDescription: window.__ecommerce_config?.cash_payment_description || '',
        cashPaymentPickupOnly: window.__ecommerce_config?.cash_payment_pickup_only || false,
        enableYape: window.__ecommerce_config?.enable_yape || false,
        enableTransfer: window.__ecommerce_config?.enable_transfer || false,
        enablePaypal: window.__ecommerce_config?.enable_paypal || false,

        enableIzipay: window.__ecommerce_config?.enable_izipay || false,
        titleIzipay: window.__ecommerce_config?.title_izipay || 'Pago con Izipay',
        descriptionIzipay: window.__ecommerce_config?.description_izipay || '',

        enableMp: window.__ecommerce_config?.enable_mp || false,
        titleMp: window.__ecommerce_config?.title_mp || 'Mercado Pago',
        descriptionMp: window.__ecommerce_config?.description_mp || '',

        enableCulqi: window.__ecommerce_config?.enable_culqi || false,
        titleCulqi: window.__ecommerce_config?.title_culqi || 'Tarjeta (VISA)',
        descriptionCulqi: window.__ecommerce_config?.description_culqi || '',
        isSecurePage: typeof window !== 'undefined' && (
            window.isSecureContext === true
            || window.location.protocol === 'https:'
            || ['localhost', '127.0.0.1'].includes(window.location.hostname)
            || String(window.location.hostname || '').endsWith('.localhost')
        ),

        acceptedTerms: false,
        processingPayment: false,
        paymentLoadingTitle: 'Estamos generando tu pedido',
        paymentLoadingText: 'Por favor no cierres esta ventana...',
        paymentSuccessVisible: false,
        paymentSuccessRedirecting: false,
        paymentAlertVisible: false,
        paymentAlertTitle: '',
        paymentAlertText: '',
        paymentAlertType: 'error',
        successOrder: null,
        successIsYape: false,
        successOrderNumber: '',
        successPaymentLabel: '',
        successItemsCount: 0,
        successOrderTotal: 0,
        thankYouUrl: null,

        // Cotización desde carrito
        quotationModalVisible: false,
        quotationSubmitting: false,
        quotationSuccessVisible: false,
        quotationSuccessRedirecting: false,
        quotationResult: null,
        quotationForm: {
            contact_name: '',
            email: '',
            telephone: '',
            notes: '',
            validity_days: 7,
        },

        mpScriptLoaded: false,
        mpBrickController: null,
        mpBrickReady: false,
        mpPreparedAmount: null,
        mpPreparedEmail: null,
        mpInstance: null,
        mpPreparePromise: null,
        mpPrepareTimer: null,
        krScriptLoaded: false,

        // Guest checkout — fase 1
        guestCheckoutAccepted: false,
        showGuestForm: false,
        guestWarningChecked: false,
        guest_form: {
            email: '',
            telephone: '',
            identity_document_type_id: '1',
            number: '',
            name: '',
        },
        izipayPublicKey: null,
        izipayPublicKeyPromise: null,

        // Guest checkout — fase 1
        guestCheckoutAccepted: false,
        showGuestForm: false,
        guestDocumentLookupLoading: false,
        guestDocumentStatus: null,
        guestExistingCustomer: false,
        guestDocumentVerifyTimeout: null,
        guestTripleVerifyTimeout: null,
        guestMatchedTripleKey: null,
        guestAutoAddressSnapshot: null,
        guestReturningAddressNotice: false,
        guestTripleLookupLoading: false,
        guestHighAmountThreshold: 700,
        guestAddressesStorageKey: 'guest_addresses_draft',
        guest_form: {
            email: '',
            telephone: '',
            identity_document_type_id: '1',
            number: '',
            name: '',
        },
    },
    computed: {
        isLoggedIn() {
            return !!(this.user && this.user.id);
        },
        isGuestCheckoutActive() {
            return !this.isLoggedIn && this.guestCheckoutAccepted && this.showGuestForm;
        },
        isGuestContactReady() {
            return this.isGuestCheckoutActive && this.isGuestFormValid();
        },
        isGuestCheckoutComplete() {
            if (!this.isGuestCheckoutActive) {
                return false;
            }

            const email = (this.guest_form.email || '').trim();
            const phone = (this.guest_form.telephone || this.form_contact.telephone || '').trim();
            const name = (this.guest_form.name || '').trim();
            const number = (this.guest_form.number || '').trim();
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const phoneDigits = phone.replace(/\D/g, '');

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return false;
            }
            if (!phoneDigits || phoneDigits.length < 7) {
                return false;
            }
            if (!name || !number) {
                return false;
            }
            if (docType === '1' && number.replace(/\D/g, '').length !== 8) {
                return false;
            }
            if (docType === '6' && number.replace(/\D/g, '').length !== 11) {
                return false;
            }
            if (!this.isPickupMode && !(this.form_contact.address || '').trim()) {
                return false;
            }
            if (this.isPickupMode && !this.selectedPickupBranch) {
                return false;
            }

            return true;
        },
        isGuestFormReady() {
            return this.isGuestCheckoutComplete;
        },
        isLoggedIn() {
            if (this.user && this.user.id) {
                return true;
            }
            // Fallback: id inyectado por Blade aunque el objeto user aún no esté hidratado
            const bootId = window.__ecommerce_quotation_boot && window.__ecommerce_quotation_boot.user_id;
            return !!bootId;
        },
        trustBadgesEnabled() {
            const boot = window.__socialProofBoot || {};
            if (typeof boot.trustBadgesEnabled === 'boolean') {
                return boot.trustBadgesEnabled;
            }
            return true;
        },
        trustBadges() {
            const boot = window.__socialProofBoot || {};
            return Array.isArray(boot.trustBadges) ? boot.trustBadges : null;
        },
        cartFbtItemIds() {
            return (this.records || [])
                .map((row) => parseInt(row.id, 10))
                .filter((id) => id > 0)
                .slice(0, 8);
        },
        isGuestCheckoutActive() {
            return !this.isLoggedIn && this.guestCheckoutAccepted && this.showGuestForm;
        },
        isGuestContactReady() {
            return this.isGuestCheckoutActive && this.isGuestFormValid();
        },
        isGuestCheckoutComplete() {
            if (!this.isGuestCheckoutActive) {
                return false;
            }

            const email = (this.guest_form.email || '').trim();
            const phone = (this.guest_form.telephone || this.form_contact.telephone || '').trim();
            const name = (this.guest_form.name || '').trim();
            const number = (this.guest_form.number || '').trim();
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const phoneDigits = phone.replace(/\D/g, '');
            const cleanNumber = number.replace(/\D/g, '');

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return false;
            }
            if (!phoneDigits || phoneDigits.length < 7) {
                return false;
            }

            if (this.guestRequiresIdentityDocument) {
                if (!name) {
                    return false;
                }
                if (docType !== '1' && docType !== '6') {
                    return false;
                }
                if (docType === '1' && cleanNumber.length !== 8) {
                    return false;
                }
                if (docType === '6' && cleanNumber.length !== 11) {
                    return false;
                }
            } else {
                if (!name || !number) {
                    return false;
                }
                if (docType === '1' && cleanNumber.length !== 8) {
                    return false;
                }
                if (docType === '6' && cleanNumber.length !== 11) {
                    return false;
                }
            }

            if (!this.isPickupMode && !(this.form_contact.address || '').trim()) {
                return false;
            }
            if (this.isPickupMode && !this.selectedPickupBranch) {
                return false;
            }

            return true;
        },
        isGuestFormReady() {
            return this.isGuestCheckoutComplete;
        },
        defaultContactName() {
            return String(
                (this.guest_form && this.guest_form.name)
                || (this.user && this.user.name)
                || ''
            ).trim();
        },
        defaultContactPhone() {
            return String(
                (this.guest_form && this.guest_form.telephone)
                || (this.user && this.user.telephone)
                || ''
            ).trim();
        },
        buyerContactPhone() {
            return String(this.form_contact.telephone || '').trim() || this.defaultContactPhone;
        },
        needsBuyerPhoneField() {
            return !this.defaultContactPhone;
        },
        deliveryContactName() {
            if (this.deliveryContactOverride) {
                return String(this.form_contact.receiver_name || '').trim();
            }
            return this.defaultContactName;
        },
        deliveryContactPhone() {
            if (this.deliveryContactOverride) {
                return String(this.form_contact.receiver_telephone || '').trim();
            }
            return this.buyerContactPhone;
        },
        deliveryContactSummary() {
            return [this.deliveryContactName, this.deliveryContactPhone].filter(Boolean).join(' · ');
        },
        checkoutContactPhone() {
            return this.buyerContactPhone || this.deliveryContactPhone;
        },
        buyerContactSummary() {
            return [this.defaultContactName, this.buyerContactPhone].filter(Boolean).join(' · ');
        },
        maxLength: function () {
            if (this.typeDocuments === '6') {
                return 11
            }
            if (this.typeDocuments === '1') {
                return 8
            }
            return 15
        },
        ubigeoLabel: function () {
            if (!this.selectedDepartment || !this.selectedProvince || !this.selectedDistrict) return '';
            const dept = this.departments.find(d => d.value === this.selectedDepartment);
            const prov = this.provinces.find(p => p.value === this.selectedProvince);
            const dist = this.districts.find(d => d.value === this.selectedDistrict);
            if (!dept || !prov || !dist) return '';
            return dept.label.toUpperCase() + ' / ' + prov.label.toUpperCase() + ' / ' + dist.label.toUpperCase() + ' (' + this.selectedDistrict + ')';
        },
        // Etiqueta del tipo de documento inferido del número del usuario (usado en modo solo-lectura)
        invoiceTypeLabel: function () {
            const num = this.user && this.user.number ? String(this.user.number).trim() : '';
            // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
            if (num.length === 8)  return 'Nota de Venta';
            if (num.length === 11) return 'Factura';
            return 'Nota de Venta';
            // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
        },
        showWhatsapp: function () {
            return this.enable_whatsapp && !!this.phone_whatsapp;
        },
        httpsCheckoutUrl: function () {
            if (typeof window === 'undefined' || !window.location) {
                return '#';
            }
            return 'https://' + window.location.host + window.location.pathname + window.location.search;
        },
        whatsappPhone: function () {
            const raw = String(this.phone_whatsapp || '').replace(/\D+/g, '');
            if (raw.length === 9 && raw.startsWith('9')) {
                return '51' + raw;
            }
            return raw;
        },
        isYapePaymentSuccess() {
            if (this.successIsYape) {
                return true;
            }
            if (!this.successOrder) return false;
            const candidates = [
                this.successOrder.referencePayment,
                this.successOrder.reference_payment,
                this.selectedPaymentMethod,
                this.successOrder.paymentLabel,
            ];
            return candidates.some((value) => {
                const normalized = String(value || '').trim().toLowerCase();
                return normalized === 'yape' || normalized.includes('yape');
            });
        },
        canSendYapeVoucherWhatsapp() {
            return this.isYapePaymentSuccess && !!this.whatsappPhone;
        },
        showCheckoutSections() {
            return this.isLoggedIn || this.guestCheckoutAccepted;
        },
        filteredPickupBranches() {
            const query = String(this.pickupBranchQuery || '').trim().toLowerCase();
            if (!query) return this.pickupBranches;

            return this.pickupBranches.filter(branch => {
                const haystack = (String(branch.name || '') + ' ' + String(branch.address || '')).toLowerCase();
                return haystack.indexOf(query) !== -1;
            });
        },
        isCashPaymentAvailable() {
            return this.enableCash && (!this.cashPaymentPickupOnly || this.isPickupMode);
        },
        isCashHiddenByPickupOnly() {
            return this.enableCash && this.cashPaymentPickupOnly && !this.isPickupMode;
        },
        availablePaymentMethodsCount() {
            return [
                this.enableCulqi,
                this.enableIzipay,
                this.enableMp,
                this.isCashPaymentAvailable,
                this.enableYape,
                this.enableTransfer,
                this.enablePaypal,
            ].filter(Boolean).length;
        },
        hasPaymentMethods() {
            return this.availablePaymentMethodsCount > 0;
        },
        /**
         * Texto del CTA del resumen. Fijo para evitar etiquetas largas según la pasarela.
         */
        primaryPayButtonLabel() {
            return 'Confirmar pedido';
        },
        guestCheckoutTotal() {
            return parseFloat(this.summary.total || 0);
        },
        guestRequiresIdentityDocument() {
            return this.guestCheckoutTotal > this.guestHighAmountThreshold;
        },
        guestHighAmountIdentityComplete() {
            if (!this.guestRequiresIdentityDocument) {
                return true;
            }

            const name = (this.guest_form.name || '').trim();
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const cleanNumber = (this.guest_form.number || '').replace(/\D/g, '');

            if (!name) {
                return false;
            }
            if (docType !== '1' && docType !== '6') {
                return false;
            }
            if (docType === '1' && cleanNumber.length !== 8) {
                return false;
            }
            if (docType === '6' && cleanNumber.length !== 11) {
                return false;
            }

            return true;
        },
        guestHighAmountIdentityNotice() {
            if (!this.isGuestCheckoutActive || !this.guestRequiresIdentityDocument) {
                return null;
            }

            if (this.guestHighAmountIdentityComplete) {
                return null;
            }

            return 'Por normativa, en compras mayores a Bs. 700.00 debes ingresar tu nombre y un documento válido (Cédula o RIF).';
        },
        guestInvoiceTypeLabel() {
            const docType = String(this.guest_form.identity_document_type_id || '0');
            // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
            if (docType === '1') return 'Nota de venta';
            if (docType === '6') return 'Factura de venta';
            return 'Nota de venta';
            // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
        },
        guestInvoiceNotice() {
            if (!this.showGuestForm || this.isLoggedIn || !this.enable_electronic_documents) {
                return null;
            }

            const docType = String(this.guest_form.identity_document_type_id || '0');
            // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
            if (docType === '1') {
                return 'Al ingresar tu Cédula se generará automáticamente una Nota de venta.';
            }
            if (docType === '6') {
                return 'Al ingresar tu RIF se generará automáticamente tu Factura electrónica.';
            }
            return 'Sin Cédula ni RIF se emitirá una Nota de venta.';
            // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
        },
        guestDocumentTypeOptions() {
            return [
                { id: '1', label: 'Cédula' },
                { id: '6', label: 'RIF' },
                { id: '0', label: 'Otro documento' },
                { id: '4', label: 'Carnet de extranjería' },
                { id: '7', label: 'Pasaporte' },
            ];
        },
        guestDocumentNumberMaxLength() {
            const docType = String(this.guest_form.identity_document_type_id || '0');
            if (docType === '6') {
                return 11;
            }
            if (docType === '1') {
                return 8;
            }
            return 15;
        },
        quotationLines() {
            return (this.records || []).map(row => {
                const qty = Number(row.cantidad) || 1;
                const unit = Number(row.sale_unit_price) || 0;
                const lineTotal = Number(row.sub_total);
                return {
                    id: row.id,
                    description: row.description || row.name || 'Producto',
                    quantity: qty,
                    unit_price: unit,
                    total: !isNaN(lineTotal) ? lineTotal : (unit * qty),
                };
            });
        },
        quotationModalTotal() {
            return this.quotationLines.reduce((sum, line) => sum + (Number(line.total) || 0), 0);
        },
        // Compras bloqueadas solo si cotizaciones están ON y el modo es solo cotizar
        quoteOnlyMode() {
            return this.quotationEnabled && this.quotationMode === 'quote_only';
        },
        // Modo híbrido: cotizar y vender
        isHybridQuotationMode() {
            return this.quotationEnabled && !this.quoteOnlyMode;
        },
        allowPurchase() {
            return !this.isQuotationCheckout;
        },
        // Checkout de cotización (inline en el carrito)
        // Activo en: Solo cotizar, o Cotizar y vender con intención "quote".
        isQuotationCheckout() {
            if (!this.quotationEnabled) return false;
            if (this.quoteOnlyMode) return true;
            return this.checkoutIntent === 'quote';
        },
        /**
         * Precios visibles en carrito:
         * - Compra: siempre sí
         * - Cotizar y vender: siempre sí (no se pueden ocultar)
         * - Solo cotizar: según quotation_show_prices
         */
        showCartPrices() {
            if (!this.isQuotationCheckout || this.isHybridQuotationMode) {
                return true;
            }
            return !!this.quotationShowPrices;
        },
        displayedQuotationSuccessMessage() {
            return (this.quotationResult && this.quotationResult.success_message)
                || this.quotationSuccessMessage
                || 'Registramos tu solicitud. Nuestro equipo la revisará a la brevedad.';
        },
        quotationValidityLabel() {
            const days = Number(this.quotationValidityDays) || 7;
            return days === 1 ? '1 día' : `${days} días`;
        },
        quotationContactSummary() {
            if (this.isLoggedIn) {
                const name = (this.user && this.user.name) || this.quotationForm.contact_name || '';
                return name ? `${name} · Cuenta conectada` : 'Cuenta conectada';
            }
            const parts = [];
            if (this.quotationForm.contact_name) parts.push(this.quotationForm.contact_name);
            if (this.quotationForm.telephone) parts.push(this.quotationForm.telephone);
            if (this.quotationForm.email) parts.push(this.quotationForm.email);
            return parts.join(' · ');
        },
    },
    watch: {
        'form_contact.telephone'(value) {
            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            const phone = (value || '').trim();
            if (phone && phone !== (this.guest_form.telephone || '').trim()) {
                this.guest_form.telephone = phone;
                this.syncGuestFormToDocument();
                this.saveGuestFormDraft();
            }
        },
        guest_form: {
            deep: true,
            handler() {
                if (!this.showGuestForm || this.isLoggedIn) {
                    return;
                }

                this.syncGuestFormToDocument();
                this.saveGuestFormDraft();
            },
        },
        records: {
            deep: true,
            handler() {
                clearTimeout(this.campaignValidationTimer);
                this.campaignValidationTimer = setTimeout(() => this.validateDiscountCampaigns(), 350);
            },
        },
        // El teléfono del comprador se hereda de los datos ya ingresados si aquí está vacío
        defaultContactPhone(value) {
            const phone = (value || '').trim();
            if (phone && !(this.form_contact.telephone || '').trim()) {
                this.form_contact.telephone = phone;
            }
        },
        'form_contact.telephone'(value) {
            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            const phone = (value || '').trim();
            if (phone && phone !== (this.guest_form.telephone || '').trim()) {
                this.invalidateGuestAutoAddressIfNeeded();
                this.guestReturningAddressNotice = false;
                this.guest_form.telephone = phone;
                this.syncGuestFormToDocument();
                this.saveGuestFormDraft();
                this.scheduleGuestTripleVerify();
            }
        },
        guest_form: {
            deep: true,
            handler() {
                if (!this.showGuestForm || this.isLoggedIn) {
                    return;
                }

                this.syncGuestFormToDocument();
                this.saveGuestFormDraft();
            },
        },
        'guest_form.identity_document_type_id'() {
            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            this.invalidateGuestAutoAddressIfNeeded();
            this.guestDocumentStatus = null;
            this.guestExistingCustomer = false;
            this.guestReturningAddressNotice = false;
            this.applyGuestDocumentDefaults();
            this.scheduleGuestDocumentVerify();
            this.scheduleGuestTripleVerify();
        },
        'guest_form.number'(value) {
            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            this.invalidateGuestAutoAddressIfNeeded();
            this.guestReturningAddressNotice = false;
            this.applyGuestDocumentDefaults();
            this.scheduleGuestDocumentVerify(value);
            this.scheduleGuestTripleVerify();
        },
        'guest_form.email'() {
            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            this.invalidateGuestAutoAddressIfNeeded();
            this.guestReturningAddressNotice = false;
            this.scheduleGuestTripleVerify();
        },
        'guest_form.telephone'() {
            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            this.invalidateGuestAutoAddressIfNeeded();
            this.guestReturningAddressNotice = false;
            this.scheduleGuestTripleVerify();
        },
        'addressModal.address': function(newValue) {
        },
        checkoutIntent(val) {
            if (val === 'quote') {
                this.preloadQuotationContact();
            }
        },
        selectedPaymentMethod(val, oldVal) {
            // Mostrar u ocultar el widget de PayPal que está fuera del scope de Vue
            const el = document.getElementById('paypal-widget-container');
            if (el) el.style.display = (val === 'paypal') ? 'block' : 'none';
        },
        isPickupMode(val) {
            // Si el método Pago contra entrega solo aplica para recojo y se cambia a delivery,
            // deseleccionar el método si estaba activo
            if (!val && this.cashPaymentPickupOnly && this.selectedPaymentMethod === 'cash') {
                this.selectedPaymentMethod = null;
            }
        }
    },
    async mounted() {
        await this.changeExchangeRate(moment().format("YYYY-MM-DD"))

        let exchange_rate_sale = this.exchange_rate_sale
        let contex = this

        $(".input_quantity").change(function (e) {
            let value = parseFloat($(this).val())
            let id = $(this).data('product')
            let row = contex.records.find(x => x.id == id)

            if(row.currency_type_id === 'USD') {
                row.sub_total = ((parseFloat(row.sale_unit_price) * value) * exchange_rate_sale).toFixed(2)
            } else {
                row.sub_total = (parseFloat(row.sale_unit_price) * value).toFixed(2)
            }

            row.cantidad = value
            contex.calculateSummary()
        })

        this.records.forEach(function (item) {
            if(item.currency_type_id === 'USD') {
                item.sub_total = (parseFloat(item.sub_total) * exchange_rate_sale).toFixed(2)
                item.exchange_rate_sale = exchange_rate_sale
            }
            item.sale_unit_price = parseFloat(item.sale_unit_price).toFixed(2)
        })

        this.calculateSummary()
        await this.validateDiscountCampaigns()
        this.campaignExpirationInterval = setInterval(() => this.validateDiscountCampaigns(), 30000)

        this.userAddresses = this.normalizeAddressList(this.userAddresses);
        if (this.userDefaultAddress) {
            this.userDefaultAddress = this.normalizeAddressRecord(this.userDefaultAddress);
        }

        // Cargar ubicaciones y autocompletar si el usuario tiene una dirección guardada
        this.fetchLocations().then(() => {
            this.loadDefaultAddress();
        });

        if (this.enableMp) {
            this.preloadMpResources();
        }
        // Modal ya abierto desde boot síncrono; no esperar nextTick.
        dismissCheckoutIntentBootOverlay();
        this.bindCheckoutIntentBootHandler();
        this.flushPendingCheckoutIntentChoice();
    },
    created() {
        let array = localStorage.getItem('products_cart');
        array = JSON.parse(array)
        if (array) {
            this.records = array.map(function (item) {
                let obj = item
                obj.cantidad = item.quantity ? parseInt(item.quantity) : 1
                obj.sub_total = (parseFloat(item.sale_unit_price) * obj.cantidad).toFixed(2)
                obj.exchange_rate_sale = ''
                // Compatibilidad: items agregados desde el catálogo guardan el símbolo
                // en currency_type.symbol; otros no lo traen. Normalizamos a un campo plano.
                obj.currency_type_symbol = item.currency_type_symbol
                    || (item.currency_type && item.currency_type.symbol)
                    || 'Bs.'
                return obj
            })
        }
        this.initForm();
        this.restoreGuestCheckoutState();
        // Si el boot pidió modal, mantener overflow bloqueado desde el primer tick de Vue
        if (this.checkoutIntentModalVisible) {
            document.body.style.overflow = 'hidden';
        }
        dismissCheckoutIntentBootOverlay();
        this.bindCheckoutIntentBootHandler();
        this.flushPendingCheckoutIntentChoice();
        this.restoreGuestCheckoutState();
    },
    beforeDestroy() {
        clearInterval(this.campaignExpirationInterval)
        clearTimeout(this.campaignValidationTimer)
    },
    methods: {
        restoreGuestCheckoutState() {
            if (this.isLoggedIn) {
                return;
            }

            const accepted = sessionStorage.getItem('guest_checkout_accepted') === 'true';
            if (!accepted) {
                return;
            }

            this.guestCheckoutAccepted = true;
            this.showGuestForm = true;
            this.initGuestFormStructure();
            this.loadGuestFormDraft();
        },
        initGuestFormStructure() {
            this.form_document = {
                acciones: {
                    enviar_email: true,
                    formato_pdf: 'a4',
                },
                serie_documento: '',
                numero_documento: '#',
                fecha_de_emision: moment().format('YYYY-MM-DD'),
                hora_de_emision: moment().format('HH:mm:ss'),
                codigo_tipo_operacion: '0101',
                codigo_tipo_documento: '80',
                codigo_tipo_moneda: 'VES',
                fecha_de_vencimiento: moment().format('YYYY-MM-DD'),
                datos_del_cliente_o_receptor: {
                    codigo_tipo_documento_identidad: '0',
                    numero_documento: '0',
                    apellidos_y_nombres_o_razon_social: '',
                    codigo_pais: 'VE',
                    ubigeo: '000619',
                    direccion: '',
                    correo_electronico: '',
                    telefono: '',
                },
                totales: {},
                items: [],
            };

            this.typeDocuments = '0';
            this.numberDocument = '0';
            this.typeDocumentList = this.getIdentityDocumentTypes(['0', '1', '6']);
            this.optionDocument();
        },
        normalizeGuestContactFields() {
            const contactPhone = (this.form_contact.telephone || '').trim();
            const guestPhone = (this.guest_form.telephone || '').trim();

            if (guestPhone) {
                this.form_contact.telephone = guestPhone;
            } else if (contactPhone) {
                this.guest_form.telephone = contactPhone;
            }
        },
        ensureGuestFormDocument() {
            if (!this.guestCheckoutAccepted || !this.showGuestForm || this.isLoggedIn) {
                return;
            }

            if (!this.form_document || !this.form_document.datos_del_cliente_o_receptor) {
                this.initGuestFormStructure();
            }

            this.normalizeGuestContactFields();
            this.syncGuestFormToDocument();
        },
        syncGuestFormToDocument() {
            if (!this.form_document || !this.form_document.datos_del_cliente_o_receptor) {
                if (this.showGuestForm && !this.isLoggedIn) {
                    this.initGuestFormStructure();
                } else {
                    return;
                }
            }

            this.normalizeGuestContactFields();

            const doc = this.form_document.datos_del_cliente_o_receptor;
            doc.correo_electronico = (this.guest_form.email || '').trim();
            doc.telefono = (this.guest_form.telephone || this.form_contact.telephone || '').trim();
            doc.apellidos_y_nombres_o_razon_social = (this.guest_form.name || '').trim();
            doc.numero_documento = (this.guest_form.number || '').trim();
            doc.codigo_tipo_documento_identidad = this.guest_form.identity_document_type_id || '0';
            doc.identity_document_type_id = this.guest_form.identity_document_type_id || '0';
            doc.direccion = this.form_contact.address || '';

            this.numberDocument = doc.numero_documento;
            this.typeDocuments = doc.codigo_tipo_documento_identidad;

            if (this.form_contact.telephone !== doc.telefono) {
                this.form_contact.telephone = doc.telefono;
            }
        },
        saveGuestFormDraft() {
            if (!this.showGuestForm) {
                return;
            }

            sessionStorage.setItem('guest_contact_draft', JSON.stringify(this.guest_form));
        },
        loadGuestFormDraft() {
            const raw = sessionStorage.getItem('guest_contact_draft');
            if (!raw) {
                return;
            }

            try {
                const draft = JSON.parse(raw);
                this.guest_form = Object.assign({}, this.guest_form, draft);
                if (this.guest_form.telephone && !this.form_contact.telephone) {
                    this.form_contact.telephone = this.guest_form.telephone;
                }
                this.syncGuestFormToDocument();
            } catch (error) {
                console.warn('No se pudo restaurar el borrador de invitado', error);
            }
        },
        getGuestFormValidationErrors() {
            this.ensureGuestFormDocument();

            const errors = [];
            const email = (this.guest_form.email || '').trim();
            const phone = (this.guest_form.telephone || this.form_contact.telephone || '').trim();
            const name = (this.guest_form.name || '').trim();
            const number = (this.guest_form.number || '').trim();
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const phoneDigits = phone.replace(/\D/g, '');

            if (!email) {
                errors.push('correo electrónico');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('correo electrónico válido');
            }

            if (!phoneDigits) {
                errors.push('teléfono');
            } else if (phoneDigits.length < 7) {
                errors.push('teléfono válido');
            }

            if (!name) {
                errors.push('nombre o razón social');
            }

            if (!number) {
                errors.push('número de documento');
            } else if (docType === '1' && number.replace(/\D/g, '').length !== 8) {
                errors.push('Cédula de 8 dígitos');
            } else if (docType === '6' && number.replace(/\D/g, '').length !== 11) {
                errors.push('RIF de 11 dígitos');
            }

            return errors;
        },
        isGuestFormValid() {
            return this.getGuestFormValidationErrors().length === 0;
        },
        validateGuestFormForPayment() {
            this.ensureGuestFormDocument();
            const errors = this.getGuestFormValidationErrors();

            if (errors.length > 0) {
                return {
                    valid: false,
                    message: 'Completa: ' + errors.join(', ') + '.',
                };
            }

            if (!this.isPickupMode && !(this.form_contact.address || '').trim()) {
                return {
                    valid: false,
                    message: 'Agrega una dirección de entrega antes de pagar.',
                };
            }

            if (this.isPickupMode && !this.selectedPickupBranch) {
                return {
                    valid: false,
                    message: 'Selecciona una sucursal de recojo antes de pagar.',
                };
            }

            return { valid: true, message: '' };
        },
        scrollToGuestForm() {
            this.$nextTick(() => {
                const el = document.getElementById('guestContactCollapse');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        scrollToPaymentSection() {
            this.$nextTick(() => {
                const el = document.getElementById('paymentCollapse');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        dispatchPayment(method) {
            if (method === 'culqi') {
                if (typeof execCulqi === 'function') {
                    execCulqi();
                }
            } else if (method === 'izipay') {
                this.execIzipay();
            } else if (method === 'mp') {
                this.execMp();
            } else if (['cash', 'yape', 'transfer'].includes(method)) {
                this.paymentCash();
            }
        },
        runPayment(method) {
            if (this.records.length < 1) {
                return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de continuar.', 'warning');
            }

            if (!this.acceptedTerms) {
                return this.showSwalMessage('Términos y condiciones', 'Debes aceptar los términos y condiciones.', 'warning');
            }

            if (!method) {
                return this.showSwalMessage('Método de pago', 'Selecciona un método de pago para continuar.', 'warning');
            }

            this.selectedPaymentMethod = method;
            this.refreshSetDataCustomer();

            if (this.isGuestCheckoutActive) {
                const validation = this.validateGuestFormForPayment();
                if (!validation.valid) {
                    this.scrollToGuestForm();
                    return this.showSwalMessage('Datos incompletos', validation.message, 'warning');
                }
            } else if (!this.validateCheckoutBeforePayment()) {
                return;
            }

            this.dispatchPayment(method);
        },
        handleCheckoutClick() {
            if (this.records.length < 1) {
                return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de continuar.', 'warning');
            }

            if (!this.acceptedTerms) {
                return this.showSwalMessage('Términos y condiciones', 'Debes aceptar los términos y condiciones.', 'warning');
            }

            if (this.isLoggedIn) {
                return this.executePayment();
            }

            if (!this.guestCheckoutAccepted || !this.showGuestForm) {
                return this.openGuestIdentityModal();
            }

            const validation = this.validateGuestFormForPayment();
            if (!validation.valid) {
                this.scrollToGuestForm();
                return this.showSwalMessage('Datos incompletos', validation.message, 'warning');
            }

            this.scrollToPaymentSection();
            if (!this.selectedPaymentMethod) {
                return this.showSwalMessage(
                    'Método de pago',
                    'Selecciona un método de pago y confirma desde el botón correspondiente.',
                    'info'
                );
            }
        },
        openGuestIdentityModal() {
            this.guestWarningChecked = false;
            $('#guestIdentityModal').modal('show');
        },
        closeGuestIdentityModal() {
            $('#guestIdentityModal').modal('hide');
        },
        chooseGuestCheckout() {
            this.closeGuestIdentityModal();
            this.$nextTick(() => {
                $('#guestWarningModal').modal('show');
            });
        },
        closeGuestWarningModal() {
            this.guestWarningChecked = false;
            $('#guestWarningModal').modal('hide');
        },
        backToGuestIdentityModal() {
            this.closeGuestWarningModal();
            this.$nextTick(() => {
                this.openGuestIdentityModal();
            });
        },
        confirmGuestCheckout() {
            if (!this.guestWarningChecked) {
                return;
            }

            sessionStorage.setItem('guest_checkout_accepted', 'true');
            this.guestCheckoutAccepted = true;
            this.showGuestForm = true;
            this.initGuestFormStructure();

            if (this.form_contact.telephone) {
                this.guest_form.telephone = this.form_contact.telephone;
            }

            this.ensureGuestFormDocument();
            this.closeGuestWarningModal();
            this.scrollToGuestForm();
        },
        openLoginRegisterModal() {
            this.closeGuestIdentityModal();
            this.$nextTick(() => {
                if (typeof window.jQuery !== 'undefined') {
                    $('#login_register_modal').modal('show');
                }
            });
        },
        restoreGuestCheckoutState() {
            if (this.isLoggedIn) {
                return;
            }

            const accepted = sessionStorage.getItem('guest_checkout_accepted') === 'true';
            if (!accepted) {
                return;
            }

            this.guestCheckoutAccepted = true;
            this.showGuestForm = true;
            this.initGuestFormStructure();
            this.loadGuestFormDraft();
            this.loadGuestAddressesDraft();
            if (this.guest_form.number) {
                this.scheduleGuestDocumentVerify(this.guest_form.number);
            }
            if (this.isGuestTripleMatchInputReady()) {
                this.scheduleGuestTripleVerify();
            }
        },
        initGuestFormStructure() {
            this.form_document = {
                acciones: {
                    enviar_email: true,
                    formato_pdf: 'a4',
                },
                serie_documento: '',
                numero_documento: '#',
                fecha_de_emision: moment().format('YYYY-MM-DD'),
                hora_de_emision: moment().format('HH:mm:ss'),
                codigo_tipo_operacion: '0101',
                codigo_tipo_documento: '80',
                codigo_tipo_moneda: 'VES',
                fecha_de_vencimiento: moment().format('YYYY-MM-DD'),
                datos_del_cliente_o_receptor: {
                    codigo_tipo_documento_identidad: '0',
                    numero_documento: '0',
                    apellidos_y_nombres_o_razon_social: '',
                    codigo_pais: 'VE',
                    ubigeo: '000619',
                    direccion: '',
                    correo_electronico: '',
                    telefono: '',
                },
                totales: {},
                items: [],
            };

            this.typeDocuments = '0';
            this.numberDocument = '0';
            this.typeDocumentList = this.getIdentityDocumentTypes(['0', '1', '6']);
            this.optionDocument();
            this.applyGuestDocumentDefaults();
        },
        normalizeGuestContactFields() {
            const contactPhone = (this.form_contact.telephone || '').trim();
            const guestPhone = (this.guest_form.telephone || '').trim();

            if (guestPhone) {
                this.form_contact.telephone = guestPhone;
            } else if (contactPhone) {
                this.guest_form.telephone = contactPhone;
            }
        },
        ensureGuestFormDocument() {
            if (!this.guestCheckoutAccepted || this.isLoggedIn) {
                return;
            }

            if (!this.form_document || !this.form_document.datos_del_cliente_o_receptor) {
                this.initGuestFormStructure();
            }

            this.normalizeGuestContactFields();
            this.syncGuestFormToDocument();
        },
        syncGuestFormToDocument() {
            if (!this.form_document || !this.form_document.datos_del_cliente_o_receptor) {
                if (!this.isLoggedIn && this.guestCheckoutAccepted) {
                    this.initGuestFormStructure();
                } else {
                    return;
                }
            }

            this.normalizeGuestContactFields();

            const doc = this.form_document.datos_del_cliente_o_receptor;
            doc.correo_electronico = (this.guest_form.email || '').trim();
            doc.telefono = (this.guest_form.telephone || this.form_contact.telephone || '').replace(/\D/g, '');
            doc.apellidos_y_nombres_o_razon_social = (this.guest_form.name || '').trim();
            doc.numero_documento = (this.guest_form.number || '').replace(/\D/g, '') || '0';
            doc.codigo_tipo_documento_identidad = this.guest_form.identity_document_type_id || '0';
            doc.identity_document_type_id = this.guest_form.identity_document_type_id || '0';
            doc.direccion = this.resolveCustomerAddressForPayment();

            this.numberDocument = doc.numero_documento;
            this.typeDocuments = doc.codigo_tipo_documento_identidad;

            if (this.form_contact.telephone !== doc.telefono) {
                this.form_contact.telephone = doc.telefono;
            }

            this.applyGuestDocumentDefaults();
        },
        applyGuestDocumentDefaults() {
            if (this.isLoggedIn || !this.form_document) {
                return;
            }

            if (!this.guestCheckoutAccepted && !this.showGuestForm) {
                return;
            }

            const docType = String(this.guest_form.identity_document_type_id || '0');
            const number = (this.guest_form.number || '').replace(/\D/g, '');

            if (!this.enable_electronic_documents) {
                this.form_document.codigo_tipo_documento = '80';
                this.typeDocuments = docType === '0' ? '0' : docType;
                this.numberDocument = number || '0';
                if (this.form_document.datos_del_cliente_o_receptor) {
                    this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = this.typeDocuments;
                    this.form_document.datos_del_cliente_o_receptor.numero_documento = this.numberDocument;
                    this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.typeDocuments;
                }
                return;
            }

            if (docType === '1') {
                // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                this.form_document.codigo_tipo_documento = '80';
                // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                this.typeDocuments = '1';
            } else if (docType === '6') {
                this.form_document.codigo_tipo_documento = '01';
                this.typeDocuments = '6';
            } else {
                this.form_document.codigo_tipo_documento = '80';
                this.typeDocuments = docType;
            }

            this.numberDocument = number || '0';
            if (this.form_document.datos_del_cliente_o_receptor) {
                this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = this.typeDocuments;
                this.form_document.datos_del_cliente_o_receptor.numero_documento = this.numberDocument;
                this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.typeDocuments;
            }
        },
        enforceGuestHighAmountIdentityRule() {
            // La validación de montos > Bs. 700 se aplica al enviar; no se restringe la selección en el selector.
        },
        normalizeGuestEmail(email) {
            return String(email || '').trim().toLowerCase();
        },
        normalizeGuestPhone(phone) {
            return String(phone || '').replace(/\D/g, '');
        },
        buildGuestTripleMatchKey() {
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const number = this.normalizeGuestPhone(this.guest_form.number || '');
            const email = this.normalizeGuestEmail(this.guest_form.email || '');
            const phone = this.normalizeGuestPhone(
                this.guest_form.telephone || this.form_contact.telephone || ''
            );

            return [docType, number, email, phone].join('|');
        },
        isGuestTripleMatchInputReady() {
            const docType = String(this.guest_form.identity_document_type_id || '0');
            if (docType !== '1' && docType !== '6') {
                return false;
            }

            const number = this.normalizeGuestPhone(this.guest_form.number || '');
            const expectedLength = docType === '6' ? 11 : 8;
            if (number.length !== expectedLength) {
                return false;
            }

            const email = this.normalizeGuestEmail(this.guest_form.email || '');
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return false;
            }

            const phone = this.normalizeGuestPhone(
                this.guest_form.telephone || this.form_contact.telephone || ''
            );

            return phone.length >= 7;
        },
        invalidateGuestAutoAddressIfNeeded() {
            if (!this.guestAutoAddressSnapshot || !this.guestMatchedTripleKey) {
                return;
            }

            if (this.buildGuestTripleMatchKey() !== this.guestMatchedTripleKey) {
                this.clearGuestAutoFilledAddress();
            }
        },
        clearGuestAutoFilledAddress() {
            if (!this.guestAutoAddressSnapshot) {
                this.guestReturningAddressNotice = false;
                return;
            }

            const snapshot = this.guestAutoAddressSnapshot;
            const currentAddress = (this.form_contact.address || '').trim();
            const snapshotAddress = (snapshot.address || '').trim();

            if (currentAddress === snapshotAddress) {
                this.form_contact.address = '';
            }

            if ((this.addressModal.address || '').trim() === snapshotAddress) {
                this.addressModal.address = '';
            }

            if (
                String(this.selectedDepartment || '') === String(snapshot.department_id || '') &&
                String(this.selectedProvince || '') === String(snapshot.province_id || '') &&
                String(this.selectedDistrict || '') === String(snapshot.district_id || '')
            ) {
                this.selectedDepartment = '';
                this.selectedProvince = '';
                this.selectedDistrict = '';
                this.provinces = [];
                this.districts = [];
                this.deliveryZone = null;
                this.availableDeliveryZones = [];
                this.deliveryMessage = '';
                this.calculateSummary();
            }

            this.guestAutoAddressSnapshot = null;
            this.guestMatchedTripleKey = null;
            this.guestReturningAddressNotice = false;
        },
        scheduleGuestTripleVerify() {
            clearTimeout(this.guestTripleVerifyTimeout);

            if (!this.showGuestForm || this.isLoggedIn) {
                return;
            }

            this.invalidateGuestAutoAddressIfNeeded();

            if (!this.isGuestTripleMatchInputReady()) {
                return;
            }

            this.guestTripleVerifyTimeout = setTimeout(() => {
                this.verifyGuestTripleMatch();
            }, 400);
        },
        async verifyGuestTripleMatch() {
            if (!this.isGuestTripleMatchInputReady()) {
                return;
            }

            const requestKey = this.buildGuestTripleMatchKey();
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const cleanNumber = this.normalizeGuestPhone(this.guest_form.number || '');
            const email = this.normalizeGuestEmail(this.guest_form.email || '');
            const phone = this.normalizeGuestPhone(
                this.guest_form.telephone || this.form_contact.telephone || ''
            );

            this.guestTripleLookupLoading = true;

            try {
                const searchUrl = window.__routes?.search_document || '/ecommerce/search-document';
                const response = await axios.get(`${searchUrl}/${cleanNumber}`, {
                    params: {
                        checkout: 1,
                        email,
                        telephone: phone,
                        identity_document_type_id: docType,
                    },
                });
                const data = response.data || {};

                if (requestKey !== this.buildGuestTripleMatchKey()) {
                    return;
                }

                if (!data.success || !data.triple_match) {
                    this.clearGuestAutoFilledAddress();
                    return;
                }

                if (data.is_registered_customer || data.from_database) {
                    this.guestExistingCustomer = true;
                    this.guestDocumentStatus = {
                        type: 'info',
                        message: data.message || 'Encontramos tus datos registrados. Puedes actualizarlos si lo necesitas para esta compra.',
                    };
                    this.clearGuestAutoFilledAddress();
                    return;
                }

                if (data.address_loaded && data.address) {
                    this.applyGuestAddressFromLookup(data, { trackAutoFill: true });
                    this.guestReturningAddressNotice = !!data.is_returning_guest;
                } else {
                    this.clearGuestAutoFilledAddress();
                }
            } catch (error) {
                if (requestKey === this.buildGuestTripleMatchKey()) {
                    this.clearGuestAutoFilledAddress();
                }
            } finally {
                this.guestTripleLookupLoading = false;
            }
        },
        scheduleGuestDocumentVerify(value) {
            clearTimeout(this.guestDocumentVerifyTimeout);

            const docType = String(this.guest_form.identity_document_type_id || '0');
            if (docType !== '1' && docType !== '6') {
                this.guestDocumentStatus = null;
                this.guestExistingCustomer = false;
                this.clearGuestAutoFilledAddress();
                return;
            }

            // Cédula: sin consulta ni autorrelleno de nombres; el usuario lo ingresa manualmente.
            if (docType === '1') {
                return;
            }

            const number = String(value !== undefined ? value : this.guest_form.number || '').replace(/\D/g, '');
            const expectedLength = docType === '6' ? 11 : 8;

            if (number.length !== expectedLength) {
                this.guestDocumentStatus = null;
                this.guestExistingCustomer = false;
                this.clearGuestAutoFilledAddress();
                return;
            }

            this.guestDocumentVerifyTimeout = setTimeout(() => {
                this.verifyGuestDocument(number);
            }, 400);
        },
        async verifyGuestDocument(number) {
            const docType = String(this.guest_form.identity_document_type_id || '0');
            if (docType !== '1' && docType !== '6') {
                return;
            }

            // Cédula: sin consulta ni autorrelleno de nombres; el usuario lo ingresa manualmente.
            if (docType === '1') {
                return;
            }

            const cleanNumber = String(number || this.guest_form.number || '').replace(/\D/g, '');
            const expectedLength = docType === '6' ? 11 : 8;

            if (cleanNumber.length !== expectedLength) {
                return;
            }

            this.guestDocumentLookupLoading = true;
            this.guestDocumentStatus = { type: 'loading', message: 'Consultando documento...' };

            try {
                const searchUrl = window.__routes?.search_document || '/ecommerce/search-document';
                const response = await axios.get(`${searchUrl}/${cleanNumber}`, {
                    params: { checkout: 1 },
                });
                const data = response.data || {};

                if (data.success) {
                    if (data.name) {
                        this.guest_form.name = data.name;
                    }

                    this.guestExistingCustomer = !!(data.from_database || data.is_registered_customer);

                    if (data.from_database || data.is_registered_customer) {
                        this.guestDocumentStatus = {
                            type: 'info',
                            message: data.message || 'Encontramos tus datos registrados. Puedes actualizarlos si lo necesitas para esta compra.',
                        };
                    } else {
                        this.guestDocumentStatus = null;
                    }

                    if (this.isGuestTripleMatchInputReady()) {
                        this.scheduleGuestTripleVerify();
                    }
                } else {
                    this.guestExistingCustomer = false;
                    this.guestDocumentStatus = {
                        type: 'warning',
                        message: data.message || 'No se encontraron datos. Completa manualmente.',
                    };
                }
            } catch (error) {
                this.guestExistingCustomer = false;
                this.guestDocumentStatus = {
                    type: 'warning',
                    message: 'No se pudo consultar el documento. Completa tus datos manualmente.',
                };
            } finally {
                this.guestDocumentLookupLoading = false;
                this.applyGuestDocumentDefaults();
                this.syncGuestFormToDocument();
            }
        },
        applyGuestAddressFromLookup(data, options = {}) {
            const { trackAutoFill = false } = options;

            if (!data || !data.address) {
                return;
            }

            this.form_contact.address = data.address;
            this.addressModal.address = data.address;

            if (trackAutoFill) {
                this.guestAutoAddressSnapshot = {
                    address: data.address,
                    department_id: data.department_id || '',
                    province_id: data.province_id || '',
                    district_id: data.district_id || '',
                };
                this.guestMatchedTripleKey = this.buildGuestTripleMatchKey();
            }

            if (!data.department_id) {
                return;
            }

            const applyUbigeo = () => {
                const dept = this.departments.find(d => d.value === data.department_id);
                if (!dept) {
                    return;
                }

                this.selectedDepartment = dept.value;
                this.provinces = dept.children || [];
                this.selectedProvince = '';
                this.districts = [];
                this.selectedDistrict = '';

                if (!data.province_id) {
                    return;
                }

                const prov = this.provinces.find(p => p.value === data.province_id);
                if (!prov) {
                    return;
                }

                this.selectedProvince = prov.value;
                this.districts = prov.children || [];

                if (data.district_id) {
                    const dist = this.districts.find(d => d.value === data.district_id);
                    if (dist) {
                        this.selectedDistrict = dist.value;
                        this.checkDeliveryZone();
                    }
                }
            };

            if (this.departments.length > 0) {
                applyUbigeo();
            } else {
                this.fetchLocations().then(applyUbigeo);
            }

            this.syncGuestAddressesFromFormContact();
        },
        isGuestAddressCheckout() {
            return !this.isLoggedIn;
        },
        saveGuestAddressesDraft() {
            if (!this.isGuestAddressCheckout()) {
                return;
            }

            try {
                sessionStorage.setItem(
                    this.guestAddressesStorageKey,
                    JSON.stringify(this.userAddresses)
                );
            } catch (error) {
                console.warn('No se pudo guardar el borrador de direcciones de invitado', error);
            }
        },
        loadGuestAddressesDraft() {
            if (!this.isGuestAddressCheckout()) {
                return;
            }

            const raw = sessionStorage.getItem(this.guestAddressesStorageKey);
            if (!raw) {
                return;
            }

            try {
                const addresses = JSON.parse(raw);
                this.userAddresses = this.normalizeAddressList(addresses);

                if (this.userAddresses.length === 0) {
                    return;
                }

                const currentAddress = (this.form_contact.address || '').trim();
                const active = currentAddress
                    ? this.userAddresses.find(item => (item.full_address || item.address || '').trim() === currentAddress)
                    : null;

                const selected = active || this.userAddresses[0];
                this.selectedAddressId = selected.id;
                this.userDefaultAddress = this.normalizeAddressRecord(selected);

                if (!currentAddress) {
                    this.form_contact.address = selected.full_address || selected.address || '';
                }
            } catch (error) {
                console.warn('No se pudo restaurar el borrador de direcciones de invitado', error);
            }
        },
        syncGuestAddressesFromFormContact() {
            if (!this.isGuestAddressCheckout()) {
                return;
            }

            const fullAddress = (this.form_contact.address || '').trim();
            if (!fullAddress) {
                return;
            }

            const parsed = this.splitAddressReference(fullAddress);
            const existing = this.userAddresses.find(item => {
                const itemFull = (item.full_address || this.composeFullAddress(
                    this.getAddressStreet(item),
                    this.getAddressReference(item)
                ) || item.address || '').trim();
                return itemFull === fullAddress;
            });

            const record = this.normalizeAddressRecord({
                id: existing ? existing.id : `guest-local-${Date.now()}`,
                address: parsed.street,
                reference: parsed.reference,
                full_address: fullAddress,
                latitude: this.addressModal.latitude,
                longitude: this.addressModal.longitude,
                department_id: this.selectedDepartment || null,
                province_id: this.selectedProvince || null,
                district_id: this.selectedDistrict || null,
                telephone: this.form_contact.telephone || null,
            });

            if (existing) {
                const index = this.userAddresses.findIndex(item => item.id === existing.id);
                if (index >= 0) {
                    this.userAddresses.splice(index, 1, record);
                }
            } else {
                this.userAddresses.push(record);
            }

            this.userDefaultAddress = record;
            this.selectedAddressId = record.id;
            this.saveGuestAddressesDraft();
        },
        saveGuestShippingAddressLocally(onSuccess, forceCreate) {
            const parsedForm = this.splitAddressReference(this.form_contact.address);
            const street = ((this.addressModal.address || parsedForm.street || this.form_contact.address) || '').trim();
            const reference = ((this.addressModal.reference || parsedForm.reference) || '').trim();

            if (!street) {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                return;
            }

            const isCreatingNew = forceCreate === true || this.addressModalMode === 'add';
            const fullAddress = this.composeFullAddress(street, reference);
            const payload = {
                address: street,
                reference,
                full_address: fullAddress,
                latitude: this.addressModal.latitude,
                longitude: this.addressModal.longitude,
                department_id: this.selectedDepartment || null,
                province_id: this.selectedProvince || null,
                district_id: this.selectedDistrict || null,
                telephone: this.form_contact.telephone || null,
            };

            if (isCreatingNew) {
                const record = this.normalizeAddressRecord(Object.assign({
                    id: `guest-local-${Date.now()}`,
                }, payload));
                this.userAddresses = [...this.userAddresses, record];
                this.selectedAddressId = record.id;
                this.userDefaultAddress = record;
                this.editingAddressId = null;
                this.addressModalMode = 'add';
            } else if (this.editingAddressId) {
                const index = this.userAddresses.findIndex(item => item.id === this.editingAddressId);
                if (index >= 0) {
                    const updated = this.normalizeAddressRecord(Object.assign(
                        {},
                        this.userAddresses[index],
                        payload,
                        { id: this.editingAddressId }
                    ));
                    this.userAddresses.splice(index, 1, updated);
                    this.userDefaultAddress = updated;
                    this.selectedAddressId = updated.id;
                }
            } else {
                this.syncGuestAddressesFromFormContact();
            }

            this.form_contact.address = fullAddress;
            this.saveGuestAddressesDraft();

            if (typeof onSuccess === 'function') {
                onSuccess();
            }
        },
        saveGuestFormDraft() {
            if (!this.showGuestForm) {
                return;
            }

            sessionStorage.setItem('guest_contact_draft', JSON.stringify(this.guest_form));
        },
        loadGuestFormDraft() {
            const raw = sessionStorage.getItem('guest_contact_draft');
            if (!raw) {
                return;
            }

            try {
                const draft = JSON.parse(raw);
                this.guest_form = Object.assign({}, this.guest_form, draft);
                if (this.guest_form.telephone && !this.form_contact.telephone) {
                    this.form_contact.telephone = this.guest_form.telephone;
                }
                this.syncGuestFormToDocument();
            } catch (error) {
                console.warn('No se pudo restaurar el borrador de invitado', error);
            }
        },
        getGuestFormValidationErrors() {
            this.ensureGuestFormDocument();

            const errors = [];
            const email = (this.guest_form.email || '').trim();
            const phone = (this.guest_form.telephone || this.form_contact.telephone || '').trim();
            const name = (this.guest_form.name || '').trim();
            const number = (this.guest_form.number || '').trim();
            const docType = String(this.guest_form.identity_document_type_id || '0');
            const phoneDigits = phone.replace(/\D/g, '');
            const cleanNumber = number.replace(/\D/g, '');

            if (!email) {
                errors.push('correo electrónico');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('correo electrónico válido');
            }

            if (!phoneDigits) {
                errors.push('teléfono');
            } else if (phoneDigits.length < 7) {
                errors.push('teléfono válido');
            }

            if (this.guestRequiresIdentityDocument) {
                if (!name) {
                    errors.push('nombre o razón social');
                }

                if (docType !== '1' && docType !== '6') {
                    errors.push('Cédula o RIF (obligatorio por monto superior a Bs. 700.00)');
                } else if (docType === '1' && cleanNumber.length !== 8) {
                    errors.push('Cédula de 8 dígitos');
                } else if (docType === '6' && cleanNumber.length !== 11) {
                    errors.push('RIF de 11 dígitos');
                }
            } else {
                if (!name) {
                    errors.push('nombre o razón social');
                }

                if (!number) {
                    errors.push('número de documento');
                } else if (docType === '1' && cleanNumber.length !== 8) {
                    errors.push('Cédula de 8 dígitos');
                } else if (docType === '6' && cleanNumber.length !== 11) {
                    errors.push('RIF de 11 dígitos');
                }
            }

            return errors;
        },
        isGuestFormValid() {
            return this.getGuestFormValidationErrors().length === 0;
        },
        validateGuestFormForPayment() {
            this.ensureGuestFormDocument();

            if (this.guestRequiresIdentityDocument) {
                const docType = String(this.guest_form.identity_document_type_id || '0');
                const name = (this.guest_form.name || '').trim();
                const cleanNumber = (this.guest_form.number || '').replace(/\D/g, '');

                if (!name || (docType !== '1' && docType !== '6')) {
                    return {
                        valid: false,
                        message: 'Por montos superiores a Bs. 700.00 debes ingresar tu nombre y un Cédula o RIF válido.',
                    };
                }

                if (docType === '1' && cleanNumber.length !== 8) {
                    return {
                        valid: false,
                        message: 'Por montos superiores a Bs. 700.00 debes ingresar un Cédula válido de 8 dígitos.',
                    };
                }

                if (docType === '6' && cleanNumber.length !== 11) {
                    return {
                        valid: false,
                        message: 'Por montos superiores a Bs. 700.00 debes ingresar un RIF válido de 11 dígitos.',
                    };
                }
            }

            const errors = this.getGuestFormValidationErrors();

            if (errors.length > 0) {
                return {
                    valid: false,
                    message: 'Completa: ' + errors.join(', ') + '.',
                };
            }

            if (!this.isPickupMode && !(this.form_contact.address || '').trim()) {
                return {
                    valid: false,
                    message: 'Agrega una dirección de entrega antes de pagar.',
                };
            }

            if (this.isPickupMode && !this.selectedPickupBranch) {
                return {
                    valid: false,
                    message: 'Selecciona una sucursal de recojo antes de pagar.',
                };
            }

            const receiverError = this.getDeliveryReceiverError();
            if (receiverError) {
                return { valid: false, message: receiverError };
            }

            return { valid: true, message: '' };
        },
        scrollToGuestForm() {
            this.scrollToContactSection();
        },
        scrollToContactSection() {
            this.$nextTick(() => {
                const el = document.getElementById('contactDataCollapse');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        scrollToPaymentSection() {
            this.$nextTick(() => {
                const el = document.getElementById('paymentCollapse');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        dispatchPayment(method) {
            if (method === 'culqi') {
                if (typeof execCulqi === 'function') {
                    execCulqi();
                }
            } else if (method === 'izipay') {
                this.execIzipay();
            } else if (method === 'mp') {
                this.execMp();
            } else if (['cash', 'yape', 'transfer'].includes(method)) {
                this.paymentCash();
            }
        },
        runPayment(method) {
            if (!this.allowPurchase) {
                return this.showSwalMessage(
                    'Compra no disponible',
                    'La tienda está en modo solo cotización. Puedes solicitar una cotización.',
                    'info'
                );
            }

            if (this.records.length < 1) {
                return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de continuar.', 'warning');
            }

            if (!this.acceptedTerms) {
                return this.showSwalMessage('Términos y condiciones', 'Debes aceptar los términos y condiciones.', 'warning');
            }

            if (!method) {
                return this.showSwalMessage('Método de pago', 'Selecciona un método de pago para continuar.', 'warning');
            }

            this.selectedPaymentMethod = method;
            this.refreshSetDataCustomer();

            if (this.isGuestCheckoutActive) {
                const validation = this.validateGuestFormForPayment();
                if (!validation.valid) {
                    this.scrollToGuestForm();
                    return this.showSwalMessage('Datos incompletos', validation.message, 'warning');
                }
            } else if (!this.validateCheckoutBeforePayment()) {
                return;
            }

            this.dispatchPayment(method);
        },
        handleCheckoutClick() {
            if (this.records.length < 1) {
                return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de continuar.', 'warning');
            }

            if (!this.acceptedTerms) {
                return this.showSwalMessage('Términos y condiciones', 'Debes aceptar los términos y condiciones.', 'warning');
            }

            if (this.isLoggedIn) {
                return this.executePayment();
            }

            if (!this.guestCheckoutAccepted) {
                this.scrollToContactSection();
                return this.startGuestCheckout();
            }

            const validation = this.validateGuestFormForPayment();
            if (!validation.valid) {
                this.scrollToGuestForm();
                return this.showSwalMessage('Datos incompletos', validation.message, 'warning');
            }

            this.scrollToPaymentSection();
            if (!this.selectedPaymentMethod) {
                return this.showSwalMessage(
                    'Método de pago',
                    'Selecciona un método de pago y confirma con el botón de pago del resumen.',
                    'info'
                );
            }
        },
        startGuestCheckout() {
            if (this.records.length < 1) {
                return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de continuar.', 'warning');
            }

            if (this.guestCheckoutAccepted) {
                const errors = this.getGuestFormValidationErrors();
                if (errors.length > 0) {
                    this.scrollToContactSection();
                    return this.showSwalMessage('Datos incompletos', 'Completa: ' + errors.join(', ') + '.', 'warning');
                }

                return this.scrollToDeliverySection();
            }

            sessionStorage.setItem('guest_checkout_accepted', 'true');
            this.guestCheckoutAccepted = true;
            this.showGuestForm = true;
            this.guestDocumentStatus = null;
            this.guestExistingCustomer = false;
            this.guestReturningAddressNotice = false;
            this.clearGuestAutoFilledAddress();
            this.initGuestFormStructure();
            this.loadGuestFormDraft();

            if (this.form_contact.telephone) {
                this.guest_form.telephone = this.form_contact.telephone;
            }

            this.loadGuestAddressesDraft();
            this.syncGuestAddressesFromFormContact();

            this.ensureGuestFormDocument();
            this.saveGuestFormDraft();
            this.$nextTick(() => {
                this.scrollToContactSection();
            });
        },
        scrollToDeliverySection() {
            this.$nextTick(() => {
                const el = document.getElementById('deliveryCollapse');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        openLoginRegisterModal() {
            this.$nextTick(() => {
                if (typeof window.jQuery !== 'undefined') {
                    $('#login_register_modal').modal('show');
                }
            });
        },
        openRegisterModal() {
            this.openLoginRegisterModal();
            this.$nextTick(() => {
                const container = document.getElementById('contenedor-form');
                if (container) {
                    container.classList.add('active');
                }
            });
        },
        extractAndSetUbigeoFromComponents(components) {
            if (!components) return;

            let department = '';
            let province   = '';
            let district   = '';

            components.forEach(component => {
                const types    = component.types || [];
                const longName = (component.long_name || '').toUpperCase();

                if (types.includes('administrative_area_level_1')) {
                    department = longName
                        .replace('PROVINCIA DE ', '')
                        .replace('DEPARTAMENTO DE ', '')
                        .replace(' REGION', '')
                        .trim();
                }
                if (types.includes('administrative_area_level_2')) {
                    province = longName
                        .replace('PROVINCIA DE ', '')
                        .trim();
                }
                if (types.includes('locality') ||
                    types.includes('sublocality_level_1') ||
                    types.includes('administrative_area_level_3')) {
                    if (!district) {
                        district = longName
                            .replace('DISTRITO DE ', '')
                            .trim();
                    }
                }
            });

            this.setDepartmentByName(department);
            this.setProvinceByName(province);
            this.setDistrictByName(district);
        },
        initAutocomplete() {
            console.log('Autocomplete listo con nueva API de Google Places');
        },

        async onAddressInputChange() {
            const query = this.addressModal.address;
            if (!query || query.length < 3) {
                this.addressSuggestions = [];
                return;
            }

            clearTimeout(this.addressSearchTimeout);
            this.addressSearchTimeout = setTimeout(async () => {
                try {
                    const { AutocompleteSuggestion } = await google.maps.importLibrary("places");

                    const request = {
                        input: query,
                        includedRegionCodes: ['pe'],
                        language: 'es'
                    };

                    const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions(request);

                    this.addressSuggestions = suggestions.map(s => {
                        const pred = s.placePrediction;
                        return {
                            placeId: pred.placeId,
                            mainText: pred.mainText?.toString() || pred.text?.toString() || '',
                            secondaryText: pred.secondaryText?.toString() || '',
                            fullText: pred.text?.toString() || ''
                        };
                    });

                    this.highlightedIndex = -1;
                } catch (error) {
                    console.error('Error obteniendo sugerencias:', error);
                    this.addressSuggestions = [];
                }
            }, 300);
        },

        async selectSuggestionFromList(suggestion) {
            this.addressModal.address = suggestion.fullText;
            this.addressSuggestions = [];
            this.highlightedIndex = -1;

            try {
                const { Place } = await google.maps.importLibrary("places");

                const place = new Place({
                    id: suggestion.placeId,
                    requestedLanguage: 'es'
                });

                await place.fetchFields({
                    fields: ['displayName', 'formattedAddress', 'location', 'addressComponents']
                });

                const loc = place.location;
                this.addressModal.latitude = loc.lat();
                this.addressModal.longitude = loc.lng();

                if (this.map && this.marker) {
                    this.marker.setPosition(loc);
                    this.map.setCenter(loc);
                    this.map.setZoom(17);
                    this.mapGeocodeEnabled = true;
                    this.lastGeocodedLat = null;
                    this.lastGeocodedLng = null;
                    this.onMarkerPositionChanged(true);
                }

                this.addressModal.address = place.formattedAddress || suggestion.fullText;

                const normalizedComponents = (place.addressComponents || []).map(c => ({
                    long_name: c.longText || c.long_name || '',
                    types: c.types || []
                }));

                this.extractAndSetUbigeoFromComponents(normalizedComponents);

                console.log('📍 Sugerencia seleccionada:');
                console.log('   Dirección    :', this.addressModal.address);
                console.log('   Latitud      :', this.addressModal.latitude);
                console.log('   Longitud     :', this.addressModal.longitude);

            } catch (error) {
                console.error('Error obteniendo detalles del lugar:', error);
            }
        },

        extractAndSetUbigeo(addressComponents) {
            if (!addressComponents) return;

            let department = '';
            let province  = '';
            let district  = '';

            addressComponents.forEach(component => {
                const types = component.types || [];
                const longName = (component.longText || component.long_name || '').toUpperCase();

                if (types.includes('administrative_area_level_1')) {
                    department = longName;
                }
                if (types.includes('administrative_area_level_2')) {
                    province = longName;
                }
                if (types.includes('locality') || types.includes('sublocality_level_1') || types.includes('administrative_area_level_3')) {
                    if (!district) district = longName;
                }
            });

            this.setDepartmentByName(department);
            this.setProvinceByName(province);
            this.setDistrictByName(district);

            console.log('📍 Ubicación seleccionada:');
            console.log('   Departamento:', department);
            console.log('   Provincia   :', province);
            console.log('   Distrito    :', district);
        },

        setDepartmentByName(name) {
            if (!name) return;
            const found = this.departments.find(d => d.label.toUpperCase() === name);
            if (found) {
                this.selectedDepartment = found.value;
                this.updateProvinces();
            }
        },

        setProvinceByName(name) {
            if (!name) return;
            this.$nextTick(() => {
                const found = this.provinces.find(p => p.label.toUpperCase() === name);
                if (found) {
                    this.selectedProvince = found.value;
                    this.updateDistricts();
                }
            });
        },

        setDistrictByName(name) {
            if (!name) return;
            this.$nextTick(() => {
                setTimeout(() => {
                    const found = this.districts.find(d => d.label.toUpperCase() === name);
                    if (found) {
                        this.selectedDistrict = found.value;
                    }
                }, 100);
            });
        },

        moveSuggestion(dir) {
            if (!this.addressSuggestions.length) return;
            this.highlightedIndex = Math.max(0, Math.min(this.addressSuggestions.length - 1, this.highlightedIndex + dir));
        },

        selectHighlighted() {
            if (this.highlightedIndex >= 0 && this.addressSuggestions[this.highlightedIndex]) {
                this.selectSuggestionFromList(this.addressSuggestions[this.highlightedIndex]);
            }
        },

        clearSuggestions() {
            this.addressSuggestions = [];
            this.highlightedIndex = -1;
        },
        incrementQuantity(row) {
            if (typeof row.cantidad !== 'number' || isNaN(row.cantidad)) {
                row.cantidad = 1;
            } else {
                row.cantidad++;
            }
            this.updateRowSubtotal(row);
            this.calculateSummary();
            this.saveCartToLocalStorage();
        },
        decrementQuantity(row) {
            if (typeof row.cantidad !== 'number' || isNaN(row.cantidad) || row.cantidad <= 1) {
                row.cantidad = 1;
            } else {
                row.cantidad--;
            }
            this.updateRowSubtotal(row);
            this.calculateSummary();
            this.saveCartToLocalStorage();
        },
        updateRowSubtotal(row) {
            let exchange_rate_sale = this.exchange_rate_sale;
            if(row.currency_type_id === 'USD') {
                row.sub_total = ((parseFloat(row.sale_unit_price) * row.cantidad) * exchange_rate_sale).toFixed(2);
            } else {
                row.sub_total = (parseFloat(row.sale_unit_price) * row.cantidad).toFixed(2);
            }
        },
        saveCartToLocalStorage() {
            localStorage.setItem('products_cart', JSON.stringify(this.records));
        },
        copyToClipboard(textToCopy) {
            if (window.isSecureContext && navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    this.showSwalMessage('¡Copiado!', 'El número ha sido copiado al portapapeles.', 'success')
                }, () => {
                    this.fallbackCopyTextToClipboard(textToCopy);
                });
            } else {
                this.fallbackCopyTextToClipboard(textToCopy);
            }
        },
        fallbackCopyTextToClipboard(text) {
            var textArea = document.createElement("textarea");
            textArea.value = text;

            // Avoid scrolling to bottom
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                var successful = document.execCommand('copy');
                if(successful) {
                    this.showSwalMessage('¡Copiado!', 'El número ha sido copiado al portapapeles.', 'success')
                } else {
                    this.showSwalMessage('Error', 'No se pudo copiar el número.', 'error')
                }
            } catch (err) {
                this.showSwalMessage('Error', 'No se pudo copiar el número.', 'error')
            }
            document.body.removeChild(textArea);
        },
        async changeExchangeRate(exchange_rate_date){
            var response = await axios.get(`/exchange_rate/ecommence/${exchange_rate_date}`)
            this.exchange_rate_sale = parseFloat(response.data.sale)
        },
        optionDocument() {
            this.typeDocumentList = []
            this.typeDocuments = null

            if(this.form_document.codigo_tipo_documento == '01')
            {
                this.typeDocumentList = this.getIdentityDocumentTypes(['6'])
            }
            else if (this.form_document.codigo_tipo_documento == '03' && this.payment_cash.amount >= 700)
            {
                this.typeDocumentList = this.getIdentityDocumentTypes(['1'])
            }
            else if (this.form_document.codigo_tipo_documento == '80')
            {
                this.typeDocumentList = (this.payment_cash.amount >= 700) ? this.getIdentityDocumentTypes(['6', '1']) : this.getIdentityDocumentTypes()
            }
            else {
                this.typeDocumentList = this.getIdentityDocumentTypes(['0', '1', '4'])
            }
        },
        getIdentityDocumentTypes(identity_document_types_id = null){
            if(!identity_document_types_id) return this.all_identity_document_types
            return this.all_identity_document_types.filter((item) => {
                return identity_document_types_id.includes(item.id)
            })
        },
        refreshSetDataCustomer() {
            if (this.isGuestCheckoutActive) {
                this.ensureGuestFormDocument();
                return;
            }

            if (!this.form_document.datos_del_cliente_o_receptor) {
                return;
            }

            if (!this.isLoggedIn && this.guestCheckoutAccepted) {
                this.ensureGuestFormDocument();
                return;
            }

            if (!this.form_document.datos_del_cliente_o_receptor) {
                return;
            }

            // El comprobante prioriza el teléfono del comprador; si no tiene, usa el de quien recibe
            this.form_document.datos_del_cliente_o_receptor.direccion = this.resolveCustomerAddressForPayment()
            this.form_document.datos_del_cliente_o_receptor.telefono = (this.checkoutContactPhone || '').replace(/\D/g, '')
            this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = this.typeDocuments
            this.form_document.datos_del_cliente_o_receptor.numero_documento = this.numberDocument
            this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.typeDocuments
        },
        validateCheckoutBeforePayment() {
            if (this.isGuestCheckoutActive) {
                const validation = this.validateGuestFormForPayment();
                if (!validation.valid) {
                    this.showSwalMessage('Datos incompletos', validation.message, 'warning');
                    return false;
                }
                return true;
            }

            if (!this.form_document.codigo_tipo_documento) {
                this.showSwalMessage('Ocurrió un error!', 'El campo tipo de comprobante es obligatorio', 'error');
                return false;
            }

            if (!this.isPickupMode && !this.form_contact.address) {
                this.showSwalMessage('Ocurrió un error!', 'El campo dirección es obligatorio', 'error');
                return false;
            }

            if (this.isPickupMode && !this.selectedPickupBranch) {
                this.showSwalMessage('Ocurrió un error!', 'Selecciona una sucursal de recojo', 'error');
                return false;
            }

            const phone = (this.form_contact.telephone || '').trim();
            if (!phone) {
                this.showSwalMessage('Ocurrió un error!', 'El campo teléfono es obligatorio', 'error');
                return false;
            }

            return true;
        },
        validateCheckoutBeforePayment() {
            if (this.isGuestCheckoutActive) {
                const validation = this.validateGuestFormForPayment();
                if (!validation.valid) {
                    this.showSwalMessage('Datos incompletos', validation.message, 'warning');
                    return false;
                }
                return true;
            }

            if (!this.form_document.codigo_tipo_documento) {
                this.showSwalMessage('Ocurrió un error!', 'El campo tipo de comprobante es obligatorio', 'error');
                return false;
            }

            if (!this.isPickupMode && !this.form_contact.address) {
                this.showSwalMessage('Ocurrió un error!', 'El campo dirección es obligatorio', 'error');
                return false;
            }

            if (this.isPickupMode && !this.selectedPickupBranch) {
                this.showSwalMessage('Ocurrió un error!', 'Selecciona una sucursal de recojo', 'error');
                return false;
            }

            const receiverError = this.getDeliveryReceiverError();
            if (receiverError) {
                this.showSwalMessage('Datos incompletos', receiverError, 'warning');
                return false;
            }

            if (!this.checkoutContactPhone) {
                this.showSwalMessage(
                    'Falta un teléfono de contacto',
                    'No tienes un teléfono guardado en tu cuenta. Agrégalo desde "Mi cuenta" o indica quién recibe el pedido.',
                    'warning'
                );
                return false;
            }

            return true;
        },
        getDeliveryReceiverError() {
            if (this.isPickupMode || !this.deliveryContactOverride) {
                return '';
            }

            if (!String(this.form_contact.receiver_name || '').trim()) {
                return 'Ingresa el nombre de quien recibe el pedido.';
            }

            const digits = String(this.form_contact.receiver_telephone || '').replace(/\D/g, '');
            if (digits.length < 7) {
                return 'Ingresa un teléfono válido de quien recibe el pedido.';
            }

            return '';
        },
        buildShippingAddress() {
            if (this.isPickupMode && this.selectedPickupBranch) {
                return 'Recojo en tienda: ' + this.selectedPickupBranch.name +
                    (this.selectedPickupBranch.address ? ' — ' + this.selectedPickupBranch.address : '');
            }

            const address = (this.form_contact.address || '').trim();
            const receiver = this.deliveryContactReceiverNote();

            return receiver ? (address + ' ' + receiver).trim() : address;
        },
        deliveryContactReceiverNote() {
            if (this.isPickupMode || !this.deliveryContactOverride) {
                return '';
            }

            const name = String(this.form_contact.receiver_name || '').trim();
            const phone = String(this.form_contact.receiver_telephone || '').trim();
            if (!name && !phone) {
                return '';
            }

            return '(Recibe: ' + [name, phone].filter(Boolean).join(' - ') + ')';
        },
        resolveCustomerAddressForPayment() {
            const shippingAddress = this.buildShippingAddress();
            if (this.isPickupMode && shippingAddress) {
                return shippingAddress;
            }

            return (this.form_contact.address || '').trim();
        },
        resolvePaymentCustomer() {
            this.refreshSetDataCustomer();

            const shippingAddress = this.buildShippingAddress();
            const source = this.form_document?.datos_del_cliente_o_receptor || {};
            const customer = Object.assign({}, source);

            customer.telefono = String(
                customer.telefono
                || this.form_contact.telephone
                || this.guest_form?.telephone
                || ''
            ).replace(/\D/g, '');

            customer.correo_electronico = (
                customer.correo_electronico
                || this.guest_form?.email
                || (this.user && this.user.email)
                || ''
            ).trim();

            customer.apellidos_y_nombres_o_razon_social = (
                customer.apellidos_y_nombres_o_razon_social
                || this.guest_form?.name
                || (this.user && this.user.name)
                || ''
            ).trim();

            let direccion = (customer.direccion || this.form_contact.address || '').trim();
            if (!direccion && shippingAddress) {
                direccion = shippingAddress;
            }
            customer.direccion = direccion;

            const docType = String(
                customer.identity_document_type_id
                || customer.codigo_tipo_documento_identidad
                || this.guest_form?.identity_document_type_id
                || this.typeDocuments
                || '0'
            );
            customer.codigo_tipo_documento_identidad = docType;
            customer.identity_document_type_id = docType;
            customer.numero_documento = String(
                customer.numero_documento
                || this.guest_form?.number
                || this.numberDocument
                || '0'
            ).replace(/\D/g, '') || '0';

            customer.codigo_pais = customer.codigo_pais || 'VE';
            customer.ubigeo = customer.ubigeo || '000619';

            return customer;
        },
        /**
         * Payload customer con las llaves exactas que espera CulqiController / paymentCash.
         */
        buildBackendPaymentCustomer() {
            if (!this.isLoggedIn && this.guestCheckoutAccepted) {
                this.ensureGuestFormDocument();
            }

            return this.resolvePaymentCustomer();
        },
        syncPurchaseCustomerData(purchase, customer) {
            const doc = purchase && typeof purchase === 'object' ? purchase : {};
            if (!doc.datos_del_cliente_o_receptor || typeof doc.datos_del_cliente_o_receptor !== 'object') {
                doc.datos_del_cliente_o_receptor = {};
            }

            doc.datos_del_cliente_o_receptor = Object.assign(
                {},
                doc.datos_del_cliente_o_receptor,
                customer
            );

            return doc;
        },
        async getFormPaymentCash() {
            this.refreshSetDataCustomer();

            const shippingAddress = this.buildShippingAddress();
            const customer = this.buildBackendPaymentCustomer();

            if (this.form_document?.datos_del_cliente_o_receptor) {
                Object.assign(this.form_document.datos_del_cliente_o_receptor, customer);
            }

            let purchase = await this.getDocument();
            purchase = this.syncPurchaseCustomerData(purchase, customer);

            let precio = Math.round(Number(this.summary.total) * 100).toFixed(2);
            let precio_culqi = Number(Number(this.summary.total).toFixed(2));
            const isGuest = !this.isLoggedIn;
            purchase.checkout = Object.assign({}, purchase.checkout || {}, {
                channel: 'ecommerce',
                is_guest: isGuest,
            });

            return {
                producto: 'Compras Ecommerce Facturador Pro',
                precio: precio,
                precio_culqi: precio_culqi,
                customer: customer,
                items: this.records,
                purchase: purchase,
                is_guest: isGuest,
                discount_coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                discount_coupon_id: this.appliedCoupon ? this.appliedCoupon.id : null,
                total_discount: (this.appliedCoupon ? this.appliedCoupon.discount : 0) + parseFloat(this.appliedCampaignDiscount || 0),
                discount_campaigns: this.appliedCampaigns,
                shipping_address: shippingAddress,
                reference_payment: this.getSelectedReferencePayment(),
            }
        },
        // Mapea el método de pago seleccionado al valor que se guarda en la orden
        getSelectedReferencePayment() {
            const map = {
                cash:     'efectivo',
                yape:     'yape',
                transfer: 'transferencia',
                culqi:    'culqi',
                paypal:   'paypal',
                izipay:   'izipay',
                mp:       'mp',
            };
            return map[this.selectedPaymentMethod] || 'efectivo';
        },
        showStoreAlert(title, text, type) {
            const normalizedType = ['error', 'warning', 'success', 'info'].includes(type)
                ? type
                : 'info';
            let message = String(text || '').trim();
            // Mensajes genéricos / mal acentuados de pasarela
            if (/contact[aá]te con soporte/i.test(message)) {
                message = 'Contáctate con soporte o intenta con otra tarjeta.';
            }
            if (!message) {
                message = normalizedType === 'error'
                    ? 'No se pudo completar la operación. Intenta nuevamente.'
                    : 'Revisa la información e intenta de nuevo.';
            }

            this.paymentAlertTitle = title || (normalizedType === 'error' ? 'Pago no realizado' : 'Aviso');
            this.paymentAlertText = message;
            this.paymentAlertType = normalizedType === 'info' ? 'warning' : normalizedType;
            this.paymentAlertVisible = true;
            document.body.style.overflow = 'hidden';
        },
        hideStoreAlert() {
            this.paymentAlertVisible = false;
            this.paymentAlertTitle = '';
            this.paymentAlertText = '';
            if (!this.paymentSuccessVisible && !this.processingPayment && !this.quotationSuccessVisible) {
                document.body.style.overflow = '';
            }
        },
        showSwalMessage(title, text, type){
            this.showStoreAlert(title, text, type || 'info');
        },
        executePayment() {
            if (!this.allowPurchase) {
                return this.showSwalMessage(
                    'Compra no disponible',
                    'La tienda está en modo solo cotización. Puedes solicitar una cotización.',
                    'info'
                );
            }
            this.runPayment(this.selectedPaymentMethod);
        },
        async paymentCash() {
            if (!this.allowPurchase) {
                return this.showSwalMessage(
                    'Compra no disponible',
                    'La tienda está en modo solo cotización. Puedes solicitar una cotización.',
                    'info'
                );
            }
            let product = JSON.parse(localStorage.getItem('products_cart'));

            if (product.length < 1){
                swal({
                    title: "No se han encontrado productos",
                    text: "Por favor seleccione algún producto de la tienda.",
                    type: "error"
                })
                return
            }

            // Métodos manuales: bloqueo inmediato mientras se genera el pedido
            this.showPaymentLoading();

            let url_finally = window.__routes?.payment_cash || '/ecommerce/payment_cash';
            try {
                const response = await axios.post(
                    url_finally,
                    await this.getFormPaymentCash(),
                    this.getHeaderConfig()
                );
                if (response.data.success) {
                    this.saveContactDataUser();
                    this.showPurchaseSuccess(response.data.order);
                } else {
                    this.hidePaymentLoading();
                    swal(
                        'Pago No realizado',
                        response.data.message || 'Sucedió algo inesperado.',
                        'error'
                    );
                }
            } catch (error) {
                this.hidePaymentLoading();
                const message = error.response?.data?.message
                    || (error.response?.data?.errors && Object.values(error.response.data.errors).flat().join(' '))
                    || 'Sucedió algo inesperado.';
                swal('Pago No realizado', message, 'error');
                if (error.response && error.response.status === 422) {
                    this.errors = error.response.data;
                } else {
                    console.log(error);
                }
            }
        },
        async loadMpScript(silent = false) {
            if (window.MercadoPago) {
                this.mpScriptLoaded = true;
                return;
            }
            if (this._mpScriptLoadPromise) {
                return this._mpScriptLoadPromise;
            }
            this._mpScriptLoadPromise = new Promise((resolve, reject) => {
                const existing = document.querySelector('script[src="https://sdk.mercadopago.com/js/v2"]');
                if (existing) {
                    if (window.MercadoPago) {
                        this.mpScriptLoaded = true;
                        resolve();
                        return;
                    }
                    existing.addEventListener('load', () => { this.mpScriptLoaded = true; resolve(); });
                    existing.addEventListener('error', () => {
                        this._mpScriptLoadPromise = null;
                        if (!silent) {
                            this.showSwalMessage('Error', 'No se pudo cargar MercadoPago', 'error');
                        }
                        reject(new Error('mp_script_load_failed'));
                    });
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://sdk.mercadopago.com/js/v2';
                script.async = true;
                script.onload = () => { this.mpScriptLoaded = true; resolve(); };
                script.onerror = () => {
                    this._mpScriptLoadPromise = null;
                    if (!silent) {
                        this.showSwalMessage('Error', 'No se pudo cargar MercadoPago', 'error');
                    }
                    reject(new Error('mp_script_load_failed'));
                };
                document.head.appendChild(script);
            }).finally(() => {
                if (this.mpScriptLoaded) {
                    this._mpScriptLoadPromise = null;
                }
            });

            return this._mpScriptLoadPromise;
        },
        preloadMpResources() {
            this.loadMpScript(true).catch(() => {});
        },
        getMpBrickInitData() {
            this.refreshSetDataCustomer();
            const customer = this.form_document.datos_del_cliente_o_receptor || {};
            return {
                amount: Number(this.summary.total || 0).toFixed(2),
                email: customer.correo_electronico || (this.user && this.user.email) || '',
            };
        },
        getStorePrimaryColor() {
            const rootColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--primary-color').trim();
            const bodyColor = document.body
                ? getComputedStyle(document.body).getPropertyValue('--primary-color').trim()
                : '';

            return bodyColor || rootColor || '#ff7a00';
        },
        buildMpBrickSettings(initData) {
            const primaryColor = this.getStorePrimaryColor();

            return {
                initialization: {
                    amount: Number(initData.amount).toFixed(2),
                    payer: {
                        email: initData.email || '',
                        entityType: 'individual',
                    },
                },
                customization: {
                    visual: {
                        style: {
                            theme: 'flat',
                            customVariables: {
                                baseColor: primaryColor,
                                baseColorFirstVariant: primaryColor,
                                baseColorSecondVariant: primaryColor,
                                buttonTextColor: '#ffffff',
                                textPrimaryColor: '#0f2137',
                                textSecondaryColor: '#667085',
                                inputBackgroundColor: '#ffffff',
                                formBackgroundColor: '#ffffff',
                                outlinePrimaryColor: '#b8b8b8',
                                outlineSecondaryColor: '#9aa1a9',
                                fontSizeExtraSmall: '11px',
                                fontSizeSmall: '12px',
                                fontSizeMedium: '13px',
                                fontSizeLarge: '16px',
                                fontSizeExtraLarge: '18px',
                                inputVerticalPadding: '7px',
                                inputHorizontalPadding: '10px',
                                inputBorderWidth: '1px',
                                inputFocusedBorderWidth: '1px',
                                inputFocusedBoxShadow: 'none',
                                borderRadiusSmall: '0px',
                                borderRadiusMedium: '0px',
                                borderRadiusLarge: '0px',
                                formPadding: '0px',
                            },
                        },
                    },
                    paymentMethods: { creditCard: 'all', debitCard: 'all' },
                },
                callbacks: {
                    onReady: () => {},
                    onSubmit: (formDataRecv) => this.handleMpBrickSubmit(formDataRecv),
                    onError: (error) => {
                        console.error('MercadoPago Brick error', error);
                    },
                },
            };
        },
        extractMpFormData(formDataRecv) {
            if (!formDataRecv) return null;
            if (formDataRecv.formData) return formDataRecv.formData;
            if (formDataRecv.form_data) return formDataRecv.form_data;
            if (formDataRecv.token) return formDataRecv;
            return null;
        },
        handleMpBrickSubmit(formDataRecv) {
            return new Promise((resolve, reject) => {
                const mpFormData = this.extractMpFormData(formDataRecv);
                if (!mpFormData) {
                    this.showSwalMessage('Error', 'No se recibieron los datos del pago de Mercado Pago.', 'error');
                    reject(new Error('mp_form_data_missing'));
                    return;
                }

                this.getFormPaymentCash()
                    .then(rawFormData => {
                        const payload = {
                            ...rawFormData,
                            form_data: mpFormData,
                            description: rawFormData.producto || 'Compras Ecommerce',
                            external_reference: 'ecommerce-' + Date.now(),
                        };
                        return axios.post(
                            window.__routes?.mercadopago_payment || '/ecommerce/mercadopago/payment',
                            payload,
                            this.getHeaderConfig()
                        );
                    })
                    .then(response => {
                        if (response.data.success && response.data.order) {
                            this.detachMpBrickFromModal();
                            swal.close();
                            this.saveContactDataUser();
                            this.showPurchaseSuccess(
                                response.data.order,
                                response.data.thank_you_url
                            );
                            resolve();
                        } else {
                            this.detachMpBrickFromModal();
                            const rejection = new Error(response.data.message || 'No se pudo procesar el pago.');
                            rejection.isPaymentRejection = true;
                            throw rejection;
                        }
                    })
                    .catch(err => {
                        const validationMsg = this.formatMpPaymentErrorMessage(
                            err.response?.data?.message
                            || (err.response?.data?.errors && Object.values(err.response.data.errors).flat().join(' '))
                            || err.message
                            || null
                        );
                        this.detachMpBrickFromModal();
                        swal(
                            err.isPaymentRejection ? "Pago Rechazado" : "Pago Fallido",
                            validationMsg || 'Ocurrió un error con la pasarela.',
                            "error"
                        );
                        console.log('MercadoPago payment error', err.response?.data || err);
                        reject(err);
                    });
            });
        },
        formatMpPaymentErrorMessage(message) {
            const text = message || 'Ocurrió un error con la pasarela.';
            const publicKey = window.__ecommerce_config?.public_key_mp || '';

            if (publicKey.startsWith('TEST-') && /entidad emisora|no pudo procesar/i.test(text)) {
                return text + ' En modo prueba de Mercado Pago use tarjetas de prueba y titular "APRO".';
            }

            return text;
        },
        unmountMpBrick() {
            if (this.mpBrickController) {
                try {
                    this.mpBrickController.unmount();
                } catch (e) {
                    // noop
                }
                this.mpBrickController = null;
            }
            this.mpBrickReady = false;
            this.mpPreparedAmount = null;
            this.mpPreparedEmail = null;
        },
        scheduleMpBrickPrepare() {
            clearTimeout(this.mpPrepareTimer);
            this.mpPrepareTimer = setTimeout(() => {
                this.prepareMpBrick().catch(() => {});
            }, 250);
        },
        async prepareMpBrick(force = false) {
            if (!this.enableMp) return;

            const initData = this.getMpBrickInitData();
            if (!initData.amount || Number(initData.amount) <= 0) return;

            if (!force
                && this.mpBrickReady
                && this.mpPreparedAmount === initData.amount
                && this.mpPreparedEmail === initData.email) {
                return;
            }

            if (this.mpPreparePromise) {
                return this.mpPreparePromise;
            }

            this.mpPreparePromise = this._createMpBrick(initData).finally(() => {
                this.mpPreparePromise = null;
            });

            return this.mpPreparePromise;
        },
        async _createMpBrick(initData) {
            await this.loadMpScript(true);

            const publicKey = window.__ecommerce_config?.public_key_mp || '';
            if (!publicKey) {
                throw new Error('mp_public_key_missing');
            }

            this.unmountMpBrick();

            if (!this.mpInstance) {
                this.mpInstance = new window.MercadoPago(publicKey, { locale: 'es-VE' });
            }

            const bricksBuilder = this.mpInstance.bricks();
            const settings = this.buildMpBrickSettings(initData);

            return new Promise((resolve, reject) => {
                settings.callbacks.onReady = () => {
                    this.mpBrickReady = true;
                    this.mpPreparedAmount = initData.amount;
                    this.mpPreparedEmail = initData.email;
                    resolve();
                };
                settings.callbacks.onError = (error) => {
                    this.mpBrickReady = false;
                    reject(error);
                };

                bricksBuilder.create('payment', MP_BRICK_HOST_ID, settings)
                    .then(controller => {
                        this.mpBrickController = controller;
                    })
                    .catch(reject);
            });
        },
        attachMpBrickToModal() {
            const host = document.getElementById(MP_BRICK_HOST_ID);
            const slot = document.getElementById('mp-swal-slot');
            if (host && slot) {
                host.style.display = 'block';
                host.style.visibility = 'visible';
                host.style.width = '100%';
                slot.appendChild(host);
            }
        },
        detachMpBrickFromModal() {
            const host = document.getElementById(MP_BRICK_HOST_ID);
            const stash = document.getElementById('mp-brick-stash');
            if (host && stash) {
                host.style.display = '';
                host.style.visibility = '';
                host.style.width = '';
                stash.appendChild(host);
            }
        },
        async execMp() {
            if (!this.allowPurchase) {
                return this.showSwalMessage(
                    'Compra no disponible',
                    'La tienda está en modo solo cotización. Puedes solicitar una cotización.',
                    'info'
                );
            }
            if (!this.validateCheckoutBeforePayment()) {
                return;
            }
            if (this.records.length < 1){
                return this.showSwalMessage('Ocurrió un error!', 'No se han encontrado productos', 'error');
            }

            swal({
                title: 'Pago Seguro con Mercado Pago',
                html: '<div id="mp-swal-slot" class="mp-swal-brick"></div>',
                width: 560,
                customClass: 'mp-payment-swal',
                showConfirmButton: false,
                showCloseButton: true,
                onOpen: () => {
                    const popup = document.querySelector('.swal2-popup.mp-payment-swal');
                    const title = popup ? popup.querySelector('.swal2-title') : null;
                    if (title) {
                        title.innerHTML = `<img class="gateway-payment-title-logo" src="${MP_LOGO_SRC}" alt="Mercado Pago">`;
                    }

                    this.attachMpBrickToModal();
                    this.prepareMpBrick(true).catch((err) => {
                        console.error('MercadoPago brick reload failed', err);
                        this.showSwalMessage('Error', 'No se pudo cargar el formulario de Mercado Pago.', 'error');
                    });
                },
                onClose: () => {
                    this.unmountMpBrick();
                    this.detachMpBrickFromModal();
                }
            });
        },
        async loadIzipaySDK() {
            const publicKey = await this.resolveIzipayPublicKey();
            if (!publicKey) {
                const err = new Error('izipay_public_key_missing');
                err.izipaySdkError = true;
                throw err;
            }

            if (this.isIzipaySdkReady(publicKey)) {
                this.krScriptLoaded = true;
                return;
            }

            this.purgeIzipaySdk();

            if (izipaySdkLoadPromise) {
                return izipaySdkLoadPromise;
            }

            izipaySdkLoadPromise = new Promise((resolve, reject) => {
                let settled = false;
                let pollInterval = null;
                let pollTimeout = null;

                const cleanup = () => {
                    if (pollInterval) clearInterval(pollInterval);
                    if (pollTimeout) clearTimeout(pollTimeout);
                };

                const succeed = () => {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    this.krScriptLoaded = true;
                    izipayLoadedPublicKey = publicKey;
                    resolve();
                };

                const fail = (message) => {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    swal('Error', message || 'No se pudo conectar con la pasarela de pagos', 'error');
                    const err = new Error(message || 'No se pudo conectar con la pasarela de pagos');
                    err.izipaySdkError = true;
                    reject(err);
                };

                const waitForKR = () => {
                    if (typeof window.KR !== 'undefined' && window.KR) {
                        succeed();
                        return;
                    }

                    pollInterval = setInterval(() => {
                        if (typeof window.KR !== 'undefined' && window.KR) {
                            succeed();
                        }
                    }, 100);

                    pollTimeout = setTimeout(() => {
                        fail('No se pudo conectar con la pasarela de pagos');
                    }, 10000);
                };

                const injectThemeAssets = () => {
                    if (!document.head.querySelector(`link[href="${IZIPAY_KR_CSS_HREF}"]`)) {
                        const style = document.createElement('link');
                        style.rel = 'stylesheet';
                        style.href = IZIPAY_KR_CSS_HREF;
                        document.head.appendChild(style);
                    }

                    if (!document.head.querySelector(`script[src="${IZIPAY_KR_EXT_SRC}"]`)) {
                        const extScript = document.createElement('script');
                        extScript.src = IZIPAY_KR_EXT_SRC;
                        extScript.onerror = () => fail('No se pudo conectar con la pasarela de pagos');
                        document.head.appendChild(extScript);
                    }
                };

                const mainScript = document.createElement('script');
                mainScript.src = IZIPAY_KR_MAIN_SRC;
                mainScript.setAttribute('kr-public-key', publicKey);
                mainScript.setAttribute('kr-language', 'es-Es');
                mainScript.onerror = () => fail('No se pudo conectar con la pasarela de pagos');
                mainScript.onload = () => {
                    injectThemeAssets();
                    waitForKR();
                };
                document.head.appendChild(mainScript);
            }).finally(() => {
                izipaySdkLoadPromise = null;
            });

            return izipaySdkLoadPromise;
        },
        normalizeIzipayPublicKey(value) {
            return String(value || '').trim();
        },
        async resolveIzipayPublicKey(force = false) {
            if (!force && this.izipayPublicKey) {
                return this.izipayPublicKey;
            }

            if (!force && this.izipayPublicKeyPromise) {
                return this.izipayPublicKeyPromise;
            }

            this.izipayPublicKeyPromise = (async () => {
                const embeddedKey = this.normalizeIzipayPublicKey(window.__ecommerce_config?.public_key_izipay);
                const recordUrl = window.__routes?.izipay_record;

                if (recordUrl) {
                    try {
                        const response = await axios.get(recordUrl, this.getHeaderConfig());
                        const apiKey = this.normalizeIzipayPublicKey(response.data?.publickey_izipay);
                        if (apiKey) {
                            this.izipayPublicKey = apiKey;
                            window.__ecommerce_config = window.__ecommerce_config || {};
                            window.__ecommerce_config.public_key_izipay = apiKey;
                            return apiKey;
                        }
                    } catch (err) {
                        console.warn('No se pudo obtener la llave pública de Izipay desde el servidor.', err);
                    }
                }

                if (embeddedKey) {
                    this.izipayPublicKey = embeddedKey;
                    return embeddedKey;
                }

                return null;
            })().finally(() => {
                this.izipayPublicKeyPromise = null;
            });

            return this.izipayPublicKeyPromise;
        },
        isIzipaySdkReady(publicKey) {
            const script = document.querySelector(`script[src="${IZIPAY_KR_MAIN_SRC}"]`);
            const scriptKey = this.normalizeIzipayPublicKey(script?.getAttribute('kr-public-key'));

            return Boolean(
                typeof window.KR !== 'undefined'
                && window.KR
                && script
                && scriptKey === publicKey
                && izipayLoadedPublicKey === publicKey
            );
        },
        purgeIzipaySdk() {
            document.querySelectorAll(`script[src="${IZIPAY_KR_MAIN_SRC}"], script[src="${IZIPAY_KR_EXT_SRC}"]`)
                .forEach(el => el.remove());
            document.querySelectorAll(`link[href="${IZIPAY_KR_CSS_HREF}"]`)
                .forEach(el => el.remove());

            if (typeof window.KR !== 'undefined') {
                try {
                    if (typeof window.KR.removeForms === 'function') {
                        window.KR.removeForms();
                    }
                } catch (e) {
                    // noop
                }
                delete window.KR;
            }

            this.krScriptLoaded = false;
            izipayLoadedPublicKey = null;
            izipaySdkLoadPromise = null;
        },
        hideIzipayPaymentHost() {
            const container = document.getElementById('izipay-payment-host');
            const modal = document.getElementById('izipay-payment-modal');
            if (container) {
                container.style.display = 'none';
                container.innerHTML = '';
            }
            if (modal) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }
            document.body.style.overflow = '';
        },
        async execIzipay() {
            if (!this.allowPurchase) {
                return this.showSwalMessage(
                    'Compra no disponible',
                    'La tienda está en modo solo cotización. Puedes solicitar una cotización.',
                    'info'
                );
            }
            if (!this.validateCheckoutBeforePayment()) {
                return;
            }
            if (this.records.length < 1){
                return this.showSwalMessage('Ocurrió un error!', 'No se han encontrado productos', 'error');
            }

            try {
                await this.loadIzipaySDK();
                const rawFormData = await this.getFormPaymentCash();

                const response = await axios.post(window.__routes?.izipay_payment || '/ecommerce/izipay/payment', rawFormData, this.getHeaderConfig());
                if(response.data.success && response.data.formToken) {
                    const formToken = response.data.formToken;
                    const order = response.data.order;
                    const paymentPublicKey = this.normalizeIzipayPublicKey(response.data.publickey_izipay);

                    if (paymentPublicKey && paymentPublicKey !== this.izipayPublicKey) {
                        this.izipayPublicKey = paymentPublicKey;
                        window.__ecommerce_config = window.__ecommerce_config || {};
                        window.__ecommerce_config.public_key_izipay = paymentPublicKey;
                        this.purgeIzipaySdk();
                        await this.loadIzipaySDK();
                    }

                    try {
                        await window.KR.setFormConfig({
                            formToken: formToken,
                            'kr-language': 'es-Es',
                        });

                        window.KR.onSubmit(async (paymentResponse) => {
                            const uuid = paymentResponse.clientAnswer.transactions[0].uuid;

                            axios.post(window.__routes?.izipay_transaction || '/ecommerce/izipay/transaction', {
                                uuid: uuid,
                                external_id: order && order.external_id ? order.external_id : null,
                            }, this.getHeaderConfig())
                            .then(res => {
                                if(res.data.success && res.data.paid) {
                                    this.hideIzipayPaymentHost();
                                    this.saveContactDataUser();
                                    this.showPurchaseSuccess(res.data.order || order);
                                } else {
                                    this.hideIzipayPaymentHost();
                                    swal("Pago Rechazado", "Su pago no fue aprobado o fue denegado", "error");
                                }
                            }).catch(err => {
                                console.error('Izipay transaction verify failed:', err);
                                this.hideIzipayPaymentHost();
                                swal("Error", "Sucedió un error al verificar la transacción", "error");
                            });
                        });

                        // Creación dinámica
                        let container = document.getElementById('izipay-payment-host');
                        if (!container) {
                            container = document.createElement('div');
                            container.id = 'izipay-payment-host';
                            document.body.appendChild(container);
                        }

                        const modal = document.getElementById('izipay-payment-modal');
                        const closeButton = document.getElementById('izipay-payment-close');

                        container.style.display = 'block';
                        if (modal) {
                            modal.classList.add('is-open');
                            modal.setAttribute('aria-hidden', 'false');
                            modal.onclick = (event) => {
                                if (event.target === modal) {
                                    this.hideIzipayPaymentHost();
                                }
                            };
                        }
                        if (closeButton) {
                            closeButton.onclick = () => this.hideIzipayPaymentHost();
                        }
                        document.body.style.overflow = 'hidden';

                        // Limpieza e Inyección de estructura
                        container.innerHTML = '';
                        const krSmartForm = document.createElement('div');
                        krSmartForm.className = 'kr-smart-form';
                        container.appendChild(krSmartForm);

                        // Renderizado seguro
                        try {
                            await window.KR.renderElements('#izipay-payment-host');
                        } catch (renderErr) {
                            console.error('Error capturado al renderizar KR.renderElements():', renderErr);
                            console.log('Estado actual del contenedor:', container.outerHTML);
                            throw renderErr; // Propagamos para el catch general
                        }
                    } catch (renderErr) {
                        console.error('Izipay embedded form setup/render failed:', renderErr);
                        this.hideIzipayPaymentHost();
                        swal('Error', 'No se pudo mostrar el formulario de pago. Intente nuevamente.', 'error');
                    }
                } else {
                    swal("Error", "No se pudo comunicar con Izipay", "error");
                }
            } catch (err) {
                console.error(err);
                if (err.izipaySdkError) {
                    if (err.message === 'izipay_public_key_missing') {
                        swal('Error', 'No se encontró la llave pública de Izipay. Verifique la configuración de pagos de la empresa.', 'error');
                    }
                    return;
                }
                if (err.response && err.response.status === 422) {
                    this.errors = err.response.data;
                    swal("Error", "Revise los campos", "error");
                } else {
                    swal("Error", "Error al procesar", "error");
                }
            }
        },
        redirectHome() {
            window.location = window.__routes?.home || "/ecommerce";
        },
        // Arma el detalle de la compra que se mostrará en el modal de confirmación
        buildSuccessOrder(order) {
            const paymentLabels = {
                cash: 'Efectivo', yape: 'Yape', transfer: 'Transferencia',
                culqi: this.titleCulqi || 'Tarjeta (VISA)', paypal: 'PayPal',
                izipay: this.titleIzipay || 'Izipay', mp: this.titleMp || 'Mercado Pago'
            };
            const deliveryLabel = (this.isPickupMode && this.selectedPickupBranch)
                ? 'Recojo en tienda — ' + this.selectedPickupBranch.name
                : (this.isPickupMode ? 'Recojo en tienda' : 'Envío a domicilio');
            const number = (order && (order.order_code || order.order_id || order.id || order.external_id))
                ? this.formatOrderNumber(order)
                : '#—';
            return {
                number: number,
                items: this.records.map(r => ({
                    description: r.description,
                    cantidad: r.cantidad,
                    symbol: r.currency_type_symbol || 'Bs.',
                    total: (parseFloat(r.sale_unit_price) * r.cantidad).toFixed(2)
                })),
                total_taxed: this.summary.total_taxed || '0.00',
                total_igv: this.summary.total_igv || '0.00',
                total_exonerated: this.summary.total_exonerated || '0.00',
                delivery: this.summary.delivery || '0.00',
                total: this.summary.total || '0.00',
                paymentLabel: paymentLabels[this.selectedPaymentMethod] || 'Efectivo',
                deliveryLabel: deliveryLabel,
            };
        },
        isYapeOrderPayload(order, formatted = null) {
            const candidates = [
                formatted && formatted.isYape,
                formatted && formatted.referencePayment,
                formatted && formatted.paymentLabel,
                order && order.reference_payment,
                this.selectedPaymentMethod,
                this.getSelectedReferencePayment ? this.getSelectedReferencePayment() : null,
            ];
            return candidates.some((value) => {
                if (value === true) return true;
                const normalized = String(value || '').trim().toLowerCase();
                return normalized === 'yape' || normalized.includes('yape');
            });
        },
        /**
         * Overlay de carga para Yape, efectivo, transferencia y Culqi post-token.
         */
        showPaymentLoading(options = {}) {
            const opts = options && typeof options === 'object' ? options : {};

            this.paymentLoadingTitle = opts.title || 'Estamos generando tu pedido';
            this.paymentLoadingText = opts.text || 'Por favor no cierres esta ventana hasta que el proceso termine.';
            this.processingPayment = true;
            this.paymentSuccessVisible = false;
            this.successIsYape = false;
            this.resetSuccessModalSummary();
            document.body.style.overflow = 'hidden';
        },
        /**
         * Overlay durante el charge Culqi (token → backend).
         * Evita el vacío visual cuando el SDK cierra y aún no llega la respuesta.
         */
        showCulqiBankLoading() {
            this.showPaymentLoading({
                title: 'Estamos hablando con su banco',
                text: 'Por favor no cierres esta ventana...',
            });
        },
        hidePaymentLoading() {
            this.processingPayment = false;
            if (!this.paymentSuccessVisible) {
                document.body.style.overflow = '';
            }
        },
        buildThankYouUrl(order, thankYouUrl = null) {
            if (thankYouUrl) return thankYouUrl;
            if (order && order.external_id && window.__routes && window.__routes.thank_you) {
                return window.__routes.thank_you.replace('EXTERNAL_ID', order.external_id);
            }
            if (order && order.external_id) {
                return `/ecommerce/thanks/${order.external_id}`;
            }
            return null;
        },
        getPaymentMethodLabel(referencePayment) {
            const labels = {
                efectivo: this.cashPaymentTitle || 'Pago contra entrega',
                yape: 'Yape',
                transferencia: 'Transferencia bancaria',
                culqi: this.titleCulqi || 'Tarjeta (Culqi)',
                culqui: this.titleCulqi || 'Tarjeta (Culqi)',
                izipay: this.titleIzipay || 'Izipay',
                mp: this.titleMp || 'Mercado Pago',
                paypal: 'PayPal',
            };
            const key = String(referencePayment || '').toLowerCase();
            return labels[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : '—');
        },
        formatOrderNumber(orderOrId) {
            if (orderOrId && typeof orderOrId === 'object') {
                const code = orderOrId.order_code || orderOrId.order_id || orderOrId.public_number;
                if (code) {
                    const clean = String(code).replace(/^#/, '');
                    return `#${clean}`;
                }
                if (orderOrId.id) {
                    return `#${String(orderOrId.id).padStart(6, '0')}`;
                }
                return '#—';
            }
            const id = Number(orderOrId) || 0;
            return `#${String(id).padStart(6, '0')}`;
        },
        formatMoney(amount) {
            const value = Number(amount) || 0;
            return `Bs. ${value.toLocaleString('es-VE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },
        getSuccessOrderItemsCount(order) {
            if (!order) return 0;
            const items = order.items;
            if (Array.isArray(items)) return items.length;
            if (items && typeof items === 'object') return Object.keys(items).length;
            if (typeof items === 'string') {
                try {
                    const parsed = JSON.parse(items);
                    if (Array.isArray(parsed)) return parsed.length;
                    if (parsed && typeof parsed === 'object') return Object.keys(parsed).length;
                } catch (e) {
                    return 0;
                }
            }
            return 0;
        },
        resetSuccessModalSummary() {
            this.successOrderNumber = '';
            this.successPaymentLabel = '';
            this.successItemsCount = 0;
            this.successOrderTotal = 0;
        },
        syncSuccessModalSummary(order, formatted) {
            const summary = formatted || this.successOrder || {};
            const backendOrder = order || {};

            if (summary.number) {
                this.successOrderNumber = summary.number;
            } else if (backendOrder.order_code || backendOrder.id) {
                this.successOrderNumber = this.formatOrderNumber(backendOrder);
            } else if (backendOrder.external_id) {
                this.successOrderNumber = '#' + String(backendOrder.external_id).padStart(6, '0');
            } else {
                this.successOrderNumber = '#—';
            }

            const referencePayment = backendOrder.reference_payment || this.getSelectedReferencePayment();
            this.successPaymentLabel = summary.paymentLabel
                || this.getPaymentMethodLabel(referencePayment);

            const itemsFromBackend = this.getSuccessOrderItemsCount(backendOrder);
            const itemsFromSummary = Array.isArray(summary.items) ? summary.items.length : 0;
            this.successItemsCount = itemsFromBackend || itemsFromSummary;

            const total = summary.total ?? backendOrder.total ?? this.response_order_total ?? 0;
            this.successOrderTotal = this.formatMoney(total);
        },
        /**
         * Modal de éxito unificado para todos los métodos de pago.
         * Culqi / Izipay / MP llegan aquí sin overlay de carga previo.
         * Yape / efectivo / transferencia primero muestran showPaymentLoading().
         */
        showPurchaseSuccess(order, thankYouUrl = null) {
            this.hidePaymentLoading();
            this.response_order_total = order ? order.total : 0;
            this.thankYouUrl = this.buildThankYouUrl(order, thankYouUrl);
            const formatted = this.buildSuccessOrder(order);
            if (order && order.external_id) {
                formatted.external_id = order.external_id;
            }
            if (order && order.id) {
                formatted.id = order.id;
            }
            if (order && order.order_code) {
                formatted.order_code = order.order_code;
            }
            this.successIsYape = this.isYapeOrderPayload(order, formatted);
            formatted.isYape = this.successIsYape;
            this.successOrder = formatted;
            this.syncSuccessModalSummary(order, formatted);
            this.clearCartSilently();
            this.paymentSuccessRedirecting = false;
            this.paymentSuccessVisible = true;
            document.body.style.overflow = 'hidden';
        },
        /**
         * No cerrar el modal antes de navegar: evita el flash del carrito vacío.
         * El overlay permanece visible con el botón en estado de carga hasta el redirect.
         */
        confirmPurchaseSuccess() {
            if (this.paymentSuccessRedirecting) return;

            this.paymentSuccessRedirecting = true;
            document.body.style.overflow = 'hidden';

            let targetUrl = null;
            if (this.isYapePaymentSuccess && this.successOrder && (this.successOrder.order_code || this.successOrder.id)) {
                const pedido = this.successOrder.order_code
                    || String(this.successOrder.id).padStart(6, '0');
                const base = window.__routes?.order_tracking || '/ecommerce/seguimiento';
                const sep = base.indexOf('?') >= 0 ? '&' : '?';
                targetUrl = `${base}${sep}pedido=${encodeURIComponent(pedido)}`;
            } else {
                targetUrl = this.thankYouUrl
                    || (this.successOrder && this.successOrder.external_id
                        ? this.buildThankYouUrl(this.successOrder)
                        : null)
                    || (window.__routes?.home || '/ecommerce');
            }

            // Deja un frame para pintar el estado de carga del botón antes de navegar
            window.requestAnimationFrame(() => {
                window.location.href = targetUrl;
            });
        },
        getYapeVoucherWhatsappText() {
            const orderNumber = this.successOrderNumber || '#—';
            const total = this.successOrderTotal || this.formatMoney(0);
            return [
                `Hola, realicé el pago de mi pedido ${orderNumber} por Yape.`,
                `Total: ${total}.`,
                `Adjunto el comprobante de pago.`,
                `Por favor confirmen la recepción.`,
            ].join('\n');
        },
        openYapeVoucherWhatsapp() {
            if (!this.canSendYapeVoucherWhatsapp) {
                return;
            }
            window.open(this.getWhatsappUrl(this.getYapeVoucherWhatsappText()), '_blank');
        },
        openQuotationModal() {
            this.setCheckoutIntent('quote');
        },
        maybeOpenCheckoutIntentModal() {
            if (!this.quotationEnabled) {
                this.checkoutIntentModalVisible = false;
                dismissCheckoutIntentBootOverlay();
                return;
            }
            // Solo cotizar: sin modal, flujo forzado a cotización
            if (this.quoteOnlyMode) {
                this.checkoutIntent = 'quote';
                this.checkoutIntentChosen = true;
                this.checkoutIntentModalVisible = false;
                this.preloadQuotationContact();
                dismissCheckoutIntentBootOverlay();
                return;
            }
            // Híbrido: si boot ya abrió el modal, no retrasar; solo asegurar estado
            if (this.isHybridQuotationMode && this.records.length > 0 && !this.checkoutIntentChosen) {
                this.checkoutIntentModalVisible = true;
                document.body.style.overflow = 'hidden';
            }
            dismissCheckoutIntentBootOverlay();
        },
        bindCheckoutIntentBootHandler() {
            window.__confirmCheckoutIntentBoot = (intent) => {
                this.confirmCheckoutIntent(intent);
            };
        },
        flushPendingCheckoutIntentChoice() {
            const pending = window.__checkoutIntentPendingChoice;
            if (!pending) return;
            window.__checkoutIntentPendingChoice = null;
            this.confirmCheckoutIntent(pending === 'quote' ? 'quote' : 'purchase');
        },
        confirmCheckoutIntent(intent) {
            const target = intent === 'quote' ? 'quote' : 'purchase';
            this.checkoutIntentChosen = true;
            this.checkoutIntentModalVisible = false;
            dismissCheckoutIntentBootOverlay();
            if (!this.quotationSuccessVisible && !this.paymentSuccessVisible && !this.processingPayment) {
                document.body.style.overflow = '';
            }
            this.setCheckoutIntent(target);
        },
        reopenCheckoutIntentModal() {
            if (!this.isHybridQuotationMode || this.quoteOnlyMode || this.records.length < 1) {
                return;
            }
            this.checkoutIntentModalVisible = true;
            document.body.style.overflow = 'hidden';
        },
        setCheckoutIntent(intent) {
            if (!this.quotationEnabled && intent === 'quote') {
                return this.showSwalMessage('Cotizaciones no disponibles', 'Las cotizaciones no están habilitadas en la tienda.', 'info');
            }
            if (this.quoteOnlyMode && intent === 'purchase') {
                return;
            }
            if (intent === 'quote') {
                if (!this.records || this.records.length < 1) {
                    return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de solicitar una cotización.', 'warning');
                }
            }
            this.checkoutIntent = intent === 'quote' ? 'quote' : 'purchase';
            this.checkoutIntentChosen = true;
            if (intent === 'quote') {
                this.preloadQuotationContact();
                this.quotationResult = null;
                this.quotationSuccessVisible = false;
                this.quotationModalVisible = false;
            } else {
                try {
                    sessionStorage.removeItem('ecommerce_checkout_intent');
                } catch (e) { /* ignore */ }
            }
        },
        enterQuotationCheckout() {
            this.setCheckoutIntent('quote');
        },
        exitQuotationCheckout() {
            if (this.quotationSubmitting || this.quoteOnlyMode) return;
            this.setCheckoutIntent('purchase');
        },
        preloadQuotationContact() {
            this.quotationForm = {
                contact_name: (this.user && this.user.name) || this.quotationForm.contact_name || '',
                email: (this.user && this.user.email) || this.quotationForm.email || '',
                telephone: this.form_contact.telephone
                    || (this.user && this.user.telephone)
                    || this.quotationForm.telephone
                    || '',
                notes: this.quotationForm.notes || '',
                validity_days: this.quotationValidityDays,
            };
        },
        closeQuotationModal() {
            if (this.quotationSubmitting) return;
            this.quotationModalVisible = false;
            if (!this.quotationSuccessVisible && !this.paymentSuccessVisible && !this.processingPayment && !this.checkoutIntentModalVisible) {
                document.body.style.overflow = '';
            }
        },
        async submitQuotationRequest() {
            if (this.quotationSubmitting) return;
            if (!this.quotationEnabled) {
                return this.showSwalMessage('Cotizaciones no disponibles', 'Las cotizaciones no están habilitadas en la tienda.', 'info');
            }

            if (!this.records || this.records.length < 1) {
                return this.showSwalMessage('Carrito vacío', 'Agrega productos antes de solicitar una cotización.', 'warning');
            }
            if (!this.acceptedTerms) {
                return this.showSwalMessage('Términos requeridos', 'Debes aceptar los términos y condiciones.', 'warning');
            }

            // Invitado: validar contacto. Logueado: se toma de la sesión (preload).
            if (!this.isLoggedIn) {
                if (!this.quotationForm.contact_name || !String(this.quotationForm.contact_name).trim()) {
                    return this.showSwalMessage('Dato requerido', 'Ingresa tu nombre de contacto.', 'warning');
                }
                if (!this.quotationForm.email || !String(this.quotationForm.email).trim()) {
                    return this.showSwalMessage('Dato requerido', 'Ingresa tu correo electrónico.', 'warning');
                }
                if (!this.quotationForm.telephone || !String(this.quotationForm.telephone).trim()) {
                    return this.showSwalMessage('Dato requerido', 'Ingresa tu teléfono.', 'warning');
                }
            } else {
                this.preloadQuotationContact();
                if (!this.quotationForm.contact_name || !this.quotationForm.email || !this.quotationForm.telephone) {
                    return this.showSwalMessage(
                        'Datos incompletos',
                        'Tu cuenta no tiene nombre, correo o teléfono. Completa tu perfil o contacta a la tienda.',
                        'warning'
                    );
                }
            }

            // Sincroniza teléfono al perfil de checkout por si vuelve a compra.
            if (this.quotationForm.telephone) {
                this.form_contact.telephone = String(this.quotationForm.telephone).trim();
            }

            const payload = {
                contact_name: String(this.quotationForm.contact_name || '').trim(),
                email: String(this.quotationForm.email || '').trim(),
                telephone: String(this.quotationForm.telephone || '').trim(),
                notes: String(this.quotationForm.notes || '').trim(),
                items: this.records.map(row => ({
                    item_id: row.id,
                    quantity: Number(row.cantidad) || 1,
                })),
            };

            this.quotationSubmitting = true;
            try {
                const response = await axios.post(
                    window.__routes?.quotation_store || '/ecommerce/quotations',
                    payload,
                    this.getHeaderConfig()
                );

                if (response.data && response.data.success && response.data.quotation) {
                    this.quotationResult = response.data.quotation;
                    this.quotationModalVisible = false;
                    this.clearCartSilently();
                    this.checkoutIntent = this.quoteOnlyMode ? 'quote' : 'purchase';
                    this.quotationSuccessVisible = true;
                    document.body.style.overflow = 'hidden';
                } else {
                    this.showSwalMessage(
                        'No se pudo cotizar',
                        (response.data && response.data.message) || 'Intenta nuevamente.',
                        'error'
                    );
                }
            } catch (error) {
                const message = error.response?.data?.message
                    || (error.response?.data?.errors && Object.values(error.response.data.errors).flat().join(' '))
                    || 'Ocurrió un error al registrar la cotización.';
                this.showSwalMessage('Error', message, 'error');
                console.error(error);
            } finally {
                this.quotationSubmitting = false;
            }
        },
        confirmQuotationSuccess() {
            if (this.quotationSuccessRedirecting) return;
            this.quotationSuccessRedirecting = true;
            const isGuestResult = this.quotationResult && this.quotationResult.is_guest;
            const target = (isGuestResult
                ? (window.__routes?.home || '/ecommerce')
                : ((this.quotationResult && this.quotationResult.list_url)
                    || window.__routes?.quotation_list
                    || '/ecommerce/quotation_list'));
            window.requestAnimationFrame(() => {
                window.location.href = target;
            });
        },
        openQuotationPdf() {
            if (this.quotationResult && this.quotationResult.print_url) {
                window.open(this.quotationResult.print_url, '_blank', 'noopener');
            }
        },
        clearCartSilently() {
            this.errors = {};
            this.records_old = this.records.slice();
            this.records = [];
            localStorage.setItem('products_cart', JSON.stringify([]));
            this.summary = {
                subtotal: '0.0', tax: '0.0', total: '0.00',
                total_taxed: '0.0', total_value: '0.0',
                total_exonerated: '0.0', total_igv: '0.0', delivery: '0.00'
            };
            this.payment_cash.amount = '0.00';
            $("#total_amount").data('total', '0.00');
        },
        goToThankYou() {
            if (this.thankYouUrl) {
                window.location = this.thankYouUrl;
            } else {
                this.redirectHome();
            }
        },
        getHeaderConfig() {
            const headers = {
                "Content-Type": "application/json",
            };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrf) {
                headers['X-CSRF-TOKEN'] = csrf;
            }
            const token = this.user && this.user.api_token;
            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }
            return { headers };
        },
        async getDocument() {
            this.form_document.items = await this.getItemsDocument();
            const descuentos = await this.getDescuentos();
            const totales = await this.getTotales(descuentos);
            let doc = Object.assign({}, this.form_document);
            doc.totales = totales;
            if (descuentos.length > 0) {
                doc.descuentos = descuentos;
            }
            // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
            if (doc.codigo_tipo_documento == '01') {
                doc.serie_documento = 'F001';
            } else {
                doc.serie_documento = null;
            }
            // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
            return doc;
        },
        async getDescuentos() {
            if(!this.appliedCoupon || !this.global_discount_type) return [];

            let montoEntrada = parseFloat(this.appliedCoupon.discount || 0);
            let codigo = this.global_discount_type.id;
            let descripcion = this.global_discount_type.description;
            let base = 0;
            let montoCalculado = 0;

            if(this.global_discount_type.base == 1) {
                // ########## INICIO CAMBIO AFECTACIÓN IVA
                montoCalculado = montoEntrada / 1.16;
                // ######### FIN CAMBIO AFECTACIÓN IVA
                base = parseFloat(this.summary.total_taxed);
            } else {
                montoCalculado = montoEntrada;
                base = parseFloat(this.summary.total_value) + parseFloat(this.summary.total_igv);
            }

            let factor = base > 0 ? (montoCalculado / base) : 0;

            return [{
                codigo: codigo,
                descripcion: descripcion,
                factor: parseFloat(factor.toFixed(5)),
                monto: parseFloat(montoCalculado.toFixed(2)),
                base: parseFloat(base.toFixed(2))
            }];
        },
        async getTotales(descuentos = []) {
            let total_descuentos_monto = 0.00;
            if (descuentos.length > 0) {
                total_descuentos_monto = descuentos.reduce((sum, d) => sum + (parseFloat(d.monto) || 0), 0);
            }

            let base_antes = parseFloat(this.aux_totals.total_taxed);
            let igv_antes  = parseFloat(this.aux_totals.total_igv);

            let delivery_price = (this.deliveryZone && this.deliveryZone.price) ? parseFloat(this.deliveryZone.price) : 0;
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            let delivery_base  = parseFloat((delivery_price / 1.16).toFixed(2));
            // ######### FIN CAMBIO AFECTACIÓN IVA
            let delivery_igv   = parseFloat((delivery_price - delivery_base).toFixed(2));

            let total_operaciones_gravadas = base_antes + delivery_base;
            let total_igv                  = igv_antes  + delivery_igv;
            let total_venta                = total_operaciones_gravadas + total_igv;

            if (descuentos.length > 0 && this.global_discount_type) {
                if (this.global_discount_type.base == 1) {
                    total_operaciones_gravadas = parseFloat((total_operaciones_gravadas - total_descuentos_monto).toFixed(2));
                    // ########## INICIO CAMBIO AFECTACIÓN IVA
                    total_igv   = parseFloat((total_operaciones_gravadas * 0.16).toFixed(2));
                    // ######### FIN CAMBIO AFECTACIÓN IVA
                    total_venta = parseFloat((total_operaciones_gravadas + total_igv).toFixed(2));
                } else {
                    total_venta = parseFloat((total_venta - total_descuentos_monto).toFixed(2));
                }
            }

            return {
                total_descuentos:               total_descuentos_monto,
                total_exportacion:              0.00,
                total_operaciones_gravadas:     total_operaciones_gravadas,
                total_operaciones_inafectas:    parseFloat(this.aux_totals.total_exonerated || 0),
                total_operaciones_exoneradas:   0.00,
                total_operaciones_gratuitas:    0.00,
                total_igv:                      total_igv,
                total_impuestos:                total_igv,
                total_valor:                    total_operaciones_gravadas,
                total_venta:                    total_venta
            };
        },
        openAddAddressFlow() {
            this.openAddressMapModal('add', null, false);
        },
        openChangeAddressFlow() {
            if (this.isGuestAddressCheckout()) {
                this.syncGuestAddressesFromFormContact();
            }
            this.openAddressListModal();
        },
        fetchUserAddresses() {
            if (!this.user || !this.user.id) {
                return Promise.resolve();
            }

            const url = window.__routes?.shipping_addresses || '/ecommerce/shipping-addresses';

            return axios.get(url, this.getHeaderConfig())
                .then(response => {
                    if (response.data && response.data.success) {
                        if (Array.isArray(response.data.addresses)) {
                            this.userAddresses = this.normalizeAddressList(response.data.addresses);
                        }
                        if (response.data.address) {
                            this.userDefaultAddress = this.normalizeAddressRecord(response.data.address);
                        }
                    }
                    return response;
                })
                .catch(error => {
                    console.error('No se pudieron cargar las direcciones', error);
                    return Promise.reject(error);
                });
        },
        ensureLocationsLoaded() {
            if (Array.isArray(this.departments) && this.departments.length > 0) {
                return Promise.resolve();
            }

            return this.fetchLocations();
        },
        openAddressListModal() {
            this.addressListMenuOpen = null;

            const showModal = () => {
                this.userAddresses = this.normalizeAddressList(this.userAddresses);

                const active = this.userDefaultAddress;
                if (active && active.id && this.userAddresses.some(a => a.id === active.id)) {
                    this.selectedAddressId = active.id;
                } else if (this.userAddresses.length) {
                    this.selectedAddressId = this.userAddresses[0].id;
                } else {
                    this.selectedAddressId = null;
                }
                jQuery('#addressListModal').modal('show');
            };

            if (this.isGuestAddressCheckout()) {
                this.syncGuestAddressesFromFormContact();
                this.ensureLocationsLoaded()
                    .then(showModal)
                    .catch(showModal);
                return;
            }

            this.ensureLocationsLoaded()
                .then(() => this.fetchUserAddresses())
                .then(showModal)
                .catch(showModal);
        },
        closeAddressListModal() {
            jQuery('#addressListModal').modal('hide');
            this.closeAddressListMenu();
        },
        getAddressListMenuItem() {
            if (!this.addressListMenuOpen) {
                return null;
            }

            return this.userAddresses.find(item => item.id === this.addressListMenuOpen) || null;
        },
        toggleAddressListMenu(addressId, event) {
            if (this.addressListMenuOpen === addressId) {
                this.closeAddressListMenu();
                return;
            }

            this.addressListMenuOpen = addressId;
            this.addressListMenuAnchorEvent = event || null;
            this.addressListMenuPositioned = false;
            this.addressListMenuStyle = {};

            this.$nextTick(() => {
                this.positionAddressListMenu(this.addressListMenuAnchorEvent);
                this.bindAddressListMenuListeners();
            });
        },
        positionAddressListMenu(event) {
            const btn = event && event.currentTarget;
            const menu = this.$refs.addressListFloatingMenu;

            if (!btn || !menu || !this.addressListMenuOpen) {
                return;
            }

            const rect = btn.getBoundingClientRect();
            const menuWidth = Math.max(menu.offsetWidth || 0, 140);
            const menuHeight = menu.offsetHeight || 88;
            const gap = 6;
            const pad = 8;

            const spaceBelow = window.innerHeight - rect.bottom - pad;
            const spaceAbove = rect.top - pad;

            let top;
            if (spaceBelow >= menuHeight + gap || spaceBelow >= spaceAbove) {
                this.addressListMenuPlacement = 'bottom';
                top = rect.bottom + gap;
            } else {
                this.addressListMenuPlacement = 'top';
                top = rect.top - gap - menuHeight;
            }

            let left = rect.right - menuWidth;
            left = Math.max(pad, Math.min(left, window.innerWidth - menuWidth - pad));
            top = Math.max(pad, Math.min(top, window.innerHeight - menuHeight - pad));

            this.addressListMenuStyle = {
                top: `${Math.round(top)}px`,
                left: `${Math.round(left)}px`,
                minWidth: `${menuWidth}px`,
            };
            this.addressListMenuPositioned = true;
        },
        bindAddressListMenuListeners() {
            if (this._addressListMenuOnResize) {
                return;
            }

            this._addressListMenuOnResize = () => {
                if (this.addressListMenuOpen && this.addressListMenuAnchorEvent) {
                    this.positionAddressListMenu(this.addressListMenuAnchorEvent);
                }
            };

            window.addEventListener('resize', this._addressListMenuOnResize);
        },
        unbindAddressListMenuListeners() {
            if (this._addressListMenuOnResize) {
                window.removeEventListener('resize', this._addressListMenuOnResize);
                this._addressListMenuOnResize = null;
            }
        },
        closeAddressListMenu() {
            this.addressListMenuOpen = null;
            this.addressListMenuStyle = {};
            this.addressListMenuPlacement = 'bottom';
            this.addressListMenuPositioned = false;
            this.addressListMenuAnchorEvent = null;
            this.unbindAddressListMenuListeners();
        },
        splitAddressReference(text) {
            const raw = (text || '').trim();
            if (!raw) {
                return { street: '', reference: '' };
            }

            const match = raw.match(/\s*-\s*Ref[.:]?\s*(.+)$/i);
            if (match) {
                return {
                    street: raw.replace(/\s*-\s*Ref[.:]?\s*.+$/i, '').trim(),
                    reference: match[1].trim(),
                };
            }

            return { street: raw, reference: '' };
        },
        composeFullAddress(street, reference) {
            const cleanStreet = (street || '').trim();
            const cleanReference = (reference || '').trim();

            if (!cleanStreet) {
                return cleanReference ? `Ref: ${cleanReference}` : '';
            }

            if (!cleanReference) {
                return cleanStreet;
            }

            return `${cleanStreet} - Ref: ${cleanReference}`;
        },
        resolveAddressUbigeo(addr) {
            if (!addr) {
                return {
                    department_id: null,
                    province_id: null,
                    district_id: null,
                    location_id: [],
                };
            }

            let department_id = addr.department_id || null;
            let province_id = addr.province_id || null;
            let district_id = addr.district_id || null;

            const location = Array.isArray(addr.location_id)
                ? addr.location_id.filter(value => value !== null && value !== '' && value !== undefined)
                : [];

            if ((!department_id || !province_id || !district_id) && location.length === 3) {
                department_id = location[0];
                province_id = location[1];
                district_id = location[2];
            }

            const location_id = (department_id && province_id && district_id)
                ? [department_id, province_id, district_id]
                : [];

            return {
                department_id,
                province_id,
                district_id,
                location_id,
            };
        },
        normalizeAddressRecord(addr) {
            if (!addr) {
                return addr;
            }

            const parsedAddress = this.splitAddressReference(addr.address || '');
            const parsedFull = this.splitAddressReference(addr.full_address || '');
            const street = parsedAddress.street || parsedFull.street;
            const reference = (addr.reference || '').trim() || parsedAddress.reference || parsedFull.reference;
            const ubigeo = this.resolveAddressUbigeo(addr);

            return Object.assign({}, addr, {
                address: street,
                reference,
                full_address: this.composeFullAddress(street, reference),
                department_id: ubigeo.department_id,
                province_id: ubigeo.province_id,
                district_id: ubigeo.district_id,
                location_id: ubigeo.location_id,
            });
        },
        normalizeAddressList(addresses) {
            if (!Array.isArray(addresses)) {
                return [];
            }

            return addresses.map(item => this.normalizeAddressRecord(item));
        },
        getAddressStreet(addr) {
            if (!addr) {
                return '';
            }

            const parsed = this.splitAddressReference(addr.address || '');
            if (parsed.street) {
                return parsed.street;
            }

            return this.splitAddressReference(addr.full_address || '').street;
        },
        getAddressReference(addr) {
            if (!addr) {
                return '';
            }

            const explicit = (addr.reference || '').trim();
            if (explicit) {
                return explicit;
            }

            return this.splitAddressReference(addr.full_address || addr.address || '').reference;
        },
        getAddressLocationLabel(addr) {
            if (!addr) {
                return '';
            }

            const explicitLabels = [
                addr.department_name || addr.department_description,
                addr.province_name || addr.province_description,
                addr.district_name || addr.district_description,
            ].filter(Boolean);

            if (explicitLabels.length === 3) {
                return explicitLabels.map(label => String(label).toUpperCase()).join(' / ');
            }

            const ubigeo = this.resolveAddressUbigeo(addr);
            if (!ubigeo.department_id && !ubigeo.province_id && !ubigeo.district_id) {
                return '';
            }

            const matchId = (left, right) => String(left || '') === String(right || '');
            const dept = this.departments.find(item => matchId(item.value, ubigeo.department_id));
            const prov = dept && (dept.children || []).find(item => matchId(item.value, ubigeo.province_id));
            const dist = prov && (prov.children || []).find(item => matchId(item.value, ubigeo.district_id));
            const parts = [];

            if (dept && dept.label) {
                parts.push(String(dept.label).toUpperCase());
            }
            if (prov && prov.label) {
                parts.push(String(prov.label).toUpperCase());
            }
            if (dist && dist.label) {
                parts.push(String(dist.label).toUpperCase());
            }

            if (parts.length > 0) {
                return parts.join(' / ');
            }

            if (ubigeo.district_id) {
                return String(ubigeo.district_id);
            }

            return '';
        },
        getAddressSecondaryLine(addr) {
            const reference = (this.getAddressReference(addr) || '').trim();
            const ubigeo = (this.getAddressLocationLabel(addr) || '').trim();

            if (reference && ubigeo) {
                return `${reference} - ${ubigeo}`;
            }

            return reference || ubigeo;
        },
        getAddressTitle(addr, index) {
            if (!addr) return 'Dirección';
            const text = this.getAddressStreet(addr);
            if (!text) return 'Dirección ' + ((index || 0) + 1);
            const firstPart = text.split(',')[0].trim();
            if (firstPart.length <= 42) return firstPart;
            return firstPart.substring(0, 42) + '…';
        },
        getAddressDetail(addr) {
            if (!addr) return '';
            const lines = [];
            const location = this.getAddressLocationLabel(addr);

            if (location) {
                lines.push(location);
            }

            const reference = this.getAddressReference(addr);
            if (reference) {
                lines.push('Ref: ' + reference);
            }

            return lines.join(' · ');
        },
        selectAddressInList(addressId) {
            this.selectedAddressId = addressId;
            this.addressListMenuOpen = null;
        },
        confirmChooseAddress() {
            const addr = this.userAddresses.find(item => item.id === this.selectedAddressId);
            if (!addr) {
                return;
            }

            this.applyAddressToModal(addr);
            this.addressModalMode = 'edit';
            this.editingAddressId = addr.id;
            this.userDefaultAddress = this.normalizeAddressRecord(addr);
            this.form_contact.address = this.composeFullAddress(
                this.getAddressStreet(addr),
                this.getAddressReference(addr)
            );
            this.closeAddressListModal();
            this.checkDeliveryZone();

            if (this.user && this.user.id) {
                this.saveShippingAddress();
                return;
            }

            this.saveGuestAddressesDraft();
            if (this.isGuestCheckoutActive) {
                this.syncGuestFormToDocument();
                this.saveGuestFormDraft();
            }
        },
        openAddressMapModal(mode = 'add', address = null, returnToList = null) {
            this.addressModalMode = mode;
            this.editingAddressId = (mode === 'edit' && address && address.id) ? address.id : null;
            const listWasOpen = jQuery('#addressListModal').hasClass('show');
            this.addressMapReturnToList = returnToList === null
                ? (listWasOpen || !!(this.user && this.user.id))
                : !!returnToList;
            this.resetAddressModalForm(mode, address);

            if (mode === 'edit' && address && address.latitude != null && address.longitude != null) {
                this.lastGeocodedLat = Number(address.latitude);
                this.lastGeocodedLng = Number(address.longitude);
            } else {
                this.lastGeocodedLat = null;
                this.lastGeocodedLng = null;
            }

            this.showAddressMapModal();
        },
        showAddressMapModal() {
            const $listModal = jQuery('#addressListModal');
            const $mapModal = jQuery('#addressModal');

            $mapModal.off('shown.bs.modal.map').on('shown.bs.modal.map', () => {
                this.ensureMapReady();
            });

            const openMap = () => {
                $mapModal.modal('show');
            };

            if ($listModal.hasClass('show')) {
                $listModal.one('hidden.bs.modal', openMap);
                $listModal.modal('hide');
                return;
            }

            openMap();
        },
        resetAddressModalForm(mode, address = null) {
            if (mode === 'edit' && address) {
                this.applyAddressToModal(address);
                return;
            }

            this.editingAddressId = null;
            this.addressModal.address = '';
            this.addressModal.reference = '';
            this.addressModal.latitude = -12.046374;
            this.addressModal.longitude = -77.042793;
            this.addressModal.preventSearch = false;
            this.selectedDepartment = '';
            this.selectedProvince = '';
            this.selectedDistrict = '';
            this.provinces = [];
            this.districts = [];
        },
        applyAddressToModal(address) {
            const normalized = this.normalizeAddressRecord(address);
            this.addressModal.address = normalized.address;
            this.addressModal.reference = normalized.reference;
            this.addressModal.preventSearch = false;

            if (address.latitude != null && address.longitude != null) {
                this.addressModal.latitude = Number(address.latitude);
                this.addressModal.longitude = Number(address.longitude);
            }

            const ubigeo = this.resolveAddressUbigeo(normalized);
            if (!ubigeo.department_id) {
                return;
            }

            const dept = this.departments.find(d => d.value === ubigeo.department_id);
            if (!dept) {
                return;
            }

            this.selectedDepartment = dept.value;
            this.provinces = dept.children || [];
            this.selectedProvince = '';
            this.districts = [];
            this.selectedDistrict = '';

            this.$nextTick(() => {
                const prov = this.provinces.find(p => p.value === ubigeo.province_id);
                if (!prov) {
                    return;
                }
                this.selectedProvince = prov.value;
                this.districts = prov.children || [];
                this.$nextTick(() => {
                    const dist = this.districts.find(d => d.value === ubigeo.district_id);
                    if (dist) {
                        this.selectedDistrict = dist.value;
                    }
                });
            });
        },
        editSavedAddress(address) {
            this.closeAddressListMenu();
            this.openAddressMapModal('edit', address);
        },
        deleteSavedAddress(address) {
            this.closeAddressListMenu();
            if (!address || !address.id) {
                return;
            }

            if (this.isGuestAddressCheckout()) {
                const deletedId = address.id;
                this.userAddresses = this.userAddresses.filter(item => item.id !== deletedId);

                if (this.userAddresses.length === 0) {
                    this.selectedAddressId = null;
                    this.editingAddressId = null;
                    this.userDefaultAddress = null;
                    this.form_contact.address = '';
                    this.addressModal.address = '';
                    this.addressModal.reference = '';
                } else {
                    this.selectedAddressId = this.userAddresses[0].id;
                    const active = this.userAddresses[0];
                    this.userDefaultAddress = active;
                    this.form_contact.address = active.full_address || active.address || '';
                }

                this.saveGuestAddressesDraft();
                this.checkDeliveryZone();
                return;
            }

            if (!this.user || !this.user.id) {
                return;
            }

            const deletedId = address.id;
            const previousAddresses = this.userAddresses.slice();
            const previousSelectedId = this.selectedAddressId;
            const previousDefault = this.userDefaultAddress;
            const previousFormAddress = this.form_contact.address;

            this.userAddresses = this.userAddresses.filter(item => item.id !== deletedId);
            this.selectedAddressId = this.userAddresses.length ? this.userAddresses[0].id : null;

            const url = window.__routes?.shipping_address_delete || '/ecommerce/shipping-address';
            axios.delete(url, {
                data: { address_id: deletedId },
                ...this.getHeaderConfig(),
            }).then(response => {
                if (response.data && response.data.success) {
                    this.userAddresses = Array.isArray(response.data.addresses)
                        ? this.normalizeAddressList(response.data.addresses)
                        : [];

                    if (this.userAddresses.length === 0) {
                        this.selectedAddressId = null;
                        this.editingAddressId = null;
                        this.userDefaultAddress = null;
                        this.form_contact.address = '';
                        this.addressModal.address = '';
                        this.addressModal.reference = '';
                        if (this.user) {
                            this.user.address = '';
                        }
                        this.checkDeliveryZone();
                        return;
                    }

                    if (this.selectedAddressId && !this.userAddresses.some(item => item.id === this.selectedAddressId)) {
                        this.selectedAddressId = this.userAddresses[0].id;
                    }
                    if (response.data.address) {
                        this.userDefaultAddress = this.normalizeAddressRecord(response.data.address);
                        let fullAddress = this.userDefaultAddress.full_address || this.userDefaultAddress.address || '';
                        this.form_contact.address = fullAddress;
                    }
                    return;
                }

                this.userAddresses = previousAddresses;
                this.selectedAddressId = previousSelectedId;
                this.userDefaultAddress = previousDefault;
                this.form_contact.address = previousFormAddress;
            }).catch(error => {
                console.error('No se pudo eliminar la dirección', error);
                this.userAddresses = previousAddresses;
                this.selectedAddressId = previousSelectedId;
                this.userDefaultAddress = previousDefault;
                this.form_contact.address = previousFormAddress;
            });
        },
        ensureMapReady() {
            if (typeof google === 'undefined') {
                return;
            }
            if (!this.map) {
                this.initMap();
                return;
            }
            google.maps.event.trigger(this.map, 'resize');
            this.syncMapToMarker();
        },
        closeAddressModal() {
            jQuery('#addressModal').modal('hide');
            this.destroyMap();
            this.addressSuggestions = [];
            this.highlightedIndex = -1;
        },
        confirmAddress() {
            const street = (this.addressModal.address || '').trim();
            const reference = (this.addressModal.reference || '').trim();
            this.form_contact.address = this.composeFullAddress(street, reference);
            const returnToList = this.addressMapReturnToList;
            const isCreatingNew = this.addressModalMode === 'add';
            this.closeAddressModal();
            this.checkDeliveryZone();

            this.saveShippingAddress(() => {
                if (returnToList) {
                    this.openAddressListModal();
                }
            }, isCreatingNew);

            if (this.showGuestForm && !this.isLoggedIn) {
                this.syncGuestFormToDocument();
                this.saveGuestFormDraft();
            }
        },
        syncMapToMarker() {
            if (!this.map || !this.marker) {
                return;
            }
            const pos = {
                lat: Number(this.addressModal.latitude),
                lng: Number(this.addressModal.longitude),
            };
            this.marker.setPosition(pos);
            this.map.setCenter(pos);
        },
        destroyMap() {
            if (this.mapListeners && this.mapListeners.length) {
                this.mapListeners.forEach(listener => google.maps.event.removeListener(listener));
            }
            this.mapListeners = [];
            if (this.marker) {
                this.marker.setMap(null);
            }
            this.map = null;
            this.marker = null;
            this.geocoder = null;
            this.mapGeocodeEnabled = false;
            this.mapGeocodeRequestId = 0;
            this.lastGeocodedLat = null;
            this.lastGeocodedLng = null;
        },
        initMap() {
            const mapElement = document.getElementById('map');
            if (!mapElement || typeof google === 'undefined') {
                return;
            }

            this.destroyMap();

            const defaultLocation = {
                lat: Number(this.addressModal.latitude),
                lng: Number(this.addressModal.longitude),
            };

            this.map = new google.maps.Map(mapElement, {
                center: defaultLocation,
                zoom: 16,
                disableDefaultUI: true,
                zoomControl: true,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true,
                clickableIcons: false,
                gestureHandling: 'greedy',
            });

            this.marker = new google.maps.Marker({
                position: defaultLocation,
                map: this.map,
                draggable: true,
            });

            this.geocoder = new google.maps.Geocoder();
            this.mapListeners = [];
            this.mapGeocodeEnabled = false;

            this.mapListeners.push(this.marker.addListener('dragend', () => {
                this.onMarkerPositionChanged(true);
            }));

            this.mapListeners.push(this.map.addListener('click', (event) => {
                this.marker.setPosition(event.latLng);
                this.onMarkerPositionChanged(true);
            }));

            this.mapListeners.push(this.map.addListener('dragend', () => {
                this.onMarkerPositionChanged(true);
            }));

            this.mapListeners.push(this.map.addListener('idle', () => {
                this.onMarkerPositionChanged(true);
            }));

            google.maps.event.addListenerOnce(this.map, 'idle', () => {
                google.maps.event.trigger(this.map, 'resize');
                this.syncCoordsFromMarker();

                const saved = this.userDefaultAddress;
                const hasSavedCoords = saved && saved.latitude != null && saved.longitude != null;

                if (!hasSavedCoords && navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const userLocation = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                            };
                            this.marker.setPosition(userLocation);
                            this.map.setCenter(userLocation);
                            this.mapGeocodeEnabled = true;
                            this.lastGeocodedLat = null;
                            this.lastGeocodedLng = null;
                            this.onMarkerPositionChanged(true);
                        },
                        (error) => {
                            console.error('Error obteniendo la ubicación actual:', error);
                            this.mapGeocodeEnabled = true;
                        }
                    );
                    return;
                }

                if (hasSavedCoords) {
                    this.lastGeocodedLat = Number(saved.latitude);
                    this.lastGeocodedLng = Number(saved.longitude);
                }
                this.mapGeocodeEnabled = true;
            });
        },
        getMarkerCoords() {
            if (!this.marker) {
                return null;
            }
            const pos = this.marker.getPosition();
            return {
                lat: Math.round(pos.lat() * 1e6) / 1e6,
                lng: Math.round(pos.lng() * 1e6) / 1e6,
            };
        },
        syncCoordsFromMarker() {
            const coords = this.getMarkerCoords();
            if (!coords) {
                return;
            }
            this.addressModal.latitude = coords.lat;
            this.addressModal.longitude = coords.lng;
        },
        onMarkerPositionChanged(updateAddressField) {
            this.syncCoordsFromMarker();
            if (!this.mapGeocodeEnabled || !updateAddressField) {
                return;
            }
            this.reverseGeocodeFromMarker(true);
        },
        reverseGeocodeFromMarker(updateAddressField = true) {
            if (!this.marker || !this.geocoder) {
                return;
            }

            const coords = this.getMarkerCoords();
            if (!coords) {
                return;
            }

            const { lat, lng } = coords;
            this.addressModal.latitude = lat;
            this.addressModal.longitude = lng;

            if (!updateAddressField) {
                return;
            }

            if (this.lastGeocodedLat === lat && this.lastGeocodedLng === lng) {
                return;
            }

            const requestId = ++this.mapGeocodeRequestId;
            this.isGeocodingAddress = true;
            this.addressModal.preventSearch = true;

            this.geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                if (requestId !== this.mapGeocodeRequestId) {
                    return;
                }

                this.isGeocodingAddress = false;
                if (status === google.maps.GeocoderStatus.OK && results[0]) {
                    this.addressModal.address = results[0].formatted_address;
                    this.extractAndSetUbigeoFromComponents(results[0].address_components);
                    this.lastGeocodedLat = lat;
                    this.lastGeocodedLng = lng;
                }
                setTimeout(() => {
                    this.addressModal.preventSearch = false;
                }, 400);
            });
        },
        getAddressFromLatLng(latLng) {
            if (!this.geocoder) return

            this.geocoder.geocode({'location': latLng}, (results, status) => {
                if (status === 'OK' && results[0]) {
                    this.addressModal.address = results[0].formatted_address
                }
            })
        },
        searchAddressInMap(address) {
            if (!this.geocoder || !this.map) {
                console.warn('Geocoder o Map no inicializados');
                return;
            }

            console.log('Buscando dirección:', address);
            this.geocoder.geocode({'address': address}, (results, status) => {
                if (status === 'OK' && results[0]) {
                    console.log('Dirección encontrada:', results[0].formatted_address);
                    const location = results[0].geometry.location;
                    this.addressModal.latitude = location.lat();
                    this.addressModal.longitude = location.lng();

                    this.map.setCenter(location);
                    this.map.setZoom(16);
                    if (this.marker) {
                        this.marker.setPosition(location);
                        this.mapGeocodeEnabled = true;
                        this.lastGeocodedLat = null;
                        this.lastGeocodedLng = null;
                        this.onMarkerPositionChanged(true);
                    }
                } else {
                    console.warn('Dirección no encontrada. Estado:', status);
                }
            })
        },
        async getItemsDocument() {
            let rec = await this.records.map((item) => {
                let sale_unit_price = 0
                let total_exonerated = 0
                let total_igv = 0
                let total_val = 0
                let total = 0
                // ########## INICIO CAMBIO AFECTACIÓN IVA
                let percentage_igv = 16
                // ######### FIN CAMBIO AFECTACIÓN IVA
                let nombre_producto_pdf = item.promotion_id ? item.description : null

                if (item.sale_affectation_igv_type_id === '10') {
                    if(item.currency_type_id === 'USD') {
                        sale_unit_price = (parseFloat(item.sale_unit_price) * this.exchange_rate_sale).toFixed(2)
                    } else {
                        sale_unit_price = item.sale_unit_price
                    }

                    let unit_value = sale_unit_price / (1 + percentage_igv / 100)
                    total_igv = item.cantidad * parseFloat(sale_unit_price - unit_value)
                    total = (item.cantidad * sale_unit_price)
                    total_val = (unit_value * item.cantidad)

                    return {
                        "codigo_interno": (item.internal_id) ? item.internal_id:"",
                        "descripcion": item.description,
                        "codigo_producto_sunat": "",
                        "unidad_de_medida": item.unit_type_id,
                        "cantidad": item.cantidad,
                        "valor_unitario": unit_value,
                        "codigo_tipo_precio": "01",
                        "precio_unitario": sale_unit_price,
                        "codigo_tipo_afectacion_igv": "10",
                        "total_base_igv": total_val,
                        "porcentaje_igv": percentage_igv,
                        "total_igv": total_igv,
                        "total_impuestos": total_igv,
                        "total_valor_item": total_val,
                        "total_item": total,
                        "actualizar_descripcion": false,
                        "nombre_producto_pdf": nombre_producto_pdf
                    }
                }

                if (item.sale_affectation_igv_type_id === '20') {
                    if(item.currency_type_id === 'USD') {
                        sale_unit_price = (parseFloat(item.sale_unit_price) * this.exchange_rate_sale).toFixed(2)
                    } else {
                        sale_unit_price = item.sale_unit_price
                    }

                    let unit_value = parseFloat(sale_unit_price)
                    total_igv = 0
                    total = (parseFloat(item.cantidad) * parseFloat(sale_unit_price))
                    total_val = (parseFloat(unit_value) * parseFloat(item.cantidad))

                    return {
                        "codigo_interno": (item.internal_id) ? item.internal_id:"",
                        "descripcion": item.description,
                        "codigo_producto_sunat": "",
                        "unidad_de_medida": item.unit_type_id,
                        "cantidad": item.cantidad,
                        "valor_unitario": unit_value,
                        "codigo_tipo_precio": "01",
                        "precio_unitario": sale_unit_price,
                        "codigo_tipo_afectacion_igv": "20",
                        "total_base_igv": total_val,
                        "porcentaje_igv": percentage_igv,
                        "total_igv": 0,
                        "total_impuestos": 0,
                        "total_valor_item": total_val,
                        "total_item": total,
                        "actualizar_descripcion": false,
                        "nombre_producto_pdf": nombre_producto_pdf
                    }
                }
            })

            if (this.deliveryZone && parseFloat(this.deliveryZone.price) > 0) {
                const delivery_price  = parseFloat(this.deliveryZone.price);
                // ########## INICIO CAMBIO AFECTACIÓN IVA
                const percentage_igv = 16;
                // ######### FIN CAMBIO AFECTACIÓN IVA
                const unit_value   = delivery_price / (1 + percentage_igv / 100);
                const igv_val      = delivery_price - unit_value;
                rec.push({
                    "codigo_interno":              "DELIVERY-ECOM",
                    "descripcion":                 "Costo de Envío - " + this.deliveryZone.name,
                    "codigo_producto_sunat":       "",
                    "unidad_de_medida":            "ZZ",
                    "cantidad":                    1,
                    "valor_unitario":              parseFloat(unit_value.toFixed(6)),
                    "codigo_tipo_precio":          "01",
                    "precio_unitario":             delivery_price,
                    "codigo_tipo_afectacion_igv":  "10",
                    "total_base_igv":              parseFloat(unit_value.toFixed(2)),
                    "porcentaje_igv":              percentage_igv,
                    "total_igv":                   parseFloat(igv_val.toFixed(2)),
                    "total_impuestos":             parseFloat(igv_val.toFixed(2)),
                    "total_valor_item":            parseFloat(unit_value.toFixed(2)),
                    "total_item":                  delivery_price,
                    "actualizar_descripcion":      false,
                    "nombre_producto_pdf":         this.deliveryZone.name
                });
            }

            return rec
        },
        initForm() {
            this.errors = {}
            this.user = window.__ecommerce_config?.user || this.user || {};

            // Cotización (quote_only / hybrid) aplica también a invitados
            if (this.quotationEnabled && this.quotationMode === 'quote_only') {
                this.checkoutIntent = 'quote';
                this.checkoutIntentChosen = true;
                this.checkoutIntentModalVisible = false;
            }

            if (!this.user || !this.user.id) {
                return false;
            }

            this.form_document = {
                "acciones": {
                    "enviar_email": true,
                    "formato_pdf": "a4"
                },
                "serie_documento": "",
                "numero_documento": "#",
                "fecha_de_emision": moment().format('YYYY-MM-DD'),
                "hora_de_emision": moment().format('HH:mm:ss'),
                "codigo_tipo_operacion": "0101",
                // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
                "codigo_tipo_documento": "80",
                // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
                "codigo_tipo_moneda": "VES",
                "fecha_de_vencimiento": moment().format('YYYY-MM-DD'),
                "datos_del_cliente_o_receptor": {
                    "codigo_tipo_documento_identidad": "0",
                    "numero_documento": "0",
                    "apellidos_y_nombres_o_razon_social": this.user.name,
                    "codigo_pais": "VE",
                    "ubigeo": "000619",
                    "direccion": this.user.address,
                    "correo_electronico": this.user.email,
                    "telefono": this.user.telephone
                },
                "totales": {},
                "items": [],
            }

            const savedAddress = this.userDefaultAddress
            this.form_contact.address = (savedAddress && (savedAddress.full_address || savedAddress.address))
                || this.user.address
                || ''
            this.form_contact.telephone = this.user.telephone || ''

            if (this.form_document.datos_del_cliente_o_receptor) {
                this.form_document.datos_del_cliente_o_receptor.direccion = this.form_contact.address
                this.form_document.datos_del_cliente_o_receptor.telefono = this.form_contact.telephone
            }

            this.preloadQuotationContact();
            if (this.quotationEnabled && this.quotationMode === 'quote_and_sell') {
                let restored = null;
                try {
                    restored = sessionStorage.getItem('ecommerce_checkout_intent');
                    if (restored === 'quote') {
                        sessionStorage.removeItem('ecommerce_checkout_intent');
                    }
                } catch (e) { /* ignore */ }
                if (restored === 'quote' && this.user && this.user.id) {
                    this.checkoutIntent = 'quote';
                    this.checkoutIntentChosen = true;
                    this.checkoutIntentModalVisible = false;
                } else if (!this.checkoutIntentChosen && this.records.length > 0) {
                    // Mantener modal visible decidido en boot síncrono
                    this.checkoutIntentModalVisible = true;
                }
            }

            this.optionDocument()
            // Aplicar defaults según configuración de documentos electrónicos
            this.applyDocumentDefaults()
        },
        // Establece el tipo de documento y los datos del cliente según la configuración de documentos electrónicos.
        // Si está desactivado: fuerza nota de venta (código 80) con los datos del usuario.
        // Si está activado: usa Nota de venta para Cédula y Factura para RIF.
        applyDocumentDefaults() {
            if (!this.enable_electronic_documents) {
                const userNumber = (this.user && this.user.number) ? String(this.user.number).trim() : '0';
                this.form_document.codigo_tipo_documento = '80';
                this.typeDocuments = '0';
                this.numberDocument = userNumber;
                if (this.form_document.datos_del_cliente_o_receptor) {
                    this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = '0';
                    this.form_document.datos_del_cliente_o_receptor.numero_documento = userNumber;
                }
                this.typeDocumentList = this.getIdentityDocumentTypes(['0']);
                return;
            }

            // Modo electrónico: inferir tipo según longitud del número del usuario
            if (!this.user || !this.user.number) return;
            const numStr = String(this.user.number).trim();

            if (numStr.length === 8) {
                // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                // Cédula → Nota de venta
                this.form_document.codigo_tipo_documento = '80';
                // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                this.typeDocuments = '1';
                this.numberDocument = numStr;
                if (this.form_document.datos_del_cliente_o_receptor) {
                    this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = '1';
                    this.form_document.datos_del_cliente_o_receptor.numero_documento = numStr;
                }
                this.typeDocumentList = this.getIdentityDocumentTypes(['1']);
            } else if (numStr.length === 11) {
                // RIF → Factura
                this.form_document.codigo_tipo_documento = '01';
                this.typeDocuments = '6';
                this.numberDocument = numStr;
                if (this.form_document.datos_del_cliente_o_receptor) {
                    this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = '6';
                    this.form_document.datos_del_cliente_o_receptor.numero_documento = numStr;
                }
                this.typeDocumentList = this.getIdentityDocumentTypes(['6']);
            }
        },
        deleteItem(id, index) {
            this.records.splice(index, 1)
            let array = localStorage.getItem('products_cart');
            array = JSON.parse(array);
            let indexFound = array.findIndex(x => x.id == id)
            array.splice(indexFound, 1);
            localStorage.setItem('products_cart', JSON.stringify(array));

            this.calculateSummary()
        },
        clearShoppingCart() {
            this.errors = {}
            this.records_old = this.records
            this.records = []
            localStorage.setItem('products_cart', JSON.stringify([]))

            this.summary = {
                subtotal: '0.0',
                tax: '0.0',
                total: '0.00',
                total_taxed: '0.0',
                total_value: '0.0',
                total_exonerated: '0.0',
                total_igv: '0.0'
            }
            this.payment_cash.amount = '0.00'
            location.reload()
        },
        calculateSummary() {
            let total_taxed = 0
            let total_value = 0
            let total_exonerated = 0
            let total_igv = 0
            let total = 0

            this.records.forEach(function (item) {
                let unit_price = item.sub_total
                let unit_value = unit_price
                // ########## INICIO CAMBIO AFECTACIÓN IVA
                let percentage_igv = 16
                // ######### FIN CAMBIO AFECTACIÓN IVA

                if (item.sale_affectation_igv_type_id === '10') {
                    unit_value = item.sub_total / (1 + percentage_igv / 100)
                    total_taxed += parseFloat(unit_value)
                    total_igv += parseFloat(unit_price - unit_value)
                }
                if (item.sale_affectation_igv_type_id === '20') {
                    total_exonerated += parseFloat(unit_value)
                }

                total_value = total_taxed + total_exonerated
                total += parseFloat(unit_price)
            })

            this.summary.total_taxed = total_taxed.toFixed(2)
            this.summary.total_exonerated = total_exonerated.toFixed(2)
            this.summary.total_igv = total_igv.toFixed(2)
            this.summary.total_value = total_value.toFixed(2)

            let computedTotal = total;
            if (this.appliedCoupon && this.appliedCoupon.discount) {
                computedTotal = Math.max(0, computedTotal - parseFloat(this.appliedCoupon.discount));
            }
            computedTotal = Math.max(0, computedTotal - parseFloat(this.appliedCampaignDiscount || 0));

            let deliveryPrice = (this.deliveryZone && this.deliveryZone.price) ? parseFloat(this.deliveryZone.price) : 0;
            // Si el modo es recojo en tienda, no se cobra delivery
            if (this.isPickupMode || (this.appliedCoupon && this.appliedCoupon.free_shipping)) {
                deliveryPrice = 0;
            }
            computedTotal += deliveryPrice;
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            let deliveryIgv = parseFloat((deliveryPrice / 1.16 * 0.16).toFixed(2));
            // ######### FIN CAMBIO AFECTACIÓN IVA
            this.summary.delivery         = deliveryPrice.toFixed(2);
            this.summary.delivery_igv     = deliveryIgv.toFixed(2);
            this.summary.total            = computedTotal.toFixed(2)
            this.aux_totals               = Object.assign({}, this.summary)

            $("#total_amount").data('total', this.summary.total);

            this.form_document.codigo_tipo_documento = null
            this.optionDocument()
            if (this.isGuestCheckoutActive) {
                this.enforceGuestHighAmountIdentityRule();
                this.applyGuestDocumentDefaults();
            } else {
                this.applyDocumentDefaults();
            }

            this.payment_cash.amount = this.summary.total;
        },
        saveContactDataUser() {
            this.saveShippingAddress();
        },
        saveShippingAddress(onSuccess, forceCreate) {
            if (!this.user || !this.user.id) {
                this.saveGuestShippingAddressLocally(onSuccess, forceCreate);
                return;
            }

            const parsedForm = this.splitAddressReference(this.form_contact.address);
            const street = ((this.addressModal.address || parsedForm.street || this.form_contact.address) || '').trim();
            const reference = ((this.addressModal.reference || parsedForm.reference) || '').trim();

            if (!street) {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                return;
            }

            const url = window.__routes?.shipping_address || '/ecommerce/shipping-address';
            const isCreatingNew = forceCreate === true || this.addressModalMode === 'add';
            const payload = {
                address: street,
                reference: reference,
                full_address: this.composeFullAddress(street, reference),
                latitude: this.addressModal.latitude,
                longitude: this.addressModal.longitude,
                department_id: this.selectedDepartment || null,
                province_id: this.selectedProvince || null,
                district_id: this.selectedDistrict || null,
                telephone: this.form_contact.telephone || null,
            };

            if (!isCreatingNew && this.editingAddressId) {
                payload.address_id = this.editingAddressId;
            }

            axios.post(url, payload, this.getHeaderConfig())
                .then(response => {
                    if (response.data && response.data.success) {
                        if (Array.isArray(response.data.addresses)) {
                            this.userAddresses = this.normalizeAddressList(response.data.addresses);
                        }
                        if (response.data.address) {
                            this.userDefaultAddress = this.normalizeAddressRecord(response.data.address);
                            if (response.data.address.id) {
                                this.selectedAddressId = response.data.address.id;
                            }
                            if (this.userDefaultAddress.full_address) {
                                this.form_contact.address = this.userDefaultAddress.full_address;
                            }
                            if (this.user) {
                                this.user.address = this.form_contact.address;
                            }
                        }
                        if (isCreatingNew) {
                            this.editingAddressId = null;
                            this.addressModalMode = 'add';
                        } else if (response.data.address && response.data.address.id) {
                            this.editingAddressId = response.data.address.id;
                        }
                    }
                    if (typeof onSuccess === 'function') {
                        onSuccess();
                    }
                })
                .catch(error => {
                    console.error('No se pudo guardar la dirección de envío', error);
                    if (typeof onSuccess === 'function') {
                        onSuccess();
                    }
                });
        },
        clickSendWhatsapp(order_id) {
            window.open(`https://wa.me/${this.whatsappPhone}?text=${encodeURIComponent('Se ha generado un nuevo pedido con código nro. ' + order_id)}`, '_blank');
        },
        getWhatsappUrl(text) {
            return `https://wa.me/${this.whatsappPhone}?text=${encodeURIComponent(text)}`;
        },
        clickConsultWhatsappCart() {
            const lines = this.records.map((row) => {
                const lineTotal = (parseFloat(row.sale_unit_price) * parseFloat(row.cantidad)).toFixed(2);
                return `• ${row.description} x${row.cantidad} - ${row.currency_type_symbol}${lineTotal}`;
            });
            const text = `Buenas, deseo consultar/finalizar mi pedido:\n\n${lines.join('\n')}\n\n*Total: Bs. ${this.summary.total}*\n\n¿Podrían ayudarme a completar la compra?`;
            window.open(this.getWhatsappUrl(text), '_blank');
        },
        onAddressInput() {
            console.log('Input detectado:', this.addressModal.address);
        },
        selectAddressSuggestion(suggestion) {
            this.addressModal.address = suggestion.description;
            this.addressSuggestions = [];
            console.log('Dirección seleccionada:', this.addressModal.address);
        },
        fetchLocations() {
            return axios.get(window.__routes?.locations || '/locations/cascade')
                .then(response => {
                    this.departments = response.data;
                })
                .catch(error => {
                    console.error('Error fetching locations:', error);
                });
        },
        loadDefaultAddress() {
            const addr = this.normalizeAddressRecord(this.userDefaultAddress);
            if (!addr || (!addr.address && !addr.full_address)) return;

            this.addressModal.address = addr.address;
            this.addressModal.reference = addr.reference;

            if (addr.latitude != null && addr.longitude != null) {
                this.addressModal.latitude = Number(addr.latitude);
                this.addressModal.longitude = Number(addr.longitude);
                if (this.map && this.marker) {
                    const pos = {
                        lat: this.addressModal.latitude,
                        lng: this.addressModal.longitude,
                    };
                    this.marker.setPosition(pos);
                    this.map.setCenter(pos);
                }
            }

            if (!this.form_contact.address) {
                this.form_contact.address = addr.full_address || addr.address || '';
            }

            if (addr.phone && !this.form_contact.telephone) {
                this.form_contact.telephone = addr.phone;
            }

            const ubigeo = this.resolveAddressUbigeo(addr);
            if (!ubigeo.department_id) return;

            const dept = this.departments.find(d => d.value === ubigeo.department_id);
            if (!dept) return;

            this.selectedDepartment = dept.value;
            this.provinces = dept.children || [];
            this.selectedProvince = '';
            this.districts = [];
            this.selectedDistrict = '';

            if (!ubigeo.province_id) return;

            this.$nextTick(() => {
                const prov = this.provinces.find(p => p.value === ubigeo.province_id);
                if (!prov) return;

                this.selectedProvince = prov.value;
                this.districts = prov.children || [];
                this.selectedDistrict = '';

                if (!ubigeo.district_id) return;

                this.$nextTick(() => {
                    setTimeout(() => {
                        const dist = this.districts.find(d => d.value === ubigeo.district_id);
                        if (!dist) return;

                        this.selectedDistrict = dist.value;
                        this.checkDeliveryZone();
                    }, 100);
                });
            });
        },
        updateProvinces() {
            const department = this.departments.find(dep => dep.value === this.selectedDepartment);
            this.provinces = department ? department.children : [];
            this.selectedProvince = '';
            this.districts = [];
            this.selectedDistrict = '';
            this.deliveryZone = null;
            this.availableDeliveryZones = [];
            this.deliveryMessage = '';
            this.calculateSummary();
        },
        updateDistricts() {
            const province = this.provinces.find(prov => prov.value === this.selectedProvince);
            this.districts = province ? province.children : [];
            this.selectedDistrict = '';
            this.deliveryZone = null;
            this.availableDeliveryZones = [];
            this.deliveryMessage = '';
            this.calculateSummary();
        },
        async checkDeliveryZone() {
            if (!this.selectedDepartment) {
                this.deliveryZone           = null;
                this.availableDeliveryZones = [];
                this.deliveryMessage        = '';
                this.calculateSummary();
                return;
            }

            try {
                const params = {
                    department: this.selectedDepartment,
                    province:   this.selectedProvince   || undefined,
                    district:   this.selectedDistrict   || undefined,
                };
                const res = await axios.get('/ecommerce/delivery-zones/check', { params });

                if (res.data.found && res.data.zones && res.data.zones.length > 0) {
                    this.availableDeliveryZones = res.data.zones;
                    this.deliveryMessage        = '';
                    // Auto-seleccionar la primera zona disponible
                    this.deliveryZone = res.data.zones[0];
                } else if (res.data.configured === false) {
                    this.deliveryZone           = null;
                    this.availableDeliveryZones = [];
                    this.deliveryMessage        = '';
                } else {
                    this.deliveryZone           = null;
                    this.availableDeliveryZones = [];
                    this.deliveryMessage        = res.data.message || 'Lo sentimos, no contamos con delivery en tu zona por el momento.';
                }
            } catch (e) {
                this.deliveryZone           = null;
                this.availableDeliveryZones = [];
                this.deliveryMessage        = '';
            }

            this.calculateSummary();
        },
        // Cambia la zona de delivery seleccionada y recalcula los totales
        selectDeliveryZone(zone) {
            this.deliveryZone = zone;
            this.calculateSummary();
        },
        // Selecciona una sucursal de recojo en tienda
        selectPickupBranch(branch) {
            this.selectedPickupBranch = branch;
            this.calculateSummary();
        },
        // Alterna el switch "otra persona recibe el pedido" (solo envío a domicilio)
        toggleDeliveryContactOverride() {
            this.deliveryContactOverride = !this.deliveryContactOverride;

            if (this.deliveryContactOverride) {
                return;
            }

            // Al apagarlo se descartan los datos de la otra persona
            this.form_contact.receiver_name = '';
            this.form_contact.receiver_telephone = '';
        },
        // Alterna el modo de recojo en tienda y resetea la selección contraria
        togglePickupMode() {
            this.setPickupMode(!this.isPickupMode);
        },
        // Establece el modo de entrega (true = recojo en tienda, false = delivery)
        setPickupMode(value) {
            if (this.isPickupMode === value) return;
            this.isPickupMode = value;
            if (this.isPickupMode) {
                // En recojo no se piden datos de entrega: se descartan los de la otra persona
                if (this.deliveryContactOverride) {
                    this.deliveryContactOverride = false;
                    this.form_contact.receiver_name = '';
                    this.form_contact.receiver_telephone = '';
                }
                // Al activar recojo: limpiar zona de delivery
                this.deliveryZone = null;
                this.availableDeliveryZones = [];
                this.deliveryMessage = '';
                // Auto-seleccionar la primera sucursal si hay disponibles
                if (this.pickupBranches.length > 0) {
                    this.selectedPickupBranch = this.pickupBranches[0];
                }
            } else {
                this.selectedPickupBranch = null;
                this.pickupBranchQuery = '';
                this.checkDeliveryZone();
            }
            this.calculateSummary();
        },
        async applyCoupon() {
            if (!this.couponField || this.couponLoading) return;
            this.couponLoading = true;
            this.couponMessage = null;
            this.couponSuccessMessage = null;

            try {
                const subtotal = this.records.reduce((sum, item) => sum + parseFloat(item.sub_total || 0), 0);
                const payload = { code: this.couponField.trim().toUpperCase(), subtotal: subtotal };
                const res = await axios.post('/api/coupons/validate', payload, this.getHeaderConfig());
                if (res.data && res.data.success) {
                    const d = res.data.data;
                    this.couponField = d.code;
                    this.appliedCoupon = {
                        id: d.id,
                        code: d.code,
                        discount: parseFloat(d.discount),
                        free_shipping: d.free_shipping
                    };
                    this.calculateSummary();
                    this.couponSuccessMessage = `Cupón ${d.code} aplicado. Ahorras Bs. ${parseFloat(d.discount || 0).toFixed(2)}.`;
                } else {
                    this.couponMessage = (res.data && res.data.message) ? res.data.message : 'El cupón no es válido.';
                }
            } catch (err) {
                if (err.response && err.response.data && err.response.data.message) {
                    this.couponMessage = err.response.data.message;
                } else {
                    this.couponMessage = 'No fue posible validar el cupón.';
                }
            } finally {
                this.couponLoading = false;
            }
        },

        async validateDiscountCampaigns() {
            if (!this.records.length || this.isQuotationCheckout) {
                this.appliedCampaignDiscount = 0;
                this.campaignSavings = 0;
                this.appliedCampaigns = [];
                return;
            }

            try {
                const items = this.records.map(item => ({
                    item_id: item.id,
                    subtotal: parseFloat(item.sub_total || 0),
                    quantity: parseFloat(item.cantidad || 1),
                }));
                const response = await axios.post('/ecommerce/discount-campaigns/validate', { items }, this.getHeaderConfig());
                (response.data.items || []).forEach(detail => {
                    const item = this.records.find(row => Number(row.id) === Number(detail.item_id));
                    if (!item) return;
                    item.original_price = parseFloat(detail.base_unit_price || 0);
                    item.compare_at_price = detail.compare_at_price ? parseFloat(detail.compare_at_price) : null;
                    item.sale_unit_price = parseFloat(detail.final_unit_price || detail.base_unit_price || 0).toFixed(2);
                    item.sub_total = (parseFloat(item.sale_unit_price) * parseFloat(item.cantidad || 1)).toFixed(2);
                    item.discount_campaign_id = detail.campaign_id;
                    item.discount_campaign_name = detail.campaign;
                    item.campaign_discount_percent = parseFloat(detail.percentage || 0);
                    item.campaign_discount_embedded = !!detail.campaign_id;
                });
                this.campaignSavings = parseFloat(response.data.discount || 0);
                const allCampaignPricesEmbedded = this.records.every(item => {
                    const detail = (response.data.items || []).find(row => Number(row.item_id) === Number(item.id));
                    return !detail || (item.campaign_discount_embedded && Number(item.discount_campaign_id) === Number(detail.campaign_id));
                });
                this.appliedCampaignDiscount = allCampaignPricesEmbedded ? 0 : this.campaignSavings;
                this.appliedCampaigns = response.data.items || [];
                this.calculateSummary();
            } catch (error) {
                this.appliedCampaignDiscount = 0;
                this.campaignSavings = 0;
                this.appliedCampaigns = [];
            }
        },

        removeCoupon() {
            this.appliedCoupon = null;
            this.couponField = '';
            this.couponMessage = null;
            this.couponSuccessMessage = null;
            this.calculateSummary();
        },
    },
})

// Exponer la instancia globalmente para que los scripts inline del blade puedan accederla
window.app_cart = app_cart;
