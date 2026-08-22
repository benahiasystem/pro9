<template>
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
                                <div v-if="row.suggested_unit_price > 0">
                                    <small class="text-muted">
                                        Ref. catálogo: {{ currencySymbol }} {{ formatMoney(row.suggested_unit_price) }}
                                        <el-button type="text" size="mini" @click="useSuggested(row)">Usar</el-button>
                                    </small>
                                </div>
                            </td>
                            <td class="text-right">
                                <el-input-number
                                    v-model="row.quantity"
                                    :min="0.01"
                                    :precision="2"
                                    :step="1"
                                    :controls="false"
                                    style="width:100px"
                                ></el-input-number>
                            </td>
                            <td class="text-right">
                                <el-input-number
                                    v-model="row.unit_price"
                                    :min="0"
                                    :precision="2"
                                    :step="0.1"
                                    :controls="false"
                                    style="width:110px"
                                ></el-input-number>
                            </td>
                            <td class="text-right">
                                <el-input-number
                                    v-model="row.discount_percentage"
                                    :min="0"
                                    :max="100"
                                    :precision="2"
                                    :step="1"
                                    :controls="false"
                                    style="width:90px"
                                ></el-input-number>
                            </td>
                            <td class="text-right">{{ currencySymbol }} {{ formatMoney(lineTotal(row)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-right mt-3" v-if="items.length > 0">
                <div v-if="summary.discount > 0">
                    <strong>Descuentos:</strong> {{ currencySymbol }} {{ formatMoney(summary.discount) }}
                </div>
                <div><strong>Gravado:</strong> {{ currencySymbol }} {{ formatMoney(summary.taxed) }}</div>
                <div><strong>IGV:</strong> {{ currencySymbol }} {{ formatMoney(summary.igv) }}</div>
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
</template>

<script>
export default {
    props: {
        showDialog: {
            type: Boolean,
            default: false,
        },
        recordId: {
            default: null,
        },
    },
    data() {
        return {
            loading: false,
            saving: false,
            quotation: null,
            items: [],
        };
    },
    computed: {
        dialogTitle() {
            if (this.quotation && this.quotation.needs_price_confirmation) {
                return "Definir precios";
            }
            return "Edición rápida";
        },
        saveButtonLabel() {
            if (this.quotation && this.quotation.needs_price_confirmation) {
                return "Confirmar precios";
            }
            return "Guardar cambios";
        },
        currencySymbol() {
            return this.quotation && this.quotation.currency_type_id === "USD" ? "$" : "Bs.";
        },
        summary() {
            let taxed = 0;
            let igv = 0;
            let total = 0;
            let discount = 0;

            this.items.forEach((row) => {
                const gross = this.lineGross(row);
                const line = this.lineTotal(row);
                discount += Math.max(0, gross - line);
                total += line;

                if ((row.affectation_igv_type_id || "10") === "10") {
                    const base = line / (1 + (Number(row.percentage_igv || 18) / 100));
                    taxed += base;
                    igv += line - base;
                }
            });

            return {
                discount: Math.round(discount * 100) / 100,
                taxed: Math.round(taxed * 100) / 100,
                igv: Math.round(igv * 100) / 100,
                total: Math.round(total * 100) / 100,
            };
        },
        canSave() {
            return (
                this.items.length > 0 &&
                this.items.every((row) => {
                    const qty = Number(row.quantity);
                    const price = Number(row.unit_price);
                    const disc = Number(row.discount_percentage || 0);
                    return qty > 0 && price > 0 && disc >= 0 && disc <= 100;
                }) &&
                !this.loading &&
                !this.saving
            );
        },
    },
    methods: {
        close() {
            this.$emit("update:showDialog", false);
            this.quotation = null;
            this.items = [];
        },
        formatMoney(value) {
            const num = Number(value || 0);
            return num.toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        lineGross(row) {
            return Math.round(Number(row.quantity || 0) * Number(row.unit_price || 0) * 100) / 100;
        },
        lineTotal(row) {
            const pct = Math.min(100, Math.max(0, Number(row.discount_percentage || 0)));
            const factor = 1 - pct / 100;
            return Math.round(this.lineGross(row) * factor * 100) / 100;
        },
        useSuggested(row) {
            if (Number(row.suggested_unit_price) > 0) {
                row.unit_price = Number(row.suggested_unit_price);
            }
        },
        async getData() {
            if (!this.recordId) return;
            this.loading = true;
            this.quotation = null;
            this.items = [];
            try {
                const { data } = await this.$http.get(`/quotations/prices/${this.recordId}`);
                if (!data.success) {
                    this.$message.error(data.message || "No se pudo cargar la cotización.");
                    this.close();
                    return;
                }
                this.quotation = data.data;
                this.items = (data.data.items || []).map((row) => ({
                    ...row,
                    quantity: Number(row.quantity) > 0 ? Number(row.quantity) : 1,
                    discount_percentage: Number(row.discount_percentage || 0),
                    unit_price:
                        Number(row.unit_price) > 0
                            ? Number(row.unit_price)
                            : Number(row.suggested_unit_price) > 0
                              ? Number(row.suggested_unit_price)
                              : 0,
                }));
            } catch (e) {
                this.$message.error("Error al cargar los ítems de la cotización.");
                this.close();
            } finally {
                this.loading = false;
            }
        },
        async submit() {
            if (!this.canSave) {
                this.$message.warning("Revise cantidad, precio unitario y descuento de cada producto.");
                return;
            }
            this.saving = true;
            try {
                const { data } = await this.$http.post("/quotations/update-prices", {
                    id: this.recordId,
                    items: this.items.map((row) => ({
                        id: row.id,
                        quantity: Number(row.quantity),
                        unit_price: Number(row.unit_price),
                        discount_percentage: Number(row.discount_percentage || 0),
                    })),
                });
                if (data.success) {
                    this.$message.success(data.message || "Cotización actualizada.");
                    this.$eventHub.$emit("reloadData");
                    this.close();
                } else {
                    this.$message.error(data.message || "No se pudieron guardar los cambios.");
                }
            } catch (e) {
                const msg =
                    (e.response && e.response.data && e.response.data.message) ||
                    "Error al guardar la cotización.";
                this.$message.error(msg);
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
