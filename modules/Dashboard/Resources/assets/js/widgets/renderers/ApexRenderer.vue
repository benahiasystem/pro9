<template>
  <div class="wg-apex" :class="{ 'is-series-tooltip': isCircular }" ref="wrap">
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
import { readOriginal, readVector, sumOriginal, ratioOriginal, relativeWidth, chartCoordinate, formatAxisTick } from '../../../../../../../resources/js/helpers/dashboard-originals'
// ######## INICIO CALCULOS EXACTOS SAB ########

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
    previousLabels() {
      const meta = this.dataset.meta || {}
      return meta.previous_labels || []
    },
    sharedTooltip() {
      return !['bar', 'barh'].includes(this.type)
    },
    tooltipXFormatter() {
      const previous = this.previousLabels
      if (!previous.length || this.sharedTooltip) return undefined
      return (value, opts) => {
        if (!opts || opts.seriesIndex < 1) return value
        return previous[opts.dataPointIndex] || value
      }
    },
    seriesTitleFormatter() {
      const previous = this.previousLabels
      const shared = this.sharedTooltip
      return (seriesName, opts) => {
        const name = seriesName || ''
        if (!name) return ''
        if (!shared || !previous.length || !opts || opts.seriesIndex < 1) return name + ': '
        const label = previous[opts.dataPointIndex]
        return label ? name + ' (' + label + '): ' : name + ': '
      }
    },
    breakdownOriginals() {
      return readVector(this.dataset.breakdown, 'values_fractions')
    },
    breakdownTotal() {
      return sumOriginal(this.breakdownOriginals)
    },
    circularTotal() {
      return readOriginal(this.dataset.totals, 'current') || this.breakdownTotal
    },
    radialPercent() {
      const first = this.breakdownOriginals[0]
      return this.unit === 'percent' ? first : ratioOriginal(first, this.breakdownTotal)
    },
    chartSeries() {
      const d = this.dataset
      if (this.type === 'pie' || this.type === 'donut') {
        return this.breakdownOriginals.some(value => value === null) ? [] : this.breakdownOriginals.map(chartCoordinate)
      }
      if (this.type === 'radial') return [relativeWidth(this.radialPercent, '100')]
      if (this.type === 'barh') return [{name: '', data: this.breakdownOriginals.map(chartCoordinate)}]
      return (d.series || []).map(serie => ({name: serie.name, data: readVector(serie, 'data_fractions').map(chartCoordinate)}))
    },
    valueTooltip() {
      return (_coordinate, options) => {
        if (this.type === 'radial') return formatValue(this.radialPercent, 'percent')
        if (!options) return '—'
        const value = (this.type === 'pie' || this.type === 'donut') ? this.breakdownOriginals[options.seriesIndex]
          : this.type === 'barh' ? this.breakdownOriginals[options.dataPointIndex]
          : readVector((this.dataset.series || [])[options.seriesIndex], 'data_fractions')[options.dataPointIndex]
        return formatValue(value, this.unit)
      }
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
        tooltip: {
          shared: this.sharedTooltip,
          intersect: !this.sharedTooltip,
          x: { formatter: this.tooltipXFormatter },
          y: {
            formatter: this.valueTooltip,
            title: { formatter: this.seriesTitleFormatter },
          },
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
          yaxis: { labels: { formatter: v => formatAxisTick(v, this.unit), style: axisStyle } },
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
          yaxis: { labels: { formatter: v => formatAxisTick(v, this.unit), style: axisStyle } },
        })
      }

      if (this.type === 'barh') {
        return Object.assign(common, {
          colors: this.palette,
          plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%', distributed: true } },
          legend: { show: false },
          xaxis: { categories: d.breakdown.labels, labels: { formatter: v => formatAxisTick(v, this.unit), style: axisStyle } },
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
                  value: { fontSize: '17px', fontWeight: 700, formatter: () => formatCompact(this.circularTotal, this.unit) },
                  total: {
                    show: true,
                    showAlways: true,
                    label: 'Total',
                    fontSize: '11px',
                    color: theme.muted,
                    formatter: () => formatCompact(this.circularTotal, this.unit),
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
              value: { fontSize: '22px', fontWeight: 600, formatter: () => formatValue(this.radialPercent, 'percent') },
            },
          },
        },
      })
    },
  },
}
// ######## FIN CALCULOS EXACTOS SAB ########
</script>

<style scoped>
.wg-apex {
  flex: 1 1 auto;
  min-height: 0;
}
.is-series-tooltip >>> .apexcharts-tooltip,
.is-series-tooltip >>> .apexcharts-tooltip *,
.is-series-tooltip >>> .apexcharts-tooltip-title {
  color: #fff !important;
}
</style>
