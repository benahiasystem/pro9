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

/* ---------------------------------------------------------------------------
   Teclado numérico y campos cortos
   ---------------------------------------------------------------------------
   Marcar campo por campo en ~400 vistas no es viable, así que se deducen dos
   cosas del propio formulario:

   1. Teclado: los campos de importes y cantidades reciben inputmode="decimal"
      y los de contacto inputmode="tel". Sólo se marcan los que son numéricos
      con certeza: los códigos internos, por ejemplo, admiten letras
      ("00025-VER-36"), así que quedan fuera a propósito.

   2. Ancho: un campo con maxlength corto (RUC, DNI, serie…) ocupaba el ancho
      completo en celular. Se le pone la clase m-campo-corto y mobile.css lo
      lleva a media fila.
   ------------------------------------------------------------------------- */

const CAMPOS_NUMERICOS = /(cantidad|precio|monto|importe|total|subtotal|descuento|cargo|stock|peso|porcentaje|unitario|vuelto|saldo|tipo de cambio|valor venta)/i;
const CAMPOS_TELEFONO = /(tel[eé]fono|celular|whatsapp|m[oó]vil)/i;
const MAXLENGTH_CORTO = 15;

const textoDelCampo = input => {
    const cont = input.closest('.el-form-item, .form-group, [class*="col-"]');
    const etiqueta = cont
        ? cont.querySelector('label, .control-label, .fp-field-label')
        : null;
    return `${(etiqueta && etiqueta.textContent) || ''} ${input.placeholder || ''}`;
};

const prepararCampos = (root = document) => {
    const campos = root.querySelectorAll(
        'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file])'
    );

    campos.forEach(input => {
        // Los desplegables (el-select) llevan un input interno que sirve de
        // filtro: darle teclado numérico abriría el teclado equivocado
        if (input.closest('.el-select, .el-date-editor') || input.readOnly) return;

        if (!input.dataset.mTeclado) {
            input.dataset.mTeclado = '1';
            const texto = textoDelCampo(input);
            const esNumero =
                input.type === 'number' || !!input.closest('.el-input-number');

            if (!input.getAttribute('inputmode')) {
                if (esNumero || CAMPOS_NUMERICOS.test(texto)) {
                    input.setAttribute('inputmode', 'decimal');
                } else if (CAMPOS_TELEFONO.test(texto)) {
                    input.setAttribute('inputmode', 'tel');
                }
            }

            // Los campos con botón adjunto (p. ej. Número + SUNAT) no se
            // acortan: el botón se superpone y deja el campo inservible
            const conBotonAdjunto = !!input.closest('.el-input-group, .input-group');
            const maxlength = parseInt(input.getAttribute('maxlength'), 10);
            if (!conBotonAdjunto && maxlength > 0 && maxlength <= MAXLENGTH_CORTO) {
                const columna = input.closest('[class*="col-"]');
                if (columna) columna.classList.add('m-campo-corto');
            }
        }
    });
};

/* Botones de la barra de listados: en celular ocupan una línea entera cada
   uno por su texto. Se marcan aquí (el texto cambia según el estado:
   "Mostrar/Ocultar filtros") y mobile.css los dibuja como icono. */
const BOTONES_ICONO = [
    { patron: /(mostrar|ocultar)\s+filtros/i, clase: 'm-ico-filtros' },
    { patron: /(mostrar|ocultar)\s+columnas/i, clase: 'm-ico-columnas' },
    { patron: /(mostrar|ver)\s+todos/i, clase: 'm-ico-todos' }
];

const marcarBotonesIcono = (root = document) => {
    root.querySelectorAll('.btn, .el-button').forEach(boton => {
        if (boton.dataset.mIcono) return;
        const texto = (boton.textContent || '').replace(/\s+/g, ' ').trim();
        if (!texto) return;
        const encontrado = BOTONES_ICONO.find(b => b.patron.test(texto));
        if (!encontrado) return;
        boton.dataset.mIcono = '1';
        boton.classList.add('m-boton-icono', encontrado.clase);
        if (!boton.getAttribute('title')) boton.setAttribute('title', texto);
    });
};

/* ---------------------------------------------------------------------------
   Fondo real de la página para las barras fijas
   ---------------------------------------------------------------------------
   El buscador del POS queda fijo arriba y necesita un fondo opaco para que
   los productos no se vean por detrás. No hay un token de color de fondo en
   los skins, así que un color fijo desentona al cambiar de tema: se lee el
   fondo efectivo del contenedor y se expone como variable.
   ------------------------------------------------------------------------- */

const ES_TRANSPARENTE = color =>
    !color || color === 'transparent' || /rgba\(0,\s*0,\s*0,\s*0\)/.test(color);

const sincronizarFondoPagina = () => {
    const raiz = document.querySelector('.pos, .garage');
    if (!raiz) return;

    let elemento = raiz;
    while (elemento && elemento !== document.documentElement) {
        const fondo = getComputedStyle(elemento).backgroundColor;
        if (!ES_TRANSPARENTE(fondo)) {
            document.documentElement.style.setProperty('--m-fondo-pagina', fondo);
            return;
        }
        elemento = elemento.parentElement;
    }
};

