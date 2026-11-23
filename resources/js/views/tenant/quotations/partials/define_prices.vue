<template>
<!-- ########## INICIO CAMBIO IGV A IVA -->
    <el-dialog
        :title="dialogTitle"
        :visible="showDialog"
        @close="close"
        @open="getData"
        width="920px"
        :close-on-click-modal="false"
        append-to-body
    >
        <div v-loading="loading">
            <div class="mb-3" v-if="quotation">
                <div><strong>Cotización:</strong> {{ quotation.identifier || quotation.number_full }}</div>
                <div><strong>Cliente:</strong> {{ quotation.customer_name }}</div>
                <el-alert
                    v-if="quotation.needs_price_confirmation"
                    class="mt-2"
                    type="warning"
                    :closable="false"
                    show-icon
                    title="Esta cotización de tienda virtual no tiene precios definidos. Ingrese los precios unitarios para confirmarla."
                ></el-alert>
            </div>

            <div class="table-responsive" v-if="items.length > 0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Producto</th>
                            <th class="text-right" style="width:110px">Cantidad</th>
                            <th class="text-right" style="width:120px">P. unitario</th>
                            <th class="text-right" style="width:100px">Desc. (%)</th>
                            <th class="text-right" style="width:120px">Total línea</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in items" :key="row.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <div>{{ row.description }}</div>
                                <small class="text-muted" v-if="row.internal_id">{{ row.internal_id }}</small>
                                <div v-if="isPositive(row.suggested_unit_price)">
                                    <small class="text-muted">
                                        Ref. catálogo: {{ currencySymbol }} {{ formatMoney(row.suggested_unit_price) }}
                                        <el-button type="text" size="mini" @click="useSuggested(row)">Usar</el-button>
                                    </small>
                                </div>
                            </td>
                            <td class="text-right">
                                <el-input v-model="row.quantity" inputmode="decimal" aria-label="Cantidad" style="width:100px"></el-input>
                            </td>
                            <td class="text-right">
                                <el-input v-model="row.unit_price" inputmode="decimal" aria-label="Precio unitario" style="width:110px"></el-input>
                            </td>
                            <td class="text-right">
                                <el-input v-model="row.discount_percentage" inputmode="decimal" aria-label="Descuento porcentual" style="width:90px"></el-input>
                            </td>
                            <td class="text-right">{{ currencySymbol }} {{ lineTotal(index) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <el-alert v-if="previewError && items.length" type="warning" :closable="false" :title="previewError"></el-alert>
            <div class="text-right mt-3" v-if="items.length > 0 && preview">
                <div v-if="isPositive(summary.total_discount)">
                    <strong>Descuentos:</strong> {{ currencySymbol }} {{ formatMoney(summary.total_discount) }}
                </div>
                <div><strong>Base imponible:</strong> {{ currencySymbol }} {{ formatMoney(summary.total_taxed) }}</div>
                <div><strong>IVA:</strong> {{ currencySymbol }} {{ formatMoney(summary.total_igv) }}</div>
                <div class="h5 mb-0 mt-1"><strong>Total:</strong> {{ currencySymbol }} {{ formatMoney(summary.total) }}</div>
            </div>
        </div>

        <span slot="footer" class="dialog-footer">
            <el-button @click="close">Cancelar</el-button>
            <el-button type="primary" :loading="saving" :disabled="!canSave" @click="submit">
                {{ saveButtonLabel }}
            </el-button>
        </span>
    </el-dialog>
<!-- ######### FIN CAMBIO IGV A IVA -->
</template>

<script>
import {exactAmount, exactInput, exactValue} from '../../../../helpers/exchange-rate-math'
import {formatMoneyExact} from '../../../../helpers/format-money-exact'
import {storefrontPreview} from '../../../../helpers/storefront-quotation-prices'

export default {
    props: {showDialog: {type: Boolean, default: false}, recordId: {default: null}},
    data() { return {loading: false, saving: false, quotation: null, items: []} },
    computed: {
        dialogTitle() { return this.quotation && this.quotation.needs_price_confirmation ? 'Definir precios' : 'Edición rápida' },
        saveButtonLabel() { return this.quotation && this.quotation.needs_price_confirmation ? 'Confirmar precios' : 'Guardar cambios' },
        currencySymbol() { return this.quotation && this.quotation.currency_type_id === 'USD' ? '$' : 'Bs.' },
        previewResult() {
            try { return {value: storefrontPreview(this.items, this.quotation || {}), error: ''} }
            catch (error) { return {value: null, error: error.message} }
        },
        preview() { return this.previewResult.value },
        previewError() { return this.previewResult.error },
        summary() { return this.preview ? this.preview.totals : null },
        canSave() { return this.items.length > 0 && !!this.preview && !this.loading && !this.saving },
    },
    methods: {
        close() { this.$emit('update:showDialog', false); this.quotation = null; this.items = [] },
        isPositive(value) { try { return exactAmount(value ?? '0').compare(0) > 0 } catch (_) { return false } },
        formatMoney(value) { return formatMoneyExact(value) },
        lineTotal(index) { return this.preview ? formatMoneyExact(exactValue(this.preview.lines[index], 'total')) : '—' },
        useSuggested(row) { if (this.isPositive(row.suggested_unit_price)) row.unit_price = String(row.suggested_unit_price) },
        async getData() {
            if (!this.recordId) return
            this.loading = true; this.quotation = null; this.items = []
            try {
                const {data} = await this.$http.get(`/quotations/prices/${this.recordId}`)
                if (!data.success) { this.$message.error(data.message || 'No se pudo cargar la cotización.'); this.close(); return }
                this.quotation = data.data
                this.items = (data.data.items || []).map(row => ({...row,
                    original_quantity: String(row.quantity), original_unit_price: String(row.unit_price),
                    original_discount_percentage: String(row.discount_percentage || '0'),
                    quantity: String(row.quantity), discount_percentage: String(row.discount_percentage || '0'),
                    unit_price: this.isPositive(row.unit_price) ? String(row.unit_price) : this.isPositive(row.suggested_unit_price) ? String(row.suggested_unit_price) : '0',
                }))
            } catch (error) { this.$message.error('Error al cargar los ítems de la cotización.'); this.close() }
            finally { this.loading = false }
        },
        async submit() {
            if (!this.canSave) { this.$message.warning(this.previewError || 'Revise los datos de cada producto.'); return }
            this.saving = true
            try {
                const {data} = await this.$http.post('/quotations/update-prices', {id: this.recordId, items: this.items.map(row => ({
                    id: row.id, quantity: exactInput(String(row.quantity)), unit_price: exactInput(String(row.unit_price)),
                    discount_percentage: exactInput(String(row.discount_percentage || '0')),
                }))})
                if (data.success) { this.$message.success(data.message || 'Cotización actualizada.'); this.$eventHub.$emit('reloadData'); this.close() }
                else this.$message.error(data.message || 'No se pudieron guardar los cambios.')
            } catch (error) {
                this.$message.error((error.response && error.response.data && error.response.data.message) || 'Error al guardar la cotización.')
            } finally { this.saving = false }
        },
    },
}
</script>
