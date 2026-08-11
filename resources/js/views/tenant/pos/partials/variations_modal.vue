<template>
    <el-dialog :title="titleDialog"
               :visible="showDialog"
               :close-on-click-modal="true"
               append-to-body
               top="7vh"
               width="480px"
               @close="close">
        <div class="variations-list">
            <div v-for="variation in variations"
                 :key="variation.id"
                 class="variation-row"
                 :class="{disabled: variation.stock <= 0}"
                 @click="selectVariation(variation)">
                <div>
                    <span class="fw-semibold">{{ variation.variation_label || variation.description }}</span>
                    <small class="text-muted d-block">{{ variation.internal_id }}<template v-if="variation.barcode"> · {{ variation.barcode }}</template></small>
                </div>
                <div class="text-end">
                    <span class="fw-semibold text-primary d-block">{{ currencySymbol }} {{ formatPrice(variation.sale_unit_price) }}</span>
                    <small :class="variation.stock > 0 ? 'text-success' : 'text-danger'">
                        Stock: {{ parseFloat(variation.stock) }}
                    </small>
                </div>
            </div>
            <p v-if="variations.length === 0" class="text-muted text-center py-3 mb-0">
                Este producto no tiene variaciones activas
            </p>
        </div>
        <div class="form-actions text-end mt-3">
            <el-button @click.prevent="close()">Cerrar</el-button>
        </div>
    </el-dialog>
</template>

<script>
export default {
    name: 'PosVariationsModal',
    props: ['showDialog', 'parent'],
    computed: {
        titleDialog() {
            return this.parent ? `Variaciones · ${this.parent.description}` : 'Variaciones'
        },
        variations() {
            return (this.parent && this.parent.variations) ? this.parent.variations : []
        },
        currencySymbol() {
            return this.parent ? this.parent.currency_type_symbol : 'S/'
        },
    },
    methods: {
        formatPrice(price) {
            return parseFloat(price || 0).toFixed(2)
        },
        selectVariation(variation) {
            if (variation.stock <= 0) {
                return this.$message.warning('La variación no tiene stock disponible')
            }
            this.$emit('select', variation)
            this.close()
        },
        close() {
            this.$emit('update:showDialog', false)
        },
    }
}
</script>

<style scoped>
.variations-list {
    max-height: 55vh;
    overflow-y: auto;
}
.variation-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.12s;
}
.variation-row:hover {
    border-color: #409EFF;
    background: #ecf5ff;
}
.variation-row.disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.variation-row.disabled:hover {
    border-color: #ebeef5;
    background: #fff;
}
</style>
