<template>
    <span class="variation-chips" v-if="attributes.length || code">
        <span v-for="(attribute, index) in attributes"
              :key="index"
              class="variation-chip">
            <span v-if="attribute.color"
                  class="variation-chip__dot"
                  :style="{ background: attribute.color }"></span>
            {{ label(attribute) }}
        </span>
        <small v-if="code" class="variation-chips__code text-muted">
            {{ code }}<template v-if="barcode"> · {{ barcode }}</template>
        </small>
    </span>
</template>

<script>
export default {
    props: {
        attributes: {
            type: Array,
            default: () => [],
        },
        code: {
            type: String,
            default: null,
        },
        barcode: {
            type: String,
            default: null,
        },
    },
    methods: {
        label(attribute) {
            if (attribute.color || !attribute.variable) {
                return attribute.value
            }

            return `${attribute.variable} ${attribute.value}`
        },
    },
}
</script>

<style scoped>
.variation-chips {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    vertical-align: middle;
}
.variation-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 1px 8px;
    border: 1px solid rgba(0, 0, 0, 0.12);
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    line-height: 18px;
    white-space: nowrap;
}
.variation-chip__dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
}
.variation-chips__code {
    font-family: monospace;
    font-size: 11px;
    text-transform: uppercase;
    white-space: nowrap;
}
</style>
