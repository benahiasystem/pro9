<template>
  <div class="widget-dashboard">

    <div v-if="state.editMode" class="wg-hint-bar">
      <small class="wg-hint">
        <i class="ti ti-info-circle"></i>
        Arrastra para reordenar · cambia gráfica o tamaño desde cada widget · esquina inferior derecha para redimensionar
      </small>
      <button type="button" class="wg-done-btn" @click="toggleEdit">
        <i class="ti ti-check"></i> Listo
      </button>
    </div>

    <div v-if="!state.ready" class="wg-loading">
      <loader-graph :rows="4" :columns="3" :radius="60"></loader-graph>
    </div>

    <div v-else-if="!state.layout.length" class="wg-empty-state">
      <i class="ti ti-layout-dashboard"></i>
      <div class="wg-empty-title">Aún no tienes widgets</div>
      <div class="text-muted">Añade métricas de cualquier módulo para armar tu dashboard.</div>
      <el-button type="primary" size="small" class="mt-3" @click="store.openModal()">
        <i class="ti ti-plus"></i> Añadir widget
      </el-button>
    </div>

    <div v-else class="wg-grid" ref="grid" @click="pickerFor = null">
      <widget-card
        v-for="(widget, index) in state.layout"
        :key="widget.id"
        :widget="widget"
        :source="store.source(widget.source)"
        :dataset="store.dataset(widget)"
        :loading="store.isLoading(widget)"
        :edit-mode="state.editMode"
        :picker-open="pickerFor === widget.id"
        :drag-over="overIdx === index && dragIdx !== null && dragIdx !== index"
        :filters="state.filters"
        :col-span="colSpan(widget)"
        :row-span="rowSpan(widget)"
        :draggable="state.editMode && !resizing"
        @dragstart.native="onDragStart($event, index)"
        @dragover.native.prevent="onDragOver(index)"
        @drop.native.prevent="onDrop(index)"
        @dragend.native="onDragEnd"
        @cycle-size="store.cycleSize(widget.id)"
        @remove="store.removeWidget(widget.id)"
        @open-picker="pickerFor = pickerFor === widget.id ? null : widget.id"
        @set-type="setType(widget.id, $event)"
        @resize-start="onResizeStart($event, widget)"
      ></widget-card>
    </div>

    <add-widget-modal
      :visible="state.modalOpen"
      @close="store.closeModal()"
      @added="onWidgetAdded"
    ></add-widget-modal>
  </div>
</template>

<script>
import '@tabler/icons-webfont/dist/tabler-icons.min.css'
import LoaderGraph from '../components/loaders/l-graph.vue'
import WidgetCard from './WidgetCard.vue'
import AddWidgetModal from './AddWidgetModal.vue'
import { widgetStore } from './store'
import { GRID_COLUMNS, GRID_GAP, GRID_ROW_HEIGHT, sizeCols, coerceSize, typeById } from './registry'

