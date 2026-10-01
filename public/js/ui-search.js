// Behaviour for the shared search field: a clear button, and "/" to jump to the page's search.
(() => {
    const fieldOf = element => element?.closest?.('[data-k-search]');
    const sync = field => {
        const input = field.querySelector('input');
        const clear = field.querySelector('[data-k-search-clear]');
        const hasValue = input.value !== '';
        field.classList.toggle('has-value', hasValue);
        if (clear) clear.hidden = !hasValue;
    };

    // Pages also set the value from code (filters, chips, re-renders), which fires no input event.
    const syncAll = () => document.querySelectorAll('[data-k-search]').forEach(sync);
    document.addEventListener('DOMContentLoaded', syncAll);
    document.addEventListener('ajax:content-updated', syncAll);
    document.addEventListener('click', () => window.requestAnimationFrame(syncAll));

    document.addEventListener('input', event => {
        const field = fieldOf(event.target);
        if (field) sync(field);
    });

    document.addEventListener('click', event => {
        const clear = event.target.closest?.('[data-k-search-clear]');
        if (!clear) return;
        const field = fieldOf(clear);
        const input = field.querySelector('input');
        const wasServerSearch = input.defaultValue !== '';
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        sync(field);
        if (field.hasAttribute('data-k-search-submit') && wasServerSearch && input.form) {
            input.form.requestSubmit();
            return;
        }
        input.focus();
    });

    document.addEventListener('keydown', event => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) return;
        if (event.target.closest?.('input, textarea, select, [contenteditable="true"], [role="dialog"]')) return;
        const input = [...document.querySelectorAll('[data-k-search] input')]
            .find(candidate => !candidate.disabled && candidate.getClientRects().length && !candidate.closest('[hidden], [role="dialog"]'));
        if (!input) return;
        event.preventDefault();
        input.focus();
        input.select();
    });
})();
