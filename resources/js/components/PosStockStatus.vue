<template>
    <div v-if="pending || error" class="alert py-2 my-2" :class="error ? 'alert-danger' : 'alert-info'" role="status" aria-live="polite">
        {{ error || 'Verificando disponibilidad de productos…' }}
        <button v-if="error && !pending && retryable" type="button" class="btn btn-sm btn-default" @click="$emit('retry')">Reintentar</button>
    </div>
</template>
<script>
export default {props:{pending:Boolean,error:{type:String,default:''},retryable:Boolean}}
</script>
