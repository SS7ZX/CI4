const fs = require('node:fs');
const path = require('node:path');

const source = path.resolve(__dirname, '..', 'resources', 'js', 'cv-builder.js');
const destination = path.resolve(__dirname, '..', 'public', 'assets', 'js', 'cv-builder.js');

fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.copyFileSync(source, destination);
