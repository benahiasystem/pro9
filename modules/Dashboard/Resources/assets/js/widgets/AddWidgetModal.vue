<template>
  <el-dialog
    :visible="visible"
    :append-to-body="true"
    custom-class="wg-modal"
    width="920px"
    top="4vh"
    @close="$emit('close')"
  >
    <template slot="title">
      <div class="el-dialog__title">
        Nuevo widget
      </div>
    </template>

    <div class="wg-modal-body" v-if="visible">

      <!-- 1. Módulo -->
      <div class="wg-modal-modules">
        <button
          v-for="mod in modules"
          :key="mod.key"
          type="button"
          class="wg-mod-item"
          :class="{ 'is-active': selection.module === mod.key }"
          @click="pickModule(mod.key)"
        >
          <i v-if="mod.icon" :class="['ti', mod.icon]"></i> {{ mod.label }}
        </button>
      </div>

      <div class="wg-modal-main">
        <!-- 2. Métrica -->
        <div class="wg-modal-metrics">
          <small class="wg-modal-step">Métrica</small>
          <button
            v-for="source in moduleSources"
            :key="source.key"
            type="button"
            class="wg-metric-item"
            :class="{ 'is-active': selection.source === source.key }"
            @click="pickSource(source.key)"
          >
            <span class="wg-metric-label">
              {{ source.label }}
              <el-tooltip v-if="isInDashboard(source.key)" content="Ya está en tu dashboard" placement="top">
                <span class="wg-metric-inuse"><i class="ti ti-check"></i></span>
              </el-tooltip>
            </span>
            <small v-if="source.description" class="text-muted">{{ source.description }}</small>
          </button>
        </div>

        <!-- 3. Tipo + 4. Tamaño + preview -->
        <div class="wg-modal-right">
          <small class="wg-modal-step">Tipo de gráfica</small>
          <div class="wg-type-gallery">
            <button
              v-for="option in typeOptions"
              :key="option.id"
              type="button"
              class="wg-type-item"
              :class="{ 'is-active': selection.type === option.id }"
              @click="pickType(option.id)"
            >
              <i :class="['ti', option.icon]"></i>
              <span>{{ option.label }}</span>
              <small v-if="option.badge" class="wg-type-badge" :class="'is-' + option.rec">{{ option.badge }}</small>
            </button>
          </div>

          <div class="wg-size-row">
            <small class="wg-modal-step">Tamaño</small>
            <div class="wg-size-options">
              <button
                v-for="size in sizeOptions"
                :key="size.id"
                type="button"
                class="wg-size-item"
                :class="{ 'is-active': selection.size === size.id, 'is-disabled': !size.enabled }"
                :title="size.enabled ? '' : 'No disponible para este tipo de gráfica'"
                @click="size.enabled && (selection.size = size.id)"
              >{{ size.label }}</button>
            </div>
          </div>

          <small class="wg-modal-step">Vista previa</small>
          <div class="wg-preview">
            <div v-if="previewLoading" class="wg-preview-loading">
              <loader-graph :rows="3" :columns="1" :radius="40"></loader-graph>
            </div>
            <div v-else-if="note" class="wg-note" :class="'is-' + noteRec">
              <i :class="['ti', noteRec === 'bad' ? 'ti-alert-triangle' : 'ti-thumb-up']"></i>
              {{ note }}
            </div>
            <widget-card
              v-if="previewDataset"
              :widget="previewWidget"
              :source="selectedSource"
              :dataset="previewDataset"
              :filters="state.filters"
              :col-span="12"
              :row-span="previewWidget.type === 'kpi' || previewWidget.type === 'kpi_spark' ? 2 : 3"
            ></widget-card>
          </div>
        </div>
      </div>
    </div>

    <template slot="footer">
      <div class="wg-modal-footer">
        <small class="text-muted text-truncate">{{ summary }}</small>
        <el-button type="primary" size="small" @click="add">Añadir widget</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script>
import LoaderGraph from '../components/loaders/l-graph.vue'
import WidgetCard from './WidgetCard.vue'
import { widgetStore } from './store'
import { SIZES, coerceSize, recFor, typeById, typesForSource } from './registry'

