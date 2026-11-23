export function initializeSectionReorder(list) {
    if (!list || list.dataset.reorderReady === 'true') {
        return;
    }

    list.dataset.reorderReady = 'true';
    const root = list.closest('[x-data]');
    const state = window.Alpine?.$data(root);
    if (!state || typeof state.reorderSections !== 'function') {
        throw new Error('Section reordering requires the initialized CV editor.');
    }

    const items = () => [...list.querySelectorAll(':scope > [data-sort-item]')];
    const setIndexes = () => items().forEach((item, index) => item.style.setProperty('--i', String(index)));
    setIndexes();

    let active = null;
    let frame = 0;

    const applyFlip = (before) => {
        const after = items();
        const changes = after.map((item) => ({
            item,
            delta: before.get(item) - item.getBoundingClientRect().top,
        })).filter(({ delta }) => Number.isFinite(delta) && delta !== 0);

        for (const { item, delta } of changes) {
            item.style.transition = 'none';
            item.style.transform = `translateY(${delta}px)${item === active?.item ? ' scale(1.015)' : ''}`;
        }
        window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(() => {
            for (const { item } of changes) {
                item.style.removeProperty('transition');
                item.style.removeProperty('transform');
            }
        });
    };

    const placeItem = (pointerY) => {
        const before = new Map(items().map((item) => [item, item.getBoundingClientRect().top]));
        const remainingItems = items().filter((item) => item !== active.item);
        const nextItem = remainingItems.find((item) => {
            const rect = item.getBoundingClientRect();
            return pointerY < rect.top + rect.height / 2;
        });
        list.insertBefore(active.item, nextItem || null);
        applyFlip(before);
    };

    const finish = (commit) => {
        if (!active) {
            return;
        }

        const { item, handle } = active;
        item.classList.remove('is-dragging');
        if (commit) {
            item.classList.add('is-drop-settling');
            const keys = items().map((section) => section.dataset.sectionKey);
            state.reorderSections(keys);
            item.addEventListener('animationend', () => item.classList.remove('is-drop-settling'), { once: true });
        } else {
            setIndexes();
        }
        if (handle.hasPointerCapture(active.pointerId)) {
            handle.releasePointerCapture(active.pointerId);
        }
        active = null;
    };

    list.addEventListener('pointerdown', (event) => {
        const handle = event.target instanceof Element ? event.target.closest('[data-sort-handle]') : null;
        if (!handle || event.button !== 0 || !event.isPrimary) {
            return;
        }

        const item = handle.closest('[data-sort-item]');
        if (!item) {
            return;
        }

        active = {
            handle,
            item,
            pointerId: event.pointerId,
            startY: event.clientY,
            dragging: false,
        };
        handle.setPointerCapture(event.pointerId);
    });

    list.addEventListener('pointermove', (event) => {
        if (!active || active.pointerId !== event.pointerId) {
            return;
        }
        if (!active.dragging && Math.abs(event.clientY - active.startY) < 5) {
            return;
        }
        active.dragging = true;
        active.item.classList.add('is-dragging');
        placeItem(event.clientY);
    });

    list.addEventListener('pointerup', (event) => {
        if (!active || active.pointerId !== event.pointerId) {
            return;
        }
        finish(active.dragging);
    });

    list.addEventListener('pointercancel', () => finish(false));
    list.addEventListener('lostpointercapture', () => {
        if (active) {
            finish(false);
        }
    });
}
