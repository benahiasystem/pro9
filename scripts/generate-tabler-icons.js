const fs = require('fs');
const path = require('path');

const PKG_DIR = path.join(__dirname, '..', 'node_modules', '@tabler', 'icons');
const OUT_FILE = path.join(__dirname, '..', 'public', 'json', 'tabler-icons.json');

const ALLOWED_ATTRS = ['d', 'fill', 'opacity', 'stroke'];

const escapeAttr = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/"/g, '&quot;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;');

function nodesToSvg(nodes) {
  return nodes
    .filter(([tag]) => tag === 'path')
    .map(([, attrs]) => {
      const serialized = ALLOWED_ATTRS
        .filter((name) => attrs[name] !== undefined)
        .map((name) => `${name}="${escapeAttr(attrs[name])}"`)
        .join(' ');

      return `<path ${serialized}/>`;
    })
    .join('');
}

function main() {
  if (!fs.existsSync(PKG_DIR)) {
    console.error('✖ Falta @tabler/icons. Ejecuta: npm install');
    process.exit(1);
  }

  const version = JSON.parse(fs.readFileSync(path.join(PKG_DIR, 'package.json'), 'utf8')).version;
  const nodes = JSON.parse(fs.readFileSync(path.join(PKG_DIR, 'tabler-nodes-outline.json'), 'utf8'));
  const meta = JSON.parse(fs.readFileSync(path.join(PKG_DIR, 'icons.json'), 'utf8'));

  const icons = Object.keys(nodes)
    .sort()
    .map((name) => {
      const info = meta[name] || {};
      const tags = Array.isArray(info.tags) ? info.tags.map(String) : [];

      return {
        n: name,
        c: info.category || '',
        t: tags.join(' ').toLowerCase(),
        s: nodesToSvg(nodes[name]),
      };
    })
    .filter((icon) => icon.s !== '');

  const payload = JSON.stringify({ version, icons });

  fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
  fs.writeFileSync(OUT_FILE, payload, 'utf8');

  console.log(`✅ ${icons.length} íconos (Tabler ${version}) → public/json/tabler-icons.json`);
  console.log(`   ${(Buffer.byteLength(payload) / 1024 / 1024).toFixed(2)} MB`);
}

main();
