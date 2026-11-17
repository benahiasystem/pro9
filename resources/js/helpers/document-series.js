export function hasNamedSeries(series = []) {
    return series.some(option => String(option.number ?? '').trim() !== '');
}
