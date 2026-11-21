(() => {
    const root = document.documentElement;
    const motionPreference = window.matchMedia?.('(prefers-reduced-motion: reduce)');

    if (!motionPreference || motionPreference.matches || !('IntersectionObserver' in window)) {
        return;
    }

    const elements = [...document.querySelectorAll('[data-reveal]')];
    if (elements.length === 0) {
        return;
    }

    root.classList.add('motion-ready');
    const observer = new IntersectionObserver((entries, currentObserver) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) {
                continue;
            }

            entry.target.classList.add('motion-visible');
            currentObserver.unobserve(entry.target);
        }
    }, {
        rootMargin: '0px 0px -24px 0px',
        threshold: 0,
    });

    for (const element of elements) {
        observer.observe(element);
    }

    const disableMotion = (event) => {
        if (!event.matches) {
            return;
        }

        observer.disconnect();
        root.classList.remove('motion-ready');
        for (const element of elements) {
            element.classList.add('motion-visible');
        }
    };

    if (typeof motionPreference.addEventListener === 'function') {
        motionPreference.addEventListener('change', disableMotion);
    } else {
        motionPreference.addListener(disableMotion);
    }
})();
