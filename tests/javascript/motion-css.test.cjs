const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const css = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'public', 'assets', 'css', 'app.css'),
    'utf8',
);
const motionSource = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'css', 'motion.css'),
    'utf8',
);

test('compiled stylesheet includes progressive page and builder transitions', () => {
    assert.match(css, /@view-transition\s*\{\s*navigation:\s*auto;/);
    assert.match(css, /\.motion-step-enter-active,[\s\S]*?transition:[\s\S]*?opacity[\s\S]*?transform/);
});

test('compiled stylesheet includes a bounded transition for user theme changes', () => {
    assert.match(css, /html\.theme-transitioning::view-transition-old\(root\)/);
    assert.match(css, /html\.theme-transitioning::view-transition-new\(root\)/);
    assert.match(css, /motion-theme-exit\s+var\(--duration-instant\)/);
    assert.match(css, /motion-theme-enter\s+var\(--duration-fast\)/);
});

test('compiled stylesheet exposes motion tokens, keyframes, and reusable utilities', () => {
    assert.match(css, /--dur-instant:\s*90ms/);
    assert.match(css, /--dur-fast:\s*180ms/);
    assert.match(css, /--dur-base:\s*320ms/);
    assert.match(css, /--dur-slow:\s*480ms/);
    assert.match(css, /--dur-page:\s*560ms/);
    assert.match(css, /--stagger:\s*45ms/);
    for (const keyframe of ['fade-rise', 'fade-in', 'reveal-mask', 'draw-line', 'soft-pulse']) {
        assert.match(css, new RegExp(`@keyframes ${keyframe}`));
    }
    assert.match(css, /\.reveal-stagger > \*/);
    assert.match(css, /\.hover-lift/);
    assert.match(css, /\.press/);
});

test('compiled stylesheet includes named transitions, the navigation line, and directional AI motion', () => {
    for (const transitionSelector of [
        '::view-transition-group(top-bar)',
        '::view-transition-old(page-title)',
        '::view-transition-new(page-content)',
        '::view-transition-new(cv-preview)',
    ]) {
        assert.ok(css.includes(transitionSelector));
    }
    assert.match(css, /\.motion-progress-line\.is-running/);
    assert.match(css, /\.motion-progress-fill\s*\{[^}]*transform:\s*scaleX/s);
    assert.match(css, /\.ai-wizard-page\.ai-wizard-entering\[data-motion-direction="forward"\]/);
    assert.match(css, /html\.ai-wizard-backward::view-transition-old\(page-content\)/);
    assert.match(css, /--duration-tooltip-delay:\s*300ms/);
    assert.match(css, /--duration-auth-intro:\s*900ms/);
});

test('motion transitions do not animate layout, color, or shadow properties', () => {
    assert.doesNotMatch(
        motionSource,
        /transition(?:-property)?\s*:[^;]*(?:color|background-color|border-color|outline-color|width|height|grid-template-rows|box-shadow|stroke-dashoffset)\s*(?:,|var\()/,
    );
});

test('card lift is limited to hover devices without reduced-motion preference', () => {
    assert.match(
        css,
        /@media\s*\(hover:\s*hover\)\s*and\s*\(prefers-reduced-motion:\s*no-preference\)[\s\S]*?\.motion-card:hover,[\s\S]*?\.theme-card:hover[\s\S]*?transform:\s*translateY\(-2px\)/,
    );
});

test('action icon movement supports hover and keyboard focus without affecting reduced motion', () => {
    assert.match(css, /\.motion-action-icon\s*\{[\s\S]*?transition:\s*transform/);
    assert.match(
        css,
        /@media\s*\(hover:\s*hover\)\s*and\s*\(prefers-reduced-motion:\s*no-preference\)[\s\S]*?\.motion-action:hover \.motion-action-icon,[\s\S]*?\.motion-action:focus-visible \.motion-action-icon[\s\S]*?transform:\s*translateX\(0\.125rem\)/,
    );
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
    assert.match(
        css,
        /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?\.theme-card:hover[\s\S]*?transform:\s*none/,
    );
    assert.match(
        css,
        /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?html\.motion-ready \.reveal-stagger > \*[\s\S]*?transform:\s*none/,
    );
});
