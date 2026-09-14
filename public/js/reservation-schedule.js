(() => {
    document.querySelectorAll('[data-schedule]').forEach(panel => {
        if (panel.dataset.ready) return;
        panel.dataset.ready = 'true';

        const form = panel.closest('form');
        const input = form?.querySelector('[name="reservation_at"]');
        const dateInput = panel.querySelector('[data-reservation-date]');
        const select = panel.querySelector('[data-slots]');
        const message = panel.querySelector('[data-schedule-message]');
        let controller;

        if (!form || !input || !dateInput || !select || !message) return;

        const normalizedValue = () => input.value.replace(' ', 'T').slice(0, 16);

        async function refresh() {
            controller?.abort();
            panel.setAttribute('aria-busy', 'false');
            select.replaceChildren(new Option(dateInput.value ? 'Choose an available time' : 'Choose a date first', ''));
            message.textContent = '';

            const date = dateInput.value;
            if (!date) {
                select.disabled = true;
                input.value = '';
                return;
            }

            const type = form.querySelector('[name="type"]:checked')?.value || 'table';
            const guests = form.querySelector(type === 'exclusive' ? '[name="guests"]' : '[name="table_size"]')?.value || '1';
            controller = new AbortController();
            const currentRequest = controller;
            panel.setAttribute('aria-busy', 'true');
            select.disabled = false;
            message.textContent = 'Checking availability…';

            try {
                const response = await fetch(panel.dataset.url + '?' + new URLSearchParams({date, type, guests}), {
                    signal: controller.signal,
                    headers: {'Accept': 'application/json'},
                });
                if (!response.ok) throw new Error('Unable to check this date. Choose a future date.');

                const result = await response.json();
                let available = 0;
                for (const slot of result.data) {
                    if (!slot.available) continue;

                    const option = new Option(slot.label, slot.start);
                    option.selected = slot.start === normalizedValue();
                    select.add(option);
                    available++;
                }

                if (!select.value) input.value = '';
                message.textContent = available
                    ? 'Choose one of the available times above.'
                    : 'No suitable tables or venue are available on this date. Please choose another date.';
            } catch (error) {
                if (error.name !== 'AbortError') message.textContent = error.message;
            } finally {
                if (controller === currentRequest) panel.setAttribute('aria-busy', 'false');
            }
        }

        select.addEventListener('change', () => {
            input.value = select.value;
        });
        dateInput.addEventListener('change', () => {
            input.value = '';
            refresh();
        });
        form.querySelectorAll('[name="type"], [name="table_size"], [name="guests"]').forEach(element => {
            element.addEventListener('change', () => {
                input.value = '';
                refresh();
            });
        });

        refresh();
    });
})();