export default {
  name: 'AddWidgetModal',
  components: { LoaderGraph, WidgetCard },
  props: {
    visible: { type: Boolean, default: false },
  },
  data() {
    return {
      store: widgetStore,
      state: widgetStore.state,
      selection: { module: null, source: null, type: 'line', size: 'm' },
      previewDataset: null,
      previewLoading: false,
    }
  },
  computed: {
    modules() {
      return this.state.catalog.modules
    },
    moduleSources() {
      return this.state.catalog.sources.filter(s => s.module === this.selection.module)
    },
    selectedSource() {
      return this.store.source(this.selection.source)
    },
    typeOptions() {
      return typesForSource(this.selectedSource).map(t => {
        const rec = recFor(this.selectedSource, this.previewDataset, t.id)
        return {
          id: t.id,
          label: t.label,
          icon: t.icon,
          rec,
          badge: rec === 'rec' ? 'Recomendado' : rec === 'bad' ? 'Poco legible' : null,
        }
      })
    },
    sizeOptions() {
      const allowed = typeById(this.selection.type).sizes
      return SIZES.map(s => ({
        id: s.id,
        label: s.label,
        enabled: allowed.includes(s.id),
      }))
    },
    previewWidget() {
      return {
        id: 'preview',
        source: this.selection.source,
        type: this.selection.type,
        size: this.selection.size,
        options: {},
      }
    },
    summary() {
      const mod = this.modules.find(m => m.key === this.selection.module)
      const type = typeById(this.selection.type)
      const size = SIZES.find(s => s.id === this.selection.size)
      return [
        mod && mod.label,
        this.selectedSource && this.selectedSource.label,
        type && type.label,
        size && size.label,
      ].filter(Boolean).join(' · ')
    },
    noteRec() {
      return recFor(this.selectedSource, this.previewDataset, this.selection.type)
    },
    note() {
      if (!this.previewDataset || this.previewDataset.error) return null
      const catN = (this.previewDataset.breakdown.labels || []).length
      if (this.noteRec === 'bad') {
        if (['line', 'area', 'bar'].includes(this.selection.type)) {
          return `Esta métrica es categórica (${catN} datos): una serie temporal la hace ilegible. Prueba torta, barras horizontales, ranking o tabla.`
        }
        return `Con ${catN} categorías esta gráfica se vuelve ilegible: prueba con otro gráfico recomendado.`
      }
      if (this.noteRec === 'rec') {
        const focus = this.selectedSource && this.selectedSource.focus
        return `Buena elección: muestra bien ${focus === 'serie' ? 'la evolución en el tiempo' : 'los ' + catN + ' datos'} de esta métrica.`
      }
      return null
    },
  },
  watch: {
    visible(open) {
      if (open) this.initSelection()
    },
  },
  methods: {
    isInDashboard(sourceKey) {
      return this.state.layout.some(w => w.source === sourceKey)
    },
    initSelection() {
      if (!this.selection.module && this.modules.length) {
        this.pickModule(this.modules[0].key)
      } else if (this.selection.source) {
        this.loadPreview()
      }
    },
    pickModule(key) {
      this.selection.module = key
      const first = this.state.catalog.sources.find(s => s.module === key)
      if (first) this.pickSource(first.key)
    },
    pickSource(key) {
      this.selection.source = key
      const source = this.store.source(key)
      if (source) {
        this.selection.type = source.default_type || 'line'
        this.selection.size = coerceSize(this.selection.type, this.selection.size)
      }
      this.loadPreview()
    },
    pickType(typeId) {
      this.selection.type = typeId
      this.selection.size = coerceSize(typeId, this.selection.size)
    },
    async loadPreview() {
      if (!this.selection.source) return
      this.previewLoading = true
      this.previewDataset = await this.store.preview(this.selection.source, {})
      this.previewLoading = false
    },
    async add() {
      if (!this.selection.source) return
      await this.store.addWidget({
        source: this.selection.source,
        type: this.selection.type,
        size: this.selection.size,
      })
      this.$emit('added')
    },
  },
}
</script>

<style>
.wg-modal .el-dialog__body {
  padding: 0 20px 10px;
}
</style>

