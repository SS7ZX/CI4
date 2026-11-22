const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'cv-builder.js'),
    'utf8',
);

function createBuilder() {
    let alpineInit;
    let builderFactory;
    const focusTarget = { focused: false, focus() { this.focused = true; } };
    const document = {
        activeElement: focusTarget,
        addEventListener: (eventName, listener) => {
            assert.equal(eventName, 'alpine:init');
            alpineInit = listener;
        },
        getElementById: (id) => {
            assert.equal(id, 'cv-builder-state');
            return { textContent: JSON.stringify({ cv: { theme_key: 'ats-classic' } }) };
        },
    };
    const Alpine = {
        data: (name, factory) => {
            assert.equal(name, 'cvBuilder');
            builderFactory = factory;
        },
    };

    vm.runInNewContext(source, { Alpine, document });
    alpineInit();

    const builder = builderFactory({
        themePreviewUrl: '/dashboard/cv/cv-id/preview/__theme__',
        themes: [
            { key: 'ats-classic', name: 'ATS Klasik', ats_friendly: true },
            { key: 'designer', name: 'Desainer', ats_friendly: false },
        ],
    });
    builder.$nextTick = (callback) => callback();
    builder.$refs = { previewClose: { focus() {} } };

    return { builder, focusTarget };
}

test('previews the selected theme and applies it without losing focus restoration', () => {
    const { builder, focusTarget } = createBuilder();
    let saveScheduled = false;
    builder.scheduleSave = () => {
        saveScheduled = true;
    };

    builder.previewTheme('designer');

    assert.equal(builder.previewOpen, true);
    assert.equal(builder.previewFrameUrl, '/dashboard/cv/cv-id/preview/designer');
    assert.equal(builder.previewThemeName(), 'Desainer');
    assert.equal(builder.previewThemeIsAtsFriendly(), false);

    builder.applyPreviewTheme();

    assert.equal(builder.selectedTheme, 'designer');
    assert.equal(builder.previewOpen, false);
    assert.equal(focusTarget.focused, true);
    assert.equal(saveScheduled, true);
});
