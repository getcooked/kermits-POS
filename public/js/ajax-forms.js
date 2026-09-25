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

    let closeActiveAlert = null;

    const showAlert = ({ message, isError = false, title = '', confirm = false }) => new Promise(resolve => {
        closeActiveAlert?.(false, true);

        const previousFocus = document.activeElement;
        const layer = document.createElement('div');
        layer.className = 'app-alert-layer';

        const toast = document.createElement('div');
        toast.className = `ajax-form-toast${isError ? ' is-error' : ''}${confirm ? ' is-confirm' : ''}`;
        toast.setAttribute('role', isError || confirm ? 'alertdialog' : 'dialog');
        toast.setAttribute('aria-modal', 'true');
        toast.setAttribute('aria-labelledby', 'app-alert-title');
        toast.setAttribute('aria-describedby', 'app-alert-message');

        const icon = document.createElement('span');
        icon.className = 'ajax-form-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = confirm ? '?' : (isError ? '!' : '\u2713');

        const copy = document.createElement('span');
        copy.className = 'ajax-form-toast-copy';
        const heading = document.createElement('strong');
        heading.id = 'app-alert-title';
        heading.textContent = title || (confirm ? 'Please confirm' : (isError ? 'Something went wrong' : 'Success'));
        copy.append(heading);
        const description = document.createElement('span');
        description.id = 'app-alert-message';
        description.textContent = message;
        copy.append(description);

        const close = document.createElement('button');
        close.className = 'ajax-form-toast-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '\u00d7';

        const actions = document.createElement('div');
        actions.className = 'app-alert-actions';

        const primary = document.createElement('button');
        primary.className = 'app-alert-button is-primary';
        primary.type = 'button';
        primary.textContent = confirm ? 'Confirm' : 'OK';

        let cancel = null;
        if (confirm) {
            cancel = document.createElement('button');
            cancel.className = 'app-alert-button';
            cancel.type = 'button';
            cancel.textContent = 'Cancel';
            actions.append(cancel);
        }
        actions.append(primary);

        toast.append(icon, copy, close, actions);
        if (!confirm) {
            const progress = document.createElement('span');
            progress.className = 'ajax-form-toast-progress';
            progress.setAttribute('aria-hidden', 'true');
            toast.append(progress);
        }
        layer.append(toast);
        document.body.append(layer);

        let hideTimer;
        let removeTimer;
        let settled = false;
        const dismiss = (result = false, immediately = false) => {
            if (settled) return;
            settled = true;
            window.clearTimeout(hideTimer);
            window.clearTimeout(removeTimer);
            document.removeEventListener('keydown', onKeydown);
            if (closeActiveAlert === dismiss) closeActiveAlert = null;
            const remove = () => {
                layer.remove();
                if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
                resolve(result);
            };
            if (immediately) {
                remove();
                return;
            }
            layer.classList.add('is-hiding');
            removeTimer = window.setTimeout(remove, 200);
        };
        const onKeydown = event => {
            if (event.key === 'Escape') dismiss(false);
            if (event.key === 'Tab') {
                const focusable = [...toast.querySelectorAll('button:not([disabled])')];
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };
        closeActiveAlert = dismiss;
        close.addEventListener('click', () => dismiss(false));
        primary.addEventListener('click', () => dismiss(true));
        cancel?.addEventListener('click', () => dismiss(false));
        layer.addEventListener('click', event => {
            if (event.target === layer) dismiss(false);
        });
        document.addEventListener('keydown', onKeydown);
        window.requestAnimationFrame(() => primary.focus());
        if (!confirm) hideTimer = window.setTimeout(() => dismiss(true), 5000);
    });

    const showToast = (message, isError = false, title = '') => showAlert({ message, isError, title });

    window.KermitsAlert = Object.freeze({
        show: (message, title = 'Notice') => showAlert({ message: String(message), title }),
        success: (message, title = 'Success') => showAlert({ message: String(message), title }),
        error: (message, title = 'Something went wrong') => showAlert({ message: String(message), title, isError: true }),
        confirm: (message, title = 'Please confirm') => showAlert({ message: String(message), title, confirm: true }),
    });
    window.alert = message => { window.KermitsAlert.show(message); };

    const confirmedForms = new WeakSet();
    document.addEventListener('submit', event => {
        const form = event.target.closest('form[data-confirm]');
        if (!form || confirmedForms.has(form)) {
            if (form) confirmedForms.delete(form);
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter;
        window.KermitsAlert.confirm(form.dataset.confirm, form.dataset.confirmTitle || 'Please confirm').then(confirmed => {
            if (!confirmed) return;
            confirmedForms.add(form);
            form.requestSubmit(submitter || undefined);
        });
    }, true);

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
    } else if (document.body.dataset.feedback) {
        const isError = document.body.dataset.feedback === 'error';
        const source = document.querySelector(isError ? errorSelector : successSelector);
        const message = source?.textContent.replace(/\s+/g, ' ').trim();
        if (message) {
            if (!isError) source.hidden = true;
            showToast(message, isError);
        }
    }

    bindPhoneInputs();
})();