export default {
  name: 'WidgetGrid',
  components: { LoaderGraph, WidgetCard, AddWidgetModal },
  props: {
    filters: { type: Object, required: true },
  },
  data() {
    return {
      store: widgetStore,
      state: widgetStore.state,
      pickerFor: null,
      dragIdx: null,
      overIdx: null,
      resizing: null,
    }
  },
  async created() {
    await this.store.init(this.filters)
  },
  watch: {
    filters: {
      deep: true,
      handler(value) {
        if (this.state.ready) this.store.setFilters(value)
      },
    },
  },
  beforeDestroy() {
    this.detachResizeListeners()
  },
  methods: {
    colSpan(widget) {
      const minCols = sizeCols(typeById(widget.type).sizes[0])
      const base = widget.cols || sizeCols(coerceSize(widget.type, widget.size))
      return Math.min(GRID_COLUMNS, Math.max(minCols, base))
    },
    rowSpan(widget) {
      if (widget.rows) return widget.rows
      return widget.type === 'kpi' || widget.type === 'kpi_spark' ? 1 : 4
    },
    toggleEdit() {
      this.pickerFor = null
      this.store.toggleEdit()
    },
    setType(id, typeId) {
      this.store.setType(id, typeId)
      this.pickerFor = null
    },
    async resetLayout() {
      this.pickerFor = null
      await this.store.resetLayout()
    },
    onWidgetAdded() {
      this.store.closeModal()
      if (!this.state.editMode) this.store.toggleEdit()
    },

    // --- Drag & drop (reordenar) ---
    onDragStart(event, index) {
      if (!this.state.editMode || this.resizing) {
        event.preventDefault()
        return
      }
      this.dragIdx = index
      try {
        event.dataTransfer.setData('text/plain', String(index))
        event.dataTransfer.effectAllowed = 'move'
      } catch (e) { /* IE/Edge legacy */ }
    },
    onDragOver(index) {
      if (this.dragIdx === null) return
      this.overIdx = index
    },
    onDrop(index) {
      this.store.moveWidget(this.dragIdx, index)
      this.dragIdx = null
      this.overIdx = null
    },
    onDragEnd() {
      this.dragIdx = null
      this.overIdx = null
    },

    // --- Resize por esquina ---
    onResizeStart(event, widget) {
      const grid = this.$refs.grid
      if (!grid) return

      const cellWidth = (grid.getBoundingClientRect().width - GRID_GAP * (GRID_COLUMNS - 1)) / GRID_COLUMNS
      const minCols = sizeCols(typeById(widget.type).sizes[0])

      this.resizing = {
        id: widget.id,
        startX: event.clientX,
        startY: event.clientY,
        cols: this.colSpan(widget),
        rows: this.rowSpan(widget),
        cellWidth,
        minCols,
      }

      this._onResizeMove = e => this.onResizeMove(e)
      this._onResizeEnd = () => this.onResizeEnd()
      window.addEventListener('mousemove', this._onResizeMove)
      window.addEventListener('mouseup', this._onResizeEnd)
    },
    onResizeMove(event) {
      const r = this.resizing
      if (!r) return

      const cols = Math.min(GRID_COLUMNS, Math.max(r.minCols, r.cols + Math.round((event.clientX - r.startX) / (r.cellWidth + GRID_GAP))))
      const rows = Math.min(8, Math.max(1, r.rows + Math.round((event.clientY - r.startY) / (GRID_ROW_HEIGHT + GRID_GAP))))

      const widget = this.state.layout.find(w => w.id === r.id)
      if (widget && (widget.cols !== cols || widget.rows !== rows)) {
        this.store.resizeWidget(r.id, cols, rows)
      }
    },
    onResizeEnd() {
      this.detachResizeListeners()
      this.resizing = null
      this.store.persist()
    },
    detachResizeListeners() {
      if (this._onResizeMove) window.removeEventListener('mousemove', this._onResizeMove)
      if (this._onResizeEnd) window.removeEventListener('mouseup', this._onResizeEnd)
      this._onResizeMove = null
      this._onResizeEnd = null
    },
  },
}
</script>

<style scoped>
.widget-dashboard {
  padding: 0;
}
.wg-hint-bar {
  align-items: center;
  background: color-mix(in srgb, var(--primary) 6%, transparent);
  border-radius: 10px;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 0.75rem;
  padding: 0.5rem 0.9rem;
}
.wg-hint {
  align-items: center;
  color: var(--primary);
  display: flex;
  gap: 0.4rem;
}
.wg-done-btn {
  align-items: center;
  background: var(--primary);
  border: none;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  display: flex;
  flex-shrink: 0;
  font-size: 0.82rem;
  font-weight: 700;
  gap: 0.3rem;
  padding: 0.4rem 0.9rem;
}
.wg-grid {
  display: grid;
  gap: 16px;
  grid-auto-flow: dense;
  grid-auto-rows: 84px;
  grid-template-columns: repeat(12, minmax(0, 1fr));
}
.wg-loading {
  padding: 2rem;
}
.wg-empty-state {
  align-items: center;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 3rem 1rem;
  text-align: center;
}
.wg-empty-state .ti-layout-dashboard {
  color: #9ca3af;
  font-size: 2.2rem;
}
.wg-empty-title {
  font-size: 1.05rem;
  font-weight: 700;
}
@media (max-width: 991.98px) {
  .wg-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .wg-grid > * {
    grid-column: span 2 !important;
  }
}
@media (max-width: 575.98px) {
  .wg-grid {
    grid-template-columns: 1fr;
  }
  .wg-grid > * {
    grid-column: span 1 !important;
  }
}
</style>
