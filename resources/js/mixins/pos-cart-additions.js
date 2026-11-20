import { exactAmount, exactValue } from '../helpers/exchange-rate-math'
import { calculateRowItem, canMergeRowPrice, canMergeRowQuantity } from '../helpers/functions'
import { ensurePosRate } from '../helpers/pos-exchange-rate'
import { isPosPriceAllowed, posLinePrice } from '../helpers/pos-invoice-line'
import { posScalePrice, samePosScalePrices } from '../helpers/pos-scale-prices'
import { sameInvoiceRecordId } from '../helpers/invoice-lots'

function presentationOf(row) {
    return row.presentation ?? row.item?.presentation ?? null
}

function presentationFactor(presentation) {
    return presentation?.quantity_unit == null ? exactAmount(1) : exactValue(presentation, 'quantity_unit')
}

function capturePresentation(item, presentation = item.presentation) {
    if (!presentation) return null

    let unitId = presentation.item_unit_type_id
    if (unitId == null && presentation.id != null) {
        if (presentation.price_label_id != null) {
            unitId = item.item_unit_types?.find(unit =>
                unit.prices?.some(price => sameInvoiceRecordId(price.id, presentation.id)))?.id
        } else if (presentation.item_id != null) {
            unitId = presentation.id
        }
    }
    return unitId == null ? { ...presentation } : { ...presentation, id: unitId, item_unit_type_id: unitId }
}

function samePresentation(left, right) {
    if (!left || !right) return left === right
    const sameUnit = left.item_unit_type_id != null || right.item_unit_type_id != null
        ? sameInvoiceRecordId(left.item_unit_type_id, right.item_unit_type_id)
        : left.id != null || right.id != null
            ? sameInvoiceRecordId(left.id, right.id)
            : left.description === right.description
    return sameUnit && presentationFactor(left).compare(presentationFactor(right)) === 0
}

function matchingLine(row, candidate) {
    return row.unit_type_id === candidate.unit_type_id &&
        row.affectation_igv_type_id === candidate.affectation_igv_type_id &&
        (row.price_input?.currency_type_id ?? row.item.currency_type_id) === candidate.price_input.currency_type_id &&
        samePresentation(capturePresentation(row.item, presentationOf(row)), candidate.presentation) &&
        samePosScalePrices(row._pos_scale_prices, candidate._pos_scale_prices) &&
        canMergeRowQuantity(row) && canMergeRowPrice(row, candidate)
}

