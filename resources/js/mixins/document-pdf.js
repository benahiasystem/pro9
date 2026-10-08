/** Explicit digital-invoice downloads: HKA A4/A5 or a local 80MM ticket with its HKA QR. */
export const documentPdf = {
    data() {
        return { pdfDownloadBusy: false };
    },
    computed: {
        pdfDownloadMessage() {
            return this.form.pdf_downloads?.a4?.message || this.form.pdf_downloads?.a5?.message
                || (this.config?.show_ticket_80 ? this.form.pdf_downloads?.ticket?.message : '') || '';
        },
    },
    methods: {
        usesHkaPdf(format) {
            return this.form.pdf_downloads?.[format]?.provider === 'hka'
                || this.form.pdf_downloads?.a4?.provider === 'hka'
                || this.form.email_delivery?.provider === 'hka'
                || (this.form.document_type_id === '01' && this.form.fiscal_emission_mode === 'digital');
        },
        pdfDownloadDisabled(format) {
            return this.pdfDownloadBusy || (this.usesHkaPdf(format) && !this.form.pdf_downloads?.[format]?.available);
        },
        async downloadDocumentPdf(format, localUrl) {
            if (!['a4', 'a5', 'ticket'].includes(format) || !this.usesHkaPdf(format)) {
                if (localUrl) window.open(localUrl, '_blank');
                return;
            }
            if (this.pdfDownloadBusy) return;
            const entry = this.form.pdf_downloads?.[format];
            if (!entry?.available || !entry.url) {
                this.$message.warning(entry?.message || 'Confirme la factura en HKA antes de descargar A4, A5 o 80MM.');
                return;
            }
            let filename = `factura-${this.form.id}-${format === 'ticket' ? '80mm' : format}.pdf`;
            this.pdfDownloadBusy = true;
            try {
                const response = await this.$http.get(entry.url, { responseType: 'blob' });
                if (!response.headers?.['content-type']?.includes('application/pdf') || !response.data?.size) {
                    throw new Error('Invalid PDF response');
                }
                const disposition = response.headers?.['content-disposition'] || '';
                const named = disposition.match(/filename=(?:"([^"]+)"|([^;\s]+))/i);
                if (named) filename = named[1] || named[2];
                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            } catch (error) {
                let data = error.response?.data;
                if (data && typeof data.text === 'function') {
                    try { data = JSON.parse(await data.text()); } catch (_) { data = null; }
                }
                const message = data?.errors?.pdf?.[0] || data?.pdf?.[0] || data?.message
                    || (format === 'ticket'
                        ? 'No se pudo descargar el ticket 80MM con su QR HKA. Intente de nuevo; la venta sigue guardada.'
                        : 'No se pudo descargar el PDF HKA. Intente de nuevo; la venta sigue guardada.');
                this.$message.error(message);
            } finally {
                this.pdfDownloadBusy = false;
            }
        },
    },
};
