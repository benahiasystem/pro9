/**
 * Agrega busqueda remota con debounce a el-input de Element UI.
 *
 * Se parchea el componente base (ElementUI.Input) antes de Vue.use(ElementUI),
 * igual que los fixes de Select y Tooltip, para que lo hereden todos los
 * el-input del proyecto sin tocar cada vista.
 *
 * Uso: basta con agregar el atributo, el @input de siempre queda igual.
 *
 *   <el-input v-model="search.value" remote-search @input="getRecords" />
 *   <el-input v-model="search.value" remote-search :remote-debounce="600" @input="getRecords" />
 *
 * Sin el atributo remote-search el componente se comporta exactamente igual que antes.
 *
 * Con remote-search activo se difiere el evento input, que es el que dispara tanto
 * el v-model como el handler de busqueda. Para que el usuario no pierda lo que
 * escribe mientras el emit esta pendiente, el valor tecleado se guarda en
 * remotePendingValue y nativeInputValue lo devuelve: asi setNativeInputValue
 * reescribe el <input> del DOM con el texto actual y no con el prop value viejo.
 *
 * El emit tambien se adelanta al salir del campo o presionar Enter, para que la
 * busqueda no quede pendiente.
 *
 * La prop no puede llamarse "debounce": el-autocomplete hace v-bind="[$props, $attrs]"
 * hacia su el-input interno y ya tiene una prop con ese nombre.
 */
import ElementUI from 'element-ui'
import debounce from 'lodash/debounce'

const ElInput = ElementUI.Input

if (ElInput && !ElInput.props.remoteSearch) {
    ElInput.props.remoteSearch = { type: Boolean, default: false }
    ElInput.props.remoteDebounce = { type: Number, default: 400 }

    const chain = (base, extra) => function (...args) {
        if (Array.isArray(base)) base.forEach(fn => fn.apply(this, args))
        else if (base) base.apply(this, args)
        return extra.apply(this, args)
    }

    const baseData = ElInput.data
    const baseNativeInputValue = ElInput.computed.nativeInputValue
    const baseHandleInput = ElInput.methods.handleInput
    const baseHandleChange = ElInput.methods.handleChange
    const baseClear = ElInput.methods.clear

    ElInput.data = function () {
        return Object.assign({}, baseData.call(this), { remotePendingValue: null })
    }

    // Mientras hay un emit pendiente, el valor visible es el que el usuario tecleo,
    // no el prop value (que todavia no se actualizo).
    ElInput.computed.nativeInputValue = function () {
        if (this.remotePendingValue === null) return baseNativeInputValue.call(this)
        return String(this.remotePendingValue)
    }

    // Programa el emit diferido. El debounce se crea por instancia para que cada
    // input tenga su propio timer.
    ElInput.methods.scheduleRemoteInput = function () {
        if (!this.remoteInputTimer) {
            this.remoteInputTimer = debounce(function () {
                if (this.remotePendingValue === null) return
                const value = this.remotePendingValue

                this.$emit('input', value)

                // Para este nextTick el padre ya sincronizo el v-model, asi que al
                // soltar el valor pendiente nativeInputValue coincide y no se pisa
                // nada. Si el padre no sincronizo, se revierte el DOM, que es el
                // comportamiento normal de un input controlado.
                this.$nextTick(() => {
                    this.remotePendingValue = null
                    this.setNativeInputValue()
                })
            }.bind(this), this.remoteDebounce)
        }
        this.remoteInputTimer()
    }

    // Emite ya lo que este pendiente (blur, Enter, clear, destroy).
    ElInput.methods.flushRemoteInput = function () {
        if (this.remoteInputTimer) this.remoteInputTimer.flush()
    }

    ElInput.methods.handleInput = function (event) {
        if (!this.remoteSearch) return baseHandleInput.call(this, event)

        // Mismas guardas que el componente original.
        if (this.isComposing) return
        if (event.target.value === this.nativeInputValue) return

        this.remotePendingValue = event.target.value
        this.scheduleRemoteInput()
    }

    ElInput.methods.handleChange = function (event) {
        // change nativo (blur o Enter): no hacer esperar la busqueda.
        if (this.remoteSearch) this.flushRemoteInput()
        baseHandleChange.call(this, event)
    }

    ElInput.methods.clear = function () {
        if (this.remoteInputTimer) this.remoteInputTimer.cancel()
        this.remotePendingValue = null
        baseClear.call(this)
    }

    ElInput.beforeDestroy = chain(ElInput.beforeDestroy, function () {
        if (this.remoteInputTimer) this.remoteInputTimer.cancel()
    })
}

export default ElInput
