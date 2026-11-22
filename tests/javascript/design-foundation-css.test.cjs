const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const css = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'public', 'assets', 'css', 'app.css'),
    'utf8',
);

function luminance(hex) {
    const channels = hex.match(/[\da-f]{2}/gi).map((channel) => Number.parseInt(channel, 16) / 255);
    const linear = channels.map((channel) => (
        channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
    ));

    return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
}

test('compiled dark theme overrides status palette tokens used by alert utilities', () => {
    const darkTheme = css.match(/html\[data-theme="dark"\]\s*\{([^}]*)\}/);
    assert.ok(darkTheme, 'compiled stylesheet must include the dark theme token block');

    const paletteShades = {
        red: ['50', '700', '800', '900'],
        green: ['50', '700', '800', '900', '950'],
        amber: ['50', '700', '900', '950'],
    };
    const tokens = {};

    for (const [palette, shades] of Object.entries(paletteShades)) {
        for (const shade of shades) {
            const token = `--color-${palette}-${shade}`;
            const value = darkTheme[1].match(new RegExp(`${token}:\\s*(#[\\da-f]{6});`, 'i'));
            assert.ok(value, `dark theme must override ${token}`);
            tokens[token] = value[1];
        }
    }

    for (const [className, property, token] of [
        ['bg-red-50', 'background-color', '--color-red-50'],
        ['text-red-900', 'color', '--color-red-900'],
        ['bg-green-50', 'background-color', '--color-green-50'],
        ['text-green-950', 'color', '--color-green-950'],
        ['bg-amber-50', 'background-color', '--color-amber-50'],
        ['text-amber-950', 'color', '--color-amber-950'],
    ]) {
        assert.match(
            css,
            new RegExp(`\\.${className}\\s*\\{[^}]*${property}:\\s*var\\(${token}\\)`, 's'),
            `compiled .${className} utility must use ${token}`,
        );
    }

    for (const [palette, textShades] of Object.entries({
        red: ['800', '900'],
        green: ['800', '900', '950'],
        amber: ['900', '950'],
    })) {
        const background = luminance(tokens[`--color-${palette}-50`]);
        for (const shade of textShades) {
            const foreground = luminance(tokens[`--color-${palette}-${shade}`]);
            const contrast = (Math.max(background, foreground) + 0.05) /
                (Math.min(background, foreground) + 0.05);
            assert.ok(
                contrast >= 4.5,
                `${palette}-${shade} text must meet WCAG AA contrast on dark ${palette}-50`,
            );
        }
    }
});

test('homepage hero surfaces follow the active color theme', () => {
    assert.match(css, /\.home-hero\s*\{[^}]*background:\s*var\(--color-paper\)/s);
    assert.match(css, /\.home-hero__art\s*\{[^}]*background-color:\s*var\(--color-canvas\)/s);
    assert.match(css, /\.home-hero__art::before\s*\{[^}]*background:\s*var\(--color-paper\)/s);
});

test('dark mode primary buttons retain readable white text', () => {
    const darkTheme = css.match(/html\[data-theme="dark"\]\s*\{([^}]*)\}/);
    assert.ok(darkTheme, 'compiled stylesheet must include dark theme tokens');

    for (const token of ['--color-accent-button', '--color-accent-button-hover']) {
        const value = darkTheme[1].match(new RegExp(`${token}:\\s*(#[\\da-f]{6});`, 'i'));
        assert.ok(value, `dark theme must define ${token}`);
        const contrast = (luminance('#ffffff') + 0.05) / (luminance(value[1]) + 0.05);
        assert.ok(contrast >= 4.5, `${token} must meet WCAG AA with white button text`);
    }

    assert.match(
        css,
        /html\[data-theme="dark"\]\s+\.bg-accent-700\.text-white,[\s\S]*?background-color:\s*var\(--color-accent-button\)/,
    );
    assert.match(
        css,
        /html\[data-theme="dark"\]\s+\.bg-accent-700\.text-white:hover,[\s\S]*?background-color:\s*var\(--color-accent-button-hover\)/,
    );
});
