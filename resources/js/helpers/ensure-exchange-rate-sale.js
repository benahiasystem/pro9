// ######## INICIO MONEDA VENEZUELA HOTEL ########
export async function ensureExchangeRateSale(document, http) {
    const current = Number(document.exchange_rate_sale);
    if (Number.isFinite(current) && current > 0) return current;

    const response = await http.get(`/services/exchange/${document.date_of_issue}`);
    const rate = Number(response.data.sale);
    if (!Number.isFinite(rate) || rate <= 0) {
        throw new Error('No se pudo obtener un tipo de cambio válido para emitir el comprobante.');
    }

    document.exchange_rate_sale = rate;
    return rate;
}
// ######## FIN MONEDA VENEZUELA HOTEL ########
