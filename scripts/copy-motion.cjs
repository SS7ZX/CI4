const fs = require('node:fs');
const path = require('node:path');

const source = path.resolve(__dirname, '..', 'resources', 'js', 'motion.js');
const destination = path.resolve(__dirname, '..', 'public', 'assets', 'js', 'motion.js');

fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.copyFileSync(source, destination);
