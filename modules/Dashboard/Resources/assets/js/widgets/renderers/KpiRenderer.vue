<template>
  <div class="wg-kpi">
    <div class="wg-kpi-main">
      <div class="wg-kpi-value">{{ valueStr }}</div>
      <div v-if="hasDelta" class="wg-kpi-delta" :style="{ color: deltaColor }">
        <i :class="['ti', deltaUp ? 'ti-trending-up' : 'ti-trending-down']"></i>
        {{ deltaStr }} <span class="wg-kpi-delta-label">vs periodo anterior</span>
      </div>
    </div>
    <div v-if="spark && sparkSeries.length" class="wg-kpi-spark">
      <apexchart type="area" :height="sparkHeight" :options="sparkOptions" :series="sparkSeries"></apexchart>
    </div>
  </div>
</template>

<script>
import { formatValue, themeColors } from '../registry'

/** KPI (valor + delta) con sparkline opcional (kpi_spark). */
export default {
  name: 'KpiRenderer',
  props: {
    dataset: { type: Object, required: true },
    spark: { type: Boolean, default: false },
    sparkHeight: { type: Number, default: 64 },
  },
  computed: {
    unit() {
      return this.dataset.unit || 'money'
    },
    valueStr() {
      return formatValue(this.dataset.totals.current, this.unit)
    },
    hasDelta() {
      return this.dataset.totals.delta !== null && this.dataset.totals.delta !== undefined
    },
    deltaUp() {
      return (Number(this.dataset.totals.delta) || 0) >= 0
    },
    deltaStr() {
      return Math.abs(Number(this.dataset.totals.delta) || 0).toFixed(1) + '%'
    },
    deltaColor() {
      const theme = themeColors()
      return this.deltaUp ? theme.success : theme.danger
    },
    sparkSeries() {
      const serie = (this.dataset.series || [])[0]
      if (!serie || !serie.data || !serie.data.length) return []
      return [{ name: '', data: serie.data }]
    },
    sparkColor() {
      // La línea sigue la inclinación real de la curva: sube en verde, baja en rojo.
      const theme = themeColors()
      const data = (this.sparkSeries[0] && this.sparkSeries[0].data) || []
      if (data.length >= 2) {
        const rising = (Number(data[data.length - 1]) || 0) >= (Number(data[0]) || 0)
        return rising ? theme.success : theme.danger
      }
      return this.deltaUp ? theme.success : theme.danger
    },
    sparkOptions() {
      return {
        chart: { sparkline: { enabled: true }, fontFamily: 'inherit', animations: { enabled: false } },
        colors: [this.sparkColor],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.22, opacityTo: 0.02 } },
        tooltip: { enabled: false },
      }
    },
  },
}
</script>

<style scoped>
/* Formato de lado (mockup): valor a la izquierda, sparkline a la derecha */
.wg-kpi {
  align-items: center;
  display: flex;
  flex: 1 1 auto;
  gap: 0.75rem;
  min-height: 0;
  overflow: hidden;
}
.wg-kpi-main {
  display: flex;
  flex: 0 1 auto;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
  overflow: hidden;
}
.wg-kpi-value {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.05;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wg-kpi-delta {
  align-items: center;
  display: flex;
  font-size: 0.8rem;
  font-weight: 600;
  gap: 0.3rem;
  line-height: 1.1;
  margin-top: 0.15rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wg-kpi-delta-label {
  color: #9ca3af;
  font-weight: 500;
}
.wg-kpi-spark {
  align-self: stretch;
  flex: 1 1 auto;
  min-width: 60px;
  overflow: hidden;
}
</style>
