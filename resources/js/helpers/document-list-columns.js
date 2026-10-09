/** Merge current columns with saved preferences, retaining their relative order. */
export function mergeDocumentColumns(defaults, saved = {}) {
    saved = saved || {};
    const columns = Object.fromEntries(Object.entries(defaults)
        .filter(([key]) => !['hka_status', 'downloads'].includes(key))
        .map(([key, column]) => [key, { ...column, ...(saved[key] || {}), title: column.title }]));

    if (columns.document_type && !Object.prototype.hasOwnProperty.call(saved, 'document_type')) {
        const ordered = Object.keys(columns)
            .filter(key => key !== 'document_type')
            .sort((a, b) => columns[a].order - columns[b].order);
        const customerIndex = ordered.indexOf('customer');
        ordered.splice(customerIndex < 0 ? ordered.length : customerIndex + 1, 0, 'document_type');
        ordered.forEach((key, index) => { columns[key].order = index; });
    }

    if (columns.total) columns.total.visible = true;

    return columns;
}
