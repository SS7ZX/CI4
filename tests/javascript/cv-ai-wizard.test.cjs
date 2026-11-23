const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'cv-ai-wizard.js'),
    'utf8',
);

class FakeElement {
    constructor(dataset = {}) {
        this.dataset = dataset;
        this.children = [];
        this.attributes = new Map();
        this.listeners = new Map();
        this.classNames = new Set();
        this.classList = {
            add: (value) => this.classNames.add(value),
            remove: (value) => this.classNames.delete(value),
            contains: (value) => this.classNames.has(value),
        };
        this.style = { setProperty() {} };
        this.textContent = '';
        this.value = '';
        this.offsetWidth = 0;
    }

    addEventListener(name, callback) {
        this.listeners.set(name, callback);
    }

    append(...children) {
        children.forEach((child) => this.appendChild(child));
    }

    appendChild(child) {
        if (child.fragment) {
            this.children.push(...child.children);
        } else {
            this.children.push(child);
        }
        return child;
    }

    replaceChildren(...children) {
        this.children = [];
        this.append(...children);
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }
}

function createHarness(initialStory) {
    const sourceField = new FakeElement({ characterLimit: '8000' });
    sourceField.value = initialStory;
    const counter = new FakeElement();
    const counterLive = new FakeElement();
    const preview = new FakeElement();
    const scanCount = new FakeElement();
    const categories = new FakeElement();
    const scanLive = new FakeElement();
    const maskButtons = ['masked', 'original'].map((mode) => {
        const button = new FakeElement({ aiMaskMode: mode });
        button.textContent = mode;
        return button;
    });
    const panel = new FakeElement({
        scanEmail: 'Email',
        scanPhone: 'Telepon',
        scanAddress: 'Alamat',
        scanIdentity: 'Identitas',
        maskEmail: '[EMAIL]',
        maskPhone: '[NOMOR TELEPON]',
        maskAddress: '[ALAMAT]',
        maskIdentity: '[IDENTITAS]',
        scanNone: 'Tidak ada data terdeteksi.',
        scanCount: '{0} pola terdeteksi.',
        counterLimit: 'Batas karakter tercapai.',
        counterWarning: 'Mendekati batas {0}%.',
        counterNormal: 'Jumlah karakter kembali di bawah ambang peringatan.',
    });
    const panelElements = new Map([
        ['[data-ai-scan-preview]', preview],
        ['[data-ai-scan-count]', scanCount],
        ['[data-ai-scan-categories]', categories],
        ['[data-ai-scan-live]', scanLive],
    ]);
    panel.querySelector = (selector) => panelElements.get(selector) ?? null;
    panel.querySelectorAll = () => maskButtons;
    const hiddenFields = [
        Object.assign(new FakeElement(), { name: 'full_name', value: 'Dina Pratama' }),
        Object.assign(new FakeElement(), { name: 'email', value: 'dina@example.test' }),
    ];
    const form = new FakeElement();
    form.querySelector = (selector) => new Map([
        ['[data-ai-character-source]', sourceField],
        ['[data-ai-character-count]', counter],
        ['[data-ai-character-live]', counterLive],
        ['[data-ai-scan-panel]', panel],
    ]).get(selector) ?? null;
    form.querySelectorAll = () => hiddenFields;

    const document = {
        querySelectorAll: () => [form],
        createDocumentFragment() {
            const fragment = new FakeElement();
            fragment.fragment = true;
            return fragment;
        },
        createElement: () => new FakeElement(),
        createTextNode: (text) => ({ textContent: text }),
    };

    vm.runInNewContext(source, { document });

    return {
        counter,
        counterLive,
        form,
        maskButtons,
        preview,
        sourceField,
    };
}

test('starts with detected personal data masked and allows a width-preserving preview toggle', () => {
    const originalStory = 'Email dina@example.test dan nomor 08123456789.';
    const harness = createHarness(originalStory);
    const token = harness.preview.children.find((child) => child instanceof FakeElement);
    const mask = token.children[0];
    const original = mask.children[0];
    const pill = mask.children[1];

    assert.equal(harness.maskButtons[0].getAttribute('aria-pressed'), 'true');
    assert.equal(harness.maskButtons[1].getAttribute('aria-pressed'), 'false');
    assert.equal(mask.dataset.masked, 'true');
    assert.equal(original.textContent, 'dina@example.test');
    assert.equal(original.getAttribute('aria-hidden'), 'true');
    assert.equal(pill.textContent, '[EMAIL]');
    assert.equal(pill.getAttribute('aria-hidden'), 'false');

    harness.maskButtons[1].listeners.get('click')();

    const unmaskedToken = harness.preview.children.find((child) => child instanceof FakeElement);
    const unmasked = unmaskedToken.children[0];
    assert.equal(unmasked.dataset.masked, 'false');
    assert.equal(unmasked.children[0].getAttribute('aria-hidden'), 'false');
    assert.equal(unmasked.children[1].getAttribute('aria-hidden'), 'true');
    assert.equal(harness.maskButtons[1].getAttribute('aria-pressed'), 'true');
    assert.equal(harness.sourceField.value, originalStory);
});

test('counts Unicode characters and announces threshold changes', () => {
    const harness = createHarness('é');

    assert.equal(harness.counter.textContent, '1');
    harness.sourceField.value = 'é'.repeat(6400);
    harness.sourceField.listeners.get('input')();

    assert.equal(harness.counter.textContent, '6400');
    assert.equal(harness.counter.dataset.level, 'warning');
    assert.equal(harness.counterLive.textContent, 'Mendekati batas 80%.');
});
