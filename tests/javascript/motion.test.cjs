const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '..', '..', 'resources', 'js', 'motion.js'),
    'utf8',
);

function createClassList() {
    const values = new Set();
    return {
        add: (value) => values.add(value),
        contains: (value) => values.has(value),
        remove: (value) => values.delete(value),
    };
}

function runMotion({
    reduced = false,
    observerAvailable = true,
    matchMediaAvailable = true,
    count = 2,
    staggerCount = 0,
    counterTargets = [],
} = {}) {
    const root = { classList: createClassList() };
    const elements = Array.from({ length: count }, () => ({
        children: [],
        classList: createClassList(),
        style: { setProperty() {} },
    }));
    const staggerChildren = Array.from({ length: staggerCount }, () => {
        const values = new Map();
        return {
            style: {
                setProperty: (name, value) => values.set(name, value),
            },
            values,
        };
    });
    if (staggerCount > 0) {
        elements[0].classList.add('reveal-stagger');
        elements[0].children = staggerChildren;
    }
    const countElements = counterTargets.map((target) => ({
        classList: createClassList(),
        dataset: { countUp: String(target) },
        textContent: '0',
    }));
    let observerInstance;
    let preferenceListener;
    let frameId = 0;
    const frames = new Map();
    const listeners = new Map();

    class MockIntersectionObserver {
        constructor(callback, options) {
            this.callback = callback;
            this.options = options;
            this.observed = [];
            this.disconnected = false;
            observerInstance = this;
        }

        observe(element) {
            this.observed.push(element);
        }

        unobserve(element) {
            this.observed = this.observed.filter((item) => item !== element);
        }

        disconnect() {
            this.disconnected = true;
        }
    }

    const motionPreference = {
        matches: reduced,
        addEventListener: (eventName, listener) => {
            assert.equal(eventName, 'change');
            preferenceListener = listener;
        },
    };
    const window = {};
    if (matchMediaAvailable) {
        window.matchMedia = () => motionPreference;
    }
    window.getComputedStyle = () => ({
        getPropertyValue: () => '480ms',
    });
    window.requestAnimationFrame = (callback) => {
        frameId += 1;
        frames.set(frameId, callback);
        return frameId;
    };
    window.cancelAnimationFrame = (id) => frames.delete(id);
    if (observerAvailable) {
        window.IntersectionObserver = MockIntersectionObserver;
    }

    const document = {
        documentElement: root,
        querySelectorAll: (selector) => selector === '[data-count-up]' ? countElements : elements,
        addEventListener(eventName, listener) {
            listeners.set(eventName, listener);
        },
    };
    const context = {
        document,
        window,
    };
    if (observerAvailable) {
        context.IntersectionObserver = MockIntersectionObserver;
    }

    vm.runInNewContext(source, context);

    return {
        elements,
        countElements,
        staggerChildren,
        runAnimationFrame(time) {
            const pendingFrames = [...frames.values()];
            frames.clear();
            pendingFrames.forEach((callback) => callback(time));
        },
        get observer() {
            return observerInstance;
        },
        get preferenceListener() {
            return preferenceListener;
        },
        dispatch(eventName, event) {
            listeners.get(eventName)?.(event);
        },
        root,
    };
}

test('does not enable reveal motion when reduced motion is requested', () => {
    const result = runMotion({ reduced: true });

    assert.equal(result.root.classList.contains('motion-ready'), false);
    assert.equal(result.observer, undefined);
});

test('reveals elements only after they intersect the viewport', () => {
    const result = runMotion();

    assert.equal(result.root.classList.contains('motion-ready'), true);
    assert.equal(result.observer.observed.length, 2);
    assert.equal(result.observer.options.rootMargin, '0px 0px -24px 0px');
    assert.equal(result.observer.options.threshold, 0);

    result.observer.callback([
        { isIntersecting: true, target: result.elements[0] },
        { isIntersecting: false, target: result.elements[1] },
    ], result.observer);

    assert.equal(result.elements[0].classList.contains('motion-visible'), true);
    assert.equal(result.elements[1].classList.contains('motion-visible'), false);
    assert.deepEqual(result.observer.observed, [result.elements[1]]);
});

test('assigns stagger indexes to direct children and reveals the group once', () => {
    const result = runMotion({ count: 1, staggerCount: 3 });

    assert.deepEqual(
        result.staggerChildren.map((child) => child.values.get('--i')),
        ['0', '1', '2'],
    );
    result.observer.callback([
        { isIntersecting: true, target: result.elements[0] },
    ], result.observer);
    assert.equal(result.elements[0].classList.contains('motion-visible'), true);
});

test('observes reveals added by a later HTMX swap', () => {
    const result = runMotion({ count: 0 });
    const swappedReveal = {
        children: [],
        classList: createClassList(),
        style: { setProperty() {} },
    };
    const target = {
        querySelectorAll(selector) {
            return selector === '[data-count-up]' ? [] : [swappedReveal];
        },
    };

    result.dispatch('htmx:afterSettle', { detail: { target } });

    assert.ok(result.observer.observed.includes(swappedReveal));
    result.observer.callback([{ isIntersecting: true, target: swappedReveal }], result.observer);
    assert.equal(swappedReveal.classList.contains('motion-visible'), true);
});

test('counts a tabular figure up after it intersects', () => {
    const result = runMotion({ count: 0, counterTargets: [125] });

    result.observer.callback([
        { isIntersecting: true, target: result.countElements[0] },
    ], result.observer);
    result.runAnimationFrame(0);
    assert.equal(result.countElements[0].textContent, '0');
    result.runAnimationFrame(480);
    assert.equal(result.countElements[0].textContent, '125');
    assert.equal(result.countElements[0].classList.contains('motion-count-up'), true);
});

test('shows all content if reduced motion becomes active', () => {
    const result = runMotion();

    result.preferenceListener({ matches: true });

    assert.equal(result.observer.disconnected, true);
    assert.equal(result.root.classList.contains('motion-ready'), false);
    assert.ok(result.elements.every((element) => element.classList.contains('motion-visible')));
});

test('leaves content visible when IntersectionObserver is unavailable', () => {
    const result = runMotion({ observerAvailable: false });

    assert.equal(result.root.classList.contains('motion-ready'), false);
    assert.equal(result.observer, undefined);
});

test('leaves content visible when matchMedia is unavailable', () => {
    const result = runMotion({ matchMediaAvailable: false });

    assert.equal(result.root.classList.contains('motion-ready'), false);
    assert.equal(result.observer, undefined);
});

test('sets count-up values immediately when reduced motion is requested', () => {
    const result = runMotion({ reduced: true, count: 0, counterTargets: [125] });

    assert.equal(result.countElements[0].textContent, '125');
});
