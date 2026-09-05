<template>
  <span class="dispatch-replace-name">
    <button
      type="button"
      class="dispatch-replace-name-btn"
      :class="{ 'is-active': hasValue, 'is-disabled': disabled }"
      :disabled="disabled"
      :title="hasValue ? 'Nombre personalizado activo' : tooltip"
      @click.stop.prevent="open"
    >
      <i class="fas fa-pen" aria-hidden="true"></i>
    </button>

    <el-dialog
      :title="label"
      :visible.sync="visible"
      width="640px"
      top="8vh"
      append-to-body
      :close-on-click-modal="false"
      custom-class="dispatch-replace-name-dialog"
      @opened="focusInput"
      @close="onDialogClose"
    >
      <div class="form-body">
        <div class="form-group mb-0">
          <label class="control-label">{{ label }}</label>
          <el-input
            ref="input"
            v-model="localValue"
            type="textarea"
            :rows="6"
            :placeholder="placeholder"
          ></el-input>
        </div>
      </div>
      <span slot="footer" class="dialog-footer">
        <el-button @click="close">Cerrar</el-button>
        <el-button type="primary" @click="confirm">Listo</el-button>
      </span>
    </el-dialog>
  </span>
</template>

<script>
export default {
  name: 'ReplaceNamePencil',
  props: {
    value: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'Reemplazar nombre' },
    placeholder: {
      type: String,
      default: 'Nombre que se mostrará en la orden de entrega (PDF)',
    },
    tooltip: {
      type: String,
      default: 'Reemplazar nombre en la orden de entrega',
    },
  },
  data() {
    return {
      visible: false,
      localValue: this.value || '',
    }
  },
  computed: {
    hasValue() {
      return !!(this.value && String(this.value).trim())
    },
  },
  watch: {
    value(val) {
      if (!this.visible) {
        this.localValue = val || ''
      }
    },
    disabled(val) {
      if (val) this.visible = false
    },
  },
  methods: {
    open() {
      if (this.disabled) return
      this.localValue = this.value || ''
      this.visible = true
    },
    focusInput() {
      this.$nextTick(() => {
        const input = this.$refs.input
        if (input && input.focus) input.focus()
      })
    },
    confirm() {
      this.$emit('input', this.localValue || '')
      this.visible = false
    },
    close() {
      this.visible = false
    },
    onDialogClose() {
      // Si cierra con X / ESC sin Listo, no descarta lo ya confirmado;
      // solo sincroniza el borrador visible al valor actual.
      this.localValue = this.value || ''
    },
  },
}
</script>

<style scoped>
.dispatch-replace-name {
  display: inline-flex;
  vertical-align: middle;
}
.dispatch-replace-name-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  margin-left: 4px;
  padding: 0;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  background: #fff;
  color: #606266;
  cursor: pointer;
  line-height: 1;
  transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}
.dispatch-replace-name-btn i {
  font-size: 14px;
  line-height: 1;
}
.dispatch-replace-name-btn:hover:not(.is-disabled) {
  color: #409eff;
  border-color: #c6e2ff;
  background: #ecf5ff;
}
.dispatch-replace-name-btn.is-active {
  color: #fff;
  border-color: #409eff;
  background: #409eff;
}
.dispatch-replace-name-btn.is-active:hover:not(.is-disabled) {
  color: #fff;
  border-color: #66b1ff;
  background: #66b1ff;
}
.dispatch-replace-name-btn.is-disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
