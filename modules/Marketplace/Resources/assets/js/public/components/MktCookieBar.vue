<template>
    <div v-if="visible" class="mkt-cookies">
        <span class="mkt-cookies__text">
            <mkt-icon name="shield-lock" :size="18"/>
            <span>
                Usamos una cookie técnica y registramos tu IP; al pedir un contacto, también tu
                nombre y WhatsApp. Solo por motivos de seguridad (Ley 29733).
                <a v-if="termsUrl" :href="termsUrl">Términos y condiciones</a><template v-if="termsUrl && arcoEmail"> · </template><a v-if="arcoEmail" :href="'mailto:' + arcoEmail">Canal ARCO</a>
            </span>
        </span>
        <button type="button" @click="acknowledge">Entendido</button>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'

const KEY = 'mkt:cookies-ack'

/**
 * Banner de datos personales (plan de seguridad, B4 / Ley 29733): dice la
 * verdad — qué se guarda, para qué — y enlaza los T&C y el canal ARCO si el
 * marketplace los tiene configurados.
 */
export default {
    name: 'MktCookieBar',

    components: { MktIcon },

    props: {
        termsUrl: { type: String, default: null },
        arcoEmail: { type: String, default: '' },
    },

    data() {
        let acked = false
        try { acked = window.localStorage.getItem(KEY) === '1' } catch (e) { /* se muestra */ }
        return { visible: !acked }
    },

    methods: {
        acknowledge() {
            this.visible = false
            try { window.localStorage.setItem(KEY, '1') } catch (e) { /* volverá a salir */ }
        },
    },
}
</script>

<style scoped>
.mkt-cookies {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 58;
    background: var(--buho-navy-950);
    color: var(--white);
    padding: 14px 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 12px 24px;
    font-size: var(--fs-caption);
    animation: mkt-fade 240ms var(--ease-out);
}

.mkt-cookies__text {
    display: inline-flex;
    align-items: flex-start;
    gap: 10px;
    max-width: 720px;
    line-height: 1.5;
}

.mkt-cookies__text svg { flex: none; margin-top: 1px; }
.mkt-cookies__text a { color: var(--white); text-decoration: underline; }

.mkt-cookies button {
    height: 40px;
    padding: 0 20px;
    border-radius: var(--radius-full);
    border: none;
    background: var(--white);
    color: var(--buho-navy-950);
    font-weight: 700;
    font-size: var(--fs-caption);
    font-family: var(--font-sans);
    cursor: pointer;
    flex: none;
}
</style>
