/* Build only the editable realtime integration. Do not rebuild SAB's Vue 2 assets. */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const esbuild = require('esbuild');
const root = path.resolve(__dirname, '..');
const publicDir = path.join(root, 'public');
const indexPath = path.join(publicDir, 'mozo/index.html');

async function build() {
    let html = fs.readFileSync(indexPath, 'utf8');
    const entry = html.match(/<script type="module" crossorigin src="([^"]+)"/)[1];
    const entryText = fs.readFileSync(path.join(publicDir, entry), 'utf8');
    const original = '/mozo/assets/' + entryText.match(/realtime\.[\w-]+\.js/)[0];
    const legacy = fs.readFileSync(path.join(publicDir, original), 'utf8');
    const vendor = legacy.match(/from "\.\/(vendor\.[^"]+)"/)[1];
    const master = legacy.match(/from "\.\/(masterService\.[^"]+)"/)[1];
    const bridge = `
        export { r as ref, d as defineStore, L as watch } from '/mozo/assets/${vendor}';
        export { u as useMesaSession, a as useProductSession, b as MesaService } from '/mozo/assets/${master}';
        export { u as useUserSession, p as provideApi } from '${entry}';
    `;
    const result = await esbuild.build({
        absWorkingDir: root,
        entryPoints: ['resources/js/mozo/realtime.js'],
        bundle: true, format: 'esm', target: 'es2020', minify: true, write: false,
        legalComments: 'eof',
        plugins: [{
            name: 'active-mozo-stores',
            setup(build) {
                build.onResolve({ filter: /^mozo:bridge$/ }, () => ({ path: 'bridge', namespace: 'mozo' }));
                build.onLoad({ filter: /.*/, namespace: 'mozo' }, () => ({ contents: bridge, loader: 'js' }));
                build.onResolve({ filter: /^\/mozo\/assets\// }, args => ({ path: args.path, external: true }));
            },
        }],
    });
    const contents = result.outputFiles[0].contents;
    const hash = crypto.createHash('sha256').update(contents).digest('hex').slice(0, 12);
    const authenticated = `/mozo/assets/realtime-auth.${hash}.js`;
    fs.writeFileSync(path.join(publicDir, authenticated), contents);
    const manifest = { original, authenticated, entry };
    fs.writeFileSync(path.join(publicDir, 'mozo/realtime-manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
    html = html.replace(/\s*<script type="importmap" id="(?:sab|sab)-realtime-importmap">[\s\S]*?<\/script>\s*/g, '\n');
    const imports = JSON.stringify({ imports: { [original]: authenticated } });
    html = html.replace('<script type="module"', `<script type="importmap" id="sab-realtime-importmap">${imports}</script>\n    <script type="module"`);
    fs.writeFileSync(indexPath, html);
    console.log(`Mozo realtime: ${original} -> ${authenticated}`);
}
build().catch(error => { console.error(error.message); process.exitCode = 1; });
