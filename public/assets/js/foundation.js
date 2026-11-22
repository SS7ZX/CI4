(() => {
    const palette = document.querySelector('[data-command-palette]');
    if (!palette) {
        return;
    }

    const input = palette.querySelector('[data-command-input]');
    const empty = palette.querySelector('[data-command-empty]');
    const results = [...palette.querySelectorAll('[data-command-result]')];
    let selectedIndex = -1;

    const visibleResults = () => results.filter((item) => !item.hidden);

    const selectResult = (index) => {
        const visible = visibleResults();
        if (visible.length === 0) {
            selectedIndex = -1;
            input.removeAttribute('aria-activedescendant');
            return;
        }
        selectedIndex = (index + visible.length) % visible.length;
        const selected = visible[selectedIndex];
        for (const item of visible) {
            item.setAttribute('aria-selected', item === selected ? 'true' : 'false');
        }
        input.setAttribute('aria-activedescendant', selected.id);
        selected.scrollIntoView({ block: 'nearest' });
    };

    const filterResults = () => {
        const query = input.value.trim().toLocaleLowerCase('id');
        let count = 0;
        input.removeAttribute('aria-activedescendant');
        for (const item of results) {
            const matches = item.textContent.trim().toLocaleLowerCase('id').includes(query);
            item.hidden = !matches;
            item.setAttribute('aria-selected', 'false');
            if (matches) {
                count += 1;
            }
        }
        if (empty) {
            empty.hidden = count !== 0;
        }
        selectedIndex = -1;
        if (count > 0) {
            selectResult(0);
        }
    };

    const openPalette = () => {
        if (typeof palette.showModal === 'function') {
            palette.showModal();
        } else {
            palette.setAttribute('open', '');
        }
        input.setAttribute('aria-expanded', 'true');
        input.value = '';
        filterResults();
        requestAnimationFrame(() => input.focus());
    };

    const closePalette = () => {
        if (typeof palette.close === 'function') {
            palette.close();
        } else {
            palette.removeAttribute('open');
        }
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        for (const item of results) {
            item.setAttribute('aria-selected', 'false');
        }
    };

    for (const button of document.querySelectorAll('[data-command-open]')) {
        button.addEventListener('click', openPalette);
    }

    document.addEventListener('keydown', (event) => {
        const isShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
        const target = event.target;
        const isTyping = target instanceof HTMLInputElement ||
            target instanceof HTMLTextAreaElement ||
            target?.isContentEditable;

        if (isShortcut || (!isTyping && event.key === '/')) {
            event.preventDefault();
            openPalette();
        }
    });

    palette.addEventListener('cancel', (event) => {
        event.preventDefault();
        closePalette();
    });

    palette.addEventListener('click', (event) => {
        if (event.target === palette) {
            closePalette();
        }
    });

    input.addEventListener('input', filterResults);
    input.addEventListener('keydown', (event) => {
        const visible = visibleResults();
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            selectResult(selectedIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            selectResult(selectedIndex - 1);
        } else if (event.key === 'Enter' && visible[selectedIndex]) {
            event.preventDefault();
            visible[selectedIndex].click();
        }
    });

    for (const item of results) {
        item.addEventListener('click', () => {
            if (item.dataset.commandTheme !== undefined) {
                window.cvTheme?.toggle();
                closePalette();
                return;
            }
            if (item.dataset.commandForm) {
                const form = document.getElementById(item.dataset.commandForm);
                closePalette();
                form?.requestSubmit();
            } else {
                closePalette();
            }
        });
    }

    for (const button of document.querySelectorAll('[data-theme-toggle]')) {
        button.addEventListener('click', () => window.cvTheme?.toggle());
    }
})();
