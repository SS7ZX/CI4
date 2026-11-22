const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { TextDecoder, TextEncoder } = require('node:util');
const vm = require('node:vm');

const sourcePath = path.resolve(__dirname, '..', '..', 'resources', 'js', 'cv-ai-stream.js');
const source = fs.readFileSync(sourcePath, 'utf8');

function loadModule() {
    const module = { exports: {} };
    vm.runInNewContext(source, { module, globalThis, TextDecoder, TextEncoder });
    return module.exports;
}

function createHarness(streamChunks, fetchOverride = null) {
    const { initialize } = loadModule();
    const output = { textContent: '' };
    const status = { textContent: '' };
    const startButton = { disabled: false };
    const cancelButton = {
        disabled: true,
        addEventListener(event, callback) {
            assert.equal(event, 'click');
            this.onClick = callback;
        },
    };
    const submitted = [];
    const form = {
        action: '/dashboard/cv/ai/generate',
        addEventListener(event, callback) {
            assert.equal(event, 'submit');
            this.onSubmit = callback;
        },
    };
    const root = {
        dataset: { reviewUrl: '/dashboard/cv/ai/review' },
        querySelector(selector) {
            return ({
                '[data-cv-ai-stream-form]': form,
                '[data-cv-ai-partial]': output,
                '[data-cv-ai-status]': status,
                '[data-cv-ai-start]': startButton,
                '[data-cv-ai-cancel]': cancelButton,
            })[selector];
        },
    };
    const document = {
        createElement(tag) {
            if (tag === 'input') {
                return {};
            }
            return {
                children: [],
                appendChild(field) { this.children.push(field); },
                submit() { submitted.push(this); },
            };
        },
        body: { appendChild() {} },
    };
    class Field {
        constructor() {
            this.type = '';
            this.name = '';
            this.value = '';
        }
    }
    class FakeFormData {
        constructor() {
            this.values = new Map([
                ['csrf_test_name', 'csrf-token'],
                ['full_name', 'Dina Pratama'],
                ['target_role', 'Koordinator Proyek'],
                ['email', 'dina@example.test'],
                ['phone', '08123456789'],
                ['city', 'Bandung'],
                ['sanitized_story', 'Mengelola proyek dan laporan.'],
                ['ai_consent', '1'],
            ]);
        }
        get(name) { return this.values.get(name) ?? null; }
        entries() { return this.values.entries(); }
    }
    const fetchCalls = [];
    const fetch = async (url, options) => {
        fetchCalls.push({ url, options });
        if (fetchOverride !== null) {
            return fetchOverride(url, options);
        }
        const chunks = [...streamChunks];
        return {
            ok: true,
            body: {
                getReader() {
                    return {
                        async read() {
                            if (chunks.length === 0) {
                                return { done: true };
                            }
                            return { done: false, value: new TextEncoder().encode(chunks.shift()) };
                        },
                    };
                },
            },
        };
    };
    const timers = new Map();
    let timerId = 0;
    initialize(root, {
        document,
        fetch,
        FormData: FakeFormData,
        AbortController,
        setTimeout(callback) {
            const id = ++timerId;
            timers.set(id, callback);
            return id;
        },
        clearTimeout(id) { timers.delete(id); },
        timeoutMs: 1000,
        messages: {
            generating: 'Menyusun',
            failed: 'Gagal',
            disconnected: 'Terputus',
            cancelled: 'Dibatalkan',
            timeout: 'Waktu habis',
        },
    });

    return {
        startButton,
        cancelButton,
        output,
        status,
        submitted,
        form,
        fetchCalls,
        timers,
    };
}

test('parses SSE events split across chunks and multiline data', () => {
    const { createSseParser } = loadModule();
    const events = [];
    const parser = createSseParser((event) => events.push(event));
    parser.push('event: partial\ndata: {"text":"bar');
    parser.push('is"}\n\nevent: complete\ndata: {"draft":');
    parser.push('{} }\n\n');
    parser.finish();

    assert.deepEqual(JSON.parse(JSON.stringify(events)), [
        { event: 'partial', data: '{"text":"baris"}' },
        { event: 'complete', data: '{"draft":{} }' },
    ]);
});

