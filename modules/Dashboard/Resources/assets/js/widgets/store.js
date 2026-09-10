import Vue from 'vue'
import { coerceSize, defaultLayout, typeById, typesForSource } from './registry'

const LS_KEY = 'dashboard_widgets_layout_v1'
/** Una sola vez: inyecta Utilidades/Ganancias en layouts viejos que no lo tengan. */
const LS_SEED_UTILIDADES = 'dashboard_widgets_seed_utilidades_v1'

const UTILIDADES_WIDGET = {
  source: 'finanzas.utilidades',
  type: 'donut',
  size: 'l',
  cols: 4,
  rows: 5,
  options: {},
}

/**
 * Estado compartido del dashboard de widgets (Vue.observable, Vue 2.6).
 * Persistencia: localStorage como cache; el backend por usuario entra en
 * la fase de persistencia (GET/PUT /dashboard/widgets/layout).
 */
const state = Vue.observable({
  ready: false,
  catalog: { modules: [], sources: [] },
  layout: [],
  datasets: {},
  loading: {},
  editMode: false,
  modalOpen: false,
  filters: {},
})

function http() {
  return Vue.prototype.$http
}

export function dataKey(widget) {
  return widget.source + '|' + JSON.stringify(widget.options || {})
}

function sourceByKey(key) {
  return state.catalog.sources.find(s => s.key === key) || null
}

function sanitizeLayout(rawLayout) {
  if (!Array.isArray(rawLayout)) return []

  return rawLayout.filter(w => {
    if (!w || !w.id || !w.source || !w.type) return false
    const source = sourceByKey(w.source)
    if (!source) return false
    if (w.type === 'custom' && !source.custom_component) return false
    if (w.type !== 'custom' && !typeById(w.type)) return false
    return typesForSource(source).some(t => t.id === w.type)
  }).map(w => ({
    id: String(w.id),
    source: w.source,
    type: w.type,
    size: w.size || 'm',
    cols: w.cols || undefined,
    rows: w.rows || undefined,
    options: w.options || {},
  }))
}

let persistTimer = null

function persistLocal() {
  try {
    localStorage.setItem(LS_KEY, JSON.stringify(state.layout))
  } catch (e) { /* storage lleno o bloqueado: se ignora */ }
  persistRemoteDebounced()
}

/** Guarda en backend con debounce; el server re-valida contra el catálogo. */
function persistRemoteDebounced() {
  if (persistTimer) clearTimeout(persistTimer)
  persistTimer = setTimeout(() => {
    persistTimer = null
    http().put('/dashboard/widgets/layout', { layout: state.layout }).catch(() => {})
  }, 800)
}

function loadLocal() {
  try {
    const raw = localStorage.getItem(LS_KEY)
    if (raw) return sanitizeLayout(JSON.parse(raw))
  } catch (e) { /* json corrupto: se descarta */ }
  return []
}

/**
 * Clientes ya creados: si el layout guardado no incluye Utilidades/Ganancias,
 * lo agrega una vez (no se vuelve a forzar si el usuario lo quita después).
 */
function ensureUtilidadesWidget(layout) {
  if (!Array.isArray(layout) || !layout.length) return layout
  if (layout.some(w => w.source === UTILIDADES_WIDGET.source)) {
    try { localStorage.setItem(LS_SEED_UTILIDADES, '1') } catch (e) { /* ignore */ }
    return layout
  }
  if (!sourceByKey(UTILIDADES_WIDGET.source)) return layout

  let alreadySeeded = false
  try { alreadySeeded = localStorage.getItem(LS_SEED_UTILIDADES) === '1' } catch (e) { /* ignore */ }
  if (alreadySeeded) return layout

  const id = 'w' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6)
  const next = layout.concat([Object.assign({ id }, UTILIDADES_WIDGET)])
  try { localStorage.setItem(LS_SEED_UTILIDADES, '1') } catch (e) { /* ignore */ }
  return next
}