export default {
    data: () => ({
        posStockByItem: {},
        posStockBalances: {},
        posCartGeneration: 0,
        posBarcodePending: 0,
    }),
    methods: {
        rememberPosStock(item) {
            if (item.pos_stock) {
                this.posStockByItem[item.id] = item.pos_stock
                for (const component of item.pos_stock.components) {
                    this.posStockBalances[component.item_id] = component
                }
            }
            item.variations?.forEach(variation => {
                if (variation.sale_item) this.rememberPosStock(variation.sale_item)
            })
        },
        posStockComponents(row) {
            return this.posStockByItem[row.item_id] ?? row.pos_stock ?? row.item?.pos_stock
        },
        posStockDemand(rows) {
            const demand = new Map()
            for (const row of rows) {
                const snapshot = this.posStockComponents(row)
                if (!snapshot?.components.length) continue
                const quantity = exactValue(row, 'quantity').times(presentationFactor(presentationOf(row)))
                for (const component of snapshot.components) {
                    const id = String(component.item_id)
                    demand.set(id, (demand.get(id) ?? exactAmount(0)).plus(quantity.times(component.quantity)))
                }
            }
            return demand
        },
        validatePosStockChange(row, next) {
            const snapshot = this.posStockComponents(next)
            if (!snapshot?.components.length) return true

            const before = this.posStockDemand(this.form.items)
            const after = this.posStockDemand([...this.form.items.filter(current => current !== row), next])
            for (const component of snapshot.components) {
                const id = String(component.item_id)
                const requested = after.get(id)
                if (requested.compare(before.get(id) ?? 0) <= 0) continue
                const available = this.posStockBalances[id] ?? component
                if (available.stock == null) {
                    this.$message.error(`El producto ${available.description} no está disponible en su almacén.`)
                    return false
                }
                if (snapshot.control && requested.compare(available.stock) > 0) {
                    this.$message.error(`El producto ${available.description} no tiene suficiente stock.`)
                    return false
                }
            }
            return true
        },
        incrementPosLine(row) {
            if (!this.form.items.includes(row)) return false
            return this.addPosItem(row.item, { line: row })
        },
        addPosItem(item, { quantity = '1', scalePrices, line = null, onAdded } = {}) {
            if (!ensurePosRate(this)) return false
            if (!line && !isPosPriceAllowed(item)) {
                this.$message.error('El precio del producto debe ser mayor a 0.1')
                return false
            }
            try {
                if (!this.posStockByItem[item.id]) this.rememberPosStock(item)
                const selection = { ...item, presentation: capturePresentation(item) }
                const candidate = line ? null : this.posAdditionRow(selection, quantity, scalePrices)
                const current = line ?? this.form.items.find(row =>
                    String(row.item_id) === String(item.id) && matchingLine(row, candidate))
                const next = current ? this.posIncrementedRow(current, quantity) : candidate
                if (!this.validatePosStockChange(current, next)) return false

                if (current) {
                    this.$set(this.form.items, this.form.items.indexOf(current), next)
                    const entry = this.localPosStockChecks.find(check => check.key === current._pos_line_key)
                    if (entry) Object.assign(entry, { row: next, inputError: '' })
                } else {
                    this.form.items.unshift(next)
                }
                this.calculateTotal()
                this.setFormPosLocalStorage()
                onAdded?.()
                this.$notify({ title: '', message: 'Producto añadido!', type: 'success', duration: 1000 })
                this.cleanInput()
                this.initFocus()
                return true
            } catch (error) {
                this.$message.error(error.message || 'No se pudo agregar el producto.')
                return false
            }
        },
        posIncrementedRow(row, increment) {
            const quantity = exactValue(row, 'quantity').plus(increment)
            const decimal = quantity.decimal()
            const fraction = quantity.fraction()
            const presentation = capturePresentation(row.item, presentationOf(row))
            let quantityInput = null
            if (quantity.compare(decimal) !== 0) {
                quantityInput = this.form.document_type_id === '01'
                    ? { amount: '1', unit_price: { n: fraction.d, d: fraction.n } }
                    : { amount: fraction.n, unit_price: fraction.d }
            }
            return calculateRowItem({
                ...row,
                item: { ...row.item, presentation, aux_quantity: decimal },
                presentation,
                quantity: decimal,
                aux_quantity: decimal,
                quantity_input: quantityInput,
            }, this.form.currency_type_id, this.form.exchange_rate_sale, this.percentage_igv)
        },
        posAdditionRow(item, quantity, scalePrices) {
            const { pos_stock, ...catalogItem } = item
            const { presentation, sale_unit_price, sale_affectation_igv_type_id, has_igv } = catalogItem
            const form = this.form
            const scalePrice = scalePrices && posScalePrice(scalePrices, form.document_type_id)
            const price = scalePrice == null
                ? posLinePrice(catalogItem, form.currency_type_id, this.percentage_igv)
                : {
                    unit_price: exactAmount(scalePrice).decimal(),
                    price_input: {
                        amount: scalePrice,
                        currency_type_id: item.currency_type_id || form.currency_type_id,
                        includes_iva: true,
                    },
                }

            return calculateRowItem({
                ...this.form_item,
                pos_stock,
                ...(scalePrices ? { _pos_scale_prices: scalePrices } : {}),
                item: { ...catalogItem, unit_price: price.unit_price, aux_quantity: quantity, sale_unit_price_original: sale_unit_price },
                unit_type_id: presentation?.unit_type_id ?? item.unit_type_id,
                presentation,
                quantity,
                aux_quantity: quantity,
                unit_price: price.unit_price,
                unit_price_value: sale_unit_price,
                price_input: price.price_input,
                quantity_input: null,
                calculation_snapshot: null,
                has_igv,
                affectation_igv_type_id: sale_affectation_igv_type_id,
                affectation_igv_type: this.affectation_igv_types.find(type => type.id === sale_affectation_igv_type_id),
            }, form.currency_type_id, form.exchange_rate_sale, this.percentage_igv)
        },
    },
}
