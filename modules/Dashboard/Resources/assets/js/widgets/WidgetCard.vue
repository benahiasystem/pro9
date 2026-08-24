<template>
  <div class="wg-cell" :class="{ 'is-edit': editMode, 'is-over': dragOver, 'is-kpi': isKpi }" :style="cellStyle">

    <!-- Vista custom: el componente trae su propio card; solo se superpone la capa de edición -->
    <component
      v-if="widget.type === 'custom' && customComponent"
      :is="customComponent"
      :filters="filters"
      class="wg-custom"
    ></component>

    <section v-else class="card card-dashboard wg-card">
      <div
        class="card-body card-body-border-radius"
        :class="{ 'card-kpi': isKpi, 'justify-content-start': isKpi }"
      >
        <template v-if="loading">
          <loader-graph :rows="4" :columns="1" :radius="50"></loader-graph>
        </template>

        <template v-else-if="hasError">
          <div class="wg-head">
            <div>
              <h5 class="wg-title m-0">{{ title }}</h5>
            </div>
          </div>
          <div class="wg-empty text-muted text-center">No se pudo cargar este widget.</div>
        </template>

        <template v-else-if="dataset">
          <small v-if="isKpi" class="text-muted">{{ title }}</small>

          <div v-else class="wg-head">
            <div>
              <h5 class="wg-title m-0">{{ title }}</h5>
              <small v-if="subtitle" class="text-muted">{{ subtitle }}</small>
            </div>
            <!-- Leyenda de series en la esquina superior derecha (patrón del card Totales original) -->
            <div v-if="headLegendItems.length && !editMode" class="wg-head-legend">
              <span v-for="item in headLegendItems" :key="item.label" class="wg-head-legend-item">
                <i class="wg-dot" :style="{ background: item.color }"></i>{{ item.label }}
              </span>
            </div>
          </div>

          <kpi-renderer
            v-if="isKpi"
            :dataset="dataset"
            :spark="widget.type === 'kpi_spark'"
            :spark-height="kpiSparkHeight"
            :period="filters.period"
          ></kpi-renderer>

          <div v-else-if="!hasData" class="wg-empty text-muted text-center">Sin datos en el periodo.</div>

          <table-renderer v-else-if="widget.type === 'table'" :dataset="dataset"></table-renderer>

          <ranking-renderer v-else-if="widget.type === 'ranking'" :dataset="dataset"></ranking-renderer>

          <!-- Circulares: chart + leyenda con valores (patrón sn-legend del dashboard original) -->
          <div v-else-if="isCircular" class="wg-circular">
            <div class="wg-circular-chart">
              <apex-renderer :type="widget.type" :dataset="dataset" :height="chartHeight" :width="colSpan"></apex-renderer>
            </div>
            <ul class="wg-vlegend">
              <li v-for="item in circularLegendItems" :key="item.label" class="wg-vlegend-item">
                <i class="wg-dot" :style="{ background: item.color }"></i>
                <span class="wg-vlegend-label text-truncate">{{ item.label }}</span>
                <span class="wg-vlegend-value">{{ item.valueStr }}</span>
              </li>
            </ul>
          </div>

          <apex-renderer v-else :type="widget.type" :dataset="dataset" :height="chartHeight" :width="colSpan"></apex-renderer>
        </template>
      </div>
    </section>

    <!-- Controles de edición -->
    <div v-if="editMode" class="wg-edit-bar">
      <el-tooltip content="Arrastra para mover" placement="top">
        <button type="button" class="wg-edit-btn wg-drag" @mousedown.stop>
          <i class="ti ti-grip-vertical"></i>
        </button>
      </el-tooltip>
      <el-tooltip :content="'Tamaño: ' + sizeLabel" placement="top">
        <button type="button" class="wg-edit-btn" @click.stop="$emit('cycle-size')">
          {{ sizeLabel }}
        </button>
      </el-tooltip>
      <el-tooltip content="Cambiar tipo de gráfica" placement="top">
        <button type="button" class="wg-edit-btn" @click.stop="$emit('open-picker')">
          <i :class="['ti', typeIcon]"></i>
        </button>
      </el-tooltip>
      <el-tooltip content="Quitar widget" placement="top">
        <button type="button" class="wg-edit-btn wg-remove" @click.stop="$emit('remove')">
          <i class="ti ti-x"></i>
        </button>
      </el-tooltip>
    </div>

    <!-- Picker de tipo in-place -->
    <div v-if="editMode && pickerOpen" class="wg-picker" @click.stop>
      <button
        v-for="option in typeOptions"
        :key="option.id"
        type="button"
        class="wg-picker-item"
        :class="{ 'is-active': option.id === widget.type }"
        @click="$emit('set-type', option.id)"
      >
        <i :class="['ti', option.icon]"></i> {{ option.label }}
        <span v-if="option.badge" class="wg-picker-badge" :class="'is-' + option.rec">{{ option.badge }}</span>
      </button>
    </div>

    <!-- Handle de resize -->
    <div
      v-if="editMode"
      class="wg-resize"
      @mousedown.prevent.stop="$emit('resize-start', $event)"
    ><i class="ti ti-arrows-diagonal-2"></i></div>
  </div>
