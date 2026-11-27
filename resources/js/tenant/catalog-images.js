const placeholder = '/logo/imagen-no-disponible.jpg';
const images = new WeakMap();
let observer;

function release(image) {
    observer?.unobserve(image);
    image.removeEventListener('error', fallback);
    images.delete(image);
}

function fallback(event) {
    const image = event.currentTarget;
    const state = images.get(image);
    if (!state || !state.started) return;
    const next = state.sources[++state.index];
    if (next) image.src = next;
}

function show(image) {
    const state = images.get(image);
    if (!state || state.started) return;
    state.started = true;
    observer?.unobserve(image);
    if (image.getAttribute('src') !== state.sources[0]) image.src = state.sources[0];
    else if (image.complete && !image.naturalWidth) fallback({currentTarget: image});
}

function configure(image, {small, original, eager, missing = placeholder}) {
    const sources = [...new Set([small, original, missing, placeholder].filter(Boolean))];
    if (sources.length > 1 && /\/logo\/imagen-no-disponible\.jpg$/.test(sources[0])) sources.shift();
    const previous = images.get(image);
    if (!previous || previous.sources.join('\n') !== sources.join('\n')) {
        release(image);
        if (previous && !eager) image.removeAttribute('src');
        images.set(image, {sources, started: false, index: 0});
        image.addEventListener('error', fallback);
    }
    image.decoding = eager ? 'auto' : 'async';
    if (eager || typeof IntersectionObserver === 'undefined') {
        show(image);
    } else if (!images.get(image).started) {
        observer ||= new IntersectionObserver(entries => {
            for (const entry of entries) if (entry.isIntersecting) show(entry.target);
        });
        observer.observe(image);
    }
}

export const catalogImage = {
    inserted(image, {value}) { configure(image, value); },
    update(image, {value}) { configure(image, value); },
    unbind: release,
};

export function installCatalogImages(root) {
    const selector = 'img[data-catalog-src],img[data-catalog-original]';
    function visit(node, action) {
        if (node.nodeType !== 1 && node !== root) return;
        if (node.matches?.(selector)) action(node);
        node.querySelectorAll(selector).forEach(action);
    }
    const mount = image => configure(image, {
        small: image.dataset.catalogSrc, original: image.dataset.catalogOriginal,
        missing: image.dataset.catalogMissing, eager: image.dataset.catalogEager === '1',
    });
    visit(root, mount);
    new MutationObserver(records => {
        for (const record of records) {
            if (record.type === 'attributes' && record.target.matches(selector)) mount(record.target);
            for (const node of record.removedNodes) visit(node, release);
            for (const node of record.addedNodes) visit(node, mount);
        }
    }).observe(root, {subtree: true, childList: true, attributeFilter: ['data-catalog-src', 'data-catalog-original', 'data-catalog-eager']});
}
