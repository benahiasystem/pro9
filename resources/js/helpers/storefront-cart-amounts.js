import {exactAmount, exactInput, exactSnapshot, normalizeExchangeRate} from './exchange-rate-math'

// ######## INICIO CALCULOS EXACTOS SAB ########
// ########## INICIO CAMBIO AFECTACIÓN IVA
// Startup fallback only; the storefront injects Localization::exactTaxPercentage().
export const DEFAULT_CART_TAX_PERCENTAGE = '16'
// ######### FIN CAMBIO AFECTACIÓN IVA

/** Positive PostgreSQL bigint key; an unsafe Number has already lost its identity. */
export function cartIdentifier(value) {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? String(value) : null
    }
    if (typeof value !== 'string' || value.length > 19 || !/^[1-9]\d*$/.test(value)) return null
    return BigInt(value) <= 9223372036854775807n ? value : null
}

/** Keep the existing request type for small keys and exact text for large keys. */
export function cartRequestIdentifier(value) {
    const token=cartIdentifier(value)
    if (token === null) return null
    return BigInt(token) <= 9007199254740991n ? Number(token) : token
}

export function cartQuantity(value) {
    const text = exactInput(String(value ?? ''))
    if (exactAmount(text).compare('0') <= 0) throw new Error('La cantidad debe ser positiva.')
    return text
}

/** Restore textual inputs without clearing an invalid cache or inventing a zero price. */
export function cartRestoreRecords(encoded, options = {}) {
    const records = JSON.parse(encoded || '[]')
    if (!Array.isArray(records)) throw new Error('El carrito guardado no es válido. Vuelve a agregar los productos.')
    return records.map(source => {
        if (!source || typeof source !== 'object' || Array.isArray(source) || cartIdentifier(source.id) === null) {
            throw new Error('El carrito contiene un producto inválido.')
        }
        const row = {...source, cantidad: cartQuantity(source.quantity ?? source.cantidad ?? '1')}
        if (row.sale_unit_price == null && !options.allowUnavailablePrices) throw new Error('El precio guardado no está disponible. Vuelve a agregar el producto.')
        row.currency_type_id = row.currency_type_id || 'VES'
        if (!['VES','USD'].includes(row.currency_type_id)) throw new Error('El producto requiere una moneda vigente.')
        row.sub_total = row.sale_unit_price == null ? null : cartLineAmount(row, {convert:false}).total.final(2)
        row.exchange_rate_sale = ''
        row.currency_type_symbol = row.currency_type_symbol || row.currency_type?.symbol || (row.currency_type_id === 'USD' ? 'USD' : 'Bs.')
        return row
    })
}

/** Cart quantities are requests; a catalog item's quantity snapshot is not a cart input. */
export function cartLineAmount(row, options = {}) {
    const quantityText = cartQuantity(row.cantidad ?? row.quantity ?? '1')
    const quantity = exactAmount(quantityText)
    if (row.sale_unit_price === undefined || row.sale_unit_price === null) {
        // A historical summary projection may contain only its already converted subtotal.
        const total = exactAmount(row.cart_calculation_snapshot?.values?.total ?? row.sub_total ?? '0')
        if (total.compare('0') < 0) throw new Error('El subtotal no puede ser negativo.')
        return {quantityText, quantity, sourceUnitPrice: total.dividedBy(quantity),
            unitPrice: total.dividedBy(quantity), total}
    }
    const priceText = exactInput(String(row.sale_unit_price))
    let price = exactAmount(priceText)
    const campaignPrice = row.campaign_pricing_snapshot?.values?.final_unit_price
        ?? row.campaign_pricing_snapshot?.values?.final_price
    const preserved = row.calculation_snapshot?.values?.sale_unit_price
    if (campaignPrice && exactAmount(campaignPrice).decimal(12) === price.decimal(12)) price = exactAmount(campaignPrice)
    else if (preserved && !row.campaign_discount_embedded && !row.discount_campaign_id && !row.has_discount && !row.promotion_id
        && exactAmount(preserved).decimal(12) === price.decimal(12)) price = exactAmount(preserved)
    if (price.compare('0') < 0) throw new Error('El precio no puede ser negativo.')
    const sourceUnitPrice = price
    if (row.currency_type_id === 'USD' && options.convert !== false) {
        price = price.times(normalizeExchangeRate(options.exchangeRate))
    }
    return {quantityText, quantity, sourceUnitPrice, unitPrice: price, total: price.times(quantity)}
}

/** Estimate from rational line amounts; authoritative checkout is recalculated by the server. */
export function cartSummaryAmounts(records, options = {}) {
    // ########## INICIO CAMBIO AFECTACIÓN IVA
    const percentage = exactAmount(exactInput(String(options.percentage ?? DEFAULT_CART_TAX_PERCENTAGE), 8, 3))
    if (percentage.compare('0') < 0 || percentage.compare('100') > 0) throw new Error('El porcentaje de IVA no es válido.')
    const multiplier = exactAmount('1').plus(percentage.dividedBy('100'))
    // ######### FIN CAMBIO AFECTACIÓN IVA
    let taxed = exactAmount('0'), exempt = exactAmount('0'), iva = exactAmount('0'), subtotal = exactAmount('0')
    for (const row of records) {
        const line = cartLineAmount(row, {exchangeRate: options.exchangeRate})
        if (row.sale_affectation_igv_type_id === '10') {
            const base = line.total.dividedBy(multiplier)
            taxed = taxed.plus(base)
            iva = iva.plus(line.total.minus(base))
        } else if (row.sale_affectation_igv_type_id === '20') exempt = exempt.plus(line.total)
        else throw new Error('Seleccione una afectación de IVA vigente.')
        subtotal = subtotal.plus(line.total)
    }
    const coupon = exactAmount(options.coupon ?? '0'), campaign = exactAmount(options.campaign ?? '0')
    if (coupon.compare('0') < 0 || campaign.compare('0') < 0) throw new Error('El descuento no puede ser negativo.')
    let total = subtotal.minus(coupon).minus(campaign)
    if (total.compare('0') < 0) total = exactAmount('0')
    const delivery = options.freeShipping ? exactAmount('0') : exactAmount(options.delivery ?? '0')
    if (delivery.compare('0') < 0) throw new Error('El envío no puede ser negativo.')
    const deliveryIva = delivery.minus(delivery.dividedBy(multiplier))
    total = total.plus(delivery)
    const values = {subtotal, tax: iva, total_taxed: taxed, total_exonerated: exempt,
        total_igv: iva, total_value: taxed.plus(exempt), delivery, delivery_igv: deliveryIva, total}
    return {...Object.fromEntries(Object.entries(values).map(([field, value]) => [field, value.final(2)])),
        calculation_snapshot: exactSnapshot(values)}
}
// ######## FIN CALCULOS EXACTOS SAB ########
