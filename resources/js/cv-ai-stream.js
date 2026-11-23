(function () {
    'use strict';

    function createSseParser(onEvent) {
        let buffer = '';

        function dispatch(frame) {
            let eventName = 'message';
            const data = [];
            frame.split(/\r?\n/).forEach((line) => {
                if (line.startsWith('event:')) {
                    eventName = line.slice(6).trim();
                } else if (line.startsWith('data:')) {
                    data.push(line.slice(5).replace(/^ /, ''));
                }
            });
            if (data.length > 0) {
                onEvent({ event: eventName, data: data.join('\n') });
            }
        }

        return {
            push(chunk) {
                buffer += chunk;
                let boundary;
                while ((boundary = buffer.search(/\r?\n\r?\n/)) !== -1) {
                    const frame = buffer.slice(0, boundary);
                    const separator = buffer.slice(boundary).match(/^\r?\n\r?\n/)[0];
                    buffer = buffer.slice(boundary + separator.length);
                    dispatch(frame);
                }
            },
            finish() {
                if (buffer.trim() !== '') {
                    dispatch(buffer);
                    buffer = '';
                }
            },
        };
    }

    function createNdjsonParser(onRecord) {
        let buffer = '';

        function dispatch(line) {
            if (line.trim() !== '') {
                onRecord(JSON.parse(line));
            }
        }

        return {
            push(chunk) {
                buffer += chunk;
                let newline;
                while ((newline = buffer.indexOf('\n')) !== -1) {
                    dispatch(buffer.slice(0, newline).replace(/\r$/, ''));
                    buffer = buffer.slice(newline + 1);
                }
            },
            finish() {
                dispatch(buffer.replace(/\r$/, ''));
                buffer = '';
            },
        };
    }

    function initialize(root, dependencies) {
        const document = dependencies.document;
        const fetch = dependencies.fetch;
        const AbortController = dependencies.AbortController;
        const FormData = dependencies.FormData || globalThis.FormData;
        const setTimeout = dependencies.setTimeout;
        const clearTimeout = dependencies.clearTimeout;
        const form = root.querySelector('[data-cv-ai-stream-form]');
        const output = root.querySelector('[data-cv-ai-partial]');
        const status = root.querySelector('[data-cv-ai-status]');
        const startButton = root.querySelector('[data-cv-ai-start]');
        const cancelButton = root.querySelector('[data-cv-ai-cancel]');
        let controller = null;
        let timeoutId = null;
        let completed = false;
        let cancelled = false;
        let completedDraft = null;

        function setStatus(message) {
            status.textContent = message;
        }

        function appendText(text) {
            root.classList.add('has-output');
            output.textContent += text;
        }

        function submitReview(draft, csrfToken) {
            const csrfTokenName = root.dataset.csrfTokenName;
            if (!csrfTokenName) {
                throw new Error(dependencies.messages.csrf);
            }

            const reviewForm = document.createElement('form');
            reviewForm.method = 'post';
            reviewForm.action = root.dataset.reviewUrl;
            const sourceData = new FormData(form);
            const fields = new Map(sourceData.entries());
            fields.set(csrfTokenName, csrfToken);
            Object.entries({
                full_name: sourceData.get('full_name'),
                email: sourceData.get('email'),
                phone: sourceData.get('phone'),
                city: sourceData.get('city'),
                sanitized_story: sourceData.get('sanitized_story'),
                ...draft,
            }).forEach(([name, value]) => fields.set(name, value));
            fields.forEach((value, name) => {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = typeof value === 'string' ? value : '';
                reviewForm.appendChild(field);
            });
            document.body.appendChild(reviewForm);
            reviewForm.submit();
        }

        async function stream() {
            if (controller !== null) {
                return;
            }
            controller = new AbortController();
            cancelled = false;
            completed = false;
            completedDraft = null;
            root.classList.remove('has-output');
            output.textContent = '';
            startButton.disabled = true;
            startButton.setAttribute('aria-busy', 'true');
            cancelButton.disabled = false;
            setStatus(dependencies.messages.generating);
            timeoutId = setTimeout(() => {
                cancelled = false;
                controller.abort();
            }, dependencies.timeoutMs);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { Accept: 'text/event-stream' },
                    signal: controller.signal,
                });
                if (!response.ok || !response.body) {
                    throw new Error(dependencies.messages.failed);
                }
                const csrfHeaderName = root.dataset.csrfHeaderName;
                const csrfToken = csrfHeaderName && typeof response.headers?.get === 'function'
                    ? response.headers.get(csrfHeaderName)
                    : null;
                if (!csrfToken) {
                    throw new Error(dependencies.messages.csrf);
                }
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                const parser = createSseParser((message) => {
                    let payload;
                    try {
                        payload = JSON.parse(message.data);
                    } catch {
                        throw new Error(dependencies.messages.failed);
                    }
                    if (message.event === 'partial' && typeof payload.text === 'string') {
                        appendText(payload.text);
                    } else if (message.event === 'error') {
                        throw new Error(typeof payload.message === 'string' ? payload.message : dependencies.messages.failed);
                    } else if (message.event === 'complete' && payload.draft && typeof payload.draft === 'object') {
                        completed = true;
                        completedDraft = payload.draft;
                    }
                });

                while (true) {
                    const result = await reader.read();
                    if (result.done) {
                        parser.push(decoder.decode());
                        parser.finish();
                        break;
                    }
                    parser.push(decoder.decode(result.value, { stream: true }));
                }
                if (!completed || completedDraft === null) {
                    throw new Error(dependencies.messages.disconnected);
                }
                submitReview(completedDraft, csrfToken);
            } catch (error) {
                if (cancelled) {
                    setStatus(dependencies.messages.cancelled);
                } else if (error && error.name === 'AbortError') {
                    setStatus(dependencies.messages.timeout);
                } else {
                    setStatus(error instanceof Error ? error.message : dependencies.messages.failed);
                }
                completed = false;
                completedDraft = null;
            } finally {
                clearTimeout(timeoutId);
                timeoutId = null;
                controller = null;
                cancelButton.disabled = true;
                startButton.disabled = completed;
                startButton.setAttribute('aria-busy', 'false');
            }
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            return stream();
        });
        cancelButton.addEventListener('click', () => {
            if (controller !== null) {
                cancelled = true;
                controller.abort();
            }
        });

        return { stream };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { createSseParser, createNdjsonParser, initialize };
    }

    if (typeof document !== 'undefined') {
        document.querySelectorAll('[data-cv-ai-stream-root]').forEach((root) => {
            const messages = root.dataset;
            initialize(root, {
                document,
                fetch: window.fetch.bind(window),
                AbortController: window.AbortController,
                setTimeout: window.setTimeout.bind(window),
                clearTimeout: window.clearTimeout.bind(window),
                timeoutMs: 120000,
                messages: {
                    generating: messages.generating,
                    failed: messages.failed,
                    csrf: messages.csrfError,
                    disconnected: messages.disconnected,
                    cancelled: messages.cancelled,
                    timeout: messages.timeout,
                },
            });
        });
    }
})();
