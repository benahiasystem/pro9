import jquery from 'jquery';

// Los plugins heredados se evalúan al cargar los módulos, antes del cuerpo de
// bootstrap.js. Conservar la instancia existente para no perder sus plugins.
const current = typeof window.jQuery === 'function' && window.jQuery.fn?.jquery
    ? window.jQuery
    : (typeof window.$ === 'function' && window.$.fn?.jquery ? window.$ : jquery);

window.jQuery = window.$ = current;
