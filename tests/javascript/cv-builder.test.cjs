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
    let fetchImpl = async () => ({
        status: 200,
        ok: true,
        json: async () => ({ status: 'saved' }),
    });
    const focusTarget = { focused: false, focus() { this.focused = true; } };
    const document = {
        activeElement: focusTarget,
        addEventListener: (eventName, listener) => {
            assert.equal(eventName, 'alpine:init');
            alpineInit = listener;
        },
        getElementById: (id) => {
            assert.equal(id, 'cv-builder-state');
            return {
                textContent: JSON.stringify({
                    cv: { title: 'CV', theme_key: 'ats-classic' },
                    profile: { full_name: '' },
                    links: [],
                }),
            };
        },
    };
    const Alpine = {
        data: (name, factory) => {
            assert.equal(name, 'cvBuilder');
            builderFactory = factory;
        },
    };

    vm.runInNewContext(source, {
        Alpine,
        document,
        fetch: (...args) => fetchImpl(...args),
        clearTimeout: () => {},
        setTimeout: () => 1,
        Date,
    });
    alpineInit();

    const builder = builderFactory({
        step: 1,
        saveUrl: '/dashboard/cv/cv-id/autosave/1',
        tokenName: 'csrf',
        token: 'token',
        themePreviewUrl: '/dashboard/cv/cv-id/preview/__theme__',
        themes: [
            { key: 'ats-classic', name: 'ATS Klasik', ats_friendly: true },
            { key: 'designer', name: 'Desainer', ats_friendly: false },
        ],
    });
    builder.$nextTick = (callback) => callback();
    builder.$refs = { previewClose: { focus() {} } };

    return {
        builder,
        focusTarget,
        setFetch(nextFetch) {
            fetchImpl = nextFetch;
        },
    };
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

test('failed validation and network saves preserve dirty state', async () => {
    const validation = createBuilder();
    validation.setFetch(async () => ({
        status: 422,
        ok: false,
        json: async () => ({
            status: 'error',
            errors: { 'profile.full_name': 'Nama wajib diisi.' },
        }),
    }));
    validation.builder.dirty = true;

    assert.equal(await validation.builder.save(), false);
    assert.equal(validation.builder.dirty, true);
    assert.equal(validation.builder.status, 'error');
    assert.equal(validation.builder.errors['profile.full_name'], 'Nama wajib diisi.');

    const network = createBuilder();
    network.setFetch(async () => {
        throw new Error('network unavailable');
    });
    network.builder.dirty = true;

    assert.equal(await network.builder.save(), false);
    assert.equal(network.builder.dirty, true);
    assert.equal(network.builder.status, 'error');
});
