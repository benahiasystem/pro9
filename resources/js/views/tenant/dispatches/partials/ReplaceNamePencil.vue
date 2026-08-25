<template>
  <el-popover
    v-model="visible"
    placement="bottom-end"
    width="340"
    trigger="manual"
    popper-class="dispatch-replace-name-popper"
  >
    <div class="dispatch-replace-name-body">
      <label class="control-label d-block mb-1">{{ label }}</label>
      <el-input
        ref="input"
        v-model="localValue"
        type="textarea"
        :rows="3"
        :placeholder="placeholder"
        @input="onInput"
      ></el-input>
      <div class="text-end mt-2">
        <el-button type="primary" size="mini" @click="close">Listo</el-button>
      </div>
    </div>
    <button
      slot="reference"
      type="button"
      class="dispatch-replace-name-btn"
      :class="{ 'is-active': hasValue, 'is-disabled': disabled }"
      :disabled="disabled"
      :title="hasValue ? 'Nombre personalizado activo' : tooltip"
      @click.stop.prevent="toggle"
    >
      <i class="fas fa-pen" aria-hidden="true"></i>
    </button>
  </el-popover>
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
      default: 'Nombre que se mostrará en la guía (PDF)',
    },
    tooltip: {
      type: String,
      default: 'Reemplazar nombre en la guía',
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
      return !!(this.localValue && String(this.localValue).trim())
    },
  },
  watch: {
    value(val) {
      this.localValue = val || ''
    },
    disabled(val) {
      if (val) this.visible = false
    },
  },
  methods: {
    toggle() {
      if (this.disabled) return
      this.visible = !this.visible
      if (this.visible) {
        this.$nextTick(() => {
          const input = this.$refs.input
          if (input && input.focus) input.focus()
        })
      }
    },
    onInput(val) {
      this.$emit('input', val)
    },
    close() {
      this.visible = false
    },
  },
}
</script>

<style scoped>
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
