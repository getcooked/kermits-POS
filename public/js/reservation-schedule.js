(() => {
    const REFRESH_INTERVAL_MS = 60000;

    document.querySelectorAll('[data-schedule]').forEach(panel => {
        if (panel.dataset.ready) return;
        panel.dataset.ready = 'true';

        const form = panel.closest('form');
        const input = form?.querySelector('[name="reservation_at"]');
        const dateInput = panel.querySelector('[data-reservation-date]');
        const select = panel.querySelector('[data-slots]');
        const message = panel.querySelector('[data-schedule-message]');
        const tablePicker = form?.querySelector('[data-table-picker]');
        let controller;

        if (!form || !input || !dateInput || !select || !message) return;

        const normalizedValue = () => input.value.replace(' ', 'T').slice(0, 16);
        const currentType = () => form.querySelector('[name="type"]:checked')?.value || 'table';
        const currentGuests = type => form.querySelector(type === 'exclusive' ? '[name="guests"]' : '[name="table_size"]')?.value || '1';

        // Tables too small for the party cannot be requested.
        function syncTablePicker(guests) {
            if (!tablePicker) return;
            for (const option of tablePicker.options) {
                if (!option.dataset.seats) continue;
                const fits = Number(option.dataset.seats) >= Number(guests);
                option.hidden = !fits;
                option.disabled = !fits;
            }
            if (tablePicker.selectedOptions[0]?.disabled) tablePicker.value = '';
        }

        function slotLabel(slot, specificTable) {
            if (specificTable || slot.tables_left == null) return slot.label;
            const unit = slot.tables_left === 1 ? 'table' : 'tables';
            return `${slot.label} · ${slot.tables_left} ${unit} left`;
        }

        async function refresh() {
            controller?.abort();
            panel.setAttribute('aria-busy', 'false');
            const type = currentType();
            const guests = currentGuests(type);
            syncTablePicker(guests);
            select.replaceChildren(new Option(dateInput.value ? 'Choose an available time' : 'Choose a date first', ''));
            message.textContent = '';

            const date = dateInput.value;
            if (!date) {
                select.disabled = true;
                input.value = '';
                return;
            }

            const table = type === 'table' && tablePicker?.value ? tablePicker.value : '';
            const params = new URLSearchParams({date, type, guests});
            if (table) params.set('table', table);
            controller = new AbortController();
            const currentRequest = controller;
            panel.setAttribute('aria-busy', 'true');
            select.disabled = false;
            message.textContent = 'Checking availability…';

            try {
                const response = await fetch(panel.dataset.url + '?' + params, {
                    signal: controller.signal,
                    headers: {'Accept': 'application/json'},
                });
                if (!response.ok) throw new Error('Unable to check this date. Choose a future date.');

                const result = await response.json();
                let available = 0;
                for (const slot of result.data) {
                    if (!slot.available) continue;

                    const option = new Option(slotLabel(slot, table), slot.start);
                    option.selected = slot.start === normalizedValue();
                    select.add(option);
                    available++;
                }

                if (!select.value) input.value = '';
                if (available) {
                    message.textContent = 'Choose one of the available times above.';
                } else if (table) {
                    message.textContent = 'The table you chose is fully booked on this date. Choose another table, any available table, or another date.';
                } else {
                    message.textContent = 'No suitable tables or venue are available on this date. Please choose another date.';
                }
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
        tablePicker?.addEventListener('change', refresh);

        // Keep counts current while the customer decides, without closing an open list.
        const refreshIfIdle = () => {
            if (!document.hidden && dateInput.value && document.activeElement !== select) refresh();
        };
        window.setInterval(refreshIfIdle, REFRESH_INTERVAL_MS);
        document.addEventListener('visibilitychange', refreshIfIdle);

        refresh();
    });
})();
