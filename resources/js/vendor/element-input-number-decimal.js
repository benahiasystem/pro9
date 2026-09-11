/**
 * el-input-number: acepta la coma como separador decimal.
 *
 * El componente original hace Number(valor) al salir del campo: "12,5" da NaN y
 * descarta lo escrito sin avisar, dejando el valor anterior. Aquí la coma se
 * cambia por punto mientras se escribe (también al pegar), así el usuario ve
 * "12.5" y el v-model recibe 12.5.
 *
 * Se parchea el componente base (ElementUI.InputNumber) antes de Vue.use(ElementUI),
 * igual que remote-search, para que lo hereden todos los el-input-number del
 * proyecto sin tocar cada vista.
 */
import ElementUI from 'element-ui'

const ElInputNumber = ElementUI.InputNumber

const normalizeDecimal = value => (typeof value === 'string' ? value.replace(/,/g, '.') : value)

if (ElInputNumber && !ElInputNumber.methods.normalizeDecimal) {
    const baseHandleInput = ElInputNumber.methods.handleInput
    const baseHandleInputChange = ElInputNumber.methods.handleInputChange

    ElInputNumber.methods.normalizeDecimal = normalizeDecimal

    ElInputNumber.methods.handleInput = function (value) {
        const normalized = normalizeDecimal(value)
        if (normalized === value) return baseHandleInput.call(this, value)

        // Al reescribir el <input> el navegador manda el cursor al final: se restaura
        const input = this.$refs.input ? this.$refs.input.getInput() : null
        const caret = input ? input.selectionStart : null

        baseHandleInput.call(this, normalized)

        if (input && caret !== null) {
            this.$nextTick(() => input.setSelectionRange(caret, caret))
        }
    }

    // change (blur o Enter): por si el valor llega sin pasar por handleInput
    ElInputNumber.methods.handleInputChange = function (value) {
        return baseHandleInputChange.call(this, normalizeDecimal(value))
    }
}

export default ElInputNumber
