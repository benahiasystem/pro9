import {exactAmount, normalizeExchangeRate} from './exchange-rate-math'

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
export function isValidPosRate(value) {
    try { normalizeExchangeRate(value); return true } catch (_) { return false }
}

export function restorePosDraftRate(draft, currentRate) {
    if (!isValidPosRate(draft.exchange_rate_sale) && isValidPosRate(currentRate)) {
        draft.exchange_rate_sale = currentRate
    }
    return draft
}

export async function refreshPosRate(component) {
    const form = component.form
    const previous = form.exchange_rate_sale
    const date = form.date_of_issue
    const request = component._posRateRequest = (component._posRateRequest || 0) + 1
    component.posRatePending = true
    try {
        const rate = normalizeExchangeRate(await component.searchExchangeRateByDate(date))
        if (component._posRateRequest === request && component.form === form && form.exchange_rate_sale === previous && form.date_of_issue === date) {
            if (Array.isArray(form.items) && form.items.length && (!isValidPosRate(previous) || exactAmount(previous).compare(rate) !== 0)) {
                component.posCurrencyRepriceRequired = true
                form._pos_currency_reprice_required = true
            }
            form.exchange_rate_sale = rate
            component.exchangeRateError = ''
            return rate
        }
        return null
    } catch (_) {
        if (component._posRateRequest === request && component.form === form && form.date_of_issue === date && !isValidPosRate(form.exchange_rate_sale)) {
            component.exchangeRateError = 'No se pudo obtener la tasa de cambio. Intente actualizarla.'
        }
        return null
    } finally {
        if (component._posRateRequest === request) component.posRatePending = false
    }
}

export function ensurePosRate(component) {
    if (component.posRatePending) {
        component.$message.error('Espere la actualización de la tasa de cambio antes de continuar.')
        return false
    }
    if (isValidPosRate(component.form.exchange_rate_sale)) return true
    component.$message.error('No hay una tasa de cambio disponible. Intente actualizarla antes de continuar.')
    return false
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
