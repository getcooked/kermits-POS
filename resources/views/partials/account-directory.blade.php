{{-- Shared look and behaviour for the Admin, Cashier and Customer account pages (matches Admin Products). --}}
@once
@push('styles')
<style>
.ad-page{--ad-line:#dfe1d7;--ad-muted:#6c7068;--ad-ink:#171817;--ad-surface:#fffefa}
.ad-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
.ad-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px!important}
.ad-head h1{margin:0;font-size:28px;line-height:1.2;letter-spacing:-.035em}
.ad-head p{margin:4px 0 0;color:var(--ad-muted);font-size:14px}
.ad-head-actions{flex:1;min-width:0;display:flex;align-items:center;justify-content:flex-end;gap:10px}
.ad-head-actions .k-search{width:min(380px,100%)}
.ad-primary{height:46px;flex:0 0 auto;display:inline-flex;align-items:center;gap:8px;padding:0 18px;border:0;border-radius:12px;background:var(--ad-ink);color:#fff;font:inherit;font-size:14px;font-weight:700;white-space:nowrap;cursor:pointer}
.ad-primary:hover{background:#30322e}
.ad-primary svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;transition:transform .18s}
.ad-primary[aria-expanded="true"] svg{transform:rotate(45deg)}
.ad-message{padding:12px;margin-bottom:16px;border-radius:10px}
.ad-create{margin:0 0 20px;padding:24px!important}
.ad-create[hidden]{display:none}
.ad-create h2{margin:0 0 4px;font-size:20px}
.ad-create>p{margin:0 0 18px;color:var(--ad-muted);font-size:13px}
.ad-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.ad-form-grid .field{margin:0;min-width:0}
.ad-form-grid label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#3b3f38}
.ad-form-grid small{display:block;margin-top:5px;color:#80857c;font-size:12px}
.ad-span{grid-column:1/-1}
.ad-form-actions{display:flex;justify-content:flex-end;gap:10px}
.ad-form-actions .button{width:auto;padding-inline:24px}

/* Count tiles double as status filters */
.ad-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}
.ad-stat{display:grid;gap:4px;padding:16px 18px;border:1px solid var(--ad-line);border-radius:14px;background:var(--ad-surface);color:inherit;font:inherit;text-align:left;cursor:pointer}
.ad-stat span{color:var(--ad-muted);font-size:13px;font-weight:600}
.ad-stat strong{font-size:26px;line-height:1.1;letter-spacing:-.03em}
.ad-stat:hover{border-color:#b9beb0}
.ad-stat[aria-pressed="true"]{border-color:var(--ad-ink);box-shadow:inset 0 0 0 1px var(--ad-ink)}
.ad-stat-disabled strong{color:#b42318}

/* Account cards */
.ad-list{display:grid;gap:10px}
.ad-row{display:grid;grid-template-columns:48px minmax(0,1fr) auto auto;align-items:center;gap:16px;padding:14px 16px;border:1px solid var(--ad-line);border-radius:14px;background:var(--ad-surface)}
.ad-row[hidden]{display:none}
.ad-avatar{width:48px;height:48px;display:grid;place-items:center;border-radius:50%;background:#aab514;color:var(--ad-ink);font-size:18px;font-weight:800}
.ad-row.is-disabled .ad-avatar{background:#e4e5df;color:#8a8d85}
.ad-row.is-disabled .ad-identity strong{color:#6c7068}
.ad-identity{min-width:0}
.ad-identity strong{display:block;font-size:15px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ad-identity strong em{font-style:normal;font-weight:600;color:var(--ad-muted);font-size:13px}
.ad-identity small{display:block;color:var(--ad-muted);font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ad-chips{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}
.ad-chip{padding:4px 10px;border-radius:999px;background:#f0f1ea;color:#3b3f38;font-size:12px;font-weight:700;white-space:nowrap}
.ad-chip.is-money{background:#e7f4eb;color:#23683c}
.ad-actions{display:flex;align-items:center;gap:8px}
.ad-actions form{margin:0}
.ad-status{position:relative;height:38px;display:inline-flex;align-items:center;gap:8px;padding:0 11px 0 9px;border:1px solid var(--ad-line);border-radius:10px;background:#fff;color:#3b3f38;font:inherit;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer}
.ad-status:hover{border-color:#aeb39f}
.ad-status-track{position:relative;flex:0 0 32px;height:18px;border-radius:999px;background:#cfd2c8;transition:background-color .15s}
.ad-status-track::after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
.ad-status[aria-checked="true"] .ad-status-track{background:#7f8a0c}
.ad-status[aria-checked="true"] .ad-status-track::after{transform:translateX(14px)}
.ad-self{padding:0 10px;color:var(--ad-muted);font-size:13px;font-weight:700}
.ad-edit{height:38px;display:inline-flex;align-items:center;gap:7px;padding:0 14px;border:1px solid var(--ad-line);border-radius:10px;background:#fff;color:var(--ad-ink);font:inherit;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;cursor:pointer}
.ad-edit:hover{background:#edf0cf;border-color:#c6cc8d}
.ad-edit svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linejoin:round;stroke-linecap:round}
.ad-status:focus-visible,.ad-edit:focus-visible,.ad-stat:focus-visible{outline:2px solid #98a20f;outline-offset:2px}
.ad-empty{padding:40px 16px;border:1px dashed #cfd2c8;border-radius:14px;color:var(--ad-muted);text-align:center}
.ad-empty[hidden]{display:none}

/* Edit drawer */
body.ad-drawer-open{overflow:hidden}
body.ad-drawer-open .admin-workspace{overflow:hidden!important}
.ad-drawer{position:fixed;inset:0;z-index:3000;display:flex;justify-content:flex-end}
.ad-drawer[hidden]{display:none}
.ad-drawer-backdrop{position:absolute;inset:0;background:rgba(16,18,15,.48);backdrop-filter:blur(3px)}
.ad-drawer-panel{position:relative;width:min(480px,100%);height:100%;display:flex;flex-direction:column;background:#fffefa;box-shadow:-24px 0 60px rgba(10,12,9,.2);animation:ad-slide .24s cubic-bezier(.22,.72,.18,1)}
.ad-drawer-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 18px;border-bottom:1px solid #e3e4dc}
.ad-drawer-head small{color:var(--ad-muted);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.ad-drawer-head h2{margin:4px 0 0;font-size:21px;letter-spacing:-.02em;overflow-wrap:anywhere}
.ad-close{width:38px;height:38px;display:grid;place-items:center;padding:0;border:0;border-radius:10px;background:transparent;color:#555b52;cursor:pointer}
.ad-close:hover{background:#eff0ea}
.ad-close svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.ad-drawer-body{flex:1;overflow-y:auto;padding:22px 24px;display:grid;align-content:start;gap:20px}
.ad-drawer-body .ad-form-grid{grid-template-columns:minmax(0,1fr)}
.ad-drawer-body .button{width:100%}
.ad-danger{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;border:1px solid #efc8c5;border-radius:12px;background:#fff8f7}
.ad-danger div{display:grid;gap:3px}
.ad-danger strong{color:#b42318;font-size:14px}
.ad-danger small{color:#80655f;font-size:12px}
.ad-danger button{flex:0 0 auto;height:38px;padding:0 14px;border:1px solid #e3b4b0;border-radius:10px;background:#fff;color:#b42318;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.ad-danger button:hover{background:#b42318;border-color:#b42318;color:#fff}
@keyframes ad-slide{from{transform:translateX(40px);opacity:0}to{transform:none;opacity:1}}

@media(max-width:1100px){.ad-head{flex-direction:column;align-items:stretch}.ad-head-actions{justify-content:stretch}.ad-head-actions .k-search{width:100%}}
@media(max-width:900px){.ad-row{grid-template-columns:44px minmax(0,1fr) auto}.ad-avatar{width:44px;height:44px}.ad-chips{grid-column:2/-1;justify-content:flex-start}.ad-actions{grid-column:2/-1;flex-wrap:wrap}}
@media(max-width:640px){.ad-stats{display:flex;overflow-x:auto;scrollbar-width:none}.ad-stat{flex:0 0 150px}.ad-form-grid{grid-template-columns:minmax(0,1fr)}.ad-head-actions{flex-direction:column;align-items:stretch}.ad-primary{justify-content:center}.ad-drawer-head,.ad-drawer-body{padding-inline:16px}.ad-danger{flex-direction:column;align-items:stretch}}
@media(prefers-reduced-motion:reduce){.ad-drawer-panel{animation:none}}
</style>
@endpush
@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    // Filters live outside the re-rendered markup so they survive AJAX saves.
    const view = { status: 'all', query: '' };
    let drawerTrigger = null;
    const normalize = value => (value || '').toLocaleLowerCase().trim();

    const apply = () => {
        const page = document.querySelector('[data-ad-page]');
        if (!page) return;
        const rows = [...page.querySelectorAll('[data-ad-row]')];
        const terms = normalize(view.query).split(/\s+/).filter(Boolean);
        let shown = 0;
        rows.forEach(row => {
            const matchesStatus = view.status === 'all' || row.dataset.status === view.status;
            const matchesSearch = terms.every(term => row.dataset.search.includes(term));
            row.hidden = !(matchesStatus && matchesSearch);
            if (!row.hidden) shown++;
        });
        page.querySelectorAll('[data-ad-status]').forEach(tile => tile.setAttribute('aria-pressed', String(tile.dataset.adStatus === view.status)));
        const empty = page.querySelector('[data-ad-empty]');
        if (empty) empty.hidden = rows.length === 0 || shown > 0;
        const search = page.querySelector('[data-ad-search]');
        if (search && search !== document.activeElement && search.value !== view.query) search.value = view.query;
        document.body.classList.toggle('ad-drawer-open', Boolean(document.querySelector('.ad-drawer:not([hidden])')));
    };

    const closeDrawer = drawer => {
        drawer.hidden = true;
        drawer.querySelectorAll('form').forEach(form => form.reset());
        document.body.classList.toggle('ad-drawer-open', Boolean(document.querySelector('.ad-drawer:not([hidden])')));
        if (drawerTrigger?.isConnected) drawerTrigger.focus({ preventScroll: true });
        drawerTrigger = null;
    };

    document.addEventListener('input', event => {
        if (!event.target.matches?.('[data-ad-search]')) return;
        view.query = event.target.value;
        apply();
    });

    document.addEventListener('click', event => {
        const tile = event.target.closest('[data-ad-status]');
        if (tile) {
            view.status = tile.dataset.adStatus;
            apply();
            return;
        }
        const opener = event.target.closest('[data-ad-open]');
        if (opener) {
            const drawer = document.getElementById(opener.dataset.adOpen);
            if (!drawer) return;
            drawerTrigger = opener;
            drawer.hidden = false;
            document.body.classList.add('ad-drawer-open');
            drawer.querySelector('input:not([type="hidden"])')?.focus({ preventScroll: true });
            return;
        }
        const closer = event.target.closest('[data-ad-close]');
        if (closer) {
            closeDrawer(closer.closest('.ad-drawer'));
            return;
        }
        const createToggle = event.target.closest('[data-ad-create-toggle]');
        if (createToggle) {
            const panel = document.getElementById(createToggle.getAttribute('aria-controls'));
            if (!panel) return;
            const opening = panel.hidden;
            panel.hidden = !opening;
            createToggle.setAttribute('aria-expanded', String(opening));
            createToggle.querySelector('span').textContent = opening ? 'Close Form' : createToggle.dataset.label;
            if (opening) panel.querySelector('input:not([type="hidden"]):not([tabindex="-1"])')?.focus();
        }
    });

    document.addEventListener('keydown', event => {
        const drawer = document.querySelector('.ad-drawer:not([hidden])');
        if (!drawer || document.querySelector('.app-alert-layer')) return;
        if (event.key === 'Escape') {
            closeDrawer(drawer);
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...drawer.querySelectorAll('button, [href], input:not([type="hidden"]), select, textarea')]
            .filter(element => !element.disabled && element.getClientRects().length);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    });

    document.addEventListener('ajax:content-updated', apply);
    apply();
})();
</script>
@endpush
@endonce
