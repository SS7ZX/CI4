const fs = require('node:fs');
const path = require('node:path');

const assets = path.resolve(__dirname, '..', 'public', 'assets', 'js');
fs.mkdirSync(assets, { recursive: true });

for (const name of ['theme.js', 'foundation.js']) {
    const source = path.resolve(__dirname, '..', 'resources', 'js', name);
    const destination = path.resolve(assets, name);
    fs.copyFileSync(source, destination);
}
