/**
 * Agrega el atributo decimal a el-input de Element UI.
 *
 * Para campos de importes escritos en un el-input de texto (precio unitario, etc.):
 * el v-model nunca recibe "12,5" ni "abc", que terminan en NaN al calcular.
 *
 *   <el-input v-model="form.unit_price" decimal @input="calculateQuantity" />
 *
 * - Al escribir: la coma se cambia por punto, se ignoran letras y símbolos, un segundo
 *   separador decimal y el signo menos que no vaya al inicio.
 * - Al pegar o soltar texto ("S/ 1.234,50", "1,234.50"): el separador que se repite o el
 *   que va primero es de miles y se quita; el último queda como decimal (1234.50).
 *
 * Sin el atributo decimal el componente se comporta exactamente igual que antes.
 *
 * Se limpia el valor del <input> antes de llamar al handleInput vigente, por eso este
 * archivo debe importarse después de element-input-remote-search: así también aplica
 * a un el-input que use remote-search y decimal a la vez.
 */
import ElementUI from 'element-ui'

const ElInput = ElementUI.Input

const countOf = (value, char) => value.split(char).length - 1

// "1.234,50" / "1,234.50" / "1,234,567": quita el separador de miles
const removeThousandsSeparator = value => {
    const commas = countOf(value, ',')
    const dots = countOf(value, '.')

    if (commas && dots) {
        const thousands = value.lastIndexOf(',') > value.lastIndexOf('.') ? /\./g : /,/g
        return value.replace(thousands, '')
    }
    if (commas > 1) return value.replace(/,/g, '')
    if (dots > 1) return value.replace(/\./g, '')

    return value
}

// Deja solo dígitos, un único punto decimal y el signo menos al inicio
const sanitizeDecimal = (value, pasted = false) => {
    let clean = String(value).replace(/[^\d.,-]/g, '')
    if (pasted) clean = removeThousandsSeparator(clean)

    let has_dot = false

    return clean
        .replace(/,/g, '.')
        .split('')
        .filter((char, index) => {
            if (char === '-') return index === 0
            if (char === '.') return has_dot ? false : (has_dot = true)
            return true
        })
        .join('')
}

if (ElInput && !ElInput.props.decimal) {
    ElInput.props.decimal = { type: Boolean, default: false }

    const baseHandleInput = ElInput.methods.handleInput

    ElInput.methods.handleInput = function (event) {
        if (this.decimal && !this.isComposing && this.type !== 'number') {
            const input = event.target
            const value = input.value
            const pasted = /^insertFrom(Paste|Drop)/.test(event.inputType || '')
            const sanitized = sanitizeDecimal(value, pasted)

            if (sanitized !== value) {
                // Al reescribir el valor el navegador manda el cursor al final: se recoloca
                const chars_after_caret = value.length - input.selectionStart
                const caret = pasted
                    ? Math.max(0, sanitized.length - chars_after_caret)
                    : sanitizeDecimal(value.slice(0, input.selectionStart)).length

                input.value = sanitized
                input.setSelectionRange(caret, caret)
            }
        }

        return baseHandleInput.call(this, event)
    }
}

export default ElInput
