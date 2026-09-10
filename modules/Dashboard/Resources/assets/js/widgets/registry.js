/**
 * Registro frontend del dashboard de widgets: tamaños, tipos de gráfica,
 * recomendador y utilidades de tema. El catálogo de fuentes (módulos y
 * métricas) viene del backend (/dashboard/widgets/catalog).
 */

export const GRID_COLUMNS = 12
export const GRID_ROW_HEIGHT = 96
export const GRID_GAP = 16

/** Títulos de "Ventas…" según el periodo global (espejo de RowTop.salesTitle). */
const PERIOD_SALES_TITLES = {
  all: 'Ventas totales',
  last_week: 'Ventas de la semana',
  month: 'Ventas del mes',
  between_months: 'Ventas entre meses',
  date: 'Venta del dia',
  between_dates: 'Ventas entre fechas',
}

const PERIOD_CHANGE_LABELS = {
  all: 'vs mes anterior',
  last_week: 'vs semana anterior',
  month: 'vs mes anterior',
  between_months: 'vs periodo anterior',
  date: 'vs día anterior',
  between_dates: 'vs periodo anterior',
}

export function salesTitleForPeriod(period) {
  return PERIOD_SALES_TITLES[period] || 'Ventas'
}

export function changeLabelForPeriod(period) {
  return PERIOD_CHANGE_LABELS[period] || 'vs periodo anterior'
}

export const SIZES = [
  { id: 's', label: 'S', cols: 3 },
  { id: 'm', label: 'M', cols: 4 },
  { id: 'l', label: 'L', cols: 6 },
  { id: 'xl', label: 'XL', cols: 12 },
]

export const GROUPS = ['Cuadro de resultados', 'Serie temporal', 'Barras', 'Circular', 'Tablas']

export const TYPES = [
  { id: 'kpi', label: 'Resultado', group: 'Cuadro de resultados', icon: 'ti-123', sizes: ['s', 'm'] },
  { id: 'kpi_spark', label: 'KPI + sparkline', group: 'Cuadro de resultados', icon: 'ti-timeline', sizes: ['s', 'm'] },
  { id: 'line', label: 'Línea', group: 'Serie temporal', icon: 'ti-chart-line', sizes: ['m', 'l', 'xl'] },
  { id: 'area', label: 'Área', group: 'Serie temporal', icon: 'ti-chart-area-line', sizes: ['m', 'l', 'xl'] },
  { id: 'bar', label: 'Columnas', group: 'Barras', icon: 'ti-chart-bar', sizes: ['l', 'xl'] },
  { id: 'barh', label: 'Barras horizontales', group: 'Barras', icon: 'ti-chart-histogram', sizes: ['m', 'l'] },
  { id: 'pie', label: 'Torta', group: 'Circular', icon: 'ti-chart-pie', sizes: ['s', 'm', 'l'] },
  { id: 'donut', label: 'Anillo', group: 'Circular', icon: 'ti-chart-donut-2', sizes: ['s', 'm', 'l'] },
  { id: 'radial', label: 'Radial', group: 'Circular', icon: 'ti-chart-arcs', sizes: ['s', 'm'] },
  { id: 'table', label: 'Tabla', group: 'Tablas', icon: 'ti-table', sizes: ['s', 'm', 'l'] },
  { id: 'ranking', label: 'Ranking', group: 'Tablas', icon: 'ti-list-numbers', sizes: ['m', 'l'] },
]

// Tipo especial: vista original del card (componente Vue propio de la fuente).
export const CUSTOM_TYPE = { id: 'custom', label: 'Vista original', group: 'Tablas', icon: 'ti-layout-dashboard', sizes: ['s', 'm', 'l', 'xl'] }

export function typeById(id) {
  if (id === 'custom') return CUSTOM_TYPE
  return TYPES.find(t => t.id === id) || TYPES[0]
}

/**
 * Tipos disponibles para una fuente del catálogo: lista explícita de la
 * fuente si la declara; si no, todos los genéricos, anteponiendo la vista
 * custom cuando la fuente aporta componente propio.
 */
export function typesForSource(source) {
  if (!source) return TYPES.slice()

  let list
  if (Array.isArray(source.types) && source.types.length) {
    list = source.types.map(typeById).filter(Boolean)
  } else {
    list = TYPES.slice()
    if (source.custom_component) list = [CUSTOM_TYPE].concat(list)
  }

  // Sin datos categóricos ni serie no hay nada que restringir aquí:
  // recFor se encarga de marcar legibilidad en el picker.
  return list
}

/**
 * Recomendador portado del prototipo: 'rec' | 'ok' | 'bad' según el foco
 * de la métrica y el nº de categorías del dataset.
 */
export function recFor(source, dataset, typeId) {
  if (typeId === 'custom') return 'rec'

  const focus = source ? source.focus : 'serie'
  const catN = dataset && dataset.breakdown ? dataset.breakdown.labels.length : 0
  const hasSerie = !!(dataset && dataset.series && dataset.series.length)
  const hasBreakdown = catN > 0

  if (typeId === 'kpi' || typeId === 'kpi_spark') return focus === 'serie' ? 'rec' : 'ok'
  if (['line', 'area', 'bar'].includes(typeId)) {
    if (!hasSerie && hasBreakdown) return 'bad'
    return focus === 'serie' ? 'rec' : 'bad'
  }
  if (['pie', 'donut'].includes(typeId)) {
    if (!hasBreakdown) return 'bad'
    if (catN > 5) return 'bad'
    return focus === 'categorias' ? 'rec' : 'ok'
  }
  if (typeId === 'radial') return catN <= 2 && focus === 'categorias' ? 'rec' : 'ok'
  if (typeId === 'barh' || typeId === 'ranking' || typeId === 'table') {
    if (!hasBreakdown) return 'bad'
    return focus === 'categorias' ? 'rec' : 'ok'
  }

  return 'ok'
}

