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

function runMotion({ reduced = false, observerAvailable = true, matchMediaAvailable = true, count = 2 } = {}) {
    const root = { classList: createClassList() };
    const elements = Array.from({ length: count }, () => ({ classList: createClassList() }));
    let observerInstance;
    let preferenceListener;

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
    if (observerAvailable) {
        window.IntersectionObserver = MockIntersectionObserver;
    }

    const context = {
        document: {
            documentElement: root,
            querySelectorAll: () => elements,
        },
        window,
    };
    if (observerAvailable) {
        context.IntersectionObserver = MockIntersectionObserver;
    }

    vm.runInNewContext(source, context);

    return {
        elements,
        get observer() {
            return observerInstance;
        },
        get preferenceListener() {
            return preferenceListener;
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
