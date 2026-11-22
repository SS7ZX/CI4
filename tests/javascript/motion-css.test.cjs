const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const css = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'public', 'assets', 'css', 'app.css'),
    'utf8',
);

test('compiled stylesheet includes progressive page and builder transitions', () => {
    assert.match(css, /@view-transition\s*\{\s*navigation:\s*auto;/);
    assert.match(css, /\.motion-step-enter-active,[\s\S]*?transition:[\s\S]*?opacity[\s\S]*?transform/);
});

test('compiled reduced-motion rules suppress nonessential movement', () => {
    assert.match(
        css,
        /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?transition-duration:\s*0\.01ms !important/,
    );
    assert.match(
        css,
        /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?\.motion-card:hover[\s\S]*?transform:\s*none/,
    );
});
