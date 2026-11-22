(() => {
    const root = document.documentElement;
    const storageKey = 'cv-maker-theme';
    const systemPreference = window.matchMedia?.('(prefers-color-scheme: dark)') || null;
    const motionPreference = window.matchMedia?.('(prefers-reduced-motion: reduce)') || null;

    const readStoredTheme = () => {
        try {
            const stored = localStorage.getItem(storageKey);
            return stored === 'dark' || stored === 'light' ? stored : null;
        } catch (_error) {
            return null;
        }
    };

    let storedTheme = readStoredTheme();
    let hasExplicitPreference = storedTheme !== null;

    const systemTheme = () => systemPreference?.matches ? 'dark' : 'light';

    const updateToggleButtons = (theme) => {
        for (const button of document.querySelectorAll('[data-theme-toggle]')) {
            const dark = theme === 'dark';
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute(
                'aria-label',
                dark ? button.dataset.labelLight : button.dataset.labelDark,
            );
        }
    };

    const applyTheme = (theme) => {
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
        updateToggleButtons(theme);
    };

    const currentTheme = storedTheme || systemTheme();
    applyTheme(currentTheme);

    const setTheme = (theme) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        hasExplicitPreference = true;
        try {
            localStorage.setItem(storageKey, nextTheme);
        } catch (_error) {
            // Theme can still work for the current page without storage.
        }

        if (root.dataset.theme === nextTheme) {
            updateToggleButtons(nextTheme);
            return;
        }

        if (motionPreference?.matches || typeof document.startViewTransition !== 'function') {
            applyTheme(nextTheme);
            return;
        }

        root.classList.add('theme-transitioning');
        let transition;
        try {
            transition = document.startViewTransition(() => applyTheme(nextTheme));
        } catch (_error) {
            root.classList.remove('theme-transitioning');
            applyTheme(nextTheme);
            return;
        }

        const clearTransitionState = () => root.classList.remove('theme-transitioning');
        if (transition?.finished && typeof transition.finished.then === 'function') {
            transition.finished.then(clearTransitionState, clearTransitionState);
        } else {
            clearTransitionState();
        }
    };

    const followSystemPreference = (event) => {
        if (hasExplicitPreference) {
            return;
        }

        storedTheme = readStoredTheme();
        if (storedTheme !== null) {
            hasExplicitPreference = true;
            applyTheme(storedTheme);
            return;
        }

        applyTheme(event.matches ? 'dark' : 'light');
    };

    if (systemPreference && ! hasExplicitPreference) {
        if (typeof systemPreference.addEventListener === 'function') {
            systemPreference.addEventListener('change', followSystemPreference);
        } else if (typeof systemPreference.addListener === 'function') {
            systemPreference.addListener(followSystemPreference);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            updateToggleButtons(root.dataset.theme || currentTheme);
        }, { once: true });
    }

    window.cvTheme = {
        get: () => root.dataset.theme || 'light',
        set: setTheme,
        toggle: () => setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'),
    };
})();
