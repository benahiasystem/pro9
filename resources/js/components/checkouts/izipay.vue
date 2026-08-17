<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
    <div class="kr-izipay-container checkout-pay">
        <button
            type="button"
            class="checkout-pay__btn checkout-pay__btn--izipay"
            :disabled="disabled || loading || paying"
            @click.prevent="submit"
        >
            <span class="checkout-pay__label">
                <span v-if="loading || paying" class="checkout-pay__spinner"></span>
                {{ loading || paying ? 'Cargando…' : 'Pagar con izipay' }}
            </span>
            <span v-if="!loading && !paying" class="checkout-pay__amount">{{ formattedAmount }}</span>
        </button>
        <div class="kr-izipay-container-inner" >

        </div>
    </div>
</template>
<script>
import _ from 'lodash';

/**
 * form : {
 *  amount: 0,
 *  currency: 'VES',
 *  orderId: '',
 *  customer: {
 *   email: '',
 *   billingDetails: {
 *      firstName: '',
 *      lastName: '',
 *      phoneNumber: '',
 *      identityType: '',
 *      identityNumber: '',
 *      address:'',
 *      country: '',
 *      city: '',
 *      state: '',
 *      zipCode: ''
 *   }
 *  }
 * }
 */

export default {
    props: {
        form: {
            type: Object,
            required: true
        },
        isTenant: {
            type: Boolean,
            default: false
        },
        disabled: {
            type: Boolean,
            default: false
        },
        endpointPrefix: {
            type: String,
            default: '/payment-gateway/izipay'
        }
    },
    data() {
        return {
            publicKey: null,
            loading: true,
            paying: false,
            // los callbacks de KR se registran una sola vez, KR es un singleton global
            events_registered: false
        }
    },
    computed: {
        resource() {
            return this.endpointPrefix;
        },
        formattedAmount() {
            const amount = Number(this.form.amount || 0) / 100;
            const symbol = this.form.currency === 'USD' ? '$' : 'Bs.';
            return `${symbol} ${amount.toLocaleString('es-PE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        }
    },
    // computed: {
    //     resource() {
    //         return this.isTenant ? '/payment-gateway/izipay' : '/payment_configurations';
    //     }
    // },
    async created() {

        if (_.isNil(this.form)) {
            this.$message.error('No se han cargado información para crear pago con Izipay');
            return;
        }

        await this.loadConfiguration();

        if (!this.publicKey) {
            this.$message.error('No se encontró la llave pública de Izipay configurada');
            return;
        }

        // Cargar el script de Izipay si no está ya cargado
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = "https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js"
        script.setAttribute('kr-public-key', this.publicKey);
        // sin kr-post-url-success el resultado del pago llega por KR.onSubmit en lugar de un POST/redirect
        script.setAttribute('kr-popin', true)
        script.setAttribute('kr-language', "es-Es")
        document.head.appendChild(script);


        //Cargar estilos del checkout
        const link = document.createElement('link');
        const script_style = document.createElement('script');

        link.rel = 'stylesheet';
        link.href = "https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css";

        document.head.appendChild(link);

        script_style.type = 'text/javascript';
        script_style.src = "https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.js"

        document.head.appendChild(script_style);

        try {
            await this.waitForKR();
            this.loading = false;
        } catch (error) {
            this.$message.error('No se pudo cargar el formulario de pago de Izipay');
        }

    },
    methods: {
        async loadConfiguration() {
            await this.$http.get(`${this.resource}/record?isTenant=${this.isTenant}`)
                .then(response => {
                    this.publicKey = response.data.publickey_izipay;
                })
                .catch(() => {
                    this.$message.error('No se pudo obtener la configuración de Izipay');
                })
        },

        submit(){

            // evita generar varios formTokens si se hace doble clic
            if (this.paying) return;

            this.paying = true;

            this.$http.post(`${this.resource}/payment?isTenant=${this.isTenant}`, this.form)
                .then(async (response) => {

                    let formToken = response.data.formToken;

                    if (response.data.success && formToken) {
                        await this.renderForm(formToken);
                    } else {
                        this.$message.error(response.data.message || 'No se pudo iniciar el pago con Izipay. Verifica que las credenciales configuradas sean válidas.');
                    }

                })
                .catch(() => {
                    this.$message.error('Ocurrió un error al iniciar el pago con Izipay. Intenta nuevamente.');
                })
                .finally(() => {
                    this.paying = false;
                })

        },
        /**
         * Monta el formulario de Izipay y abre el popin
         *
         * El formulario debe estar adjunto y visible antes de abrir el popin,
         * de lo contrario el cliente de Izipay falla al montar sus componentes
         */
        async renderForm(formToken) {

            const selector = '.kr-izipay-container-inner';
            const container = document.querySelector(selector);

            // se limpia para no acumular formularios entre intentos de pago
            // Izipay crea el div .kr-embedded dentro del contenedor, no debe crearse aca
            container.innerHTML = '';

            try {

                const { KR: kr } = await KR.setFormConfig({
                    formToken: formToken
                });

                // los callbacks se registran sobre la instancia devuelta por setFormConfig,
                // ya inicializada con la public key, y no sobre el KR global aún sin arrancar
                await this.registerEvents(kr);

                const { result } = await kr.attachForm(selector);

                await kr.showForm(result.formId);
                await kr.openPopin();

            } catch (error) {
                container.innerHTML = '';
                this.$message.error('No se pudo mostrar el formulario de pago de Izipay.');
            }

        },
        /**
         * KR es un singleton global, registrar los callbacks una sola vez
         */
        async registerEvents(kr) {

            if (this.events_registered) return;

            await kr.onSubmit(this.onSubmit);
            await kr.onError(this.onError);

            this.events_registered = true;

        },
        /**
         * onSubmit solo se dispara cuando el pago se procesó correctamente,
         * los rechazos llegan por onError
         */
        onSubmit(event) {

            const uuid = _.get(event, 'clientAnswer.transactions[0].uuid');

            if (!uuid) {
                this.$message.error('No se pudo obtener la transacción de Izipay');
                return false;
            }

            this.$http.post(`${this.resource}/transaction?isTenant=${this.isTenant}`, { uuid: uuid })
                .then(response => {
                    if (response.data.success) {
                        this.$emit('submit', {
                            status : response.data.result.answer.status,
                            customer: this.form._customer,
                            data: response.data
                        });
                        this.$message.success('Pago realizado con éxito');
                    } else {
                        this.$message.error(response.data.message || 'No se pudo verificar el pago');
                    }
                })
                .catch(() => {
                    this.$message.error('No se pudo verificar el pago');
                })
                .finally(() => {
                    KR.closePopin();
                    this.clearForm();
                })

            // evita que Izipay haga el submit por defecto del formulario y recargue la página
            return false;

        },
        onError(error) {

            this.$message.error(_.get(error, 'errorMessage') || 'No se pudo procesar el pago con Izipay');

        },
        clearForm() {

            const container = document.querySelector('.kr-izipay-container-inner');

            if (container) container.innerHTML = '';

        },
        /**
         * Espera a que el cliente de Izipay termine de cargar, rechaza si no llega
         * para no dejar el botón deshabilitado indefinidamente
         */
        waitForKR(timeout = 15000) {
            return new Promise((resolve, reject) => {

                const started = Date.now();

                const check = setInterval(() => {

                    if (window.KR) {
                        clearInterval(check);
                        return resolve(window.KR);
                    }

                    if (Date.now() - started > timeout) {
                        clearInterval(check);
                        reject(new Error('Izipay client timeout'));
                    }

                }, 100)
            })
        },
    }

}
</script>

<style scoped>
.checkout-pay {
    width: 100%;
}
.checkout-pay__btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 14px 20px;
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.checkout-pay__btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
}
.checkout-pay__btn:active:not(:disabled) {
    transform: translateY(0);
}
.checkout-pay__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.checkout-pay__btn--izipay {
    background: linear-gradient(135deg, #e2204e 0%, #c1001e 100%);
    box-shadow: 0 4px 12px rgba(193, 0, 30, 0.25);
}
.checkout-pay__label {
    display: flex;
    align-items: center;
    gap: 8px;
}
.checkout-pay__amount {
    font-size: 16px;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 6px;
    white-space: nowrap;
}
.checkout-pay__spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: checkout-spin 0.7s linear infinite;
}
@keyframes checkout-spin {
    to { transform: rotate(360deg); }
}
</style>
<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
