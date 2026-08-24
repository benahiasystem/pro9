<template>
  <div class="wg-kpi">
    <div class="kpi-main">
      <div class="kpi-values">
        <h3 class="font-weight-bold m-0 text-nowrap">{{ valueStr }}</h3>
      </div>
      <kpi-sparkline
        v-if="spark"
        class="kpi-spark"
        :data="sparkData"
        :labels="sparkLabels"
        :color="sparkColor"
        :height="sparkHeight"
        :formatter="sparkFormatter"
      ></kpi-sparkline>
    </div>
    <small v-if="hasDelta" class="kpi-change" :class="deltaUp ? 'is-up' : 'is-down'">
      {{ deltaUp ? '▲' : '▼' }} {{ deltaStr }}
      <span class="kpi-change-label text-muted">{{ changeLabel }}</span>
    </small>
  </div>
</template>

<script>
import KpiSparkline from '../../views/KpiSparkline.vue'
import { changeLabelForPeriod, formatKpi, formatValue, themeColors } from '../registry'

/** KPI (valor + delta) con sparkline opcional (kpi_spark). */
export default {
  name: 'KpiRenderer',
  components: { KpiSparkline },
  props: {
    dataset: { type: Object, required: true },
    spark: { type: Boolean, default: false },
    sparkHeight: { type: Number, default: 46 },
    period: { type: String, default: '' },
  },
  computed: {
    unit() {
      return this.dataset.unit || 'money'
    },
    valueStr() {
      return formatKpi(this.dataset.totals.current, this.unit)
    },
    hasDelta() {
      const totals = this.dataset.totals || {}
      if (totals.delta === null || totals.delta === undefined) return false
      // Sin valor previo real no hay comparación (espejo de RowTop.changes).
      return !!Number(totals.previous)
    },
    deltaUp() {
      return (Number(this.dataset.totals.delta) || 0) >= 0
    },
    deltaStr() {
      return Math.abs(Number(this.dataset.totals.delta) || 0).toFixed(1) + '%'
    },
    changeLabel() {
      return changeLabelForPeriod(this.period)
    },
    sparkData() {
      const serie = (this.dataset.series || [])[0]
      return serie && serie.data && serie.data.length ? serie.data : [0, 0]
    },
    sparkLabels() {
      return this.dataset.labels || []
    },
    sparkColor() {
      // La línea sigue la inclinación real de la curva: sube en verde, baja en rojo.
      const theme = themeColors()
      const data = this.sparkData
      if (data.length >= 2) {
        const rising = (Number(data[data.length - 1]) || 0) >= (Number(data[0]) || 0)
        return rising ? theme.success : theme.danger
      }
      return this.deltaUp ? theme.success : theme.danger
    },
    sparkFormatter() {
      const unit = this.unit
      return value => formatValue(value, unit)
    },
  },
}
</script>

<style scoped>
.wg-kpi {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  justify-content: center;
  min-height: 0;
  min-width: 0;
}
</style>
