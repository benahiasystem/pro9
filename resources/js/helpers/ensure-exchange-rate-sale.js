// ######## INICIO MONEDA VENEZUELA HOTEL ########
import { normalizeExchangeRate } from './exchange-rate-math'
export async function ensureExchangeRateSale(document, http) {
    try {
        const rate = normalizeExchangeRate(document.exchange_rate_sale)
        document.exchange_rate_sale = rate
        return rate
    } catch (_) { /* Consultar sólo cuando no existe una tasa válida. */ }
    const response = await http.get(`/services/exchange/${document.date_of_issue}`)
    const rate = normalizeExchangeRate(response.data.sale)
    document.exchange_rate_sale = rate
    return rate
}
// ######## FIN MONEDA VENEZUELA HOTEL ########
