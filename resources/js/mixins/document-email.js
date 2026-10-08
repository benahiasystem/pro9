/** Shared email delivery behavior for web sales, POS and quick sales. */
export const documentEmail = {
    data() {
        return {emailBusy: false, emailRequestId: null};
    },
    computed: {
        emailSendLabel() {
            return this.form.email_delivery && this.form.email_delivery.status === 'accepted' ? 'Enviar de nuevo' : 'Enviar';
        },
    },
    methods: {
        setEmailDeliveryRecord(record) {
            this.form = record;
            const delivery = record.email_delivery;
            this.emailRequestId = delivery && ['pending', 'uncertain'].includes(delivery.status)
                ? delivery.request_id : this.newEmailRequestId();
            this.errors = {};
        },
        newEmailRequestId() {
            const bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 15) | 64;
            bytes[8] = (bytes[8] & 63) | 128;
            const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
            return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        },
        async clickSendEmail() {
            const delivery = this.form.email_delivery;
            if (!this.form.id || this.emailBusy || (delivery && !delivery.can_send)) return;
            this.emailBusy = true;
            const documentId = this.form.id;
            this.errors = {};
            try {
                if (!this.emailRequestId) this.emailRequestId = this.newEmailRequestId();
                const response = await this.$http.post(`/${this.resource}/email`, {
                    customer_email: this.form.customer_email,
                    id: documentId,
                    request_id: this.emailRequestId,
                    resend: Boolean(delivery && delivery.status === 'accepted'),
                });
                if (this.form.id !== documentId) return;
                if (response.data.email_delivery) {
                    this.$set(this.form, 'email_delivery', response.data.email_delivery);
                    // A new UUID is only used by a subsequent explicit request after a definitive result.
                    if (['accepted', 'rejected'].includes(response.data.email_delivery.status)) this.emailRequestId = this.newEmailRequestId();
                }
                const message = response.data.message || (response.data.success ? 'El correo fue enviado satisfactoriamente' : 'Factura guardada. No se pudo confirmar el correo.');
                if (response.data.success) this.$message.success(message);
                else this.$message.warning(message);
            } catch (error) {
                if (this.form.id !== documentId) return;
                if (error.response && error.response.status === 422) {
                    // Pro9 AJAX validation uses a flat field map; API responses wrap it in errors.
                    const data = error.response.data || {};
                    this.errors = data.errors || Object.fromEntries(Object.entries(data).filter(([, value]) => Array.isArray(value)));
                    if (!this.errors.customer_email) {
                        const key = Object.keys(this.errors).find(name => name.startsWith('recipients'));
                        if (key) this.$set(this.errors, 'customer_email', this.errors[key]);
                    }
                    if (!this.errors.customer_email) this.$message.error(data.message || 'Factura guardada. No se pudo validar el envío de correo.');
                } else {
                    this.$message.error('Factura guardada. No se pudo confirmar el correo. Consulte el estado antes de repetir.');
                    // Retain the request UUID across a network failure; a retry cannot duplicate this attempt.
                }
            } finally {
                this.emailBusy = false;
            }
        },
        async queryHkaEmail() {
            if (this.emailBusy) return;
            this.emailBusy = true;
            const documentId = this.form.id;
            try {
                const response = await this.$http.post(`/documents/${documentId}/query-hka-email`);
                if (this.form.id !== documentId) return;
                this.$set(this.form, 'email_delivery', response.data.email_delivery);
                if (response.data.email_delivery.status === 'accepted') this.emailRequestId = this.newEmailRequestId();
                this.$message.info(response.data.message);
            } catch (error) {
                this.$message.error('Factura guardada. No se pudo consultar el rastreo del correo.');
            } finally {
                this.emailBusy = false;
            }
        },
    },
};
