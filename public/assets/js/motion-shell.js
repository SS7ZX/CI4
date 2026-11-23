(() => {
    const root = document.documentElement;
    const progressLine = document.querySelector('[data-motion-progress]');
    const focusTarget = { id: null };
    let progressTimer = null;
    let restoreFocusAfterSwap = false;
    let transitionLockedUntil = 0;
    let updateNavigation = () => {};

    if (typeof document.startViewTransition !== 'function') {
        root.classList.add('motion-no-view-transition');
    }

    const findIn = (container, selector) => (
        container.matches?.(selector) ? container : container.querySelector?.(selector)
    );

    const assignViewNames = (container = document) => {
        const topbar = findIn(container, '[data-view-topbar]');
        const content = findIn(container, '[data-view-content]');
        const title = content?.querySelector('h1');
        const preview = container.querySelector?.('[data-view-cv-preview]');

        if (topbar) {
            topbar.style.viewTransitionName = 'top-bar';
        }
        if (content) {
            content.style.viewTransitionName = 'page-content';
        }
        if (title) {
            title.style.viewTransitionName = 'page-title';
        }
        if (preview) {
            preview.style.viewTransitionName = 'cv-preview';
        }
    };

    const clearNavigationFlag = () => {
        try {
            window.sessionStorage.removeItem('cv-motion-navigation-pending');
        } catch {
            return;
        }
    };

    const rememberAiDirection = (direction) => {
        if (direction !== 'forward' && direction !== 'backward') {
            return;
        }
        root.classList.add(`ai-wizard-${direction}`);
        try {
            window.sessionStorage.setItem('cv-motion-ai-direction', direction);
        } catch {
            root.classList.add('motion-no-session-storage');
        }
    };

    const startProgress = () => {
        if (!progressLine) {
            return;
        }
        clearTimeout(progressTimer);
        progressLine.classList.remove('is-complete');
        progressLine.classList.add('is-running');
    };

    const completeProgress = () => {
        if (!progressLine) {
            return;
        }
        progressLine.classList.add('is-complete');
        progressLine.classList.remove('is-running');
        clearNavigationFlag();
        clearTimeout(progressTimer);
        progressTimer = window.setTimeout(() => {
            progressLine.classList.remove('is-complete');
        }, 240);
    };

    assignViewNames();

    try {
        if (window.sessionStorage.getItem('cv-motion-navigation-pending') === '1') {
            startProgress();
        }
    } catch {
        root.classList.add('motion-no-session-storage');
    }

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const directionControl = target?.closest('[data-ai-direction]');
        if (directionControl) {
            rememberAiDirection(directionControl.dataset.aiDirection);
        }
        const link = target?.closest('a[href]') || null;
        if (
            !link
            || event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || link.target === '_blank'
            || link.hasAttribute('download')
        ) {
            return;
        }

        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin) {
            return;
        }
        if (
            destination.pathname === window.location.pathname
            && destination.search === window.location.search
            && destination.hash !== ''
        ) {
            return;
        }

        startProgress();
        try {
            window.sessionStorage.setItem('cv-motion-navigation-pending', '1');
        } catch {
            root.classList.add('motion-no-session-storage');
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const direction = event.target.dataset.aiDirection
            || event.submitter?.dataset.aiDirection;
        if (direction) {
            rememberAiDirection(direction);
        }
        if (event.target.matches('[data-cv-ai-stream-form]')) {
            return;
        }
        startProgress();
    }, true);

    document.addEventListener('pageshow', () => {
        if (progressLine?.classList.contains('is-running')) {
            window.requestAnimationFrame(() => completeProgress());
        }
    });

    document.addEventListener('htmx:beforeRequest', startProgress);
    document.addEventListener('htmx:configRequest', (event) => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta || event.detail.verb === 'get') {
            return;
        }

        const headerName = meta.dataset.headerName;
        if (headerName) {
            event.detail.headers[headerName] = meta.content;
        }
    });
    document.addEventListener('htmx:beforeTransition', (event) => {
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        const durationToken = window.getComputedStyle(root).getPropertyValue('--duration-page').trim();
        const durationMatch = durationToken.match(/^(\d+(?:\.\d+)?)(ms|s)$/);
        if (reducedMotion || window.performance.now() < transitionLockedUntil || !durationMatch) {
            event.preventDefault();
            if (!durationMatch) {
                console.error('The motion page duration token is missing or invalid.');
            }
            return;
        }

        const duration = Number(durationMatch[1]) * (durationMatch[2] === 's' ? 1000 : 1);
        transitionLockedUntil = window.performance.now() + duration + 100;
    });
    document.addEventListener('htmx:afterRequest', (event) => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const headerName = meta?.dataset.headerName;
        const nextToken = headerName ? event.detail.xhr.getResponseHeader(headerName) : null;
        if (meta && nextToken) {
            meta.content = nextToken;
        }
        if (event.detail.failed || event.detail.xhr.status >= 400) {
            completeProgress();
        }
    });
    document.addEventListener('htmx:afterSwap', (event) => {
        assignViewNames(event.detail.target || document);
        updateNavigation();
        if (root.classList.contains('motion-no-view-transition')) {
            const content = event.detail.target?.matches?.('[data-view-content]')
                ? event.detail.target
                : findIn(event.detail.target || document, '[data-view-content]');
            if (content) {
                content.classList.remove('motion-fallback-enter');
                void content.offsetWidth;
                content.classList.add('motion-fallback-enter');
                content.addEventListener('animationend', () => content.classList.remove('motion-fallback-enter'), { once: true });
            }
        }
    });
    document.addEventListener('htmx:beforeSwap', (event) => {
        const target = event.detail.target;
        const active = document.activeElement;
        restoreFocusAfterSwap = target instanceof Element && target.contains(active);
        focusTarget.id = restoreFocusAfterSwap && active instanceof HTMLElement ? active.id : null;
    });
    document.addEventListener('htmx:afterSettle', (event) => {
        assignViewNames(event.detail.target || document);
        if (restoreFocusAfterSwap) {
            const nextFocus = focusTarget.id ? document.getElementById(focusTarget.id) : null;
            (nextFocus || event.detail.target)?.focus?.({ preventScroll: true });
        }
        restoreFocusAfterSwap = false;
        focusTarget.id = null;
        updateNavigation();
        completeProgress();
    });

    const navigation = document.querySelector('[data-app-navigation]');
    if (navigation) {
        updateNavigation = () => {
            const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
            for (const link of navigation.querySelectorAll('[data-nav-key]')) {
                const key = link.dataset.navKey;
                const targetPath = new URL(link.href, window.location.href).pathname.replace(/\/+$/, '') || '/';
                const isActive = key === 'dashboard'
                    ? currentPath === targetPath || currentPath.startsWith(`${targetPath}/`)
                    : key === 'password'
                        ? currentPath.includes('password')
                        : key === 'themes'
                            ? currentPath === targetPath
                            : currentPath === targetPath;
                link.querySelector('.nav-active-indicator')?.remove();
                if (isActive) {
                    link.setAttribute('aria-current', 'page');
                    const indicator = document.createElement('span');
                    indicator.className = 'nav-active-indicator';
                    indicator.setAttribute('aria-hidden', 'true');
                    link.appendChild(indicator);
                } else {
                    link.removeAttribute('aria-current');
                }
            }
        };
        updateNavigation();
        let seen = false;
        try {
            seen = window.sessionStorage.getItem('cv-motion-navigation-seen') === '1';
            if (!seen) {
                window.sessionStorage.setItem('cv-motion-navigation-seen', '1');
            }
        } catch {
            root.classList.add('motion-no-session-storage');
        }
        if (!seen) {
            root.classList.add('motion-first-navigation');
            [...navigation.querySelectorAll('.nav-item')].forEach((item, index) => {
                item.style.setProperty('--i', String(index));
            });
            window.setTimeout(() => root.classList.remove('motion-first-navigation'), 600);
        }
    }

    const parallax = document.querySelector('[data-auth-parallax]');
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    if (parallax && !reduceMotion?.matches) {
        const maxOffset = 6;
        const moveParallax = (event) => {
            const bounds = parallax.getBoundingClientRect();
            if (bounds.width === 0 || bounds.height === 0) {
                return;
            }
            const x = ((event.clientX - bounds.left) / bounds.width - 0.5) * maxOffset * 2;
            const y = ((event.clientY - bounds.top) / bounds.height - 0.5) * maxOffset * 2;
            parallax.style.setProperty('--auth-shift-x', `${x.toFixed(2)}px`);
            parallax.style.setProperty('--auth-shift-y', `${y.toFixed(2)}px`);
        };
        const resetParallax = () => {
            parallax.style.setProperty('--auth-shift-x', '0px');
            parallax.style.setProperty('--auth-shift-y', '0px');
        };
        parallax.addEventListener('pointermove', moveParallax);
        parallax.addEventListener('pointerleave', resetParallax);
        const disableParallax = (event) => {
            if (!event.matches) {
                return;
            }
            parallax.removeEventListener('pointermove', moveParallax);
            parallax.removeEventListener('pointerleave', resetParallax);
            resetParallax();
        };
        if (typeof reduceMotion?.addEventListener === 'function') {
            reduceMotion.addEventListener('change', disableParallax);
        } else {
            reduceMotion?.addListener?.(disableParallax);
        }
    }

    const wizardPage = document.querySelector('[data-ai-wizard-page]');
    if (wizardPage) {
        const currentStep = Number(wizardPage.dataset.aiStep);
        let previousStep = 0;
        let direction = '';
        try {
            previousStep = Number(window.sessionStorage.getItem('cv-motion-ai-step')) || 0;
            direction = window.sessionStorage.getItem('cv-motion-ai-direction') || '';
            window.sessionStorage.removeItem('cv-motion-ai-direction');
            window.sessionStorage.setItem('cv-motion-ai-step', String(currentStep));
        } catch {
            root.classList.add('motion-no-session-storage');
        }
        if (direction !== 'forward' && direction !== 'backward') {
            direction = previousStep > currentStep ? 'backward' : 'forward';
        }
        root.classList.add(`ai-wizard-${direction}`);
        wizardPage.dataset.motionDirection = direction;
        wizardPage.classList.add('ai-wizard-entering');
        wizardPage.addEventListener('animationend', (event) => {
            if (event.target !== wizardPage) {
                return;
            }
            wizardPage.classList.remove('ai-wizard-entering');
            root.classList.remove('ai-wizard-forward', 'ai-wizard-backward');
        }, { once: true });

        const progress = document.querySelector('[data-ai-progress]');
        const progressLine = progress?.querySelector('span');
        const stepCount = Number(progress?.dataset.stepCount);
        if (progressLine && previousStep > 0 && stepCount > 1 && previousStep !== currentStep) {
            const progressFrom = Math.max(0, Math.min(1, (previousStep - 1) / (stepCount - 1)));
            progressLine.style.setProperty('--progress-from', String(progressFrom));
            progress.classList.add('is-animating');
            progress.addEventListener('animationend', () => progress.classList.remove('is-animating'), { once: true });
        }
    } else {
        try {
            window.sessionStorage.removeItem('cv-motion-ai-step');
            window.sessionStorage.removeItem('cv-motion-ai-direction');
        } catch {
            root.classList.add('motion-no-session-storage');
        }
    }
})();