/* ---------------------------------------------------------------------------
   Acciones de la cabecera agrupadas en un menú
   ---------------------------------------------------------------------------
   En celular los botones de la cabecera (Reporte de Pagos, Validación masiva,
   Importar, Exportar…) ocupan varias líneas. Se deja fuera sólo el de crear,
   convertido en un "+", y el resto pasa a un menú de tres puntos.

   Los botones originales NO se mueven de sitio: Vue los administra y moverlos
   rompería su renderizado. Se ocultan y el menú lleva copias que reenvían el
   clic al botón real, así se conservan sus manejadores.
   ------------------------------------------------------------------------- */

const ES_BOTON_CREAR = /^\s*(\+\s*)?(nuevo|crear|agregar)\b/i;

const opcionesElementUi = boton => {
    const idMenu = boton.getAttribute('aria-controls');
    const menuEl = idMenu ? document.getElementById(idMenu) : null;
    if (!menuEl || !menuEl.classList.contains('el-dropdown-menu')) return [];

    return [
        ...menuEl.querySelectorAll('.el-dropdown-menu__item:not(.is-disabled)')
    ].map(opcion => ({
        boton: opcion,
        texto: (opcion.textContent || '').replace(/\s+/g, ' ').trim()
    }));
};

const construirMenuAcciones = () => {
    if (!isMobile()) return;

    document.querySelectorAll('.page-header .right-wrapper').forEach(cabecera => {
        // Los botones no siempre cuelgan directo de la cabecera: varios
        // módulos los agrupan en un .btn-group. Se excluyen los que ya son
        // opciones de un desplegable y los del propio menú.
        const botones = [...cabecera.querySelectorAll('.btn, .el-button')].filter(
            b => !b.closest('.m-menu-acciones') && !b.closest('.dropdown-menu')
        );
        if (!botones.length) return;

        const secundarios = [];
        botones.forEach(boton => {
            const texto = (boton.textContent || '').replace(/\s+/g, ' ').trim();

            if (ES_BOTON_CREAR.test(texto)) {
                boton.classList.add('m-ico-nuevo');
                if (!boton.getAttribute('title')) boton.setAttribute('title', texto);
                return;
            }

            boton.classList.add('m-accion-en-menu');

            // Si el botón abre su propio desplegable (p. ej. "Reporte de
            // Pagos" → Generar Reporte / Descargar Excel), sus opciones se
            // suman al menú: reenviar el clic al botón dejaría el
            // desplegable anclado a un elemento oculto.
            if (boton.classList.contains('dropdown-toggle')) {
                const grupo = boton.closest('.btn-group, .dropdown');
                const propio = grupo ? grupo.querySelector('.dropdown-menu') : null;
                const opciones = propio ? [...propio.querySelectorAll('a, button')] : [];
                if (opciones.length) {
                    opciones.forEach(opcion =>
                        secundarios.push({
                            boton: opcion,
                            texto: (opcion.textContent || '').replace(/\s+/g, ' ').trim()
                        })
                    );
                    return;
                }
            }

            const anidadas = opcionesElementUi(boton);
            if (anidadas.length) {
                secundarios.push({ boton, texto, opciones: anidadas });
                return;
            }

            secundarios.push({ boton, texto });
        });

        const menuPrevio = cabecera.querySelector('.m-menu-acciones');
        if (!secundarios.length) {
            if (menuPrevio) menuPrevio.remove();
            return;
        }

        // Se rehace sólo si cambiaron las acciones disponibles
        const firma = secundarios
            .map(s =>
                s.opciones
                    ? `${s.texto}>${s.opciones.map(o => o.texto).join(',')}`
                    : s.texto
            )
            .join('|');
        if (menuPrevio && menuPrevio.dataset.firma === firma) return;
        if (menuPrevio) menuPrevio.remove();

        const menu = document.createElement('div');
        menu.className = 'm-menu-acciones';
        menu.dataset.firma = firma;

        const disparador = document.createElement('button');
        disparador.type = 'button';
        disparador.className = 'm-menu-acciones__btn';
        disparador.setAttribute('aria-label', 'Más acciones');

        const panel = document.createElement('div');
        panel.className = 'm-menu-acciones__panel';

        const crearItem = (texto, clase) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = clase;
            item.textContent = texto;
            return item;
        };

        secundarios.forEach(({ boton, texto, opciones }) => {
            if (opciones) {
                const grupo = document.createElement('div');
                grupo.className = 'm-menu-acciones__grupo';

                const titulo = crearItem(
                    texto,
                    'm-menu-acciones__item m-menu-acciones__grupo-btn'
                );
                titulo.setAttribute('aria-expanded', 'false');
                titulo.addEventListener('click', evento => {
                    evento.preventDefault();
                    const abierto = grupo.classList.toggle('is-open');
                    titulo.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                });
                grupo.appendChild(titulo);

                opciones.forEach(opcion => {
                    const subitem = crearItem(
                        opcion.texto,
                        'm-menu-acciones__item m-menu-acciones__subitem'
                    );
                    subitem.addEventListener('click', evento => {
                        evento.preventDefault();
                        menu.classList.remove('is-open');
                        opcion.boton.click();
                    });
                    grupo.appendChild(subitem);
                });

                panel.appendChild(grupo);
                return;
            }

            const item = crearItem(texto, 'm-menu-acciones__item');
            item.addEventListener('click', evento => {
                evento.preventDefault();
                menu.classList.remove('is-open');
                boton.click();
            });
            panel.appendChild(item);
        });

        disparador.addEventListener('click', evento => {
            evento.preventDefault();
            evento.stopPropagation();
            // Los grupos vuelven plegados en cada apertura
            panel.querySelectorAll('.m-menu-acciones__grupo.is-open').forEach(g => {
                g.classList.remove('is-open');
                const titulo = g.querySelector('.m-menu-acciones__grupo-btn');
                if (titulo) titulo.setAttribute('aria-expanded', 'false');
            });
            menu.classList.toggle('is-open');
        });

        menu.appendChild(disparador);
        menu.appendChild(panel);
        cabecera.appendChild(menu);
    });
};

