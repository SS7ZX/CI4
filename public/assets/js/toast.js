(() => {
    const region = document.querySelector('[data-motion-toast-region]');
    if (!region) {
        return;
    }

    const dismissLabel = region.dataset.dismissLabel;
    const durationToken = window.getComputedStyle(region).getPropertyValue('--duration-toast').trim();
    const durationMatch = durationToken.match(/^(\d+(?:\.\d+)?)(ms|s)$/);
    if (!durationMatch) {
        throw new Error('The motion toast duration token is missing or invalid.');
    }
    const defaultDuration = Number(durationMatch[1]) * (durationMatch[2] === 's' ? 1000 : 1);
    const activeTimeouts = new WeakMap();

    const dismiss = (toast) => {
        if (toast.classList.contains('is-dismissing')) {
            return;
        }
        window.clearTimeout(activeTimeouts.get(toast));
        activeTimeouts.delete(toast);
        toast.classList.add('is-dismissing');
        window.setTimeout(() => toast.remove(), 180);
    };

    const removeToast = (toast) => {
        window.clearTimeout(activeTimeouts.get(toast));
        activeTimeouts.delete(toast);
        toast.remove();
    };

    const show = (message, duration = defaultDuration) => {
        if (typeof message !== 'string' || message.trim() === '' || !Number.isFinite(duration) || duration <= 0) {
            return null;
        }

        while (region.children.length >= 4) {
            if (region.firstElementChild) {
                removeToast(region.firstElementChild);
            }
        }

        const toast = document.createElement('div');
        toast.className = 'motion-toast';
        toast.setAttribute('role', 'status');
        toast.tabIndex = 0;
        toast.style.setProperty('--toast-duration', `${duration}ms`);

        const content = document.createElement('div');
        content.className = 'flex items-start justify-between gap-4 p-4 pr-3 text-sm leading-6 text-ink';
        const text = document.createElement('p');
        text.textContent = message;

        const close = document.createElement('button');
        close.className = 'press shrink-0 rounded-sm px-2 py-1 text-xs font-medium text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700';
        close.type = 'button';
        close.setAttribute('aria-label', dismissLabel);
        close.textContent = dismissLabel;
        close.addEventListener('click', () => dismiss(toast));

        content.append(text, close);
        const countdown = document.createElement('span');
        countdown.className = 'motion-toast-countdown';
        countdown.setAttribute('aria-hidden', 'true');
        toast.append(content, countdown);
        region.appendChild(toast);

        let remaining = duration;
        let startedAt = performance.now();
        let timeout = window.setTimeout(() => dismiss(toast), remaining);
        activeTimeouts.set(toast, timeout);

        toast.addEventListener('pointerenter', () => {
            if (toast.classList.contains('is-dismissing')) {
                return;
            }
            window.clearTimeout(timeout);
            activeTimeouts.delete(toast);
            remaining = Math.max(0, remaining - (performance.now() - startedAt));
            countdown.style.animationPlayState = 'paused';
        });
        toast.addEventListener('pointerleave', () => {
            if (toast.classList.contains('is-dismissing')) {
                return;
            }
            startedAt = performance.now();
            countdown.style.animationPlayState = 'running';
            timeout = window.setTimeout(() => dismiss(toast), remaining);
            activeTimeouts.set(toast, timeout);
        });

        let touchStartX = null;
        toast.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'touch') {
                touchStartX = event.clientX;
            }
        });
        toast.addEventListener('pointerup', (event) => {
            if (touchStartX !== null && Math.abs(event.clientX - touchStartX) > 48) {
                window.clearTimeout(timeout);
                activeTimeouts.delete(toast);
                dismiss(toast);
            }
            touchStartX = null;
        });

        return toast;
    };

    window.cvToasts = { show };
})();
