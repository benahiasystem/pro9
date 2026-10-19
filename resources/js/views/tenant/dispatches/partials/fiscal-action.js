export default async function performFiscalAction(record, action) {
    if (record.hka_action_busy || !['send-hka','query-hka','relate-hka'].includes(action)) return;
    this.$set(record, 'hka_action_busy', true);
    try {
        await this.$http.post(`/dispatches/${record.id}/${action}`);
        const response=await this.$http.get(`/dispatches/record/${record.id}`);
        const current=response.data.data || response.data;
        this.$set(record,'fiscal_emission',current.fiscal_emission);
        this.$set(record,'pdf_downloads',current.pdf_downloads);
        this.$message.info(current.fiscal_emission.diagnostic || current.fiscal_emission.description);
        return current;
    } catch (error) {
        this.$message.error(error.response?.data?.message || 'No se pudo completar la operación HKA. La orden sigue guardada.');
    } finally { this.$set(record, 'hka_action_busy', false); }
}
