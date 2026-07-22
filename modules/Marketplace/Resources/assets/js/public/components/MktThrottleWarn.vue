<template>
    <div class="mkt-warn" role="dialog" aria-modal="true">
        <div class="mkt-warn__box">
            <span class="mkt-warn__icon"><mkt-icon name="hand-stop" :size="32"/></span>
            <h2>Vas muy rápido</h2>
            <p>
                Llevas la mitad del límite de contactos permitidos en esta ventana de tiempo.
                Cada solicitud queda registrada con tu cookie e IP; si alcanzas el límite,
                quedarás bloqueado temporalmente.
            </p>
            <button type="button" class="mkt-warn__continue" @click="$emit('continue')">
                Entiendo, continuar <span class="mkt-mono">→</span>
            </button>
            <button type="button" class="mkt-warn__cancel" @click="$emit('cancel')">
                Volver al marketplace
            </button>
        </div>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'

/**
 * El aviso a pantalla completa del 50% del throttle (plan de seguridad, B3).
 *
 * Aparece ANTES de abrir la puerta de contacto cuando el uso registrado cruza
 * la mitad del límite del servidor. Un vecino lo lee y sigue; un scraping
 * semiautomático se frena — y queda avisado de que todo está registrado.
 */
export default {
    name: 'MktThrottleWarn',
    components: { MktIcon },
}
</script>

<style scoped>
.mkt-warn {
    position: fixed;
    inset: 0;
    z-index: 80;
    background: var(--buho-navy-950);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px;
    animation: mkt-fade 200ms var(--ease-out);
}

.mkt-warn__box {
    max-width: 440px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
    color: var(--white);
}

.mkt-warn__icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.mkt-warn__box h2 { margin: 0; font-size: var(--fs-h4); font-weight: 800; }
.mkt-warn__box p { margin: 0; font-size: var(--fs-body-sm); line-height: 1.6; color: rgba(255, 255, 255, 0.75); }

.mkt-warn__continue {
    height: 52px;
    padding: 0 28px;
    border-radius: var(--radius-full);
    border: none;
    background: var(--white);
    color: var(--buho-navy-950);
    font-weight: 700;
    font-size: var(--fs-body-sm);
    font-family: var(--font-sans);
    cursor: pointer;
}

.mkt-warn__cancel {
    background: none;
    border: none;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.7);
    font-family: var(--font-sans);
    font-size: var(--fs-caption);
    text-decoration: underline;
}
</style>
