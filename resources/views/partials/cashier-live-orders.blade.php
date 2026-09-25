<div id="cashier-order-toast" class="cashier-order-toast" role="status" aria-live="assertive" aria-atomic="true" hidden>
    <span class="cashier-order-toast-icon" aria-hidden="true">!</span>
    <div>
        <strong id="cashier-order-toast-title">New customer order received</strong>
        <span id="cashier-order-toast-message"></span>
    </div>
    <a id="cashier-order-toast-review" href="{{ route('cashier.orders.index') }}">Review</a>
    <button id="cashier-order-toast-close" type="button" aria-label="Dismiss notification">&times;</button>
</div>

<style>
.admin-sidebar nav a .cashier-order-badge{box-sizing:border-box;width:auto!important;min-width:23px;height:23px;margin-left:auto;padding:0 7px;border-radius:999px;background:#d93636;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;line-height:1}
.admin-sidebar nav a .cashier-order-badge[hidden]{display:none!important}
.cashier-order-toast{position:fixed;z-index:1000;top:22px;right:22px;width:min(440px,calc(100% - 28px));box-sizing:border-box;padding:15px 48px 15px 15px;border:1px solid #d7dbc9;border-left:5px solid #aab514;border-radius:15px;background:#fff;box-shadow:0 18px 50px rgba(21,24,18,.22);display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:12px;animation:cashier-order-in .2s ease-out}
.cashier-order-toast[hidden]{display:none}
.cashier-order-toast-icon{width:42px;height:42px;border-radius:50%;background:#eef1d5;color:#626b00;display:grid;place-items:center;font-size:21px;font-weight:950}
.cashier-order-toast>div{min-width:0;display:grid;gap:4px}.cashier-order-toast strong{font-size:14px}.cashier-order-toast>div span{color:#697064;font-size:12px;line-height:1.4}.cashier-order-toast>a{min-height:36px;padding:0 13px;border-radius:9px;background:#171817;color:#fff;display:inline-flex;align-items:center;text-decoration:none;font-size:12px;font-weight:850}.cashier-order-toast>button{position:absolute;top:7px;right:9px;width:30px;height:30px;border:0;background:transparent;color:#70756c;font-size:23px;cursor:pointer}
@keyframes cashier-order-in{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:780px){.admin-sidebar nav a .cashier-order-badge{display:inline-flex!important}.admin-sidebar nav a .cashier-order-badge[hidden]{display:none!important}.cashier-order-toast{top:12px;right:14px;grid-template-columns:36px minmax(0,1fr);padding:13px 42px 13px 13px}.cashier-order-toast-icon{width:36px;height:36px}.cashier-order-toast>a{grid-column:2;width:max-content}.cashier-order-toast>div span{font-size:11px}}
</style>

<script>
(() => {
    const endpoint = @json(route('cashier.orders.notifications'));
    const badge = document.getElementById('cashier-order-badge');
    const toast = document.getElementById('cashier-order-toast');
    const title = document.getElementById('cashier-order-toast-title');
    const message = document.getElementById('cashier-order-toast-message');
    const review = document.getElementById('cashier-order-toast-review');
    const close = document.getElementById('cashier-order-toast-close');
    const originalTitle = document.title.replace(/^\(\d+\)\s*/, '');
    let latestOrderId = null;
    let timer = null;
    let audioContext = null;

    const unlockAudio = () => {
        try {
            audioContext ??= new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') audioContext.resume();
        } catch (_) {}
    };

    const updateCount = (count) => {
        const pending = Number(count) || 0;
        badge.textContent = pending > 99 ? '99+' : String(pending);
        badge.hidden = pending === 0;
        document.title = pending > 0 ? `(${pending}) ${originalTitle}` : originalTitle;
    };

    const playChime = () => {
        try {
            audioContext ??= new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') audioContext.resume();
            const now = audioContext.currentTime;
            [660, 880].forEach((frequency, index) => {
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.frequency.value = frequency;
                oscillator.type = 'sine';
                gain.gain.setValueAtTime(0.0001, now + index * .16);
                gain.gain.exponentialRampToValueAtTime(.16, now + index * .16 + .02);
                gain.gain.exponentialRampToValueAtTime(.0001, now + index * .16 + .14);
                oscillator.connect(gain).connect(audioContext.destination);
                oscillator.start(now + index * .16);
                oscillator.stop(now + index * .16 + .15);
            });
        } catch (_) {}
    };

    const showNotification = (orders) => {
        const order = orders[orders.length - 1];
        const extra = orders.length - 1;
        title.textContent = extra > 0 ? `${orders.length} new customer orders` : 'New customer order received';
        message.textContent = extra > 0
            ? `Latest: #${order.number} · ${order.customer} · ₱${order.total}`
            : `#${order.number} · ${order.customer} · ₱${order.total} · ${order.payment_method}`;
        review.href = order.review_url;
        toast.hidden = false;
        playChime();

        if ('Notification' in window && Notification.permission === 'granted') {
            const browserNotice = new Notification(title.textContent, {
                body: message.textContent,
                tag: `cashier-order-${order.id}`,
            });
            browserNotice.onclick = () => {
                window.focus();
                window.location.href = order.review_url;
            };
        }
    };

    const poll = async () => {
        try {
            const url = new URL(endpoint, window.location.origin);
            if (latestOrderId !== null) url.searchParams.set('after', latestOrderId);
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
            });
            if (!response.ok) return;

            const data = await response.json();
            updateCount(data.pending_count);

            if (latestOrderId !== null && Array.isArray(data.orders) && data.orders.length) {
                showNotification(data.orders);
            }

            latestOrderId = Math.max(latestOrderId ?? 0, Number(data.latest_order_id) || 0);
        } catch (_) {
            // A temporary connection failure is retried automatically.
        } finally {
            window.clearTimeout(timer);
            timer = window.setTimeout(poll, 3000);
        }
    };

    close.addEventListener('click', () => { toast.hidden = true; });
    window.addEventListener('pointerdown', unlockAudio, {once: true});
    window.addEventListener('keydown', unlockAudio, {once: true});
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            window.clearTimeout(timer);
            poll();
        }
    });
    poll();
})();
</script>
