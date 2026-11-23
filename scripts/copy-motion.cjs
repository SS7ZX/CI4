const fs = require('node:fs');
const path = require('node:path');

const assets = path.resolve(__dirname, '..', 'public', 'assets', 'js');
fs.mkdirSync(assets, { recursive: true });

for (const name of ['motion.js', 'motion-shell.js', 'toast.js', 'cv-ai-wizard.js', 'ai-wizard-loader.js', 'section-reorder.js']) {
    const source = path.resolve(__dirname, '..', 'resources', 'js', name);
    fs.copyFileSync(source, path.resolve(assets, name));
}
