// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
export function newFiscalOperationKey() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
}
// Refresh from the persisted sequence, never increment locally on a retry.
export function updateFiscalProfileEstimate(profiles, fiscal) {
    const next = Number(fiscal && fiscal.next_number);
    if (!fiscal || !Number.isSafeInteger(next) || next < 1) return profiles;
    return profiles.map(profile => Number(profile.id) === Number(fiscal.profile_id)
        ? {...profile, next_number: Math.max(Number(profile.next_number) || 0, next)}
        : profile);
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
