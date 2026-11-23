(() => {
    const root = document.documentElement;
    const motionPreference = window.matchMedia?.('(prefers-reduced-motion: reduce)') || null;
    const revealSelector = '[data-reveal], .reveal, .reveal-stagger';
    const observed = new WeakSet();
    const animatedCounters = new WeakSet();
    const allRevealElements = new Set();
    const allCountElements = new Set();
    const activeCounters = new Map();
    let observer = null;

    function collect(container, selector) {
        const elements = [...(container.querySelectorAll?.(selector) || [])];
        if (container.matches?.(selector)) {
            elements.unshift(container);
        }

        return elements;
    }

    function setVisible(element) {
        element.classList.add('motion-visible');
    }

    function setCounter(element, value) {
        const precision = Math.max(0, String(element.dataset.countUp).split('.')[1]?.length || 0);
        element.classList.add('motion-count-up');
        element.textContent = Number(value).toFixed(precision);
    }

    function completeCounter(element) {
        const target = Number(element.dataset.countUp);
        if (!Number.isFinite(target)) {
            return;
        }

        const activeCounter = activeCounters.get(element);
        if (activeCounter) {
            window.cancelAnimationFrame?.(activeCounter.frame);
            activeCounters.delete(element);
        }

        setCounter(element, target);
        animatedCounters.add(element);
    }

    function parseCssTime(value) {
        const match = value.trim().match(/^(\d+(?:\.\d+)?)(ms|s)$/);
        if (!match) {
            return 0;
        }

        return Number(match[1]) * (match[2] === 's' ? 1000 : 1);
    }

    function animateCounter(element) {
        if (animatedCounters.has(element)) {
            return;
        }
        const target = Number(element.dataset.countUp);
        const start = Number(element.dataset.countStart ?? 0);
        const duration = parseCssTime(
            window.getComputedStyle?.(root).getPropertyValue('--duration-slow') || '',
        );
        if (!Number.isFinite(target) || !Number.isFinite(start)) {
            return;
        }

        if (
            duration <= 0
            || typeof window.requestAnimationFrame !== 'function'
            || motionPreference?.matches
        ) {
            completeCounter(element);
            return;
        }

        let startTime = null;
        const counter = { frame: 0 };
        activeCounters.set(element, counter);
        const tick = (time) => {
            if (startTime === null) {
                startTime = time;
            }

            const progress = Math.min((time - startTime) / duration, 1);
            const easedProgress = 1 - ((1 - progress) ** 3);
            setCounter(element, start + ((target - start) * easedProgress));
            if (progress < 1) {
                counter.frame = window.requestAnimationFrame(tick);
            } else {
                activeCounters.delete(element);
                animatedCounters.add(element);
            }
        };

        counter.frame = window.requestAnimationFrame(tick);
    }

    function showAllContent() {
        root.classList.remove('motion-ready');
        allRevealElements.forEach(setVisible);
        allCountElements.forEach(completeCounter);
    }

    function indexStaggerChildren(elements) {
        for (const element of elements) {
            if (!element.classList.contains('reveal-stagger')) {
                continue;
            }

            [...(element.children || [])].forEach((child, index) => {
                child.style?.setProperty('--i', String(index));
            });
        }
    }

    function refresh(container = document) {
        const revealElements = collect(container, revealSelector);
        const countElements = collect(container, '[data-count-up]');
        revealElements.forEach((element) => allRevealElements.add(element));
        countElements.forEach((element) => allCountElements.add(element));
        indexStaggerChildren(revealElements);

        if (
            !motionPreference
            || motionPreference.matches
            || typeof window.IntersectionObserver !== 'function'
        ) {
            revealElements.forEach(setVisible);
            countElements.forEach(completeCounter);
            return;
        }

        const elements = [...new Set([...revealElements, ...countElements])];
        if (elements.length === 0) {
            return;
        }

        root.classList.add('motion-ready');
        observer ??= new window.IntersectionObserver((entries, currentObserver) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                if (allRevealElements.has(entry.target)) {
                    setVisible(entry.target);
                }
                if (allCountElements.has(entry.target)) {
                    animateCounter(entry.target);
                }
                currentObserver.unobserve(entry.target);
            }
        }, {
            rootMargin: '0px 0px -24px 0px',
            threshold: 0,
        });

        for (const element of elements) {
            if (observed.has(element)) {
                continue;
            }
            observed.add(element);
            observer.observe(element);
        }
    }

    window.cvMotion = { refresh };
    refresh();
    document.addEventListener('htmx:afterSettle', (event) => {
        refresh(event.detail.target || document);
    });

    const disableMotion = (event) => {
        if (!event.matches) {
            return;
        }

        observer?.disconnect();
        showAllContent();
    };

    if (motionPreference) {
        if (typeof motionPreference.addEventListener === 'function') {
            motionPreference.addEventListener('change', disableMotion);
        } else {
            motionPreference.addListener(disableMotion);
        }
    }
})();
