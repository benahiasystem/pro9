/**
 * Comportamiento tipo app en celular.
 *
 * El meta viewport (maximum-scale=1, user-scalable=no) basta en Android,
 * pero Safari en iOS lo ignora desde iOS 10: el pellizco y el doble toque
 * siguen ampliando la página. Estos manejadores lo bloquean.
 *
 * Solo actúa en anchos de celular; en tablet y escritorio el zoom del
 * navegador se conserva intacto.
 */

const MOBILE_QUERY = '(max-width: 767.98px)';

const isMobile = () => window.matchMedia(MOBILE_QUERY).matches;

// Pellizco en iOS: eventos propietarios gesture* de Safari
['gesturestart', 'gesturechange', 'gestureend'].forEach(type => {
    document.addEventListener(
        type,
        event => {
            if (isMobile()) event.preventDefault();
        },
        { passive: false }
    );
});

// Doble toque para ampliar: se descarta el segundo toque si llega antes
// de 300ms. touch-action: manipulation (en mobile.css) cubre la mayoría
// de los casos; esto es el respaldo para iOS.
let last_touch_end = 0;
document.addEventListener(
    'touchend',
    event => {
        if (!isMobile()) return;
        const now = Date.now();
        if (now - last_touch_end <= 300) event.preventDefault();
        last_touch_end = now;
    },
    { passive: false }
);

// Safari amplía al enfocar un campo con letra menor a 16px. mobile.css ya
// fuerza 16px en los campos conocidos; si el zoom igual ocurre (campos de
// terceros), se restaura la escala al salir del campo.
document.addEventListener('focusout', () => {
    if (!isMobile()) return;
    if (window.visualViewport && window.visualViewport.scale > 1) {
        window.scrollTo(window.scrollX, window.scrollY);
    }
});
