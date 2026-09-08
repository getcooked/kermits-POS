(() => {
    document.querySelectorAll('[data-schedule]').forEach(panel => {
        if (panel.dataset.ready) return;
        panel.dataset.ready = 'true';
        const form = panel.closest('form');
        const input = form.querySelector('[name="reservation_at"]');
        const select = panel.querySelector('[data-slots]');
        const message = panel.querySelector('[data-schedule-message]');
        let controller;
        function hours() {
            const time = input.value.split('T')[1] || '';
            input.setCustomValidity(time && (time < '08:00' || time > '22:00') ? 'Choose a start time from 8:00 AM to 10:00 PM. We close at 11:00 PM.' : '');
        }
        async function refresh() {
            hours();
            controller?.abort();
            panel.setAttribute('aria-busy', 'false');
            select.replaceChildren(new Option('Choose an available time', ''));
            message.textContent = '';
            const date = input.value.split('T')[0];
            if (!date) return;
            const type = form.querySelector('[name="type"]:checked')?.value || 'table';
            const guests = form.querySelector(type === 'exclusive' ? '[name="guests"]' : '[name="table_size"]')?.value || '1';
            controller = new AbortController();
            const currentRequest = controller;
            panel.setAttribute('aria-busy', 'true');
            message.textContent = 'Checking availability…';
            try {
                const response = await fetch(panel.dataset.url + '?' + new URLSearchParams({date, type, guests}), {signal: controller.signal, headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error('Unable to check this date. Choose a future date.');
                const result = await response.json();
                let available = 0;
                for (const slot of result.data) {
                    const option = new Option(slot.label + (slot.available ? '' : ' — Unavailable'), slot.start);
                    option.disabled = !slot.available;
                    option.selected = slot.start === input.value;
                    select.add(option);
                    if (slot.available) available++;
                }
                message.textContent = available ? 'Availability is checked again when you submit.' : 'No suitable tables or venue are available on this date. Please choose another date.';
            } catch (error) {
                if (error.name !== 'AbortError') message.textContent = error.message;
            } finally {
                if (controller === currentRequest) panel.setAttribute('aria-busy', 'false');
            }
        }
        select.addEventListener('change', () => {if (select.value) {input.value = select.value; hours();}});
        input.addEventListener('input', hours);
        input.addEventListener('change', refresh);
        form.querySelectorAll('[name="type"], [name="table_size"], [name="guests"]').forEach(el => el.addEventListener('change', refresh));
        refresh();
    });
})();
