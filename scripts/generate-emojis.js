const fs = require('fs');
const path = require('path');

const PKG_DIR = path.join(__dirname, '..', 'node_modules', 'emojibase-data');
const OUT_FILE = path.join(__dirname, '..', 'public', 'json', 'emojis.json');

const COMPONENT_GROUP = 2;

function main() {
  if (!fs.existsSync(PKG_DIR)) {
    console.error('✖ Falta emojibase-data. Ejecuta: npm install');
    process.exit(1);
  }

  const version = JSON.parse(fs.readFileSync(path.join(PKG_DIR, 'package.json'), 'utf8')).version;
  const data = JSON.parse(fs.readFileSync(path.join(PKG_DIR, 'es', 'compact.json'), 'utf8'));
  const messages = JSON.parse(fs.readFileSync(path.join(PKG_DIR, 'es', 'messages.json'), 'utf8'));

  const groups = messages.groups
    .filter((group) => group.order !== COMPONENT_GROUP)
    .map((group) => ({ g: group.order, n: group.message }));

  const emojis = data
    .filter((item) => item.group !== undefined && item.group !== COMPONENT_GROUP)
    .sort((a, b) => a.order - b.order)
    .map((item) => ({
      u: item.unicode,
      l: item.label,
      t: Array.isArray(item.tags) ? item.tags.join(' ').toLowerCase() : '',
      g: item.group,
    }));

  const payload = JSON.stringify({ version, groups, emojis });

  fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
  fs.writeFileSync(OUT_FILE, payload, 'utf8');

  console.log(`✅ ${emojis.length} emojis en ${groups.length} categorías (Emojibase ${version}) → public/json/emojis.json`);
  console.log(`   ${(Buffer.byteLength(payload) / 1024).toFixed(0)} KB`);
}

main();