<style scoped>
.wg-modal-title {
  align-items: center;
  display: flex;
  font-size: 0.78rem;
  font-weight: 700;
  gap: 0.5rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.wg-modal-dot {
  background: var(--danger);
  border-radius: 50%;
  height: 7px;
  width: 7px;
}
.wg-modal-body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.wg-modal-step {
  color: #9ca3af;
  display: block;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  margin-bottom: 0.35rem;
  text-transform: uppercase;
}
.wg-modal-modules {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.wg-mod-item {
  align-items: center;
  background: transparent;
  border: 1px solid #d6dae0;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  font-size: 0.85rem;
  font-weight: 600;
  gap: 0.4rem;
  padding: 0.45rem 0.8rem;
}
.wg-mod-item.is-active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
.wg-modal-main {
  display: grid;
  gap: 1rem;
  grid-template-columns: 240px minmax(0, 1fr);
}
.wg-modal-metrics {
  align-self: flex-start;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.wg-metric-inuse {
  align-items: center;
  background: color-mix(in srgb, var(--success, #00c666) 15%, transparent);
  border-radius: 50%;
  color: var(--success, #00c666);
  display: inline-flex;
  font-size: 0.7rem;
  height: 16px;
  justify-content: center;
  margin-left: 0.3rem;
  vertical-align: 1px;
  width: 16px;
}
.wg-metric-item {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  padding: 0.5rem 0.6rem;
  text-align: left;
}
.wg-metric-item:hover {
  background: rgba(0, 0, 0, 0.04);
}
.wg-metric-item.is-active {
  background: color-mix(in srgb, var(--primary) 8%, transparent);
  border-color: color-mix(in srgb, var(--primary) 30%, transparent);
}
.wg-metric-label {
  font-size: 0.88rem;
  font-weight: 600;
}
.wg-type-gallery {
  display: grid;
  gap: 0.35rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
/* Icono a la izquierda y textos a la derecha: filas bajas, sin scroll */
.wg-type-item {
  align-items: center;
  background: #fff;
  border: 1px solid #d6dae0;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  font-size: 0.78rem;
  gap: 0.45rem;
  min-width: 0;
  padding: 0.4rem 0.55rem;
  text-align: left;
  white-space: nowrap;
}
.wg-type-item span {
  overflow: hidden;
  text-overflow: ellipsis;
}
.wg-type-item .ti {
  flex-shrink: 0;
  font-size: 1.05rem;
}
.wg-type-item.is-active {
  border: 2px solid var(--primary);
  color: var(--primary);
  font-weight: 700;
}
.wg-type-badge {
  border-radius: 4px;
  flex-shrink: 0;
  font-size: 0.6rem;
  font-weight: 700;
  margin-left: auto;
  padding: 0 4px;
}
.wg-type-badge.is-rec {
  background: #e4f4ec;
  color: #0e7a4a;
}
.wg-type-badge.is-bad {
  background: #fbefdf;
  color: #b54708;
}
.wg-size-row {
  margin-top: 0.5rem;
}
.wg-size-options {
  display: flex;
  gap: 0.3rem;
}
.wg-size-item {
  background: transparent;
  border: 1px solid #d6dae0;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.3rem 0.8rem;
}
.wg-size-item.is-active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
.wg-size-item.is-disabled {
  color: #c9cfd9;
  cursor: not-allowed;
}
.wg-preview {
  background: rgba(0, 0, 0, 0.02);
  border: 1px dashed #d6dae0;
  border-radius: 10px;
  margin-top: 0.25rem;
  min-height: 150px;
  padding: 0.6rem;
}
.wg-preview-loading {
  padding: 1rem;
}
.wg-note {
  align-items: flex-start;
  border-radius: 8px;
  display: flex;
  font-size: 0.8rem;
  gap: 0.45rem;
  margin-top: 0.6rem;
  padding: 0.5rem 0.7rem;
}
.wg-note.is-rec {
  background: #e9f7f0;
  border: 1px solid #bce6d2;
  color: #0e7a4a;
}
.wg-note.is-bad {
  background: #fdf3e7;
  border: 1px solid #f6d9ae;
  color: #b54708;
}
.wg-modal-footer {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}
@media (max-width: 767.98px) {
  .wg-modal-main {
    grid-template-columns: 1fr;
  }
  .wg-type-gallery {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
