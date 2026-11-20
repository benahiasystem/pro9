<template>
    <div>
        <el-input :value="draft" :disabled="disabled" :size="size" inputmode="decimal"
            @input="onInput" @input.native="onNativeInput" @compositionend.native="onNativeInput"
            @change="commitPending" @focus="$emit('focus', $event)" />
        <small v-if="error" class="text-danger" role="alert">{{ error }}</small>
    </div>
</template>

<script>
import {exactAmount, exactInput} from '../../../../helpers/exchange-rate-math'

/** Billing-only input: editing text never changes the committed document. */
export default {
    props: {
        value: {default:'0'},
        exactSource: {default:null},
        apply: {type:Function,required:true},
        precision: {type:Number,default:12},
        disabled: {type:Boolean,default:false},
        size: {type:String,default:''},
        contextKey: {type:String,default:''},
        generation: {type:Number,default:0},
    },
    data() { return {draft:'',dirty:false,error:null,editingContext:''}; },
    created() { this.resetDraft(); },
    watch: {
        value() { if (!this.dirty) this.resetDraft(); },
        exactSource() { if (!this.dirty) this.resetDraft(); },
        generation() {
            if (this.dirty) this.$emit('cancelled','Se descartó un importe sin confirmar al cambiar de documento.');
            this.resetDraft();
        },
        disabled(value) {
            if (value) {
                if (this.dirty) this.$emit('cancelled','Se descartó un importe sin confirmar al deshabilitar el campo.');
                this.resetDraft();
            }
        },
    },
    beforeDestroy() { if (this.dirty) this.$emit('cancelled','Se descartó un importe sin confirmar al cerrar el campo.'); },
    methods: {
        displayValue() {
            const value=String(this.value ?? '0');
            return this.precision===2 ? exactAmount(value).final(2) : value;
        },
        resetDraft() { this.draft=this.displayValue();this.dirty=false;this.error=null;this.editingContext=this.contextKey; },
        onNativeInput(event) {
            // Element UI suppresses its input emission when a user replaces a visible zero with zero.
            // That explicit edit must still clear a nonzero fraction; focus/blur alone must preserve it.
            if (this.disabled || event.isComposing || event.target?.tagName!=='INPUT') return;
            this.onInput(event.target.value);
        },
        onInput(value) {
            if (!this.dirty || this.editingContext!==this.contextKey) this.editingContext=this.contextKey;
            this.draft=String(value);this.dirty=true;this.error=null;
        },
        validatePending() {
            if (this.disabled || !this.dirty) return {valid:true,unchanged:true};
            try {
                if (this.editingContext!==this.contextKey) throw new Error('La moneda o el tipo de descuento cambió. Revise el importe.');
                const text=this.draft.trim();
                if (!/^\d+(?:[.,]\d+)?$/.test(text)) throw new Error('Ingrese un importe decimal válido.');
                let value=exactInput(text.replace(',','.'));
                // Preserve the existing two-decimal line-discount contract, without Number.
                if (this.precision===2) value=exactInput(exactAmount(value).final(2));
                else if (this.precision!==12) throw new Error('Precisión decimal no admitida.');
                const clearingFraction=value==='0' && exactAmount(this.exactSource ?? this.value ?? '0').compare(0)!==0;
                return {valid:true,value,unchanged:!clearingFraction && exactAmount(value).compare(this.displayValue())===0};
            } catch (error) { this.error=error.message;return {valid:false}; }
        },
        commitPending() {
            const pending=this.validatePending();
            if (!pending.valid) return false;
            if (pending.unchanged) { this.resetDraft();return true; }
            try {
                const result=this.apply(pending.value,this);
                if (result===false) throw new Error('Revise el importe antes de continuar.');
                this.dirty=false;this.error=null;
                this.draft=this.precision===2 ? exactAmount(typeof result==='string' ? result : pending.value).final(2)
                    : typeof result==='string' ? result : pending.value;
                return true;
            } catch (error) { this.error=error.message;return false; }
        },
    },
};
</script>