/** Ajusta el tamaño al mínimo permitido por el tipo (portado del prototipo). */
export function coerceSize(typeId, sizeId) {
  const type = typeById(typeId)
  if (type.sizes.includes(sizeId)) return sizeId
  const cur = (SIZES.find(s => s.id === sizeId) || SIZES[1]).cols
  return type.sizes.find(id => SIZES.find(z => z.id === id).cols >= cur) || type.sizes[type.sizes.length - 1]
}

export function sizeCols(sizeId) {
  return (SIZES.find(s => s.id === sizeId) || SIZES[1]).cols
}

/** Colores del tema leídos de las CSS vars, con fallback (como index.vue). */
export function themeColors() {
  const styles = getComputedStyle(document.documentElement)
  const read = (names, fallback) => {
    for (const name of [].concat(names)) {
      const value = styles.getPropertyValue(name).trim()
      if (value) return value
    }
    return fallback
  }

  return {
    primary: read(['--primary', '--primary-color'], '#2447e8'),
    danger: read('--danger', '#fe006c'),
    success: read('--success', '#00c666'),
    warning: read('--warning', '#ff8400'),
    info: read('--info', '#00cfe8'),
    muted: '#9ca3af',
    grid: '#eef0f3',
  }
}

/** Paleta categórica para donut/pie/barh (deriva del tema). */
export function themePalette() {
  const c = themeColors()
  return [c.primary, c.danger, c.success, c.warning, c.info, '#7c3aed', '#0e7490', '#b45309']
}

export function formatCompact(value, unit) {
  const n = Number(value) || 0
  let s
  if (Math.abs(n) >= 1000000) s = (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
  else if (Math.abs(n) >= 1000) s = (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K'
  else s = unit === 'money' ? String(Math.round(n * 100) / 100) : String(Math.round(n))
  return unit === 'money' ? 'S/ ' + s : s
}

/**
 * Valor de KPI compacto (espejo de RowTop.formatNumber) para que quepa
 * junto al sparkline: 29.3K / 1.2M, o 2 decimales si es menor a mil.
 */
export function formatKpi(value, unit) {
  const n = Number(value) || 0
  if (unit === 'percent') {
    return n.toLocaleString('es-PE', { maximumFractionDigits: 1 }) + '%'
  }
  if (unit !== 'money') {
    if (Math.abs(n) >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0+$/, '') + 'M'
    if (Math.abs(n) >= 1000) return (n / 1000).toFixed(1).replace(/\.0+$/, '') + 'K'
    return Math.round(n).toLocaleString('es-PE')
  }
  let amount
  if (Math.abs(n) >= 1000000) amount = (n / 1000000).toFixed(1).replace(/\.0+$/, '') + 'M'
  else if (Math.abs(n) >= 1000) amount = (n / 1000).toFixed(1).replace(/\.0+$/, '') + 'K'
  else amount = n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return 'S/ ' + amount
}

export function formatValue(value, unit) {
  const n = Number(value) || 0
  if (unit === 'money') {
    return 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  }
  if (unit === 'percent') return n.toLocaleString('es-PE', { maximumFractionDigits: 1 }) + '%'
  return Math.round(n).toLocaleString('es-PE')
}

/**
 * Layout por defecto: réplica del dashboard actual. Se filtra contra el
 * catálogo (los flags dashboard_* ya ocultan fuentes en el backend) y se
 * ajustan spans según qué fuentes existan, igual que hace la vista legacy
 * con sus v-if de configuración.
 */
export function defaultLayout(catalogSources) {
  const has = key => catalogSources.some(s => s.key === key)
  const goal = has('finanzas.meta_mes')
  const products = has('ventas.top_productos')

  // Todas las entradas se declaran y el filtro final por catálogo decide;
  // los flags solo ajustan anchos para que las bandas sumen 12 columnas.
  const layout = [
    { source: 'ventas.ventas_totales', type: 'kpi_spark', size: 's', rows: 1 },
    { source: 'ventas.ticket_promedio', type: 'kpi_spark', size: 's', rows: 1 },
    { source: 'finanzas.por_cobrar', type: 'kpi_spark', size: 's', rows: 1 },
    { source: 'finanzas.utilidad_neta', type: 'kpi_spark', size: 's', rows: 1 },
    { source: 'ventas.notas_venta', type: 'donut', size: 's' },
    { source: 'ventas.comprobantes', type: 'donut', size: 's' },
    { source: 'ventas.totales', type: 'area', size: 'l' },
    { source: 'ventas.periodo', type: 'bar', size: 'xl', cols: goal ? 8 : 12, rows: 4 },
    { source: 'finanzas.meta_mes', type: 'custom', size: 'm', cols: 4, rows: 4 },
    { source: 'finanzas.deudores', type: 'custom', size: 'l', cols: products ? 4 : 6, rows: 6 },
    { source: 'ventas.top_productos', type: 'ranking', size: 'm', cols: 4, rows: 6 },
    { source: 'finanzas.medios_pago', type: 'donut', size: 'l', cols: products ? 4 : 6, rows: 6 },
    { source: 'finanzas.flujo_caja', type: 'area', size: 'l', cols: 8, rows: 5 },
    { source: 'finanzas.utilidades', type: 'donut', size: 'l', cols: 4, rows: 5 },
    { source: 'sunat.estado_cpe', type: 'custom', size: 'm', cols: 4, rows: 2 },
    { source: 'inventario.stock_bajo', type: 'custom', size: 'm', cols: 4, rows: 3 },
  ]

  return layout
    .filter(w => has(w.source))
    .map((w, index) => Object.assign({ id: 'w' + (index + 1), options: {} }, w))
}
