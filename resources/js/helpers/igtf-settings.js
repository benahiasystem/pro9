import Vue from 'vue'
import {exactAmount} from './exchange-rate-math'
import {isRecordedReceipt} from './payment-classification'

// ######## INICIO CONFIGURACION COMPARTIDA IGTF ########
export const igtfSettings = Vue.observable({enabled: false, rate: null, loaded: false, loading: false, error: '', revision: 0})
let pending = null

export function setIgtfSettings(settings) {
    igtfSettings.revision++
    Object.assign(igtfSettings, {enabled: settings.enabled === true, rate: settings.rate, loaded: true, loading: false, error: ''})
}

export function loadIgtfSettings(http) {
    if (pending) return pending
    const revision = igtfSettings.revision
    igtfSettings.loading = true
    igtfSettings.error = ''
    pending = Promise.resolve().then(() => http.get('/companies/igtf')).then(response => {
        if (revision === igtfSettings.revision) setIgtfSettings(response.data.data)
    }).catch(() => {
        if (revision === igtfSettings.revision) {
            igtfSettings.loaded = false
            igtfSettings.error = 'No se pudo cargar la configuración IGTF. Reintente antes de registrar el cobro.'
        }
    }).finally(() => {
        igtfSettings.loading = false
        pending = null
    })
    return pending
}

export function fiscalPaymentProblem(payments, settings = igtfSettings) {
    const rows = (payments || []).filter(row => !isRecordedReceipt(row) && exactAmount(row.payment || '0').compare(0) > 0)
    if (!rows.length) return ''
    if (!settings.loaded || settings.loading) return settings.error || 'Espere la configuración IGTF antes de registrar el cobro.'
    if (!settings.enabled && rows.some(row => ['subject', 'exempt'].includes(row.igtf_status) || exactAmount(row.tax_amount || '0').compare(0) > 0)) {
        return 'IGTF está deshabilitado y el borrador conserva datos IGTF. Revise el cobro o habilite IGTF en Configuración avanzada; el borrador se conserva.'
    }
    return ''
}
// ######## FIN CONFIGURACION COMPARTIDA IGTF ########
