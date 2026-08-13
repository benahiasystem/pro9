<template>
    <div class="pl-page">

        <h1 class="pl-page__greeting">
            ¡Hola, <strong>{{ greeting_name }}</strong>!
        </h1>

        <div class="pl-card">

            <h2 class="pl-card__title">Detalle del Pago</h2>

            <div v-if="is_paid" class="pl-banner pl-banner--success">
                Este link de pago ya fue pagado. No es necesario volver a pagarlo.
            </div>

            <div v-if="apply_conversion" class="pl-banner pl-banner--info">
                Se aplicó conversión al tipo de cambio.
            </div>

            <dl class="pl-detail">

                <div class="pl-detail__row">
                    <dt>Código de pago</dt>
                    <dd>{{ payment_link.number_full }}</dd>
                </div>

                <div class="pl-detail__row">
                    <dt>Fecha de emisión</dt>
                    <dd>{{ payment_link.date_of_issue }}</dd>
                </div>

                <div class="pl-detail__row">
                    <dt>Empresa</dt>
                    <dd>{{ company.name }}</dd>
                </div>

                <div class="pl-detail__row" v-if="payment_link.customer_name">
                    <dt>Nombre del cliente</dt>
                    <dd>{{ payment_link.customer_name }}</dd>
                </div>

                <div class="pl-detail__row" v-if="has_documents">
                    <dt>Comprobantes</dt>
                    <dd>
                        <div class="pl-documents">
                            <div class="pl-documents__row" v-for="(document, index) in payment_link.documents" :key="index">
                                <span>{{ document.number_full }}</span>
                                <span>S/ {{ formatNumber(document.total) }}</span>
                            </div>
                        </div>
                    </dd>
                </div>

                <div class="pl-detail__row" v-else>
                    <dt>Concepto</dt>
                    <dd>Cobro por link de pago</dd>
                </div>

                <div class="pl-detail__row pl-detail__row--total">
                    <dt>Importe</dt>
                    <dd>S/ {{ formatNumber(total) }}</dd>
                </div>

            </dl>

            <div class="pl-note" v-if="!is_paid">
                <p class="pl-note__title">Antes de pagar sigue estas recomendaciones</p>
                <ul class="pl-note__list">
                    <li v-for="(recommendation, index) in recommendations" :key="index">{{ recommendation }}</li>
                </ul>
            </div>

            <template v-if="!is_paid">

                <div class="pl-actions">
                    <checkout-tenant :form="form" @submit="onPaymentSubmit">
                    </checkout-tenant>
                </div>

            </template>

        </div>

        <p class="pl-page__footer">{{ company.name }} - {{ company.number }}</p>

    </div>
</template>

