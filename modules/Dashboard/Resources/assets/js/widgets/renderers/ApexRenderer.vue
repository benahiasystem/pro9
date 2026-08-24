<template>
  <div class="wg-apex" ref="wrap">
    <apexchart
      :key="chartKey"
      :type="apexType"
      :height="chartHeight"
      :options="chartOptions"
      :series="chartSeries"
    ></apexchart>
  </div>
</template>

<script>
import { formatCompact, formatValue, themeColors, themePalette } from '../registry'

/**
 * Renderer genérico ApexCharts: line, area, bar, barh, pie, donut, radial.
 * Consume el WidgetDataset normalizado sin conocer la fuente.
 */
export default {
  name: 'ApexRenderer',
  props: {
    type: { type: String, required: true },
    dataset: { type: Object, required: true },
    height: { type: Number, default: 240 },
    // Ancho lógico del cell (columnas); solo fuerza el re-render al cambiar.
    width: { type: Number, default: 0 },
  },
  computed: {
    colors() {
      const theme = themeColors()
      return [theme.primary, theme.danger, theme.success, theme.warning, theme.info]
    },
    palette() {
      return themePalette()
    },
    unit() {
      return this.dataset.unit || 'money'
    },
    isCircular() {
      return ['pie', 'donut', 'radial'].includes(this.type)
    },
    apexType() {
      if (this.type === 'barh') return 'bar'
      if (this.type === 'radial') return 'radialBar'
      return this.type
    },
    chartKey() {
      // Recrear el chart cuando cambia tipo, datos o dimensiones del cell:
      // Apex no se adapta solo a cambios de tamaño del contenedor y el svg
      // viejo queda desbordando el card.
      return [
        this.type,
        this.height,
        this.width,
        (this.dataset.labels || []).length,
        (this.dataset.breakdown.labels || []).length,
      ].join('-')
    },
    chartHeight() {
      return this.height
    },
    breakdownTotal() {
      return (this.dataset.breakdown.values || []).reduce((a, b) => a + (Number(b) || 0), 0)
    },
    chartSeries() {
      const d = this.dataset

      if (this.type === 'pie' || this.type === 'donut') {
        return (d.breakdown.values || []).map(v => Number(v) || 0)
      }

      if (this.type === 'radial') {
        const first = Number((d.breakdown.values || [])[0]) || 0
        if (this.unit === 'percent') return [Math.min(100, Math.round(first))]
        const total = this.breakdownTotal
        return [total > 0 ? Math.min(100, Math.round((first / total) * 100)) : 0]
      }

      if (this.type === 'barh') {
        return [{ name: '', data: (d.breakdown.values || []).map(v => Number(v) || 0) }]
      }

      return (d.series || []).map(s => ({ name: s.name, data: s.data }))
    },
    chartOptions() {
      const d = this.dataset
      const theme = themeColors()
      const axisStyle = { colors: theme.muted, fontSize: '11px' }
      // Las leyendas las pinta WidgetCard con un patrón único (cabecera para
      // series, lista con valores para circulares); Apex no muestra ninguna.
      const legend = { show: false }
      const common = {
        chart: { toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false }, animations: { enabled: false }, parentHeightOffset: 0, background: 'transparent' },
        dataLabels: { enabled: false },
        grid: { borderColor: theme.grid, strokeDashArray: 4 },
        // Tema dark: texto blanco legible (evita tooltip negro/ilegible en pie/donut).
        tooltip: {
          theme: 'dark',
          style: { fontSize: '12px' },
          y: { formatter: v => formatValue(v, this.unit) },
        },
      }

      if (this.type === 'line' || this.type === 'area') {
        return Object.assign(common, {
          colors: this.colors,
          stroke: { curve: 'smooth', width: 2.4 },
          fill: this.type === 'area'
            ? { type: 'gradient', gradient: { opacityFrom: 0.2, opacityTo: 0.02, stops: [0, 100] } }
            : { type: 'solid' },
          legend,
          markers: { size: 0, hover: { size: 5 } },
          xaxis: { categories: d.labels, tickAmount: 8, labels: { style: axisStyle }, axisBorder: { show: false }, axisTicks: { show: false } },
          yaxis: { labels: { formatter: v => formatCompact(v, this.unit), style: axisStyle } },
        })
      }

      if (this.type === 'bar') {
        const colors = this.colors.slice()
        if ((d.series || []).length === 2) colors[1] = '#d9dee7'

        return Object.assign(common, {
          colors,
          plotOptions: { bar: { borderRadius: 4, columnWidth: '52%' } },
          legend,
          xaxis: { categories: d.labels, tickAmount: 10, labels: { style: axisStyle }, axisBorder: { show: false }, axisTicks: { show: false } },
          yaxis: { labels: { formatter: v => formatCompact(v, this.unit), style: axisStyle } },
        })
      }

      if (this.type === 'barh') {
        return Object.assign(common, {
          colors: this.palette,
          plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%', distributed: true } },
          legend: { show: false },
          xaxis: { categories: d.breakdown.labels, labels: { formatter: v => formatCompact(v, this.unit), style: axisStyle } },
          yaxis: { labels: { style: axisStyle, maxWidth: 140 } },
        })
      }

      if (this.type === 'pie' || this.type === 'donut') {
        const options = Object.assign(common, {
          labels: d.breakdown.labels,
          colors: this.palette,
          stroke: { width: 2, colors: ['#fff'] },
          legend,
        })

        if (this.type === 'donut') {
          options.plotOptions = {
            pie: {
              donut: {
                size: '70%',
                labels: {
                  show: true,
                  name: { fontSize: '11px', color: theme.muted },
                  value: { fontSize: '17px', fontWeight: 700, formatter: v => formatCompact(+v, this.unit) },
                  total: {
                    show: true,
                    label: 'Total',
                    fontSize: '11px',
                    color: theme.muted,
                    formatter: () => formatCompact(this.dataset.totals.current !== null ? this.dataset.totals.current : this.breakdownTotal, this.unit),
                  },
                },
              },
            },
          }
        }

        return options
      }

      // radial
      return Object.assign(common, {
        labels: [(d.breakdown.labels || [])[0] || ''],
        colors: [theme.primary],
        plotOptions: {
          radialBar: {
            hollow: { size: '58%' },
            track: { background: theme.grid },
            dataLabels: {
              name: { fontSize: '12px', color: theme.muted },
              value: { fontSize: '22px', fontWeight: 600, formatter: v => Math.round(v) + '%' },
            },
          },
        },
      })
    },
  },
}
</script>

<style scoped>
.wg-apex {
  flex: 1 1 auto;
  min-height: 0;
}
/* Contraste forzado: montos visibles al hover (sobre todo segmentos claros/naranja). */
.wg-apex ::v-deep .apexcharts-tooltip {
  background: rgba(33, 37, 41, 0.96) !important;
  border: 0 !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25) !important;
  color: #fff !important;
}
.wg-apex ::v-deep .apexcharts-tooltip *,
.wg-apex ::v-deep .apexcharts-tooltip-title {
  color: #fff !important;
  background: transparent !important;
  border: 0 !important;
}
</style>
