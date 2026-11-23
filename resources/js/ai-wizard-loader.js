if (document.querySelector('[data-ai-wizard]')) {
    import('./cv-ai-wizard.js').catch((error) => {
        const status = document.querySelector('[data-ai-scan-live]');
        const message = status?.closest('[data-ai-scan-panel]')?.dataset.scanError;
        if (status && message) {
            status.textContent = message;
        }
        console.error('Unable to load the AI wizard interactions.', error);
    });
}