</template>

<script>
import LoaderGraph from '../components/loaders/l-graph.vue'
import ApexRenderer from './renderers/ApexRenderer.vue'
import KpiRenderer from './renderers/KpiRenderer.vue'
import RankingRenderer from './renderers/RankingRenderer.vue'
import TableRenderer from './renderers/TableRenderer.vue'
import Debtors from '../views/partials/Debtors.vue'
import MonthGoal from '../views/partials/MonthGoal.vue'
import SunatStatus from '../views/partials/SunatStatus.vue'
import LowStock from '../views/partials/LowStock.vue'
import { GRID_GAP, GRID_ROW_HEIGHT, formatValue, recFor, salesTitleForPeriod, themeColors, themePalette, typeById, typesForSource } from './registry'

// Componentes custom aportados por fuentes del propio módulo Dashboard.
// Fuentes de otros módulos registran los suyos vía registerCustomComponent.
const CUSTOM_COMPONENTS = {
  'widget-debtors': Debtors,
  'widget-month-goal': MonthGoal,
  'widget-sunat-status': SunatStatus,
  'widget-low-stock': LowStock,
}

export function registerCustomComponent(name, component) {
  CUSTOM_COMPONENTS[name] = component
}

export default {
  name: 'WidgetCard',
  components: { LoaderGraph, ApexRenderer, KpiRenderer, RankingRenderer, TableRenderer },
  props: {
    widget: { type: Object, required: true },
    source: { type: Object, default: null },
    dataset: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    editMode: { type: Boolean, default: false },
    pickerOpen: { type: Boolean, default: false },
    dragOver: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    colSpan: { type: Number, required: true },
    rowSpan: { type: Number, required: true },
  },
  computed: {
    customComponent() {
      return this.source && this.source.custom_component
        ? CUSTOM_COMPONENTS[this.source.custom_component] || null
        : null
    },
    title() {
      if (this.source && this.source.key === 'ventas.ventas_totales') {
        return salesTitleForPeriod(this.filters && this.filters.period)
      }
      return this.source ? this.source.label : this.widget.source
    },
    subtitle() {
      // Los KPI ya dicen "vs periodo anterior" junto al delta; sin subtítulo duplicado.
      if (this.widget.type === 'kpi' || this.widget.type === 'kpi_spark') return ''
      if (this.dataset && this.dataset.meta && this.dataset.meta.subtitle) return this.dataset.meta.subtitle
      return this.source ? this.source.description || this.source.module_label : ''
    },
    hasError() {
      return !!(this.dataset && this.dataset.error)
    },
    hasData() {
      const d = this.dataset
      if (!d || d.error) return false
      const serieHas = (d.series || []).some(s => (s.data || []).some(v => Number(v) !== 0))
      const breakdownHas = (d.breakdown && d.breakdown.values || []).some(v => Number(v) !== 0)
      return serieHas || breakdownHas
    },
    isCircular() {
      return ['pie', 'donut', 'radial'].includes(this.widget.type)
    },
    /** Colores espejo de ApexRenderer para que la leyenda coincida con el chart. */
    serieColors() {
      const theme = themeColors()
      const colors = [theme.primary, theme.danger, theme.success, theme.warning, theme.info]
      if (this.widget.type === 'bar' && (this.dataset.series || []).length === 2) colors[1] = '#d9dee7'
      return colors
    },
    headLegendItems() {
      if (!this.dataset || this.dataset.error || !this.hasData) return []
      if (!['line', 'area', 'bar'].includes(this.widget.type)) return []
      const series = this.dataset.series || []
      if (series.length < 2) return []
      return series.map((s, i) => ({
        label: s.name,
        color: this.serieColors[i % this.serieColors.length],
      }))
    },
    circularLegendItems() {
      const d = this.dataset
      if (!d || d.error) return []
      const palette = themePalette()
      const labels = d.breakdown.labels || []
      const values = d.breakdown.values || []

      const items = labels.slice(0, 6).map((label, i) => ({
        label,
        valueStr: formatValue(values[i], d.unit),
        color: palette[i % palette.length],
      }))

      if (d.totals.current !== null && d.totals.current !== undefined && d.unit !== 'percent') {
        items.push({
          label: 'Total',
          valueStr: formatValue(d.totals.current, d.unit),
          color: themeColors().success,
        })
      }

      return items
    },
    isKpi() {
      return this.widget.type === 'kpi' || this.widget.type === 'kpi_spark'
    },
    cellStyle() {
      return {
        gridColumn: 'span ' + this.colSpan,
        gridRow: 'span ' + this.rowSpan,
      }
    },
    chartHeight() {
      // Altura útil real de la celda: filas × alto de fila + los gaps internos
      // del span, menos cabecera y padding del card — así la gráfica llena el
      // card sin dejar franja vacía abajo.
      const cell = this.rowSpan * GRID_ROW_HEIGHT + (this.rowSpan - 1) * GRID_GAP
      return Math.max(120, cell - 104)
    },
    kpiSparkHeight() {
      const cell = this.rowSpan * GRID_ROW_HEIGHT + (this.rowSpan - 1) * GRID_GAP
      if (this.rowSpan <= 1) return 42
      return Math.max(40, Math.min(72, cell - 96))
    },
    sizeLabel() {
      return this.widget.cols ? this.widget.cols + 'c' : (this.widget.size || 'm').toUpperCase()
    },
    typeIcon() {
      return typeById(this.widget.type).icon
    },
    typeOptions() {
      const options = typesForSource(this.source)
      return options.map(t => {
        const rec = recFor(this.source, this.dataset, t.id)
        return {
          id: t.id,
          label: t.label,
          icon: t.icon,
          rec,
          badge: rec === 'rec' ? 'Recomendado' : rec === 'bad' ? 'Poco legible' : null,
        }
      })
    },
  },
}
</script>
<style scoped>
.wg-cell {
  display: flex;
  flex-direction: column;
  min-height: 0;
  min-width: 0;
  position: relative;
}
.wg-cell.is-edit .wg-card,
.wg-cell.is-edit .wg-custom {
  outline: 1px dashed #a9b2c2;
  outline-offset: -1px;
}
.wg-cell.is-over .wg-card,
.wg-cell.is-over .wg-custom {
  outline: 2px dashed var(--primary);
}
/* Cadena estricta de alturas: el card nunca crece más que su celda del grid,
   el contenido se recorta — así los widgets jamás se montan entre sí. */
