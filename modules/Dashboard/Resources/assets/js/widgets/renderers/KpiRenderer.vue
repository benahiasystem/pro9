<template>
  <div class="wg-kpi">
    <div class="wg-kpi-row">
      <div class="wg-kpi-value">{{ valueStr }}</div>
      <div v-if="spark" class="wg-kpi-spark" :style="{ height: sparkHeight + 'px' }">
        <apexchart type="area" :height="sparkHeight" :options="sparkOptions" :series="sparkSeries"></apexchart>
      </div>
    </div>
    <div v-if="hasDelta" class="wg-kpi-delta" :style="{ color: deltaColor }">
      <i :class="['ti', deltaUp ? 'ti-trending-up' : 'ti-trending-down']"></i>
      {{ deltaStr }} <span class="wg-kpi-delta-label">{{ changeLabel }}</span>
    </div>
  </div>
</template>

<script>
import { changeLabelForPeriod, formatKpi, themeColors } from '../registry'

/** KPI (valor + delta) con sparkline opcional (kpi_spark). */
export default {
  name: 'KpiRenderer',
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
    deltaColor() {
      const theme = themeColors()
      return this.deltaUp ? theme.success : theme.danger
    },
    changeLabel() {
      return changeLabelForPeriod(this.period)
    },
    sparkSeries() {
      const serie = (this.dataset.series || [])[0]
      const data = serie && serie.data && serie.data.length ? serie.data : [0, 0]
      return [{ name: '', data: data }]
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
        chart: {
          sparkline: { enabled: true },
          fontFamily: 'inherit',
          animations: { enabled: false },
          parentHeightOffset: 0,
          toolbar: { show: false },
        },
        colors: [this.sparkColor],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] } },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 0 } },
        tooltip: { enabled: false },
        grid: { show: false, padding: { left: 0, right: 0, top: 2, bottom: 2 } },
      }
    },
  },
}
</script>

<style scoped>
/* Formato clásico (RowTop): valor a la izquierda, sparkline fija a la derecha */
.wg-kpi {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  justify-content: center;
  min-height: 0;
  overflow: hidden;
}
.wg-kpi-row {
  align-items: center;
  display: flex;
  gap: 8px;
  min-height: 0;
  min-width: 0;
}
.wg-kpi-value {
  flex: 1 1 auto;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.15;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wg-kpi-delta {
  align-items: center;
  display: flex;
  flex-shrink: 0;
  font-size: 0.74rem;
  font-weight: 600;
  gap: 0.3rem;
  line-height: 1.2;
  margin-top: 4px;
  white-space: nowrap;
}
.wg-kpi-delta-label {
  color: #9ca3af;
  font-weight: 400;
}
.wg-kpi-spark {
  flex: 0 0 96px;
  max-width: 96px;
  min-width: 96px;
  overflow: hidden;
  width: 96px;
}
</style>
