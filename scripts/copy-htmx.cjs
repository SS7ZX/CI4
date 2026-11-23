const fs = require('node:fs');
const path = require('node:path');

const source = path.resolve(__dirname, '..', 'node_modules', 'htmx.org', 'dist', 'htmx.min.js');
const destination = path.resolve(__dirname, '..', 'public', 'assets', 'js', 'htmx.min.js');

fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.copyFileSync(source, destination);
