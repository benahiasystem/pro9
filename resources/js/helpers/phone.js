// ########### INICIO CAMBIO TELEFONÍA VENEZUELA
// Mirrors Localization::whatsappNumber without converting foreign prefixes.
export function whatsappNumber(value) {
    const input = String(value ?? '').trim()
    const digits = input.replace(/\D/g, '')
    if (!digits) return null
    if (digits.startsWith('58')) return digits
    if (input.startsWith('+')) return null
    const local = digits.replace(/^0+/, '')
    return local ? `58${local}` : null
}
// ########### FIN CAMBIO TELEFONÍA VENEZUELA