.wg-card {
  flex: 1 1 auto;
  margin-bottom: 0 !important;
  min-height: 0;
}
.wg-custom {
  flex: 1 1 auto;
  min-height: 0;
}
.wg-custom > .card,
.wg-custom.card {
  height: 100%;
  margin-bottom: 0 !important;
}
.wg-body {
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  height: 100%;
  /* Anula el margin: 10px global de .card-body (admin_styles), que
     recortaba el contenido y hacía solapar título y valor en KPIs. */
  margin: 0;
  min-height: 0;
  overflow: hidden;
}
.wg-head {
  align-items: flex-start;
  display: flex;
  flex-shrink: 0;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}
.wg-title {
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.25;
}
.wg-cell.is-kpi .wg-body {
  padding: 12px 16px 10px;
}
.wg-custom >>> .card-body {
  box-sizing: border-box;
  height: 100%;
  margin: 0;
  overflow: hidden;
}
.wg-empty {
  padding: 2rem 0;
}
.wg-head-legend {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: flex-end;
}
.wg-head-legend-item {
  align-items: center;
  color: #6b7280;
  display: flex;
  font-size: 0.76rem;
  font-weight: 700;
  gap: 0.35rem;
}
.wg-dot {
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
  height: 9px;
  width: 9px;
}
.wg-circular {
  align-items: center;
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  gap: 1rem;
  min-height: 0;
}
.wg-circular-chart {
  flex: 1 1 160px;
  min-width: 150px;
}
.wg-vlegend {
  flex: 1 1 140px;
  list-style: none;
  margin: 0;
  min-width: 0;
  padding: 0;
}
.wg-vlegend-item {
  align-items: center;
  display: flex;
  gap: 0.5rem;
  padding: 0.32rem 0;
}
.wg-vlegend-label {
  flex: 1 1 auto;
  font-size: 0.85rem;
  min-width: 0;
}
.wg-vlegend-value {
  flex-shrink: 0;
  font-size: 0.85rem;
  font-weight: 700;
}
.wg-edit-bar {
  background: #fff;
  border: 1px solid #d6dae0;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  display: flex;
  gap: 2px;
  padding: 2px;
  position: absolute;
  right: 8px;
  top: 8px;
  z-index: 5;
}
.wg-edit-btn {
  align-items: center;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: #57688b;
  cursor: pointer;
  display: flex;
  font-size: 0.72rem;
  font-weight: 700;
  height: 26px;
  justify-content: center;
  min-width: 26px;
  padding: 0 4px;
}
.wg-edit-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}
.wg-drag {
  cursor: grab;
}
.wg-remove:hover {
  background: rgba(240, 68, 56, 0.1);
  color: #f04438;
}
.wg-picker {
  background: #fff;
  border: 1px solid #d6dae0;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.14);
  max-height: 260px;
  overflow-y: auto;
  padding: 4px;
  position: absolute;
  right: 8px;
  top: 42px;
  width: 230px;
  z-index: 20;
}
.wg-picker-item {
  align-items: center;
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  font-size: 0.82rem;
  gap: 0.5rem;
  padding: 0.4rem 0.55rem;
  text-align: left;
  width: 100%;
}
.wg-picker-item:hover {
  background: rgba(0, 0, 0, 0.05);
}
.wg-picker-item.is-active {
  background: color-mix(in srgb, var(--primary) 12%, transparent);
  color: var(--primary);
  font-weight: 700;
}
.wg-picker-badge {
  border-radius: 4px;
  font-size: 0.62rem;
  font-weight: 700;
  margin-left: auto;
  padding: 1px 5px;
}
.wg-picker-badge.is-rec {
  background: #e4f4ec;
  color: #0e7a4a;
}
.wg-picker-badge.is-bad {
  background: #fbefdf;
  color: #b54708;
}
.wg-resize {
  align-items: center;
  background: #fff;
  border: 1px solid #d6dae0;
  border-radius: 6px;
  bottom: 6px;
  color: #9ca3af;
  cursor: nwse-resize;
  display: flex;
  font-size: 13px;
  height: 22px;
  justify-content: center;
  position: absolute;
  right: 6px;
  width: 22px;
  z-index: 5;
}
.wg-resize:hover {
  color: var(--primary);
}
</style>
