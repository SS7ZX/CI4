const fs = require('node:fs');
const path = require('node:path');

const source = path.resolve(__dirname, '..', 'resources', 'css', 'cv-preview-motion.css');
const destination = path.resolve(__dirname, '..', 'public', 'assets', 'css', 'cv-preview-motion.css');

fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.copyFileSync(source, destination);
