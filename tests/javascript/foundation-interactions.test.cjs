const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const themeSource = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'theme.js'),
    'utf8',
);
const foundationSource = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'foundation.js'),
    'utf8',
);

class MockElement {
    constructor(textContent = '') {
        this.id = '';
        this.textContent = textContent;
        this.dataset = {};
        this.attributes = new Map();
        this.listeners = new Map();
        this.hidden = false;
        this.value = '';
        this.focused = false;
        this.clickCount = 0;
    }

    addEventListener(name, listener) {
        const listeners = this.listeners.get(name) || [];
        listeners.push(listener);
        this.listeners.set(name, listeners);
    }

    dispatch(name, event = {}) {
        event.target ??= this;
        event.preventDefault ??= () => {
            event.defaultPrevented = true;
        };
        for (const listener of this.listeners.get(name) || []) {
            listener(event);
        }
        return event;
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    hasAttribute(name) {
        return this.attributes.has(name);
    }

    click() {
        this.clickCount += 1;
        this.dispatch('click');
    }

    focus() {
        this.focused = true;
    }

    scrollIntoView() {
        this.scrolledIntoView = true;
    }
}

class MockInput extends MockElement {}

function createTheme({ storedTheme = null, systemDark = false } = {}) {
    const root = { dataset: {}, style: {} };
    const buttons = [];
    const documentListeners = new Map();
    const writes = [];
    const document = {
        documentElement: root,
        readyState: 'loading',
        querySelectorAll: (selector) => selector === '[data-theme-toggle]' ? buttons : [],
        addEventListener: (name, listener) => documentListeners.set(name, listener),
    };
    const window = {
        matchMedia: (query) => {
            assert.equal(query, '(prefers-color-scheme: dark)');
            return { matches: systemDark };
        },
    };
    const localStorage = {
        getItem: (key) => {
            assert.equal(key, 'cv-maker-theme');
            return storedTheme;
        },
        setItem: (key, value) => writes.push([key, value]),
    };

    vm.runInNewContext(themeSource, { document, localStorage, window });

    return {
        addToggleButton() {
            const button = new MockElement();
            button.dataset.labelLight = 'Aktifkan tema terang';
            button.dataset.labelDark = 'Aktifkan tema gelap';
            buttons.push(button);
            return button;
        },
        dispatchReady() {
            documentListeners.get('DOMContentLoaded')();
        },
        get root() {
            return root;
        },
        get writes() {
            return writes;
        },
        window,
    };
}

function createPalette() {
    const input = new MockInput();
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    const empty = new MockElement();
    empty.setAttribute('role', 'status');
    empty.setAttribute('aria-live', 'polite');
    const results = [
        new MockElement('Buka dashboard'),
        new MockElement('Dashboard CV'),
        new MockElement('Lihat tema CV'),
    ];
    results.forEach((result, index) => {
        result.id = `command-result-${index + 1}`;
        result.setAttribute('role', 'option');
        result.setAttribute('aria-selected', 'false');
    });
    const openButton = new MockElement();
    const themeButton = new MockElement();
    themeButton.dataset.themeToggle = '';
    const palette = new MockElement();
    palette.querySelector = (selector) => {
        if (selector === '[data-command-input]') return input;
        if (selector === '[data-command-empty]') return empty;
        return null;
    };
    palette.querySelectorAll = (selector) => (
        selector === '[data-command-result]' ? results : []
    );
    const documentListeners = new Map();
    const document = {
        querySelector: (selector) => (
            selector === '[data-command-palette]' ? palette : null
        ),
        querySelectorAll: (selector) => {
            if (selector === '[data-command-open]') return [openButton];
            if (selector === '[data-theme-toggle]') return [themeButton];
            return [];
        },
        addEventListener: (name, listener) => documentListeners.set(name, listener),
        getElementById: () => null,
    };
    const window = { cvTheme: { toggleCount: 0, toggle() { this.toggleCount += 1; } } };
    const context = {
        document,
        window,
        HTMLInputElement: MockInput,
        HTMLTextAreaElement: class MockTextArea {},
        requestAnimationFrame: (callback) => callback(),
    };

    vm.runInNewContext(foundationSource, context);

    return {
        documentListeners,
        empty,
        input,
        openButton,
        palette,
        results,
        themeButton,
        window,
    };
}

test('stored theme wins over system preference and updates persisted toggle aria state', () => {
    const theme = createTheme({ storedTheme: 'dark', systemDark: false });
    const button = theme.addToggleButton();
    theme.dispatchReady();

    assert.equal(theme.window.cvTheme.get(), 'dark');
    assert.equal(theme.root.style.colorScheme, 'dark');
    assert.equal(button.getAttribute('aria-pressed'), 'true');
    assert.equal(button.getAttribute('aria-label'), 'Aktifkan tema terang');

    theme.window.cvTheme.toggle();

    assert.equal(theme.window.cvTheme.get(), 'light');
    assert.equal(theme.root.style.colorScheme, 'light');
    assert.equal(button.getAttribute('aria-pressed'), 'false');
    assert.equal(button.getAttribute('aria-label'), 'Aktifkan tema gelap');
    assert.deepEqual(theme.writes, [['cv-maker-theme', 'light']]);
});

test('system preference supplies the theme when there is no stored choice', () => {
    const darkTheme = createTheme({ systemDark: true });
    assert.equal(darkTheme.window.cvTheme.get(), 'dark');
    assert.deepEqual(darkTheme.writes, []);

    const lightTheme = createTheme();
    assert.equal(lightTheme.window.cvTheme.get(), 'light');
});

test('command shortcut filters results, moves selection, and works without dialog methods', () => {
    const ui = createPalette();
    const shortcut = ui.documentListeners.get('keydown');
    const shortcutEvent = {
        key: 'k',
        ctrlKey: true,
        metaKey: false,
        target: new MockElement(),
        preventDefault() {
            this.defaultPrevented = true;
        },
    };
    shortcut(shortcutEvent);

    assert.equal(shortcutEvent.defaultPrevented, true);
    assert.equal(ui.palette.hasAttribute('open'), true);
    assert.equal(ui.input.focused, true);
    assert.equal(ui.input.getAttribute('aria-expanded'), 'true');

    ui.input.value = 'dashboard';
    ui.input.dispatch('input');
    assert.deepEqual(ui.results.map((item) => item.hidden), [false, false, true]);
    assert.equal(ui.results[0].getAttribute('aria-selected'), 'true');
    assert.equal(ui.input.getAttribute('aria-activedescendant'), ui.results[0].id);
    assert.equal(ui.empty.hidden, true);

    ui.input.dispatch('keydown', {
        key: 'ArrowDown',
        preventDefault() {
            this.defaultPrevented = true;
        },
    });
    assert.equal(ui.results[1].getAttribute('aria-selected'), 'true');
    assert.equal(ui.results[0].getAttribute('aria-selected'), 'false');
    assert.equal(ui.input.getAttribute('aria-activedescendant'), ui.results[1].id);
    assert.equal(ui.results[1].scrolledIntoView, true);

    ui.input.dispatch('keydown', {
        key: 'Enter',
        preventDefault() {
            this.defaultPrevented = true;
        },
    });
    assert.equal(ui.results[1].clickCount, 1);
    assert.equal(ui.palette.hasAttribute('open'), false);
    assert.equal(ui.input.getAttribute('aria-expanded'), 'false');
    assert.equal(ui.input.getAttribute('aria-activedescendant'), null);
});

test('command palette reports empty searches and binds theme toggle without dialog methods', () => {
    const ui = createPalette();
    ui.openButton.click();
    assert.equal(ui.palette.hasAttribute('open'), true);

    ui.input.value = 'tidak ditemukan';
    ui.input.dispatch('input');
    assert.equal(ui.empty.hidden, false);
    assert.equal(ui.empty.getAttribute('role'), 'status');
    assert.equal(ui.empty.getAttribute('aria-live'), 'polite');
    assert.equal(ui.input.getAttribute('aria-activedescendant'), null);
    assert.ok(ui.results.every((item) => item.hidden));

    ui.themeButton.click();
    assert.equal(ui.window.cvTheme.toggleCount, 1);
});
