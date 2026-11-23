const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'motion-shell.js'),
    'utf8',
);

function runMotionShell({ reduced = false } = {}) {
    const listeners = new Map();
    const root = {
        classList: {
            add() {},
            remove() {},
            contains: () => false,
        },
    };
    const title = { style: {} };
    const content = {
        style: {},
        querySelector: () => title,
    };
    const topbar = { style: {} };
    let currentTime = 0;
    const window = {
        matchMedia: () => ({ matches: reduced }),
        getComputedStyle: () => ({ getPropertyValue: () => '560ms' }),
        performance: { now: () => currentTime },
        sessionStorage: {
            getItem: () => null,
            setItem() {},
            removeItem() {},
        },
    };
    const document = {
        documentElement: root,
        querySelector(selector) {
            if (selector === '[data-view-topbar]') {
                return topbar;
            }
            if (selector === '[data-view-content]') {
                return content;
            }
            return null;
        },
        addEventListener(name, callback) {
            listeners.set(name, callback);
        },
    };
    vm.runInNewContext(source, { document, window });

    return {
        dispatch(name, event) {
            listeners.get(name)?.(event);
        },
        setTime(value) {
            currentTime = value;
        },
    };
}

function transitionEvent() {
    return {
        prevented: false,
        preventDefault() {
            this.prevented = true;
        },
    };
}

test('HTMX transitions fall back when swaps overlap or reduced motion is enabled', () => {
    const shell = runMotionShell();
    const first = transitionEvent();
    shell.dispatch('htmx:beforeTransition', first);
    assert.equal(first.prevented, false);

    const overlapping = transitionEvent();
    shell.setTime(300);
    shell.dispatch('htmx:beforeTransition', overlapping);
    assert.equal(overlapping.prevented, true);

    const settled = transitionEvent();
    shell.setTime(660);
    shell.dispatch('htmx:beforeTransition', settled);
    assert.equal(settled.prevented, false);

    const reducedShell = runMotionShell({ reduced: true });
    const reducedTransition = transitionEvent();
    reducedShell.dispatch('htmx:beforeTransition', reducedTransition);
    assert.equal(reducedTransition.prevented, true);
});
