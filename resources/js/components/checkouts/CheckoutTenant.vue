<template>
    <div>
        <tenant-checkout-izipay @submit="submitChild" :isTenant="true" :form="_form" :disabled="disabled" v-if="type === 'izipay' && up" />
        <tenant-checkout-culqi @submit="submitChild" :isTenant="true" :form="_form" :disabled="disabled" v-else-if="type === 'culqi' && up" />
        <tenant-checkout-mercadopago @submit="submitChild" :isTenant="true" :form="_form" :disabled="disabled" v-else-if="type === 'mercadopago' && up" />
    </div>

</template>


<script>


/*
        <checkout-tenant :form="{
            amount: 50 * 100,
            currency: 'PEN',
            orderId: '123456',
            description: 'Pago de prueba',
            customer: {
                name: 'John',
                lastName: 'Doe',
                email: 'cristian@buho.la',
                phone: 987654321
            }
        }">
        </checkout-tenant>
*/
/**
 *  form: {
 *    amount: 0,
 *    currency: 'PEN', 
 *    order_id: '',   -> Unicamente para Izipay o Culqi (order)
 *    description: '', -> Unicamente para Culqi 
 *    customer: {
 *      name: '',
 *      email: '',
 *      phone: '', -> Unicamente para Izipay
 *      lastName: '', -> Unicamente para Izipay
 *    }
 *  }
 */
export default {
    props: {
        form: {
            type: Object,
            required: true
        },
        disabled: {
            type: Boolean,
            default: false
        },
    },
    data() {
        return {
            resource: '/payment-gateway',
            type: null,
            up: false,
            isTenant: false
        }
    },
    computed: {
        customer() {
            return this.form.customer || {}
        },
        _form() {
            if (this.type === 'izipay') {
                return {
                    amount: this.form.amount,
                    currency: this.form.currency,
                    orderId: this.form.order_id,
                    _customer: this.form.customer,
                    customer: {
                        email: this.customer.email,
                        billingDetails: {
                            firstName: this.customer.name,
                            lastName: this.customer.lastName,
                            phoneNumber: this.customer.phone,
                        }
                    },
                }
            } else if (this.type === 'culqi') {
                return {
                    amount: this.form.amount,
                    _customer: this.form.customer,
                    currency: this.form.currency,
                    title: this.form.description,
                    email: this.customer.email,
                    order: this.form.order_id
                }
            } else if (this.type === 'mercadopago') {
                return {
                    amount: this.form.amount,
                    _customer: this.form.customer,
                    currency: this.form.currency,
                    description: this.form.description,
                    orderId: this.form.order_id,
                    customer: {
                        name: this.customer.name,
                        email: this.customer.email,
                    }
                }
            }
            return {}
        }
    },
    created() {
        this.enabledCheckout();
    },
    methods: {
        enabledCheckout(){
            this.$http.get(`${this.resource}/enabled-checkout?isTenant=true`)
                .then( response => {
                    this.type = response.data.checkout
                    this.isTenant = response.data.is_tenant
                    this.up = true;
                })
        },
        submitChild(data) {
            this.$emit('submit', data);
        },
    }
}
</script>