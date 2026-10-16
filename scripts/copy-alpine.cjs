const fs = require('node:fs');
const path = require('node:path');

const destination = path.resolve(__dirname, '..', 'public', 'assets', 'js', 'alpine.min.js');
fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.copyFileSync(require.resolve('alpinejs/dist/cdn.min.js'), destination);
