// Display only: numeric persistence, filenames and fiscal payloads keep their own values.
export function displayDocumentNumber(number) {
    const value = number == null ? '' : String(number);
    return /^[0-9]+$/.test(value) ? value.padStart(8, '0') : value;
}

export function documentNumberFull(series, number) {
    const value = displayDocumentNumber(number);
    return series == null || series === '' ? value : `${series}-${value}`;
}
