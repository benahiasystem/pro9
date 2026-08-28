// Genera public/porto-light/css/icons-tabler.css: mapea las clases de
// Font Awesome usadas en el proyecto a glifos de Tabler, sin tocar el markup.
const fs = require('fs');

const SRC = '/Users/deyvis/Sites/pro71/node_modules/@tabler/icons-webfont/dist/tabler-icons.css';
const OUT = '/Users/deyvis/Sites/pro71/public/porto-light/css/icons-tabler.css';

// name -> codepoint, leído del CSS oficial
const css = fs.readFileSync(SRC, 'utf8');
const cp = {};
const re = /\.ti-([a-z0-9-]+):before\s*\{\s*content:\s*"\\([0-9a-f]+)"/g;
let m;
while ((m = re.exec(css))) cp[m[1]] = m[2];

// Font Awesome -> Tabler (nombre del icono equivalente)
const MAP = {
    'android': 'brand-android', 'angle-down': 'chevron-down', 'angle-left': 'chevron-left',
    'angle-right': 'chevron-right', 'archive': 'archive', 'arrow-left': 'arrow-left',
    'arrow-right': 'arrow-right', 'arrows-alt': 'arrows-maximize', 'bars': 'menu-2',
    'bell': 'bell', 'bolt': 'bolt', 'book-medical': 'book', 'border-all': 'layout-grid',
    'box-open': 'package', 'bus-alt': 'bus', 'calendar': 'calendar',
    'calendar-alt': 'calendar-event', 'cart-plus': 'shopping-cart-plus',
    'cash-register': 'cash-register', 'chart-line': 'chart-line', 'check': 'check',
    'check-circle': 'circle-check', 'chevron-down': 'chevron-down',
    'chevron-left': 'chevron-left', 'chevron-right': 'chevron-right', 'clock': 'clock',
    'cloud-upload-alt': 'cloud-upload', 'cog': 'settings', 'cogs': 'adjustments',
    'comment': 'message-circle', 'copy': 'copy', 'credit-card': 'credit-card',
    'cube': 'box', 'database': 'database', 'dollar-sign': 'currency-dollar',
    'download': 'download', 'edit': 'edit', 'ellipsis-h': 'dots',
    'ellipsis-v': 'dots-vertical', 'envelope': 'mail', 'exclamation': 'exclamation-mark',
    'exclamation-circle': 'alert-circle', 'exclamation-triangle': 'alert-triangle',
    'external-link-alt': 'external-link', 'eye': 'eye', 'eye-slash': 'eye-off',
    'file': 'file', 'file-alt': 'file-text', 'file-code': 'file-code',
    'file-download': 'file-download', 'file-excel': 'file-spreadsheet',
    'file-export': 'file-export', 'file-invoice': 'file-invoice',
    'file-pdf': 'file-type-pdf', 'file-signature': 'signature',
    'file-text-o': 'file-text', 'file-upload': 'file-upload', 'flag': 'flag',
    'github': 'brand-github', 'gitlab': 'brand-gitlab', 'heart': 'heart',
    'history': 'history', 'home': 'home', 'hotel': 'building', 'industry': 'building-factory',
    'infinity': 'infinity', 'info-circle': 'info-circle', 'link': 'link', 'list': 'list',
    'list-ul': 'list', 'lock': 'lock', 'minus': 'minus', 'minus-circle': 'circle-minus',
    'money-bill-alt': 'cash', 'money-bill-wave-alt': 'cash-banknote',
    'paint-brush': 'brush', 'paper-plane': 'send', 'pause': 'player-pause',
    'pen': 'pencil', 'play': 'player-play', 'play-circle': 'player-play',
    'plus': 'plus', 'plus-circle': 'circle-plus', 'power-off': 'power', 'print': 'printer',
    'question-circle': 'help-circle', 'receipt': 'receipt', 'refresh': 'refresh',
    'save': 'device-floppy', 'search': 'search', 'search-plus': 'zoom-in',
    'shopping-cart': 'shopping-cart', 'sort': 'arrows-sort', 'sort-down': 'chevron-down',
    'sort-up': 'chevron-up', 'spinner': 'loader-2', 'sync-alt': 'refresh',
    'tachometer-alt': 'dashboard', 'text-width': 'text-size', 'times': 'x',
    'times-circle': 'circle-x', 'toggle-off': 'toggle-left', 'toggle-on': 'toggle-right',
    'trash': 'trash', 'trash-alt': 'trash', 'undo': 'arrow-back-up', 'upload': 'upload',
    'usd': 'currency-dollar', 'user': 'user', 'user-circle': 'user-circle',
    'user-lock': 'user-shield', 'user-shield': 'user-shield', 'utensils': 'tools-kitchen-2',
    'warehouse': 'building-warehouse', 'whatsapp': 'brand-whatsapp', 'wifi': 'wifi',
    'youtube': 'brand-youtube'
};

const faltantes = [];
const reglas = [];
for (const [fa, ti] of Object.entries(MAP)) {
    if (!cp[ti]) { faltantes.push(`${fa} -> ${ti}`); continue; }
    reglas.push(`.fa-${fa}:before { content: "\\${cp[ti]}"; }`);
}

const out = `/* ============================================================================
   icons-tabler.css — Iconografía Tabler (tabler.io) sobre las clases de
   Font Awesome que ya existen en las vistas.

   GENERADO automáticamente: no editar a mano. Para regenerar, ver el mapa
   Font Awesome -> Tabler en el script de generación.

   Se sustituye la fuente y el glifo de cada clase .fa-*, de modo que todo
   el sistema queda con iconos Tabler sin tocar el markup (1348 usos en 461
   archivos). Los modificadores de Font Awesome (fa-fw, fa-2x, fa-spin...)
   siguen funcionando porque solo cambian tamaño/animación.
   ============================================================================ */

@font-face {
    font-family: "tabler-icons";
    font-style: normal;
    font-weight: 400;
    font-display: block;
    src: url("../vendor/tabler-icons/fonts/tabler-icons.woff2") format("woff2"),
         url("../vendor/tabler-icons/fonts/tabler-icons.woff") format("woff"),
         url("../vendor/tabler-icons/fonts/tabler-icons.ttf") format("truetype");
}

/* Las clases base de Font Awesome pasan a usar la fuente Tabler */
.fa,
.fas,
.far,
.fal,
.fab,
.fa-solid,
.fa-regular,
.fa-light,
.fa-brands {
    font-family: "tabler-icons" !important;
    font-weight: 400 !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
    line-height: 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Glifos equivalentes */
${reglas.join('\n')}
`;

fs.writeFileSync(OUT, out);
console.log('Reglas generadas:', reglas.length);
console.log('Sin equivalente:', faltantes.length ? faltantes.join(', ') : 'ninguno');