<script>


    export default {
        props: [
            'payment_link',
            'company',
            'payment_configuration',
            'total',
            'apply_conversion',
        ],
        data() {
            return {
                resource: 'public-payment-links',
                form: {},
                // se marca al confirmar el pago sin recargar la página
                paid: false,
            }
        },
        created() {
            this.initForm()
        },
        computed: {
            is_paid() {
                return this.paid || this.payment_link.is_paid
            },
            greeting_name() {
                return this.payment_link.customer_name || this.company.name
            },
            has_documents() {
                return (this.payment_link.documents || []).length > 0
            },
            customer() {
                return this.payment_link.customer || {}
            },
            description() {

                if (!this.has_documents) return `Pago ${this.payment_link.number_full}`

                const numbers = this.payment_link.documents.map(document => document.number_full)

                return `Pago de ${numbers.join(', ')}`

            },
            recommendations() {
                return [
                    'Activa las compras por internet de tu tarjeta',
                    'Revisa los límites de compra y el saldo de tu cuenta',
                ]
            },
        },
        methods: {
            /**
             * Datos que recibe la pasarela de pago
             *
             * El monto se envía en céntimos y el order_id incluye la marca de tiempo
             * para que cada intento de pago sea único
             */
            initForm() {

                this.form = {
                    amount: Math.round(parseFloat(this.total || 0) * 100),
                    currency: 'PEN',
                    order_id: `${this.payment_link.number_full}-${Date.now()}`,
                    description: this.description,
                    customer: {
                        name: this.customer.name,
                        lastName: this.customer.last_name,
                        email: this.customer.email,
                        phone: this.customer.phone,
                    }
                }

            },
            formatNumber(value) {
                return parseFloat(value || 0).toFixed(2)
            },
            /**
             * La pasarela devuelve el resultado del cobro
             *
             * Si fue aprobado se marca el link como pagado y se registran
             * los pagos de los comprobantes asociados
             */
            onPaymentSubmit(response) {
                

                let paid = !!(response && response.data && response.data.paid)

                paid = true
                
                if (!paid) return this.$message.error('El pago no fue aprobado')

                this.$http.post(`/pagos/${this.payment_link.uuid}/confirmar`, {
                        paid: true,
                        status: response.status,
                    })
                    .then(response => {
                        if (response.data.success) {
                            this.paid = true
                            this.$message.success(response.data.message)
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(() => {
                        this.$message.error('El pago fue aprobado pero no se pudo registrar, comuníquese con la empresa')
                    })

            },
        }
    }
</script>

<style scoped>

    .pl-page {
        --pl-accent: #ef6045;
        --pl-text: #2f3a45;
        --pl-muted: #7b8794;
        --pl-border: #e8ebf0;

        width: 100%;
        padding: 8px 0 32px;
        font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--pl-text);
    }

    .pl-page__greeting {
        margin: 0 0 24px;
        font-size: 1.75rem;
        font-weight: 400;
        text-align: center;
        color: var(--pl-text);
    }

    .pl-page__greeting strong {
        font-weight: 800;
    }

    .pl-page__footer {
        margin: 20px 0 0;
        font-size: .8rem;
        text-align: center;
        color: var(--pl-muted);
    }

    .pl-card {
        padding: 32px;
        background: #fff;
        border: 1px solid var(--pl-border);
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(47, 58, 69, .06);
    }

    .pl-card__title {
        margin: 0 0 24px;
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--pl-accent);
    }

    .pl-banner {
        margin-bottom: 20px;
        padding: 12px 16px;
        font-size: .875rem;
        border-radius: 8px;
    }

    .pl-banner--success {
        color: #1d6b46;
        background: #e7f6ee;
        border: 1px solid #bfe6d2;
    }

    .pl-banner--info {
        color: #1f5f8b;
        background: #e8f2fa;
        border: 1px solid #c3ddf1;
    }

    .pl-detail {
        margin: 0;
    }

    .pl-detail__row {
        display: flex;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid var(--pl-border);
    }

    .pl-detail__row:last-child {
        border-bottom: 0;
    }

    .pl-detail__row dt {
        flex: 0 0 42%;
        font-weight: 700;
    }

    .pl-detail__row dd {
        flex: 1;
        margin: 0;
        color: var(--pl-muted);
        word-break: break-word;
    }

    .pl-detail__row--total dt,
    .pl-detail__row--total dd {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--pl-text);
    }

    .pl-documents__row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 2px 0;
    }

    .pl-note {
        margin-top: 24px;
        padding: 16px 20px;
        border: 1px solid #d9d6f2;
        border-radius: 8px;
        background: #fbfaff;
    }

    .pl-note__title {
        margin: 0 0 8px;
        font-size: .95rem;
        color: #6c63b5;
    }

    .pl-note__list {
        margin: 0;
        padding-left: 18px;
        font-size: .875rem;
        color: var(--pl-muted);
    }

    .pl-note__list li {
        margin-bottom: 4px;
    }

    .pl-actions {
        margin-top: 28px;
        text-align: center;
    }

    .pl-button {
        min-width: 220px;
        padding: 14px 32px;
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        background: var(--pl-accent);
        border: 0;
        border-radius: 30px;
        cursor: pointer;
        transition: opacity .2s ease;
    }

    .pl-button:hover {
        opacity: .9;
    }

    .pl-payment {
        margin-top: 28px;
        padding-top: 24px;
        border-top: 1px solid var(--pl-border);
    }

    @media (max-width: 575.98px) {

        .pl-card {
            padding: 20px;
        }

        .pl-page__greeting {
            font-size: 1.35rem;
        }

        .pl-detail__row {
            flex-direction: column;
            gap: 4px;
        }

        .pl-detail__row dt {
            flex: none;
        }

    }

</style>
