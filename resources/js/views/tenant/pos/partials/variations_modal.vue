<template>
    <el-dialog :visible="showDialog"
               :close-on-click-modal="true"
               append-to-body
               custom-class="pos-variations-dialog"
               top="7vh"
               :width="isGallery ? '780px' : '480px'"
               @open="open"
               @close="close">

        <div slot="title" class="variations-title">
            <span class="el-dialog__title">{{ titleDialog }}</span>
            <el-radio-group v-if="variables.length > 0" v-model="view" size="mini" class="d-flex" @change="changeView">
                <el-radio-button label="gallery">Atributos</el-radio-button>
                <el-radio-button label="list">Lista</el-radio-button>
            </el-radio-group>
        </div>

        <div v-if="isGallery" class="variation-gallery">
            <div class="variation-gallery__image">
                <img :src="currentImage" alt="">
            </div>
            <div class="variation-gallery__info">
                <h4 class="variation-gallery__name">
                    {{ parent.description }}<template v-if="currentLabel"> · {{ currentLabel }}</template>
                </h4>
                <div class="variation-gallery__price">
                    {{ currencySymbol }} {{ formatPrice(currentPrice) }}
                </div>

                <div v-for="variable in variables"
                     :key="'variable-' + variable.name"
                     class="variation-gallery__group">
                    <label>{{ variable.name }}</label>
                    <div class="variation-gallery__values">
                        <span v-for="value in variable.values"
                              :key="variable.name + '-' + value.value"
                              class="attr-chip"
                              :class="{
                                  active: selection[variable.name] === value.value,
                                  'is-empty': !isValueAvailable(variable.name, value.value),
                              }"
                              @click="selectValue(variable.name, value.value)">
                            <span v-if="value.color"
                                  class="attr-chip__dot"
                                  :style="{ background: value.color }"></span>{{ value.value }}
                        </span>
                    </div>
                </div>

                <div class="variation-gallery__stock">
                    <template v-if="current">
                        <span class="fw-semibold">Disponible: {{ formatStock(current.stock) }}</span>
                        <el-tag size="mini"
                                :type="current.stock > 0 ? 'success' : 'danger'"
                                effect="plain">{{ current.stock > 0 ? 'En stock' : 'Sin stock' }}</el-tag>
                        <small class="text-muted d-block mt-1">{{ current.internal_id }}<template v-if="current.barcode"> · {{ current.barcode }}</template></small>
                    </template>
                    <span v-else class="text-muted">Esta combinación no existe</span>
                </div>
            </div>
        </div>

        <template v-else>
            <el-input v-model="search"
                      class="mb-3 mt-2"
                      size="small"
                      clearable
                      prefix-icon="el-icon-search"
                      placeholder="Buscar por atributo, código o código de barras"></el-input>
            <div class="variations-list">
                <div v-for="variation in filteredVariations"
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
                            Stock: {{ formatStock(variation.stock) }}
                        </small>
                    </div>
                </div>
                <p v-if="variations.length === 0" class="text-muted text-center py-3 mb-0">
                    Este producto no tiene variaciones activas
                </p>
                <p v-else-if="filteredVariations.length === 0" class="text-muted text-center py-3 mb-0">
                    Ninguna variación coincide con «{{ search }}»
                </p>
            </div>
        </template>

        <div class="form-actions text-end mt-3">
            <el-button @click.prevent="close()">Cerrar</el-button>
            <el-button v-if="isGallery"
                       type="primary"
                       class="ms-2"
                       :disabled="!current || current.stock <= 0"
                       @click.prevent="selectVariation(current)">Agregar</el-button>
        </div>
    </el-dialog>
</template>

<script>
const VIEW_STORAGE_KEY = 'pos_variations_view'

