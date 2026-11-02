import {normalizeExchangeRate} from './exchange-rate-math'

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
    try {
        const rate = normalizeExchangeRate(await component.searchExchangeRateByDate(date))
        if (component._posRateRequest === request && component.form === form && form.exchange_rate_sale === previous && form.date_of_issue === date) {
            form.exchange_rate_sale = rate
            component.exchangeRateError = ''
        }
        return rate
    } catch (_) {
        if (component._posRateRequest === request && component.form === form && form.date_of_issue === date && !isValidPosRate(form.exchange_rate_sale)) {
            component.exchangeRateError = 'No se pudo obtener la tasa de cambio. Intente actualizarla.'
        }
        return null
    }
}

export function ensurePosRate(component) {
    if (isValidPosRate(component.form.exchange_rate_sale)) return true
    component.$message.error('No hay una tasa de cambio disponible. Intente actualizarla antes de continuar.')
    return false
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
