<template>
    <el-dialog
        :title="dialogTitle"
        :visible="showDialog"
        @close="close"
        @open="getData"
        width="720px"
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
                            <th style="width:40px">#</th>
                            <th>Producto</th>
                            <th class="text-right" style="width:90px">Cant.</th>
                            <th class="text-right" style="width:130px">P. unitario</th>
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
                            <td class="text-right">{{ formatMoney(row.quantity) }}</td>
                            <td class="text-right">
                                <el-input-number
                                    v-model="row.unit_price"
                                    :min="0"
                                    :precision="2"
                                    :step="0.1"
                                    :controls="false"
                                    style="width:120px"
                                ></el-input-number>
                            </td>
                            <td class="text-right">{{ currencySymbol }} {{ formatMoney(lineTotal(row)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-right mt-3" v-if="items.length > 0">
                <div><strong>Gravado:</strong> {{ currencySymbol }} {{ formatMoney(summary.taxed) }}</div>
                <div><strong>IGV:</strong> {{ currencySymbol }} {{ formatMoney(summary.igv) }}</div>
                <div class="h5 mb-0 mt-1"><strong>Total:</strong> {{ currencySymbol }} {{ formatMoney(summary.total) }}</div>
            </div>
        </div>

        <span slot="footer" class="dialog-footer">
            <el-button @click="close">Cancelar</el-button>
            <el-button type="primary" :loading="saving" :disabled="!canSave" @click="submit">
                Confirmar precios
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
            return "Confirmar precios";
        },
        currencySymbol() {
            return this.quotation && this.quotation.currency_type_id === "USD" ? "$" : "S/";
        },
        summary() {
            let taxed = 0;
            let igv = 0;
            let total = 0;
            this.items.forEach((row) => {
                const line = this.lineTotal(row);
                total += line;
                if ((row.affectation_igv_type_id || "10") === "10") {
                    const base = line / (1 + ((row.percentage_igv || 18) / 100));
                    taxed += base;
                    igv += line - base;
                }
            });
            return {
                taxed: Math.round(taxed * 100) / 100,
                igv: Math.round(igv * 100) / 100,
                total: Math.round(total * 100) / 100,
            };
        },
        canSave() {
            return (
                this.items.length > 0 &&
                this.items.every((row) => Number(row.unit_price) > 0) &&
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
        lineTotal(row) {
            return Math.round(Number(row.quantity || 0) * Number(row.unit_price || 0) * 100) / 100;
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
                this.$message.warning("Ingrese un precio mayor a cero en todos los productos.");
                return;
            }
            this.saving = true;
            try {
                const { data } = await this.$http.post("/quotations/update-prices", {
                    id: this.recordId,
                    items: this.items.map((row) => ({
                        id: row.id,
                        unit_price: Number(row.unit_price),
                    })),
                });
                if (data.success) {
                    this.$message.success(data.message || "Precios confirmados.");
                    this.$eventHub.$emit("reloadData");
                    this.close();
                } else {
                    this.$message.error(data.message || "No se pudieron guardar los precios.");
                }
            } catch (e) {
                const msg =
                    (e.response && e.response.data && e.response.data.message) ||
                    "Error al confirmar precios.";
                this.$message.error(msg);
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
