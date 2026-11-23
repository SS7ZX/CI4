(() => {
    const forms = [...document.querySelectorAll('[data-ai-wizard]')];
    if (forms.length === 0) {
        return;
    }

    const patterns = [
        ['email', /(?<![\w.+-])[\w.!#$%&'*+/=?^_`{|}~-]+@[\w-]+(?:\.[\w-]+)+(?![\w-])/giu],
        ['phone', /(?<!\w)(?:\+?62|0)(?:[\s().-]*\d){8,13}(?!\w)/gu],
        ['address', /\b(?:jalan|jl\.?|jln\.?)\s+[^,\r\n]+?(?=\s+\b(?:tinggal|berdomisili|mengelola|bekerja|memiliki|membuat|menyusun|mengembangkan)\b|[,;\r\n]|$)/giu],
    ];

    const escapePattern = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    for (const form of forms) {
        const source = form.querySelector('[data-ai-character-source]');
        const counter = form.querySelector('[data-ai-character-count]');
        const counterLive = form.querySelector('[data-ai-character-live]');
        const limit = Number(source?.dataset.characterLimit);
        const scanPanel = form.querySelector('[data-ai-scan-panel]');
        const preview = scanPanel?.querySelector('[data-ai-scan-preview]');
        const scanCount = scanPanel?.querySelector('[data-ai-scan-count]');
        const categories = scanPanel?.querySelector('[data-ai-scan-categories]');
        const scanLive = scanPanel?.querySelector('[data-ai-scan-live]');
        const maskButtons = [...(scanPanel?.querySelectorAll('[data-ai-mask-mode]') || [])];
        if (!source || !counter || !Number.isInteger(limit) || limit < 1 || !scanPanel || !preview || !scanCount || !categories || !scanLive) {
            continue;
        }

        const personalValues = [...form.querySelectorAll('input[type="hidden"]')]
            .filter((input) => ['full_name', 'email', 'phone', 'city', 'country'].includes(input.name.toLowerCase()))
            .map((input) => input.value.trim())
            .filter((value) => value.length >= 3);
        let lastAnnouncement = '';
        let previousLevel = 0;
        let scanInitialized = false;
        let maskMode = 'masked';
        let previousTotals = new Map();

        const tickNumber = (element, value, shouldTick) => {
            if (shouldTick) {
                element.classList.remove('is-ticking');
                void element.offsetWidth;
                element.classList.add('is-ticking');
            }
            element.textContent = String(value);
        };

        const updateCounter = () => {
            const length = Array.from(source.value).length;
            tickNumber(counter, length, counter.textContent !== String(length));
            counter.dataset.level = length >= limit ? 'limit' : (length >= limit * 0.8 ? 'warning' : 'normal');

            const threshold = length >= limit
                ? 3
                : (length >= limit * 0.9 ? 2 : (length >= limit * 0.8 ? 1 : 0));
            if (threshold !== previousLevel) {
                const message = threshold === 3
                    ? scanPanel.dataset.counterLimit
                    : (threshold === 0
                        ? scanPanel.dataset.counterNormal
                        : scanPanel.dataset.counterWarning.replace('{0}', String(Math.round((threshold === 2 ? 0.9 : 0.8) * 100))));
                counterLive.textContent = message;
            }
            previousLevel = threshold;
        };

        const findMatches = (text) => {
            const matches = [];
            for (const [type, pattern] of patterns) {
                for (const match of text.matchAll(pattern)) {
                    matches.push({ end: match.index + match[0].length, start: match.index, text: match[0], type });
                }
            }

            const personalPattern = personalValues.length > 0
                ? new RegExp(personalValues.map(escapePattern).sort((a, b) => b.length - a.length).join('|'), 'giu')
                : null;
            if (personalPattern) {
                for (const match of text.matchAll(personalPattern)) {
                    matches.push({ end: match.index + match[0].length, start: match.index, text: match[0], type: 'identity' });
                }
            }

            matches.sort((first, second) => first.start - second.start || second.text.length - first.text.length);
            const accepted = [];
            for (const match of matches) {
                if (accepted.some((current) => match.start < current.end && match.end > current.start)) {
                    continue;
                }
                accepted.push(match);
            }
            return accepted;
        };

        const updateScan = () => {
            const text = source.value;
            const matches = findMatches(text);
            const fragment = document.createDocumentFragment();
            let cursor = 0;
            const totals = new Map();

            matches.forEach((match, index) => {
                fragment.appendChild(document.createTextNode(text.slice(cursor, match.start)));
                const token = document.createElement('mark');
                token.className = 'ai-pii-token';
                token.style.setProperty('--i', String(index));
                const mask = document.createElement('span');
                mask.className = 'ai-mask-token';
                mask.dataset.masked = String(maskMode === 'masked');
                const original = document.createElement('span');
                original.className = 'ai-mask-original';
                original.textContent = match.text;
                original.setAttribute('aria-hidden', String(maskMode === 'masked'));
                const pill = document.createElement('span');
                pill.className = 'ai-mask-pill';
                const maskLabel = scanPanel.dataset[`mask${match.type[0].toUpperCase()}${match.type.slice(1)}`] || scanPanel.dataset.maskIdentity;
                pill.textContent = maskLabel;
                pill.setAttribute('aria-hidden', String(maskMode !== 'masked'));
                mask.append(original, pill);
                token.appendChild(mask);
                fragment.appendChild(token);
                totals.set(match.type, (totals.get(match.type) ?? 0) + 1);
                cursor = match.end;
            });
            fragment.appendChild(document.createTextNode(text.slice(cursor)));
            preview.replaceChildren(fragment);

            const count = matches.length;
            tickNumber(scanCount, count, scanCount.textContent !== String(count));
            categories.replaceChildren();
            for (const [type, total] of totals) {
                const item = document.createElement('li');
                item.className = 'inline-flex items-center gap-2 border border-line px-2 py-1 text-xs text-muted';
                const label = document.createElement('span');
                label.textContent = scanPanel.dataset[`scan${type[0].toUpperCase()}${type.slice(1)}`] || type;
                const badge = document.createElement('span');
                badge.className = 'motion-count-up ai-pii-badge';
                if (previousTotals.get(type) !== total) {
                    badge.classList.add('is-ticking');
                }
                badge.textContent = String(total);
                item.append(label, badge);
                categories.appendChild(item);
            }
            previousTotals = new Map(totals);
            maskButtons.forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.aiMaskMode === maskMode));
            });

            const announcement = count === 0
                ? scanPanel.dataset.scanNone
                : scanPanel.dataset.scanCount.replace('{0}', String(count));
            if (announcement !== lastAnnouncement) {
                scanLive.textContent = announcement;
                lastAnnouncement = announcement;
            }
            if (!scanInitialized) {
                preview.classList.add('is-scanning');
                preview.addEventListener('animationend', () => preview.classList.remove('is-scanning'), { once: true });
                scanInitialized = true;
            }
        };

        source.addEventListener('input', () => {
            updateCounter();
            updateScan();
        });
        maskButtons.forEach((button) => {
            button.addEventListener('click', () => {
                maskMode = button.dataset.aiMaskMode === 'original' ? 'original' : 'masked';
                updateScan();
            });
        });
        updateCounter();
        updateScan();
    }
})();
