// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
export function newFiscalOperationKey() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
