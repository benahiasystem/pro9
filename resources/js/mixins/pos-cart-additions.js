import { exactAmount, exactValue } from '../helpers/exchange-rate-math'
import { calculateRowItem, canMergeRowPrice, canMergeRowQuantity } from '../helpers/functions'
import { ensurePosRate } from '../helpers/pos-exchange-rate'

function presentationOf(row) {
    return row.presentation ?? row.item?.presentation ?? null
}

function presentationFactor(presentation) {
    return exactAmount(presentation?.quantity_unit ?? '1')
}

function capturePresentation(item) {
    const presentation = item.presentation
    if (!presentation) return null

    let unitId = presentation.item_unit_type_id
    if (unitId == null && presentation.id != null) {
        if (presentation.price_label_id != null) {
            unitId = item.item_unit_types?.find(unit =>
                unit.prices?.some(price => price.id === presentation.id))?.id
        } else if (presentation.item_id != null) {
            unitId = presentation.id
        }
    }
    return { ...presentation, item_unit_type_id: unitId }
}

function samePresentation(left, right) {
    if (!left || !right) return left === right
    const sameUnit = left.item_unit_type_id != null && right.item_unit_type_id != null
        ? String(left.item_unit_type_id) === String(right.item_unit_type_id)
        : left.description === right.description
    return sameUnit && presentationFactor(left).compare(presentationFactor(right)) === 0
}

function matchingLine(row, candidate) {
    return row.unit_type_id === candidate.unit_type_id &&
        row.affectation_igv_type_id === candidate.affectation_igv_type_id &&
        (row.price_input?.currency_type_id ?? row.item.currency_type_id) === candidate.price_input.currency_type_id &&
        samePresentation(presentationOf(row), candidate.presentation) &&
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
        addPosItem(item, { quantity = '1', scalePrice, line = null, onAdded } = {}) {
            if (!ensurePosRate(this)) return false
            if (!line && Number(item.sale_unit_price) < 0.1) {
                this.$message.error('El precio del producto debe ser mayor a 0.1')
                return false
            }
            try {
                if (!this.posStockByItem[item.id]) this.rememberPosStock(item)
                const selection = { ...item, presentation: capturePresentation(item) }
                const candidate = line ? null : this.posAdditionRow(selection, quantity, scalePrice)
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
            const quantityInput = quantity.compare(decimal) === 0
                ? null : { amount: fraction.n, unit_price: fraction.d }
            return calculateRowItem({
                ...row,
                item: { ...row.item, aux_quantity: decimal },
                quantity: decimal,
                aux_quantity: decimal,
                quantity_input: quantityInput,
            }, this.form.currency_type_id, this.form.exchange_rate_sale, this.percentage_igv)
        },
        posAdditionRow(item, quantity, scalePrice) {
            const { pos_stock, ...catalogItem } = item
            const { presentation, sale_unit_price, sale_affectation_igv_type_id, has_igv } = catalogItem
            const form = this.form
            const sourcePrice = exactAmount(scalePrice ?? sale_unit_price)
            const catalogPrice = item.calculation_snapshot?.values?.unit_price ??
                item.calculation_snapshot?.values?.sale_unit_price
            let price = scalePrice == null && catalogPrice && exactAmount(catalogPrice).decimal() === sourcePrice.decimal()
                ? exactAmount(catalogPrice) : sourcePrice
            if (scalePrice == null && !has_igv) {
                price = price.times(exactAmount(1).plus(this.percentage_igv))
            }
            const unitPrice = price.decimal()

            return calculateRowItem({
                ...this.form_item,
                pos_stock,
                item: { ...catalogItem, unit_price: unitPrice, aux_quantity: quantity, sale_unit_price_original: sale_unit_price },
                unit_type_id: presentation?.unit_type_id ?? item.unit_type_id,
                presentation,
                quantity,
                aux_quantity: quantity,
                unit_price: unitPrice,
                unit_price_value: sale_unit_price,
                price_input: {
                    amount: price.compare(unitPrice) === 0 ? unitPrice : price.fraction(),
                    currency_type_id: item.currency_type_id || form.currency_type_id,
                    includes_iva: true,
                },
                has_igv,
                affectation_igv_type_id: sale_affectation_igv_type_id,
                affectation_igv_type: this.affectation_igv_types.find(type => type.id === sale_affectation_igv_type_id),
            }, form.currency_type_id, form.exchange_rate_sale, this.percentage_igv)
        },
    },
}
