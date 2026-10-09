/** Presentation only: fiscal results never replace the stored commercial state. */
export function documentStatus(record = {}) {
    const emission = record.fiscal_emission || {};
    const localState = String(record.state_type_id || '');
    const useFiscal = Boolean(emission.status) && !['09', '11', '13'].includes(localState);
    const rejected = localState === '09' || (useFiscal && emission.status === 'rejected');
    const color = useFiscal
        ? ({ confirmed: 'success', rejected: 'danger', pending: 'warning', uncertain: 'warning', cancelled: 'dark' }[emission.status] || 'secondary')
        : ({ '03': 'info', '05': 'success', '07': 'warning', '09': 'danger', '11': 'danger', '13': 'warning' }[localState] || 'secondary');

    return {
        label: (useFiscal ? emission.description : record.state_type_description) || '—',
        badgeClass: `bg-${color} text-${color === 'warning' ? 'dark' : 'white'}`,
        controlNumber: emission.control_number || 'Sin asignar',
        rejectionReason: (rejected && emission.status === 'rejected' && emission.diagnostic)
            || 'Motivo de rechazo no disponible',
        rejected,
    };
}
