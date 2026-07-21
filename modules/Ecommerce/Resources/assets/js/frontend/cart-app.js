// Cart Application - Ecommerce Module
// Main Vue instance for shopping cart detail page

let izipaySdkLoadPromise = null;
let izipayLoadedPublicKey = null;
const IZIPAY_KR_MAIN_SRC = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js';
const IZIPAY_KR_CSS_HREF = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css';
const IZIPAY_KR_EXT_SRC = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.js';
const MP_BRICK_HOST_ID = 'mp-brick-container';

var app_cart = new Vue({
    el: '#app',
    data: {
        form_contact: {
            address:   '',
            telephone:   '',
        },
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
            description: 'DNI'
        }, {
            id: '6',
            description: 'RUC'
        }],
        formIdentity: {
            identity_document_type_id: ''
        },
        records: [],
        records_old: [],
        couponField: '',
        couponMessage: null,
        couponLoading: false,
        appliedCoupon: null,
        order_generated: {},
        summary: {
            subtotal: '0.0',
            tax: '0.0',
            total: '0.0'
        },
        aux_totals: {},
        form_document: {},
        user: {},
        typeDocumentSelected: '',
        response_order_total:0,
        errors: {},
        exchange_rate_sale: '',
        typeDocuments: '',
        typeDocumentList: [],
        numberDocument: '',
        phone_whatsapp: window.__ecommerce_config?.phone_whatsapp || '',
        global_discount_type: window.__ecommerce_config?.global_discount_type || {},
        all_identity_document_types : [{id: '6', name: 'RUC'}, {id: '0', name: 'DOC'},{id: '4', name: 'CE'},{id: '1', name: 'DNI'}],
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

        // Controla si se emiten documentos electrónicos (factura/boleta) o solo notas de venta
        enable_electronic_documents: window.__ecommerce_config?.enable_electronic_documents || false,
        // Recojo en tienda
        enableStorePickup: window.__ecommerce_config?.enable_store_pickup || false,
        pickupBranches: window.__ecommerce_config?.pickup_branches || [],
        selectedPickupBranch: null,
        isPickupMode: false,
        // Métodos de pago adicionales
        enableCash: window.__ecommerce_config?.enable_cash || false,
        cashPaymentTitle: window.__ecommerce_config?.cash_payment_title || 'Pago contra entrega',
        cashPaymentDescription: window.__ecommerce_config?.cash_payment_description || '',
        cashPaymentPickupOnly: window.__ecommerce_config?.cash_payment_pickup_only || false,
        enableYape: window.__ecommerce_config?.enable_yape || false,
        enableTransfer: window.__ecommerce_config?.enable_transfer || false,
        
        enableIzipay: window.__ecommerce_config?.enable_izipay || false,
        titleIzipay: window.__ecommerce_config?.title_izipay || 'Pago con Izipay',
        descriptionIzipay: window.__ecommerce_config?.description_izipay || '',
        
        enableMp: window.__ecommerce_config?.enable_mp || false,
        titleMp: window.__ecommerce_config?.title_mp || 'Mercado Pago',
        descriptionMp: window.__ecommerce_config?.description_mp || '',
        
        enableCulqi: window.__ecommerce_config?.enable_culqi || false,
        titleCulqi: window.__ecommerce_config?.title_culqi || 'Tarjeta (VISA)',
        descriptionCulqi: window.__ecommerce_config?.description_culqi || '',

        acceptedTerms: false,
        processingPayment: false,
        thankYouUrl: null,
        
        mpScriptLoaded: false,
        mpBrickController: null,
        mpBrickReady: false,
        mpPreparedAmount: null,
        mpPreparedEmail: null,
        mpInstance: null,
        mpPreparePromise: null,
        mpPrepareTimer: null,
        krScriptLoaded: false,
        izipayPublicKey: null,
        izipayPublicKeyPromise: null,
    },
    computed: {
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
            if (num.length === 8)  return 'Boleta de Venta';
            if (num.length === 11) return 'Factura';
            return 'Nota de Venta';
        },
    },
    watch: {
        'addressModal.address': function(newValue) {
        },
        selectedPaymentMethod(val, oldVal) {
            // Mostrar u ocultar el widget de PayPal que está fuera del scope de Vue
            const el = document.getElementById('paypal-widget-container');
            if (el) el.style.display = (val === 'paypal') ? 'block' : 'none';

            if (val === 'mp') {
                this.scheduleMpBrickPrepare();
            } else if (oldVal === 'mp') {
                this.unmountMpBrick();
            } else if (val === 'izipay') {
                this.loadIzipaySDK().catch(() => {});
            }
        },
        'summary.total'() {
            if (this.selectedPaymentMethod === 'mp') {
                this.scheduleMpBrickPrepare();
            }
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

        jQuery(".input_quantity").change(function (e) {
            let value = parseFloat(jQuery(this).val())
            let id = jQuery(this).data('product')
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

        // Cargar ubicaciones y autocompletar si el usuario tiene una dirección guardada
        this.fetchLocations().then(() => {
            this.loadDefaultAddress();
        });

        if (this.enableMp) {
            this.preloadMpResources();
        }
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
                    || 'S/'
                return obj
            })
        }
        this.initForm();
    },
    methods: {
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
            this.form_document.datos_del_cliente_o_receptor.direccion = this.form_contact.address
            this.form_document.datos_del_cliente_o_receptor.telefono = this.form_contact.telephone
            this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = this.typeDocuments
            this.form_document.datos_del_cliente_o_receptor.numero_documento = this.numberDocument
            this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.typeDocuments
        },
        async getFormPaymentCash() {
            this.refreshSetDataCustomer()

            // Calcular la dirección de envio según el modo seleccionado
            let shippingAddress = '';
            if (this.isPickupMode && this.selectedPickupBranch) {
                shippingAddress = 'Recojo en tienda: ' + this.selectedPickupBranch.name +
                    (this.selectedPickupBranch.address ? ' — ' + this.selectedPickupBranch.address : '');
            } else {
                shippingAddress = this.form_contact.address || '';
            }

            let precio = Math.round(Number(this.summary.total) * 100).toFixed(2);
            let precio_culqi = Number(Number(this.summary.total).toFixed(2));
            return {
                producto: 'Compras Ecommerce Facturador Pro',
                precio: precio,
                precio_culqi: precio_culqi,
                customer: this.form_document.datos_del_cliente_o_receptor,
                items: this.records,
                purchase: await this.getDocument(),
                discount_coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                discount_coupon_id: this.appliedCoupon ? this.appliedCoupon.id : null,
                total_discount: this.appliedCoupon ? this.appliedCoupon.discount : 0,
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
        showSwalMessage(title, text, type){
            swal({
                title: title,
                text: text,
                type: type
            })
        },
        executePayment() {
            if (this.selectedPaymentMethod === 'culqi') {
                if (typeof execCulqi === 'function') execCulqi();
            } else if (this.selectedPaymentMethod === 'izipay') {
                this.execIzipay();
            } else if (this.selectedPaymentMethod === 'mp') {
                this.execMp();
            } else if (['cash', 'yape', 'transfer'].includes(this.selectedPaymentMethod)) {
                this.paymentCash();
            }
        },
        async paymentCash() {
            if(!this.form_document.codigo_tipo_documento) {
                return this.showSwalMessage('Ocurrió un error!', 'El campo tipo de comprobante es obligatorio', 'error')
            }

            if(!this.form_contact.address) {
                return this.showSwalMessage('Ocurrió un error!', 'El campo dirección es obligatorio', 'error')
            }

            if(!this.form_contact.telephone) {
                return this.showSwalMessage('Ocurrió un error!', 'El campo teléfono es obligatorio', 'error')
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

            this.processingPayment = true;

            let url_finally = window.__routes?.payment_cash || '/ecommerce/payment/cash';
            let response = await axios.post(url_finally, await this.getFormPaymentCash(), this.getHeaderConfig()).then(response => {
                    if (response.data.success) {
                        this.saveContactDataUser()
                        this.processingPayment = false
                        this.showPurchaseSuccess(response.data.order)
                    } else {
                        this.processingPayment = false
                    }
                }).catch(error => {
                    this.processingPayment = false
                    swal("Pago No realizado", 'Sucedió algo inesperado.', "error");
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data;
                    } else {
                        console.log(error);
                    }
                });
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
        buildMpBrickSettings(initData) {
            return {
                initialization: {
                    amount: Number(initData.amount).toFixed(2),
                    payer: { email: initData.email || '' },
                },
                customization: {
                    visual: { style: { theme: 'default' } },
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
                        const payload = { ...rawFormData, form_data: mpFormData };
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
                            swal("Pago Rechazado", response.data.message || 'No se pudo procesar el pago.', "error");
                            reject();
                        }
                    })
                    .catch(err => {
                        const validationMsg = err.response?.data?.message
                            || (err.response?.data?.errors && Object.values(err.response.data.errors).flat().join(' '))
                            || null;
                        this.detachMpBrickFromModal();
                        swal("Pago Fallido", validationMsg || 'Ocurrió un error con la pasarela.', "error");
                        console.log('MercadoPago payment error', err.response?.data || err);
                        reject();
                    });
            });
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
                this.mpInstance = new window.MercadoPago(publicKey, { locale: 'es-PE' });
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
                slot.appendChild(host);
            }
        },
        detachMpBrickFromModal() {
            const host = document.getElementById(MP_BRICK_HOST_ID);
            const stash = document.getElementById('mp-brick-stash');
            if (host && stash) {
                stash.appendChild(host);
            }
        },
        async execMp() {
            if (!this.form_document.codigo_tipo_documento || !this.form_contact.address || !this.form_contact.telephone) {
                return this.showSwalMessage('Ocurrió un error!', 'Complete sus datos y dirección antes de pagar', 'error');
            }
            if (this.records.length < 1){
                return this.showSwalMessage('Ocurrió un error!', 'No se han encontrado productos', 'error');
            }

            const initData = this.getMpBrickInitData();
            const needsRefresh = !this.mpBrickReady
                || this.mpPreparedAmount !== initData.amount
                || this.mpPreparedEmail !== initData.email;

            try {
                if (this.mpPreparePromise) {
                    await this.mpPreparePromise;
                }
                if (needsRefresh) {
                    await this.prepareMpBrick(true);
                }
            } catch (err) {
                console.error(err);
                return this.showSwalMessage('Error', 'No se pudo cargar el formulario de Mercado Pago.', 'error');
            }

            if (!this.mpBrickReady) {
                return this.showSwalMessage('Error', 'No se pudo cargar el formulario de Mercado Pago.', 'error');
            }

            swal({
                title: 'Pago Seguro con Mercado Pago',
                html: '<div id="mp-swal-slot" class="mp-swal-brick"></div>',
                width: 640,
                customClass: 'mp-payment-swal',
                showConfirmButton: false,
                showCloseButton: true,
                onOpen: () => {
                    this.attachMpBrickToModal();
                },
                onClose: () => {
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
            if (container) {
                container.style.display = 'none';
                container.innerHTML = '';
            }
        },
        async execIzipay() {
            if (!this.form_document.codigo_tipo_documento || !this.form_contact.address || !this.form_contact.telephone) {
                return this.showSwalMessage('Ocurrió un error!', 'Complete sus datos y dirección antes de pagar', 'error');
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

                            axios.post(window.__routes?.izipay_transaction || '/ecommerce/izipay/transaction', { uuid: uuid }, this.getHeaderConfig())
                            .then(res => {
                                if(res.data.success && res.data.paid) {
                                    this.hideIzipayPaymentHost();
                                    this.saveContactDataUser();
                                    this.showPurchaseSuccess(order);
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

                        // Estilos de Overlay (Visibilidad forzada)
                        container.style.position = 'fixed';
                        container.style.top = '50%';
                        container.style.left = '50%';
                        container.style.transform = 'translate(-50%, -50%)';
                        container.style.zIndex = '9999';
                        container.style.backgroundColor = 'white';
                        container.style.padding = '20px';
                        container.style.boxShadow = '0 4px 15px rgba(0,0,0,0.5)';
                        container.style.display = 'block';

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
        /**
         * Modal de éxito unificado (Yape / efectivo / transferencia / MP / Izipay).
         * Mismo swal del sistema: título, aviso de correo y botón OK.
         */
        showPurchaseSuccess(order, thankYouUrl = null) {
            this.processingPayment = false;
            this.response_order_total = order ? order.total : 0;
            this.thankYouUrl = thankYouUrl || null;
            if (!this.thankYouUrl && order && order.external_id && window.__routes && window.__routes.thank_you) {
                this.thankYouUrl = window.__routes.thank_you.replace('EXTERNAL_ID', order.external_id);
            }
            this.clearCartSilently();
            swal({
                title: '¡Gracias por su pago!',
                text: 'En breve le enviaremos un correo electrónico con los detalles de su compra',
                type: 'success',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
                allowEscapeKey: false,
            }).then(() => {
                this.goToThankYou();
            });
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
            jQuery("#total_amount").data('total', '0.00');
        },
        goToThankYou() {
            if (this.thankYouUrl) {
                window.location = this.thankYouUrl;
            } else {
                this.redirectHome();
            }
        },
        getHeaderConfig() {
            let token = this.user.api_token
            let axiosConfig = {
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${token}`
                }
            };
            return axiosConfig;
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
            if (doc.codigo_tipo_documento == '01') {
                doc.serie_documento = 'F001';
            } else if (doc.codigo_tipo_documento == '03') {
                doc.serie_documento = 'B001';
            } else {
                doc.serie_documento = null;
            }
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
                montoCalculado = montoEntrada / 1.18;
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
            let delivery_base  = parseFloat((delivery_price / 1.18).toFixed(2));
            let delivery_igv   = parseFloat((delivery_price - delivery_base).toFixed(2));

            let total_operaciones_gravadas = base_antes + delivery_base;
            let total_igv                  = igv_antes  + delivery_igv;
            let total_venta                = total_operaciones_gravadas + total_igv;

            if (descuentos.length > 0 && this.global_discount_type) {
                if (this.global_discount_type.base == 1) {
                    total_operaciones_gravadas = parseFloat((total_operaciones_gravadas - total_descuentos_monto).toFixed(2));
                    total_igv   = parseFloat((total_operaciones_gravadas * 0.18).toFixed(2));
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
            if (this.user && this.user.id) {
                this.openAddressListModal();
                return;
            }
            this.openAddressMapModal('add', null, false);
        },
        openAddressListModal() {
            this.addressListMenuOpen = null;
            const active = this.userDefaultAddress;
            if (active && active.id && this.userAddresses.some(a => a.id === active.id)) {
                this.selectedAddressId = active.id;
            } else if (this.userAddresses.length) {
                this.selectedAddressId = this.userAddresses[0].id;
            } else {
                this.selectedAddressId = null;
            }
            jQuery('#addressListModal').modal('show');
        },
        closeAddressListModal() {
            jQuery('#addressListModal').modal('hide');
            this.addressListMenuOpen = null;
        },
        toggleAddressListMenu(addressId) {
            this.addressListMenuOpen = this.addressListMenuOpen === addressId ? null : addressId;
        },
        closeAddressListMenu() {
            this.addressListMenuOpen = null;
        },
        getAddressTitle(addr, index) {
            if (!addr) return 'Dirección';
            const text = (addr.address || addr.full_address || '').trim();
            if (!text) return 'Dirección ' + ((index || 0) + 1);
            const firstPart = text.split(',')[0].trim();
            if (firstPart.length <= 42) return firstPart;
            return firstPart.substring(0, 42) + '…';
        },
        getAddressDetail(addr) {
            if (!addr) return '';
            const lines = [];
            const street = (addr.address || addr.full_address || '').trim();
            if (street) lines.push(street);
            if (addr.reference) lines.push('Ref: ' + addr.reference);
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

            let fullAddress = addr.full_address || addr.address || '';
            if (addr.reference && fullAddress.indexOf('Ref:') === -1) {
                fullAddress += ' - Ref: ' + addr.reference;
            }

            this.form_contact.address = fullAddress;
            this.closeAddressListModal();
            this.checkDeliveryZone();
            this.saveShippingAddress();
        },
        openAddressMapModal(mode = 'add', address = null, returnToList = null) {
            this.addressModalMode = mode;
            this.editingAddressId = (mode === 'edit' && address && address.id) ? address.id : null;
            this.addressMapReturnToList = returnToList === null
                ? !!(this.user && this.user.id)
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
            this.addressModal.address = address.address || address.full_address || '';
            this.addressModal.reference = address.reference || '';
            this.addressModal.preventSearch = false;

            if (address.latitude != null && address.longitude != null) {
                this.addressModal.latitude = Number(address.latitude);
                this.addressModal.longitude = Number(address.longitude);
            }

            if (!address.department_id) {
                return;
            }

            const dept = this.departments.find(d => d.value === address.department_id);
            if (!dept) {
                return;
            }

            this.selectedDepartment = dept.value;
            this.provinces = dept.children || [];
            this.selectedProvince = '';
            this.districts = [];
            this.selectedDistrict = '';

            this.$nextTick(() => {
                const prov = this.provinces.find(p => p.value === address.province_id);
                if (!prov) {
                    return;
                }
                this.selectedProvince = prov.value;
                this.districts = prov.children || [];
                this.$nextTick(() => {
                    const dist = this.districts.find(d => d.value === address.district_id);
                    if (dist) {
                        this.selectedDistrict = dist.value;
                    }
                });
            });
        },
        editSavedAddress(address) {
            this.addressListMenuOpen = null;
            this.openAddressMapModal('edit', address);
        },
        deleteSavedAddress(address) {
            this.addressListMenuOpen = null;
            if (!address || !address.id || !this.user || !this.user.id) {
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
                        ? response.data.addresses
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
                        this.userDefaultAddress = response.data.address;
                        let fullAddress = response.data.address.full_address || response.data.address.address || '';
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
            let fullAddress = '';
            if (this.addressModal.address) {
                fullAddress = this.addressModal.address;
            }
            if (this.addressModal.reference) {
                fullAddress += ' - Ref: ' + this.addressModal.reference;
            }

            this.form_contact.address = fullAddress;
            const returnToList = this.addressMapReturnToList;
            const isCreatingNew = this.addressModalMode === 'add';
            this.closeAddressModal();
            this.checkDeliveryZone();

            this.saveShippingAddress(() => {
                if (returnToList) {
                    this.openAddressListModal();
                }
            }, isCreatingNew);
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
                zoomControl: false,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
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
                let percentage_igv = 18
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
                const percentage_igv = 18;
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
            this.user = window.__ecommerce_config?.user || {};
            if(!this.user){
                return false
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
                "codigo_tipo_documento": "03",
                "codigo_tipo_moneda": "PEN",
                "fecha_de_vencimiento": moment().format('YYYY-MM-DD'),
                "datos_del_cliente_o_receptor": {
                    "codigo_tipo_documento_identidad": "0",
                    "numero_documento": "0",
                    "apellidos_y_nombres_o_razon_social": this.user.name,
                    "codigo_pais": "PE",
                    "ubigeo": "150101",
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

            this.optionDocument()
            // Aplicar defaults según configuración de documentos electrónicos
            this.applyDocumentDefaults()
        },
        // Establece el tipo de documento y los datos del cliente según la configuración de documentos electrónicos.
        // Si está desactivado: fuerza nota de venta (código 80) con los datos del usuario.
        // Si está activado: infiere el tipo según la longitud del número (8 dígitos=boleta, 11=factura).
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
                // DNI → Boleta
                this.form_document.codigo_tipo_documento = '03';
                this.typeDocuments = '1';
                this.numberDocument = numStr;
                if (this.form_document.datos_del_cliente_o_receptor) {
                    this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = '1';
                    this.form_document.datos_del_cliente_o_receptor.numero_documento = numStr;
                }
                this.typeDocumentList = this.getIdentityDocumentTypes(['1']);
            } else if (numStr.length === 11) {
                // RUC → Factura
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
                let percentage_igv = 18

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

            let deliveryPrice = (this.deliveryZone && this.deliveryZone.price) ? parseFloat(this.deliveryZone.price) : 0;
            // Si el modo es recojo en tienda, no se cobra delivery
            if (this.isPickupMode) {
                deliveryPrice = 0;
            }
            computedTotal += deliveryPrice;
            let deliveryIgv = parseFloat((deliveryPrice / 1.18 * 0.18).toFixed(2));
            this.summary.delivery         = deliveryPrice.toFixed(2);
            this.summary.delivery_igv     = deliveryIgv.toFixed(2);
            this.summary.total            = computedTotal.toFixed(2)
            this.aux_totals               = Object.assign({}, this.summary)

            jQuery("#total_amount").data('total', this.summary.total);

            this.form_document.codigo_tipo_documento = null
            this.optionDocument()
            // Re-aplicar defaults tras cada cálculo para mantener el tipo forzado
            this.applyDocumentDefaults()

            this.payment_cash.amount = this.summary.total;
        },
        saveContactDataUser() {
            this.saveShippingAddress();
        },
        saveShippingAddress(onSuccess, forceCreate) {
            if (!this.user || !this.user.id) {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                return;
            }

            const street = this.addressModal.address || this.form_contact.address;
            if (!street) {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                return;
            }

            const url = window.__routes?.shipping_address || '/ecommerce/shipping-address';
            const isCreatingNew = forceCreate === true || this.addressModalMode === 'add';
            const payload = {
                address: this.addressModal.address || street,
                reference: this.addressModal.reference || '',
                full_address: this.form_contact.address || street,
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
                            this.userAddresses = response.data.addresses;
                        }
                        if (response.data.address) {
                            this.userDefaultAddress = response.data.address;
                            if (response.data.address.id) {
                                this.selectedAddressId = response.data.address.id;
                            }
                            if (response.data.address.full_address) {
                                this.form_contact.address = response.data.address.full_address;
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
            window.open(`https://wa.me/51${this.phone_whatsapp}?text=Se ha generado un nuevo pedido con código nro. ${order_id}`, '_blank');
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
            const addr = this.userDefaultAddress;
            if (!addr || (!addr.address && !addr.full_address)) return;

            this.addressModal.address = addr.address || addr.full_address || '';
            this.addressModal.reference = addr.reference || '';

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

            if (!addr.department_id) return;

            const dept = this.departments.find(d => d.value === addr.department_id);
            if (!dept) return;

            this.selectedDepartment = dept.value;
            this.provinces = dept.children || [];
            this.selectedProvince = '';
            this.districts = [];
            this.selectedDistrict = '';

            if (!addr.province_id) return;

            this.$nextTick(() => {
                const prov = this.provinces.find(p => p.value === addr.province_id);
                if (!prov) return;

                this.selectedProvince = prov.value;
                this.districts = prov.children || [];
                this.selectedDistrict = '';

                if (!addr.district_id) return;

                this.$nextTick(() => {
                    setTimeout(() => {
                        const dist = this.districts.find(d => d.value === addr.district_id);
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
        // Alterna el modo de recojo en tienda y resetea la selección contraria
        togglePickupMode() {
            this.setPickupMode(!this.isPickupMode);
        },
        // Establece el modo de entrega (true = recojo en tienda, false = delivery)
        setPickupMode(value) {
            if (this.isPickupMode === value) return;
            this.isPickupMode = value;
            if (this.isPickupMode) {
                // Al activar recojo: limpiar zona de delivery
                this.deliveryZone = null;
                this.availableDeliveryZones = [];
                this.deliveryMessage = '';
                // Auto-seleccionar la primera sucursal si hay disponibles
                if (this.pickupBranches.length > 0) {
                    this.selectedPickupBranch = this.pickupBranches[0];
                }
            } else {
                // Al desactivar recojo: limpiar sucursal y disparar búsqueda de zona
                this.selectedPickupBranch = null;
                this.checkDeliveryZone();
            }
            this.calculateSummary();
        },
        /**
         * Subtotal de ítems sin cupón ni delivery.
         * Debe coincidir con la base que usa calculateSummary al restar el descuento.
         */
        getTotalBeforeCoupon() {
            let total = 0;
            (this.records || []).forEach(function (item) {
                total += parseFloat(item.sub_total) || 0;
            });
            return Math.round(total * 100) / 100;
        },

        async applyCoupon() {
            if (!this.couponField || this.couponLoading) return;

            // Un solo cupón por carrito: bloquear reaplicación acumulativa
            if (this.appliedCoupon && this.appliedCoupon.code) {
                this.couponMessage = 'Ya tienes un cupón aplicado. Elimínalo para aplicar otro.';
                return;
            }

            this.couponLoading = true;
            this.couponMessage = null;

            try {
                const payload = {
                    code: this.couponField,
                    order_total: this.getTotalBeforeCoupon(),
                    coupon_already_applied: !!(this.appliedCoupon && this.appliedCoupon.code)
                };
                const res = await axios.post('/ecommerce/validate-coupon', payload, this.getHeaderConfig());
                if (res.data && res.data.success) {
                    const d = res.data.data;
                    this.appliedCoupon = {
                        id: d.id,
                        code: d.code,
                        discount: parseFloat(d.discount),
                        free_shipping: d.free_shipping
                    };
                    // Recalcular resumen una sola vez (items - descuento + delivery)
                    this.calculateSummary();
                    this.couponField = d.code || this.couponField;
                    this.couponMessage = null;
                } else {
                    this.couponMessage = (res.data && res.data.message) ? res.data.message : 'cupon no valido';
                }
            } catch (err) {
                if (err.response && err.response.data && err.response.data.message) {
                    this.couponMessage = err.response.data.message;
                } else {
                    this.couponMessage = 'cupon no valido';
                }
            } finally {
                this.couponLoading = false;
            }
        },

        removeCoupon() {
            this.appliedCoupon = null;
            this.couponField = '';
            this.couponMessage = null;
            this.calculateSummary();
        },
    },
})

// Exponer la instancia globalmente para que los scripts inline del blade puedan accederla
window.app_cart = app_cart;
