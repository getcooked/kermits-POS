(() => {
    'use strict';

    const errorSelector = [
        '[data-ajax-feedback="error"]',
        '.error',
        '.account-error',
        '.account-message.error',
        '.security-message.error',
        '.review-error',
        '.orders-error',
        '.settings-error',
        '.reservation-error',
        '.sell-error',
    ].join(',');
    const successSelector = [
        '[data-ajax-feedback="success"]',
        '.notice',
        '.account-notice',
        '.account-message.success',
        '.security-message.success',
        '.account-verification-sent',
        '.security-toast > span:nth-child(2)',
    ].join(',');

    const messageFrom = (root, selector) => root.querySelector(selector)?.textContent.replace(/\s+/g, ' ').trim() || '';

    const showToast = (message, isError = false, title = '') => {
        document.querySelector('.ajax-form-toast')?.remove();
        const toast = document.createElement('div');
        toast.className = `ajax-form-toast${isError ? ' is-error' : ''}`;
        toast.setAttribute('role', isError ? 'alert' : 'status');
        toast.setAttribute('aria-live', 'polite');

        const icon = document.createElement('span');
        icon.className = 'ajax-form-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = isError ? '!' : '\u2713';

        const copy = document.createElement('span');
        copy.className = 'ajax-form-toast-copy';
        if (title) {
            const heading = document.createElement('strong');
            heading.textContent = title;
            copy.append(heading);
        }
        const description = document.createElement('span');
        description.textContent = message;
        copy.append(description);

        const close = document.createElement('button');
        close.className = 'ajax-form-toast-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '\u00d7';

        const progress = document.createElement('span');
        progress.className = 'ajax-form-toast-progress';
        progress.setAttribute('aria-hidden', 'true');

        toast.append(icon, copy, close, progress);
        document.body.append(toast);

        let hideTimer;
        let removeTimer;
        const dismiss = () => {
            window.clearTimeout(hideTimer);
            window.clearTimeout(removeTimer);
            toast.classList.add('is-hiding');
            removeTimer = window.setTimeout(() => toast.remove(), 240);
        };
        close.addEventListener('click', dismiss);
        hideTimer = window.setTimeout(dismiss, 5000);
    };

    const showFormError = (form, message, field = '') => {
        form.querySelector('.ajax-form-inline-error')?.remove();
        const error = document.createElement('div');
        error.className = 'ajax-form-inline-error';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        form.prepend(error);
        const input = field ? form.elements.namedItem(field) : null;
        if (input instanceof HTMLElement) input.focus();
        showToast(message, true);
    };

    const bindPhoneInputs = (root = document) => {
        root.querySelectorAll('input[type="tel"][maxlength="11"]').forEach(input => {
            if (input.dataset.numericPhoneBound === 'true') return;
            input.dataset.numericPhoneBound = 'true';
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('minlength', '11');
            input.setAttribute('maxlength', '11');
            input.setAttribute('pattern', '09[0-9]{9}');
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 11);
            });
        });
    };

    const parseJson = text => {
        try {
            return JSON.parse(text);
        } catch (error) {
            return null;
        }
    };

    document.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-ajax-form]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();

        const submitButton = event.submitter ?? form.querySelector('[type="submit"], button:not([type])');
        const originalButtonContent = submitButton?.innerHTML;
        form.querySelector('.ajax-form-inline-error')?.remove();
        form.setAttribute('aria-busy', 'true');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = form.dataset.ajaxLoading || 'Saving...';
        }

        try {
            const response = await fetch(form.action || window.location.href, {
                method: (form.method || 'POST').toUpperCase(),
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const responseText = await response.text();
            const isJson = response.headers.get('content-type')?.includes('application/json');
            const payload = isJson ? parseJson(responseText) : null;

            if (response.status === 422) {
                const firstField = Object.keys(payload?.errors ?? {})[0] ?? '';
                const message = payload?.errors?.[firstField]?.[0] ?? payload?.message ?? 'Please check the form and try again.';
                showFormError(form, message, firstField);
                return;
            }

            if (!response.ok) {
                const message = response.status < 500 && payload?.message
                    ? payload.message
                    : 'The request could not be completed. Please try again.';
                showFormError(form, message);
                return;
            }

            const responseUrl = new URL(response.url, window.location.href);
            if (response.redirected && responseUrl.pathname !== window.location.pathname) {
                showFormError(form, 'Your session may have expired. Refresh the page and try again.');
                return;
            }

            const responseDocument = new DOMParser().parseFromString(responseText, 'text/html');
            const serverError = messageFrom(responseDocument, errorSelector);
            const successMessage = messageFrom(responseDocument, successSelector)
                || form.dataset.ajaxSuccess
                || 'Changes saved successfully.';
            const targetSelector = form.dataset.ajaxTarget;

            if (targetSelector) {
                const currentTarget = document.querySelector(targetSelector);
                const nextTarget = responseDocument.querySelector(targetSelector);
                if (!currentTarget || !nextTarget) {
                    showFormError(form, 'Your session may have expired. Refresh the page and try again.');
                    return;
                }

                const workspace = document.querySelector('.admin-workspace');
                const workspaceScroll = workspace?.scrollTop ?? 0;
                const windowScroll = window.scrollY;
                const openDetails = [...currentTarget.querySelectorAll('details')]
                    .map((details, index) => details.open ? index : -1)
                    .filter(index => index >= 0);
                const replacement = document.importNode(nextTarget, true);
                openDetails.forEach(index => {
                    const details = replacement.querySelectorAll('details')[index];
                    if (details) details.open = true;
                });
                currentTarget.replaceWith(replacement);
                bindPhoneInputs(replacement);
                window.requestAnimationFrame(() => {
                    if (workspace) workspace.scrollTop = workspaceScroll;
                    window.scrollTo(0, windowScroll);
                });
                document.dispatchEvent(new CustomEvent('ajax:content-updated', {
                    detail: { selector: targetSelector, target: replacement },
                }));
            } else if (!serverError && form.dataset.ajaxReset === 'true') {
                form.reset();
            }

            document.body.dataset.feedback = serverError ? 'error' : 'success';
            showToast(serverError || successMessage, Boolean(serverError));
        } catch (error) {
            showFormError(form, 'A network error occurred. Check your connection and try again.');
        } finally {
            form.removeAttribute('aria-busy');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;
            }
        }
    });

    const pageToast = document.querySelector('[data-page-toast]');
    if (pageToast) {
        showToast(
            pageToast.textContent.replace(/\s+/g, ' ').trim(),
            pageToast.dataset.toastType === 'error',
            pageToast.dataset.toastTitle || '',
        );
        pageToast.remove();
    }

    bindPhoneInputs();
})();
