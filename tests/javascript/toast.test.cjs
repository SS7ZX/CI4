const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'toast.js'),
    'utf8',
);

class FakeElement {
    constructor() {
        this.children = [];
        this.listeners = new Map();
        this.attributes = new Map();
        this.classNames = new Set();
        this.classList = {
            add: (value) => this.classNames.add(value),
            contains: (value) => this.classNames.has(value),
        };
        this.style = { setProperty() {}, animationPlayState: '' };
        this.dataset = {};
        this.textContent = '';
        this.parentElement = null;
    }

    addEventListener(name, callback) {
        this.listeners.set(name, callback);
    }

    append(...children) {
        children.forEach((child) => this.appendChild(child));
    }

    appendChild(child) {
        child.parentElement = this;
        this.children.push(child);
        return child;
    }

    remove() {
        if (!this.parentElement) {
            return;
        }
        this.parentElement.children = this.parentElement.children.filter((child) => child !== this);
        this.parentElement = null;
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    get firstElementChild() {
        return this.children[0] ?? null;
    }
}

function createHarness() {
    const region = new FakeElement();
    region.dataset.dismissLabel = 'Tutup';
    const timers = new Map();
    let timerId = 0;
    let currentTime = 0;
    const window = {
        getComputedStyle: () => ({ getPropertyValue: () => '4s' }),
        setTimeout(callback, delay) {
            const id = ++timerId;
            timers.set(id, { callback, delay });
            return id;
        },
        clearTimeout(id) {
            timers.delete(id);
        },
    };
    const document = {
        querySelector: () => region,
        createElement: () => new FakeElement(),
    };
    const performance = { now: () => currentTime };
    vm.runInNewContext(source, { document, window, performance });

    return {
        region,
        timers,
        setTime(value) {
            currentTime = value;
        },
        toasts: window.cvToasts,
    };
}

test('toast timers pause on hover, swipe dismisses, and the region stays bounded', () => {
    const harness = createHarness();
    const toast = harness.toasts.show('Saved', 1000);
    const countdown = toast.children.at(-1);
    const hoverTimer = [...harness.timers.keys()][0];

    harness.setTime(300);
    toast.listeners.get('pointerenter')();
    assert.equal(harness.timers.has(hoverTimer), false);
    assert.equal(countdown.style.animationPlayState, 'paused');

    toast.listeners.get('pointerleave')();
    const resumedTimer = [...harness.timers.keys()][0];
    assert.equal(harness.timers.get(resumedTimer).delay, 700);
    assert.equal(countdown.style.animationPlayState, 'running');

    toast.listeners.get('pointerdown')({ pointerType: 'touch', clientX: 100 });
    toast.listeners.get('pointerup')({ clientX: 20 });
    assert.equal(toast.classList.contains('is-dismissing'), true);
    assert.equal(harness.timers.has(resumedTimer), false);

    for (let index = 0; index < 4; index += 1) {
        harness.toasts.show(`Toast ${index}`, 1000);
    }
    assert.equal(harness.region.children.length, 4);
});
