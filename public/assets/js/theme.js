(() => {
    const root = document.documentElement;
    const storageKey = 'cv-maker-theme';

    const readStoredTheme = () => {
        try {
            const stored = localStorage.getItem(storageKey);
            return stored === 'dark' || stored === 'light' ? stored : null;
        } catch (_error) {
            return null;
        }
    };

    const systemTheme = () => (
        window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    );

    const currentTheme = readStoredTheme() || systemTheme();

    const applyTheme = (theme) => {
        root.dataset.theme = theme;
        root.style.colorScheme = theme;

        for (const button of document.querySelectorAll('[data-theme-toggle]')) {
            const dark = theme === 'dark';
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute(
                'aria-label',
                dark ? button.dataset.labelLight : button.dataset.labelDark,
            );
        }
    };

    const setTheme = (theme) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        try {
            localStorage.setItem(storageKey, nextTheme);
        } catch (_error) {
            // Theme can still work for the current page without storage.
        }
        applyTheme(nextTheme);
    };

    applyTheme(currentTheme);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => applyTheme(currentTheme), { once: true });
    }

    window.cvTheme = {
        get: () => root.dataset.theme || 'light',
        set: setTheme,
        toggle: () => setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'),
    };
})();