export default {
    name: 'PosVariationsModal',
    props: ['showDialog', 'parent'],
    data() {
        return {
            view: localStorage.getItem(VIEW_STORAGE_KEY) || 'gallery',
            selection: {},
            search: '',
        }
    },
    computed: {
        titleDialog() {
            return this.parent ? `Variaciones · ${this.parent.description}` : 'Variaciones'
        },
        variations() {
            return (this.parent && this.parent.variations) ? this.parent.variations : []
        },
        currencySymbol() {
            return this.parent ? this.parent.currency_type_symbol : 'Bs.'
        },
        filteredVariations() {
            const terms = this.search.toLowerCase().split(' ').filter(term => term !== '')
            if (terms.length === 0) return this.variations

            return this.variations.filter(variation => {
                const haystack = [
                    variation.variation_label,
                    variation.description,
                    variation.internal_id,
                    variation.barcode,
                    this.attributesOf(variation).map(attribute => `${attribute.variable} ${attribute.value}`).join(' '),
                ].join(' ').toLowerCase()

                return terms.every(term => haystack.indexOf(term) !== -1)
            })
        },
        variables() {
            const list = []
            const index = {}

            this.variations.forEach(variation => {
                this.attributesOf(variation).forEach(attribute => {
                    if (!attribute.variable) return

                    const variable_key = `variable-${attribute.variable}`
                    if (!index[variable_key]) {
                        index[variable_key] = {name: attribute.variable, values: [], seen: {}}
                        list.push(index[variable_key])
                    }

                    const value_key = `value-${attribute.value}`
                    if (!index[variable_key].seen[value_key]) {
                        index[variable_key].seen[value_key] = true
                        index[variable_key].values.push({value: attribute.value, color: attribute.color})
                    }
                })
            })

            return list
        },
        isGallery() {
            return this.view === 'gallery' && this.variables.length > 0
        },
        current() {
            if (this.variables.length === 0) return null

            return this.variations.find(variation => {
                return this.variables.every(variable => {
                    return this.attributeValue(variation, variable.name) === this.selection[variable.name]
                })
            }) || null
        },
        currentLabel() {
            return this.current ? (this.current.variation_label || '') : ''
        },
        currentPrice() {
            return this.current ? this.current.sale_unit_price : (this.parent ? this.parent.sale_unit_price : 0)
        },
        currentImage() {
            if (this.current && this.current.image_url) return this.current.image_url
            return this.parent ? this.parent.image_url : ''
        },
    },
    watch: {
        parent() {
            this.resetSelection()
        },
    },
    methods: {
        open() {
            this.resetSelection()
        },
        changeView(value) {
            localStorage.setItem(VIEW_STORAGE_KEY, value)
        },
        attributesOf(variation) {
            return variation.variation_attributes || []
        },
        attributeValue(variation, variable_name) {
            const attribute = this.attributesOf(variation).find(row => row.variable === variable_name)
            return attribute ? attribute.value : null
        },
        selectionOf(variation) {
            const selection = {}
            this.attributesOf(variation).forEach(attribute => {
                if (attribute.variable) selection[attribute.variable] = attribute.value
            })
            return selection
        },
        resetSelection() {
            const variation = this.variations.find(row => parseFloat(row.stock || 0) > 0) || this.variations[0]
            this.selection = variation ? this.selectionOf(variation) : {}
            this.search = ''
        },
        isValueAvailable(variable_name, value) {
            return this.variations.some(variation => {
                return this.attributeValue(variation, variable_name) === value && parseFloat(variation.stock || 0) > 0
            })
        },
        selectValue(variable_name, value) {
            const next = Object.assign({}, this.selection, {[variable_name]: value})
            const exists = this.variations.some(variation => {
                return this.variables.every(variable => {
                    return this.attributeValue(variation, variable.name) === next[variable.name]
                })
            })

            if (exists) {
                this.selection = next
                return
            }

            const fallback = this.variations.find(variation => {
                return this.attributeValue(variation, variable_name) === value
            })
            if (fallback) this.selection = this.selectionOf(fallback)
        },
        formatPrice(price) {
            return parseFloat(price || 0).toFixed(2)
        },
        formatStock(stock) {
            return parseFloat(stock || 0)
        },
        selectVariation(variation) {
            if (!variation) return
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
.variations-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-right: 24px;
}
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
.variation-gallery {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: flex-start;
}
.variation-gallery__image {
    flex: 0 1 300px;
    max-width: 100%;
    background: #f2f4f6;
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.variation-gallery__image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.variation-gallery__info {
    flex: 1 1 280px;
    min-width: 0;
}
.variation-gallery__name {
    margin: 0 0 8px;
    font-size: 19px;
    font-weight: 700;
    line-height: 1.3;
}
.variation-gallery__price {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary, #409EFF);
    margin-bottom: 18px;
}
.variation-gallery__group {
    margin-bottom: 14px;
}
.variation-gallery__group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #909399;
    margin-bottom: 6px;
}
.variation-gallery__values {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.attr-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 44px;
    padding: 7px 16px;
    border: 1px solid #dcdfe6;
    border-radius: 999px;
    font-size: 13px;
    background: #fff;
    cursor: pointer;
    user-select: none;
    transition: all 0.12s;
}
.attr-chip:hover {
    border-color: #c0c4cc;
}
.attr-chip.active {
    border-color: #303133;
    border-width: 2px;
    font-weight: 600;
    padding: 6px 15px;
}
.attr-chip.is-empty {
    color: #c0c4cc;
    border-style: dashed;
}
.attr-chip__dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
}
.variation-gallery__stock {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #ebeef5;
    padding-top: 12px;
    margin-top: 18px;
}
.variation-gallery__stock small {
    flex-basis: 100%;
}
@media (max-width: 767px) {
    .variations-title {
        flex-wrap: wrap;
        gap: 8px;
        padding-right: 20px;
    }
    .variations-title .el-dialog__title {
        font-size: 16px;
    }
    .variation-gallery {
        gap: 14px;
    }
    .variation-gallery__image {
        flex: 1 1 100%;
        aspect-ratio: auto;
        height: 32vh;
    }
    .variation-gallery__name {
        font-size: 16px;
    }
    .variation-gallery__price {
        font-size: 19px;
        margin-bottom: 14px;
    }
    .attr-chip {
        padding: 6px 13px;
        font-size: 12px;
    }
    .attr-chip.active {
        padding: 5px 12px;
    }
}
</style>

<style>
@media (max-width: 767px) {
    .pos-variations-dialog {
        width: 94% !important;
        margin-top: 4vh !important;
    }
    .pos-variations-dialog .el-dialog__header {
        padding: 14px 16px 10px;
    }
    .pos-variations-dialog .el-dialog__body {
        padding: 12px 16px;
        max-height: 74vh;
        overflow-y: auto;
    }
}
</style>
