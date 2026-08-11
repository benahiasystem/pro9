<template>
    <el-dialog title="Seleccionar atributos"
               :visible="showDialog"
               :close-on-click-modal="false"
               append-to-body
               top="7vh"
               width="560px"
               @open="open"
               @close="cancel">

        <p class="text-muted mt-0">
            Activa las variables que participarán en las combinaciones de este producto.
            Sus valores se eligen en la pestaña Atributos.
        </p>

        <div class="pv-chip-group">
            <span v-for="variable in productVariables"
                  :key="'variable-chip-' + variable.id"
                  class="pv-chip"
                  :class="{active: sel_variable_ids.includes(variable.id)}"
                  @click="toggleVariable(variable.id)">
                {{ variable.name }}
            </span>
        </div>
        <p v-if="productVariables.length === 0" class="text-muted mb-0">
            Aún no hay variables activas.
        </p>

        <div class="d-flex align-items-center justify-content-between pt-3 mt-3" style="border-top: 1px solid #ebeef5;">
            <a href="/configurations/product-variables" target="_blank">
                <i class="fa fa-cog"></i> Gestionar atributos
            </a>
            <span class="d-flex justify-content-end">
                <el-button size="small" class="second-buton me-2" @click.prevent="cancel">Cancelar</el-button>
                <el-button size="small" type="primary" @click.prevent="apply">Aplicar</el-button>
            </span>
        </div>
    </el-dialog>
</template>

<script>
export default {
    name: 'ItemVariationPicker',
    props: {
        showDialog: {default: false},
        productVariables: {type: Array, default: () => []},
        selectedVariableIds: {type: Array, default: () => []},
    },
    data() {
        return {
            sel_variable_ids: [],
        }
    },
    methods: {
        open() {
            this.sel_variable_ids = [...this.selectedVariableIds]
        },
        toggleVariable(variableId) {
            const index = this.sel_variable_ids.indexOf(variableId)
            if (index === -1) {
                this.sel_variable_ids.push(variableId)
            } else {
                this.sel_variable_ids.splice(index, 1)
            }
        },
        apply() {
            this.$emit('apply', {
                selected_variable_ids: [...this.sel_variable_ids],
            })
            this.$emit('update:showDialog', false)
        },
        cancel() {
            this.$emit('update:showDialog', false)
        },
    }
}
</script>

<style scoped>
.pv-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.pv-chip {
    display: inline-flex;
    align-items: center;
    border: 1px solid #dcdfe6;
    border-radius: 999px;
    padding: 5px 14px;
    font-size: 13px;
    background: #fff;
    cursor: pointer;
    user-select: none;
    transition: all 0.12s;
}
.pv-chip:hover {
    border-color: var(--primary);
    color: var(--primary);
}
.pv-chip.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    font-weight: 600;
}
.pv-chip.active:hover {
    color: #fff;
}
</style>