document.addEventListener('click', evento => {
    document.querySelectorAll('.m-menu-acciones.is-open').forEach(menu => {
        if (!menu.contains(evento.target)) menu.classList.remove('is-open');
    });
});

/* ---------------------------------------------------------------------------
   Bloqueo del scroll de fondo con un modal abierto
   ---------------------------------------------------------------------------
   Element UI ya pone overflow:hidden en el body, pero Safari en iOS lo ignora
   y la página de atrás se sigue desplazando. La técnica que sí funciona es
   fijar el body y compensar el desplazamiento actual, restaurándolo al
   cerrar (si no, la página salta al inicio).
   ------------------------------------------------------------------------- */

let scrollGuardado = null;

/* Se comprueba el modal REAL, no la marca del body: Element UI a veces deja
   puesta su clase el-popup-parent--hidden después de cerrar (lleva un
   contador de popups que se descuadra), y confiar en ella dejaría la página
   bloqueada para siempre. */
const hayModalAbierto = () =>
    [
        ...document.querySelectorAll(
            '.el-dialog__wrapper, .el-drawer__wrapper, .el-message-box__wrapper, .modal.show, .swal2-container'
        )
    ].some(ventana => {
        // offsetParent no sirve aquí: es null en todo elemento position:fixed,
        // que es justo lo que son estas ventanas
        if (getComputedStyle(ventana).display === 'none') return false;
        // Durante el cierre la ventana sigue visible con la clase de salida
        // (dialog-fade-leave, swal2-hide…). Se considera ya cerrada: si esa
        // animación se interrumpiera, la página quedaría bloqueada.
        const clases = ventana.className.toString();
        if (/-leave/.test(clases) || ventana.classList.contains('swal2-hide')) {
            return false;
        }
        return ventana.getBoundingClientRect().height > 0;
    });

const actualizarBloqueoScroll = () => {
    const abierto = hayModalAbierto();

    // Sin modal a la vista, la marca de Element UI sólo puede ser un resto:
    // mientras siga puesta el body queda con overflow:hidden y la página no
    // se desplaza. Se limpia en cualquier tamaño de pantalla.
    if (!abierto && document.body.classList.contains('el-popup-parent--hidden')) {
        document.body.classList.remove('el-popup-parent--hidden');
        document.body.style.paddingRight = '';
    }

    if (!isMobile()) return;

    if (abierto && scrollGuardado === null) {
        scrollGuardado = window.scrollY || document.documentElement.scrollTop || 0;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollGuardado}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
        return;
    }

    if (!abierto && scrollGuardado !== null) {
        const volverA = scrollGuardado;
        scrollGuardado = null;
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        // Instantáneo a propósito: hay scroll-behavior:smooth global y con
        // desplazamiento animado la posición no llega a restaurarse
        window.scrollTo({ top: volverA, left: 0, behavior: 'instant' });
    }
};

// Los formularios y modales se montan después (Vue), y varios se abren y
// cierran durante la sesión: se re-examina el DOM al haber cambios.
let pendiente = null;
const observador = new MutationObserver(() => {
    if (pendiente) return;
    pendiente = setTimeout(() => {
        pendiente = null;
        prepararCampos();
        marcarBotonesIcono();
        construirMenuAcciones();
        sincronizarFondoPagina();
    }, 250);
});

/* Observador aparte para el bloqueo. Los modales se muestran y ocultan
   cambiando su style/class, no siempre añadiendo o quitando nodos, así que
   hay que mirar atributos; con un retardo corto para no encarecer los
   cambios continuos de Vue. */
let pendienteModal = null;
const observadorModal = new MutationObserver(() => {
    if (pendienteModal) return;
    pendienteModal = setTimeout(() => {
        pendienteModal = null;
        actualizarBloqueoScroll();
    }, 80);
});

const iniciar = () => {
    prepararCampos();
    marcarBotonesIcono();
    construirMenuAcciones();
    sincronizarFondoPagina();
    observador.observe(document.body, { childList: true, subtree: true });
    observadorModal.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style']
    });
    actualizarBloqueoScroll();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