async function fetchDatasets(widgets) {
  const pending = []
  const seen = {}

  widgets.forEach(widget => {
    const key = dataKey(widget)
    if (seen[key]) return
    seen[key] = true
    pending.push({ key, source: widget.source, options: widget.options || {} })
    Vue.set(state.loading, key, true)
  })

  if (!pending.length) return

  try {
    const response = await http().post('/dashboard/widgets/data', {
      widgets: pending.map(p => ({ key: p.key, source: p.source, options: p.options })),
      filters: state.filters,
    })

    const data = response.data.data || {}
    pending.forEach(p => {
      Vue.set(state.datasets, p.key, data[p.key] || { error: 'empty' })
    })
  } catch (e) {
    pending.forEach(p => Vue.set(state.datasets, p.key, { error: 'request_failed' }))
  } finally {
    pending.forEach(p => Vue.set(state.loading, p.key, false))
  }
}

export const widgetStore = {
  state,

  async init(filters) {
    state.filters = Object.assign({}, filters)

    const response = await http().get('/dashboard/widgets/catalog')
    state.catalog = response.data

    // Prioridad: layout del usuario (backend) > cache local > réplica por defecto.
    let layout = []
    try {
      const saved = await http().get('/dashboard/widgets/layout')
      layout = sanitizeLayout(saved.data.layout)
    } catch (e) { /* sin backend de layout: cae a local */ }

    if (!layout.length) layout = loadLocal()
    layout = layout.length ? layout : defaultLayout(state.catalog.sources)
    const seeded = ensureUtilidadesWidget(layout)
    state.layout = seeded
    state.ready = true

    if (seeded !== layout) persistLocal()

    await this.refresh()
  },

  async setFilters(filters) {
    state.filters = Object.assign({}, filters)
    state.datasets = {}
    await this.refresh()
  },

  async refresh() {
    await fetchDatasets(state.layout)
  },

  source(key) {
    return sourceByKey(key)
  },

  dataset(widget) {
    return state.datasets[dataKey(widget)] || null
  },

  isLoading(widget) {
    return !!state.loading[dataKey(widget)]
  },

  toggleEdit() {
    const wasEditing = state.editMode
    state.editMode = !state.editMode
    if (wasEditing) persistLocal()
  },

  openModal() {
    state.modalOpen = true
  },

  closeModal() {
    state.modalOpen = false
  },

  moveWidget(fromIndex, toIndex) {
    if (fromIndex === null || toIndex === null || fromIndex === toIndex) return
    const layout = state.layout.slice()
    const [item] = layout.splice(fromIndex, 1)
    layout.splice(toIndex, 0, item)
    state.layout = layout
    persistLocal()
  },

  removeWidget(id) {
    state.layout = state.layout.filter(w => w.id !== id)
    persistLocal()
  },

  setType(id, typeId) {
    state.layout = state.layout.map(w => {
      if (w.id !== id) return w
      return Object.assign({}, w, {
        type: typeId,
        size: typeId === 'custom' ? w.size : coerceSize(typeId, w.size),
        cols: undefined,
        rows: undefined,
      })
    })
    persistLocal()
  },

  cycleSize(id) {
    state.layout = state.layout.map(w => {
      if (w.id !== id) return w
      const sizes = typeById(w.type).sizes
      const current = sizes.indexOf(w.size)
      return Object.assign({}, w, { size: sizes[(current + 1) % sizes.length], cols: undefined, rows: undefined })
    })
    persistLocal()
  },

  resizeWidget(id, cols, rows) {
    state.layout = state.layout.map(w => {
      return w.id === id ? Object.assign({}, w, { cols, rows }) : w
    })
  },

  persist() {
    persistLocal()
  },

  async addWidget(widget) {
    const id = 'w' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6)
    const entry = Object.assign({ id, options: {} }, widget)
    state.layout = state.layout.concat([entry])
    persistLocal()
    await fetchDatasets([entry])
    return entry
  },

  async resetLayout() {
    state.layout = defaultLayout(state.catalog.sources)
    persistLocal()
    await this.refresh()
  },

  /** Dataset puntual para el preview del modal (no toca el layout). */
  async preview(source, options) {
    const widget = { source, options: options || {} }
    await fetchDatasets([widget])
    return this.dataset(widget)
  },
}
