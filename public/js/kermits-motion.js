(() => {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const ease = 'cubic-bezier(.22,.72,.18,1)';
    const playing = new Set();
    const motions = new WeakMap();
    function animate(element, frames, duration = 260, delay = 0) {
        if (!element || reduced.matches || !element.animate) return;
        motions.get(element)?.cancel();
        const animation = element.animate(frames, {duration, delay, easing: ease, fill: delay ? 'backwards' : 'none'});
        motions.set(element, animation);
        playing.add(animation);
        animation.finished.catch(() => {}).finally(() => playing.delete(animation));
    }
    reduced.addEventListener('change', () => {if (reduced.matches) playing.forEach(a => a.cancel());});
    window.addEventListener('beforeprint', () => playing.forEach(a => a.cancel()));
    function enter(selector, {distance = '0 8px', duration = 280, stagger = 28, limit = 8} = {}) {
        [...document.querySelectorAll(selector)].filter(el => el.getClientRects().length).slice(0, limit)
            .forEach((el, i) => animate(el, [{opacity:.35, translate:distance}, {opacity:1, translate:'0 0'}], duration, Math.min(i * stagger, 140)));
    }

    // Headings orient the page; dense work areas stay still and immediately usable.
    enter('.topbar, .dash-head, .report-topbar, .sell-head, .reservation-view-header, .customer-shop > header, .history-page > header', {distance:'0 5px', duration:220, limit:1});
    enter('.hero-copy > h1, .hero-copy > .hero-text, .hero-copy > .hero-actions', {distance:'0 10px', duration:380, stagger:50, limit:3});
    enter('.hero-visual .plate', {distance:'12px 0', duration:480, limit:1});
    enter('table tbody > tr', {distance:'0 6px', duration:260, stagger:35, limit:6});
    enter('.product-grid > .product-card', {distance:'0 8px', duration:280, stagger:32, limit:6});
    enter('.reservation-card', {distance:'0 8px', duration:290, stagger:35, limit:6});
    enter('.reservation-schedule', {distance:'0 7px', duration:280, stagger:45, limit:1});
    enter('.activity-card .activity-title', {distance:'0 6px', duration:260, limit:6});
    enter('.booking-brand img, .login-brand img, .page .logo', {distance:'0 -6px', duration:340, limit:1});
    enter('.login-inner > .eyebrow, .login-inner > h1', {distance:'0 6px', duration:300, stagger:40, limit:2});
    enter('.reservation-result .reservation-summary', {distance:'0 8px', duration:340, stagger:50, limit:3});
    enter('.hero-metrics > *, .stat-row > *, .metric-grid > *, .metrics > *, .stats > *, .stat-grid > *', {distance:'0 5px', duration:280, stagger:35, limit:4});
    enter('.inventory-item .inventory-info', {distance:'-5px 0', duration:220, stagger:18, limit:6});
    document.querySelectorAll('.bars > i').forEach((bar, i) => {
        animate(bar, [{clipPath:'inset(100% 0 0 0)'}, {clipPath:'inset(0 0 0 0)'}], 420, Math.min(i * 25, 140));
    });

    // Animate only newly visible dialog panels, including the two checkout steps.
    const panels = 'dialog, #review-modal, [data-checkout-step], #cash-fields, #gcash-fields, #proof-field, #product-create-panel, #product-search-options';
    function visible(el) {return !el.hidden && (el.tagName !== 'DIALOG' || el.open) && el.getClientRects().length > 0;}
    document.querySelectorAll(panels).forEach(panel => {
        let wasVisible = visible(panel);
        new MutationObserver(() => {
            const isVisible = visible(panel);
            if (isVisible && !wasVisible) {
                const isStep = panel.hasAttribute('data-checkout-step');
                const direction = panel.dataset.checkoutStep === 'payment' ? '14px 0' : '-10px 0';
                animate(panel, [{opacity:.2, translate:isStep ? direction : '0 14px'}, {opacity:1, translate:'0 0'}], isStep ? 220 : 280, isStep ? 35 : 55);
            }
            wasVisible = isVisible;
        }).observe(panel, {attributes:true, attributeFilter:['hidden', 'open', 'style', 'class']});
    });

    // Cart numbers respond to a real total change, without rolling through fake amounts.
    document.querySelectorAll('#cart-total, #customer-cart-total, #cart-count, #menu-total').forEach(total => {
        let previous = total.textContent;
        new MutationObserver(() => {
            if (previous === total.textContent) return;
            previous = total.textContent;
            animate(total, [{opacity:.55, translate:'0 3px'}, {opacity:1, translate:'0 0'}], 180);
        }).observe(total, {childList:true, characterData:true, subtree:true});
    });

    function pendingLabel(form) {
        const path = new URL(form.action, location.href).pathname;
        if (/\/reservations\/\d+\/status$/.test(path)) {
            const status = new FormData(form).get('status');
            return {confirmed:'Approving reservation', cancelled:'Cancelling reservation', rejected:'Declining request', completed:'Completing reservation'}[status] || 'Updating reservation';
        }
        if (/\/book$|\/shop\/orders$/.test(path)) return 'Securing your table';
        if (/confirm-payment$|\/cashier\/checkout$/.test(path)) return 'Recording payment';
        if (/\/login$/.test(path)) return 'Opening your workspace';
        if (/\/inventory\//.test(path)) return 'Updating stock';
        if (/\/products(?:\/\d+)?$/.test(path)) return 'Saving menu item';
        if (/\/logout$/.test(path)) return 'Signing out';
        return 'Saving changes';
    }
    const pending = new Map();
    function reset(form) {
        const entry = pending.get(form);
        if (!entry) return;
        entry.button.replaceChildren(...entry.original);
        entry.button.classList.remove('is-submitting');
        entry.button.removeAttribute('aria-disabled');
        entry.panel?.classList.remove('km-pending-rail');
        form.removeAttribute('aria-busy');
        entry.status.remove();
        pending.delete(form);
    }
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || form.target === '_blank') return;
        if (pending.has(form)) {event.preventDefault(); return;}
        // Existing confirmation dialogs and validation retain control over submission.
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            const button = event.submitter;
            if (!button || button.tagName !== 'BUTTON') return;
            const label = pendingLabel(form);
            const original = [...button.childNodes];
            const copy = document.createElement('span');
            copy.className = 'km-submit-copy';
            const mark = document.createElement('span');
            mark.className = 'km-submit-mark'; mark.setAttribute('aria-hidden', 'true');
            copy.append(mark, document.createTextNode(label + '…'));
            button.replaceChildren(copy);
            button.classList.add('is-submitting'); button.setAttribute('aria-disabled', 'true');
            form.setAttribute('aria-busy', 'true');
            const panel = form.closest('.reservation-card') || form.querySelector('.payment-card');
            panel?.classList.add('km-pending-rail');
            const status = document.createElement('span');
            status.className = 'km-submit-status'; status.setAttribute('role', 'status');
            form.append(status);
            pending.set(form, {button, original, panel, status});
            try {sessionStorage.setItem('km-action', JSON.stringify({action:form.action, time:Date.now()}));} catch {}
            // A slow network gets an honest explanation, never an automatic retry.
            setTimeout(() => {if (pending.has(form)) status.textContent = 'This is taking longer than usual. Please wait for confirmation.';}, 8000);
        });
    });
    window.addEventListener('pageshow', () => [...pending.keys()].forEach(reset));
    try {
        const last = JSON.parse(sessionStorage.getItem('km-action') || 'null');
        sessionStorage.removeItem('km-action');
        if (last && Date.now() - last.time < 120000 && document.body.dataset.feedback === 'success') {
            const form = [...document.forms].find(form => form.action === last.action);
            (form?.closest('.reservation-card') || form)?.classList.add('km-saved');
            enter('.notice', {distance:'0 -4px', duration:250, limit:1});
        }
    } catch {}
})();
