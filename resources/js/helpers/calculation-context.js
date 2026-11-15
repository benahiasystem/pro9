// ######## INICIO CONTEXTO DE CALCULO LOCAL ########
import Vue from 'vue'
export const calculationContexts = Vue.observable({entries: {}})
const pending = new Map()
const generations = new Map()
export function contextKey(input) { return JSON.stringify(input) }
export function loadCalculationContext(http, input, force = false) {
    const key = contextKey(input)
    if (!force && pending.has(key)) return pending.get(key)
    if (!force && calculationContexts.entries[key]) return Promise.resolve(calculationContexts.entries[key])
    const generation = (generations.get(key) || 0) + 1
    generations.set(key,generation)
    const task = http.post('/documents/calculation-context', input).then(({data}) => {
        if (generations.get(key) !== generation) return pending.get(key) || calculationContexts.entries[key]
        Vue.set(calculationContexts.entries, key, data.data)
        return data.data
    }, error => {
        if (generations.get(key) !== generation) return pending.get(key) || calculationContexts.entries[key]
        throw error
    }).finally(() => { if (generations.get(key) === generation) pending.delete(key) })
    pending.set(key, task)
    return task
}
// ######## FIN CONTEXTO DE CALCULO LOCAL ########
