<template>
  <div class="wg-table-wrap">
    <table class="wg-table" v-if="rows.length">
      <tbody>
        <tr v-for="(row, index) in rows" :key="index">
          <td class="wg-table-label text-truncate" :title="row.label">{{ row.label }}</td>
          <td class="wg-table-value">{{ row.valueStr }}</td>
        </tr>
      </tbody>
      <tfoot v-if="totalStr">
        <tr>
          <td class="wg-table-label">Total</td>
          <td class="wg-table-value">{{ totalStr }}</td>
        </tr>
      </tfoot>
    </table>
    <div v-else class="wg-empty text-muted text-center">Sin datos en el periodo.</div>
  </div>
</template>

<script>
import { formatValue } from '../registry'

/** Tabla simple etiqueta/valor sobre el breakdown del dataset. */
export default {
  name: 'TableRenderer',
  props: {
    dataset: { type: Object, required: true },
    limit: { type: Number, default: 10 },
  },
  computed: {
    rows() {
      const labels = this.dataset.breakdown.labels || []
      const values = this.dataset.breakdown.values || []

      return labels.slice(0, this.limit).map((label, i) => ({
        label,
        valueStr: formatValue(values[i], this.dataset.unit),
      }))
    },
    totalStr() {
      const total = this.dataset.totals.current
      return total === null || total === undefined ? null : formatValue(total, this.dataset.unit)
    },
  },
}
</script>

<style scoped>
.wg-table-wrap {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
}
.wg-table {
  border-collapse: collapse;
  width: 100%;
}
.wg-table td {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  font-size: 0.85rem;
  padding: 0.4rem 0.2rem;
}
.wg-table-label {
  max-width: 0;
  width: 60%;
}
.wg-table-value {
  font-weight: 700;
  text-align: right;
  white-space: nowrap;
}
.wg-table tfoot td {
  border-bottom: none;
  border-top: 2px solid rgba(0, 0, 0, 0.12);
}
.wg-empty {
  padding: 2rem 0;
}
</style>
