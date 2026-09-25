<div id="cashier-order-toast" class="cashier-order-toast" role="status" aria-live="assertive" aria-atomic="true" hidden>
    <span class="cashier-order-toast-icon" aria-hidden="true">!</span>
    <div>
        <strong id="cashier-order-toast-title">New customer order received</strong>
        <span id="cashier-order-toast-message"></span>
    </div>
    <a id="cashier-order-toast-review" href="{{ route('cashier.orders.index') }}">Review order</a>
    <button id="cashier-order-toast-close" type="button" aria-label="Dismiss notification">&times;</button>
</div>

<div id="cashier-alert-setup" class="cashier-alert-setup">
    <button id="cashier-alert-control" type="button" aria-pressed="false">
        <span class="cashier-alert-bell" aria-hidden="true">&#128276;</span>
        <span>
            <strong id="cashier-alert-control-label">Enable sound &amp; desktop alerts</strong>
            <small id="cashier-alert-control-status">Never miss a new customer order</small>
        </span>
    </button>
</div>

<style>
.admin-sidebar nav a .cashier-order-badge{box-sizing:border-box;width:auto!important;min-width:23px;height:23px;margin-left:auto;padding:0 7px;border-radius:999px;background:#d93636;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;line-height:1}
.admin-sidebar nav a .cashier-order-badge[hidden]{display:none!important}
.cashier-order-toast{position:fixed;z-index:1000;top:22px;right:22px;width:min(440px,calc(100% - 28px));box-sizing:border-box;padding:15px 48px 15px 15px;border:1px solid #d7dbc9;border-left:5px solid #aab514;border-radius:15px;background:#fff;box-shadow:0 18px 50px rgba(21,24,18,.22);display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:12px;animation:cashier-order-in .2s ease-out}
.cashier-order-toast[hidden]{display:none}
.cashier-order-toast-icon{width:42px;height:42px;border-radius:50%;background:#eef1d5;color:#626b00;display:grid;place-items:center;font-size:21px;font-weight:950}
.cashier-order-toast>div{min-width:0;display:grid;gap:4px}.cashier-order-toast strong{font-size:14px}.cashier-order-toast>div span{color:#697064;font-size:12px;line-height:1.4}.cashier-order-toast>a{min-height:36px;padding:0 13px;border-radius:9px;background:#171817;color:#fff;display:inline-flex;align-items:center;text-decoration:none;font-size:12px;font-weight:850}.cashier-order-toast>button{position:absolute;top:7px;right:9px;width:30px;height:30px;border:0;background:transparent;color:#70756c;font-size:23px;cursor:pointer}
.cashier-alert-setup{position:fixed;z-index:990;right:22px;bottom:20px}.cashier-alert-setup button{max-width:310px;min-height:54px;padding:8px 13px 8px 9px;border:1px solid #d7dbc9;border-radius:14px;background:#fff;color:#252720;box-shadow:0 10px 32px rgba(21,24,18,.14);display:flex;align-items:center;gap:10px;text-align:left;cursor:pointer}.cashier-alert-setup button:hover{border-color:#aab514}.cashier-alert-setup button:focus-visible{outline:3px solid rgba(170,181,20,.35);outline-offset:2px}.cashier-alert-setup button[aria-pressed="true"]{border-color:#bec76a;background:#f7f8e9}.cashier-alert-bell{width:36px;height:36px;border-radius:10px;background:#eef1d5;display:grid;place-items:center;font-size:17px}.cashier-alert-setup button>span:last-child{display:grid;gap:2px}.cashier-alert-setup strong{font-size:12px}.cashier-alert-setup small{color:#6d7368;font-size:10px;line-height:1.25}
@keyframes cashier-order-in{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:780px){.admin-sidebar nav a .cashier-order-badge{display:inline-flex!important}.admin-sidebar nav a .cashier-order-badge[hidden]{display:none!important}.cashier-order-toast{top:12px;right:14px;grid-template-columns:36px minmax(0,1fr);padding:13px 42px 13px 13px}.cashier-order-toast-icon{width:36px;height:36px}.cashier-order-toast>a{grid-column:2;width:max-content}.cashier-order-toast>div span{font-size:11px}.cashier-alert-setup{right:14px;bottom:14px}.cashier-alert-setup button{max-width:260px}}
</style>

<script>
(() => {
    const endpoint = @json(route('cashier.orders.notifications'));
    const ordersUrl = @json(route('cashier.orders.index'));
    const preferenceKey = 'cashier-order-alerts-enabled';
    const badge = document.getElementById('cashier-order-badge');
    const toast = document.getElementById('cashier-order-toast');
    const title = document.getElementById('cashier-order-toast-title');
    const message = document.getElementById('cashier-order-toast-message');
    const review = document.getElementById('cashier-order-toast-review');
    const close = document.getElementById('cashier-order-toast-close');
    const alertControl = document.getElementById('cashier-alert-control');
    const alertControlLabel = document.getElementById('cashier-alert-control-label');
    const alertControlStatus = document.getElementById('cashier-alert-control-status');

    if (!badge || !toast || !title || !message || !review || !close || !alertControl) return;

    const originalTitle = document.title.replace(/^\(\d+\)\s*/, '');
    let latestOrderId = null;
    let pendingCount = 0;
    let timer = null;
    let reminderTimer = null;
    let audioContext = null;
    let alertsEnabled = false;

    try {
        alertsEnabled = window.localStorage.getItem(preferenceKey) === '1';
    } catch (_) {}

    const saveAlertPreference = () => {
        try {
            window.localStorage.setItem(preferenceKey, alertsEnabled ? '1' : '0');
        } catch (_) {}
    };

    const desktopPermission = () => 'Notification' in window ? Notification.permission : 'unsupported';

    const updateAlertControl = () => {
        alertControl.setAttribute('aria-pressed', alertsEnabled ? 'true' : 'false');

        if (!alertsEnabled) {
            alertControlLabel.textContent = 'Enable sound & desktop alerts';
            alertControlStatus.textContent = 'Never miss a new customer order';
            return;
        }

        alertControlLabel.textContent = desktopPermission() === 'granted' ? 'Order alerts are on' : 'Sound alerts are on';
        alertControlStatus.textContent = desktopPermission() === 'denied'
            ? 'Desktop alerts are blocked in browser settings'
            : desktopPermission() === 'unsupported'
                ? 'Desktop alerts are unavailable in this browser'
                : 'Click to turn sound and desktop alerts off';
    };

    const unlockAudio = async () => {
        if (!alertsEnabled) return;

        try {
            audioContext ??= new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') await audioContext.resume();
        } catch (_) {}
    };

    const playChime = async (gentle = false) => {
        if (!alertsEnabled) return;

        try {
            await unlockAudio();
            if (!audioContext || audioContext.state !== 'running') return;

            const now = audioContext.currentTime;
            const frequencies = gentle ? [520] : [660, 880];
            frequencies.forEach((frequency, index) => {
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.frequency.value = frequency;
                oscillator.type = 'sine';
                gain.gain.setValueAtTime(0.0001, now + index * .16);
                gain.gain.exponentialRampToValueAtTime(gentle ? .08 : .16, now + index * .16 + .02);
                gain.gain.exponentialRampToValueAtTime(.0001, now + index * .16 + .14);
                oscillator.connect(gain).connect(audioContext.destination);
                oscillator.start(now + index * .16);
                oscillator.stop(now + index * .16 + .15);
            });
        } catch (_) {}
    };

    const showDesktopNotification = (notificationTitle, notificationBody, tag, url) => {
        if (!alertsEnabled || desktopPermission() !== 'granted' || !document.hidden) return;

        const browserNotice = new Notification(notificationTitle, {
            body: notificationBody,
            tag,
        });
        browserNotice.onclick = () => {
            window.focus();
            window.location.href = url;
        };
    };

    const scheduleReminder = (delay = 45000) => {
        window.clearTimeout(reminderTimer);
        reminderTimer = null;
        if (pendingCount === 0) return;

        reminderTimer = window.setTimeout(() => {
            reminderTimer = null;
            if (pendingCount === 0) return;

            const label = pendingCount === 1 ? 'order is' : 'orders are';
            const reminderTitle = 'Pending orders need attention';
            const reminderMessage = `${pendingCount} customer ${label} still waiting for review.`;
            title.textContent = reminderTitle;
            message.textContent = reminderMessage;
            review.href = ordersUrl;
            review.textContent = 'View pending orders';
            toast.hidden = false;
            playChime(true);
            showDesktopNotification(reminderTitle, reminderMessage, 'cashier-pending-orders', ordersUrl);
            scheduleReminder(60000);
        }, delay);
    };

    const updateCount = (count) => {
        const previousCount = pendingCount;
        pendingCount = Number(count) || 0;
        badge.textContent = pendingCount > 99 ? '99+' : String(pendingCount);
        badge.hidden = pendingCount === 0;
        document.title = pendingCount > 0 ? `(${pendingCount}) ${originalTitle}` : originalTitle;

        if (pendingCount === 0) {
            window.clearTimeout(reminderTimer);
            reminderTimer = null;
            return;
        }

        if (previousCount === 0 || reminderTimer === null) scheduleReminder();
    };

    const showNewOrderNotification = (orders) => {
        const order = orders[orders.length - 1];
        const extra = orders.length - 1;
        title.textContent = extra > 0 ? `${orders.length} new customer orders` : 'New customer order received';
        message.textContent = extra > 0
            ? `Latest: #${order.number} · ${order.customer} · ₱${order.total}`
            : `#${order.number} · ${order.customer} · ₱${order.total} · ${order.payment_method}`;
        review.href = order.review_url;
        review.textContent = 'Review order';
        toast.hidden = false;
        playChime();
        showDesktopNotification(title.textContent, message.textContent, `cashier-order-${order.id}`, order.review_url);
        scheduleReminder();
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
                showNewOrderNotification(data.orders);
            }

            latestOrderId = Math.max(latestOrderId ?? 0, Number(data.latest_order_id) || 0);
        } catch (_) {
            // A temporary connection failure is retried automatically.
        } finally {
            window.clearTimeout(timer);
            timer = window.setTimeout(poll, 3000);
        }
    };

    alertControl.addEventListener('click', async () => {
        alertsEnabled = !alertsEnabled;
        saveAlertPreference();

        if (alertsEnabled) {
            await unlockAudio();
            if (desktopPermission() === 'default') {
                try {
                    await Notification.requestPermission();
                } catch (_) {}
            }
        }

        updateAlertControl();
    });

    close.addEventListener('click', () => { toast.hidden = true; });
    window.addEventListener('pointerdown', unlockAudio, {once: true});
    window.addEventListener('keydown', unlockAudio, {once: true});
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            window.clearTimeout(timer);
            poll();
        }
    });

    updateAlertControl();
    poll();
})();
</script>