test('parses fragmented NDJSON records and rejects malformed records', () => {
    const { createNdjsonParser } = loadModule();
    const records = [];
    const parser = createNdjsonParser((record) => records.push(record));
    parser.push('{"message":');
    parser.push('{"content":"teks"}}\r\n{"done":');
    parser.push('true}');
    parser.finish();
    assert.deepEqual(JSON.parse(JSON.stringify(records)), [{ message: { content: 'teks' } }, { done: true }]);

    assert.throws(() => {
        const invalid = createNdjsonParser(() => {});
        invalid.push('{bad}\n');
    }, (error) => error.name === 'SyntaxError');
});

test('keeps partial output text-only and posts the validated draft to review', async () => {
    const harness = createHarness([
        'event: partial\ndata: {"text":"<script>alert(1)</script>"}\n\n',
        'event: complete\ndata: {"draft":{"headline":"Koordinator","summary":"","experience":"","education":"","skills":""}}\n\n',
    ]);
    let prevented = false;
    await harness.form.onSubmit({ preventDefault() { prevented = true; } });

    assert.equal(prevented, true);
    assert.equal(harness.output.textContent, '<script>alert(1)</script>');
    assert.equal('innerHTML' in harness.output, false);
    assert.equal(harness.fetchCalls[0].url, '/dashboard/cv/ai/generate');
    assert.equal(harness.fetchCalls[0].url.includes('dina@example.test'), false);
    assert.equal(harness.fetchCalls[0].url.includes('Dina Pratama'), false);
    assert.equal(harness.fetchCalls[0].options.method, 'POST');
    assert.equal(harness.fetchCalls[0].options.headers.Accept, 'text/event-stream');
    assert.equal(harness.fetchCalls[0].options.body.values.get('email'), 'dina@example.test');
    assert.equal(harness.submitted.length, 1);
    assert.equal(harness.submitted[0].action, '/dashboard/cv/ai/review');
    const reviewFields = new Map(harness.submitted[0].children.map((field) => [field.name, field.value]));
    assert.equal(reviewFields.get('csrf_test_name'), 'csrf-token');
    assert.equal(reviewFields.get('sanitized_story'), 'Mengelola proyek dan laporan.');
    assert.equal(reviewFields.get('headline'), 'Koordinator');
    assert.equal(reviewFields.has('action'), false);
    assert.equal(harness.startButton.disabled, true);
    assert.equal(/localStorage|sessionStorage/.test(source), false);
});

test('does not submit a failed stream partial as a draft', async () => {
    const harness = createHarness([
        'event: partial\ndata: {"text":"hasil sementara"}\n\n',
        'event: error\ndata: {"message":"Respons tidak valid"}\n\n',
    ]);
    await harness.form.onSubmit({ preventDefault() {} });

    assert.equal(harness.output.textContent, 'hasil sementara');
    assert.equal(harness.status.textContent, 'Respons tidak valid');
    assert.equal(harness.submitted.length, 0);
    assert.equal(harness.startButton.disabled, false);
});

test('reports disconnect and cancellation without saving partial output', async () => {
    const disconnected = createHarness(['event: partial\ndata: {"text":"awal"}\n\n']);
    await disconnected.form.onSubmit({ preventDefault() {} });
    assert.equal(disconnected.status.textContent, 'Terputus');
    assert.equal(disconnected.submitted.length, 0);

    let activeSignal;
    const cancelled = createHarness([], (url, options) => new Promise((resolve, reject) => {
        activeSignal = options.signal;
        options.signal.addEventListener('abort', () => {
            const error = new Error('aborted');
            error.name = 'AbortError';
            reject(error);
        }, { once: true });
    }));
    const cancellationRun = cancelled.form.onSubmit({ preventDefault() {} });
    await Promise.resolve();
    assert.equal(cancelled.cancelButton.disabled, false);
    cancelled.cancelButton.onClick();
    await cancellationRun;
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(activeSignal.aborted, true);
    assert.equal(cancelled.status.textContent, 'Dibatalkan');
    assert.equal(cancelled.submitted.length, 0);

    let timeoutHarness;
    const timedOut = createHarness([], (url, options) => new Promise((resolve, reject) => {
        timeoutHarness = { options, reject };
        options.signal.addEventListener('abort', () => {
            const error = new Error('aborted');
            error.name = 'AbortError';
            reject(error);
        }, { once: true });
    }));
    const timeoutRun = timedOut.form.onSubmit({ preventDefault() {} });
    await Promise.resolve();
    const timeoutCallback = timedOut.timers.values().next().value;
    timeoutCallback();
    await timeoutRun;
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(timeoutHarness.options.signal.aborted, true);
    assert.equal(timedOut.status.textContent, 'Waktu habis');
    assert.equal(timedOut.submitted.length, 0);
});
