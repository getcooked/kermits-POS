<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('kermits-logo.jpg') }}">
    <title>@yield('title', 'Simple Login')</title>
    <style>
:root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f5f7fb; color: #172033; }
        button, input, textarea { font: inherit; }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: 100%; max-width: 420px; background: #fff; border: 1px solid #e7eaf0; border-radius: 18px; padding: 36px; box-shadow: 0 18px 50px rgba(26, 40, 75, .08); }
        .brand { display: inline-grid; place-items: center; width: 46px; height: 46px; border-radius: 13px; background: #315efb; color: white; font-weight: 800; margin-bottom: 24px; }
        .logo { display: block; width: 104px; height: 104px; object-fit: contain; border-radius: 50%; border: 1px solid #e7eaf0; background: #fff; padding: 8px; margin-bottom: 22px; box-shadow: 0 8px 24px rgba(26,40,75,.08); }
        .logo-small { width: 58px; height: 58px; object-fit: contain; border-radius: 50%; border: 1px solid #e7eaf0; background: #fff; padding: 5px; }
        h1 { margin: 0 0 8px; font-size: 28px; letter-spacing: -.03em; }
        .muted { color: #687286; margin: 0 0 28px; line-height: 1.6; }
        label { display: block; margin: 0 0 8px; font-size: 14px; font-weight: 650; }
        .field { margin-bottom: 18px; }
        input[type=email], input[type=password] { width: 100%; border: 1px solid #d7dce5; border-radius: 10px; padding: 12px 14px; outline: none; transition: .2s; }
        input:focus { border-color: #315efb; box-shadow: 0 0 0 3px rgba(49, 94, 251, .12); }
        .control { width: 100%; border: 1px solid #d7dce5; border-radius: 10px; padding: 11px 13px; outline: none; background: #fff; }
        .control:focus { border-color: #315efb; box-shadow: 0 0 0 3px rgba(49, 94, 251, .12); }
        .check { display: flex; gap: 9px; align-items: center; margin: 2px 0 22px; color: #536078; font-size: 14px; }
        .check input { width: 16px; height: 16px; }
        .button { width: 100%; border: 0; border-radius: 10px; padding: 13px 18px; background: #315efb; color: #fff; font-weight: 700; cursor: pointer; }
        .button:hover { background: #244bd5; }
        .error { color: #c42b2b; font-size: 13px; margin: 7px 0 0; }
        .notice { padding: 11px 13px; margin-bottom: 18px; border-radius: 9px; background: #eaf8ef; color: #267444; font-size: 14px; }
        .dashboard { width: min(920px, 100%); }
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
        .topbar form { margin: 0; }
        .logout { border: 1px solid #d7dce5; background: #fff; border-radius: 9px; padding: 9px 14px; cursor: pointer; }
        .welcome { background: #fff; border: 1px solid #e7eaf0; border-radius: 18px; padding: 38px; box-shadow: 0 18px 50px rgba(26, 40, 75, .06); }
        @media (max-width: 520px) { .card, .welcome { padding: 26px; } }
        .admin-shell{width:min(1440px,calc(100% - 48px));min-height:calc(100vh - 48px);margin:24px auto;display:grid;grid-template-columns:250px 1fr;background:#f5f6ef;border:10px solid #181918;border-radius:30px;overflow:hidden;box-shadow:0 30px 80px rgba(22,25,22,.15)}
        .admin-sidebar{background:#171817;color:#f7f7f2;padding:28px 20px;display:flex;flex-direction:column}.admin-brand{display:flex;align-items:center;gap:12px;margin-bottom:42px;padding:0 8px}.admin-brand img{width:46px;height:46px;border-radius:50%;object-fit:contain;background:#fff;padding:4px}.admin-brand strong{letter-spacing:.12em}.admin-sidebar nav{display:grid;gap:8px}.admin-sidebar nav a{color:#d8d9d5;text-decoration:none;padding:13px 15px;border-radius:10px;display:flex;align-items:center;gap:14px}.admin-sidebar nav a span{font-size:20px;width:22px;text-align:center}.admin-sidebar nav a:hover,.admin-sidebar nav a.active{color:#fff;background:#292b27}.admin-sidebar nav a.active{box-shadow:inset 3px 0 #b7c31b}.admin-user{margin-top:auto;display:flex;align-items:center;gap:10px;padding:14px 6px}.admin-user>span{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:#aab514;color:#111}.admin-user div{display:grid;flex:1}.admin-user small{color:#969991}.admin-user form{margin:0}.admin-user button{border:0;background:none;color:#fff;font-size:18px;cursor:pointer}.admin-workspace{padding:34px;min-width:0}.admin-workspace .dashboard{width:100%;max-width:none}.admin-workspace .welcome{border-color:#daddd1;box-shadow:none}.admin-workspace .button{background:#171817}.admin-workspace .button:hover{background:#333530}@media(max-width:900px){.admin-shell{width:100%;margin:0;border:0;border-radius:0;grid-template-columns:1fr}.admin-sidebar{padding:14px}.admin-brand{margin:0}.admin-sidebar nav{display:flex;overflow:auto;margin-top:12px}.admin-user{display:none}.admin-workspace{padding:20px}}
        img{max-width:100%} table{min-width:620px}
        html,body,.admin-workspace,.admin-sidebar nav,.booking-form,.sidebar,.workspace{scrollbar-width:none;-ms-overflow-style:none}
        html::-webkit-scrollbar,body::-webkit-scrollbar,.admin-workspace::-webkit-scrollbar,.admin-sidebar nav::-webkit-scrollbar,.booking-form::-webkit-scrollbar,.sidebar::-webkit-scrollbar,.workspace::-webkit-scrollbar{display:none;width:0;height:0}
        @media(max-width:1100px){.admin-shell{width:calc(100% - 24px);margin:12px auto;grid-template-columns:210px 1fr;border-width:7px;border-radius:22px}.admin-sidebar{padding:22px 14px}.admin-brand strong{font-size:14px}.admin-workspace{padding:24px}.admin-workspace [style*="grid-template-columns:1fr 2fr"]{grid-template-columns:1fr!important}.admin-workspace [style*="grid-template-columns:2fr 1fr 1fr"]{grid-template-columns:1.5fr 1fr 1fr!important}}
        @media(max-width:780px){.admin-shell{width:100%;min-height:100vh;margin:0;border:0;border-radius:0;grid-template-columns:1fr;overflow:visible}.admin-sidebar{padding:12px 14px;position:sticky;top:0;z-index:20;box-shadow:0 4px 18px rgba(0,0,0,.18)}.admin-brand{margin:0 0 9px;padding:0}.admin-brand img{width:38px;height:38px}.admin-brand strong{font-size:13px}.admin-sidebar nav{display:flex;overflow-x:auto;gap:6px;padding-bottom:2px;scrollbar-width:none}.admin-sidebar nav::-webkit-scrollbar{display:none}.admin-sidebar nav a{white-space:nowrap;padding:9px 12px;font-size:13px}.admin-sidebar nav a span{display:none}.admin-user{display:none}.admin-workspace{padding:18px 14px}.topbar{gap:14px;align-items:flex-start}.topbar h1{font-size:23px!important}.topbar>.logout{white-space:nowrap}.admin-workspace [style*="grid-template-columns"]{grid-template-columns:1fr!important}.admin-workspace [style*="display:flex"]{flex-wrap:wrap}.welcome{border-radius:14px}.button,.logout{min-height:44px}.admin-workspace table{font-size:13px}.admin-workspace th,.admin-workspace td{white-space:nowrap}}
        @media(max-width:520px){.page{padding:14px}.card{padding:24px;border-radius:15px}.admin-workspace{padding:15px 11px}.topbar{margin-bottom:18px}.admin-workspace .welcome{padding:18px!important}.admin-workspace form.welcome{align-items:stretch!important}.admin-workspace form.welcome>div{width:100%}.admin-workspace form.welcome .control{width:100%}.admin-workspace [style*="grid-template-columns:repeat"]{grid-template-columns:1fr!important}input,textarea,button,.control{font-size:16px}.logo{margin-inline:auto}}
        @media(min-width:781px){
            .admin-shell{width:min(1440px,calc(100% - 24px));height:calc(100dvh - 24px);min-height:540px;margin:12px auto;grid-template-columns:clamp(200px,18vw,250px) minmax(0,1fr);border-width:8px;border-radius:26px}
            .admin-sidebar{padding:clamp(16px,3vh,28px) 16px;min-height:0;overflow:hidden}
            .admin-brand{margin-bottom:clamp(16px,4vh,42px)}
            .admin-sidebar nav{min-height:0;overflow-y:auto;scrollbar-width:thin}
            .admin-sidebar nav a{padding:clamp(9px,1.5vh,13px) 13px}
            .admin-user{flex-shrink:0;padding-top:10px}
            .admin-workspace{height:100%;overflow-y:auto;overflow-x:hidden;padding:clamp(20px,3vw,34px)}
        }
        @media(max-height:650px) and (min-width:781px){.admin-brand img{width:38px;height:38px}.admin-brand{margin-bottom:12px}.admin-sidebar nav{gap:3px}.admin-sidebar nav a{padding:7px 11px}.admin-user{padding:8px 4px}.admin-workspace{padding:18px}}
        .global-back{height:42px;border:1px solid #daddd1;border-radius:10px;background:#fff;color:#171817;display:flex;align-items:center;gap:8px;padding:0 13px 0 7px;cursor:pointer;font-size:13px;font-weight:750;transition:background .18s ease,color .18s ease,transform .18s ease}.global-back-icon{width:28px;height:28px;border-radius:8px;background:#e9ecd4;color:#667000;display:grid;place-items:center;transition:transform .18s ease}.global-back svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.global-back:hover{background:#f4f5ef;transform:translateX(-2px)}.global-back:hover .global-back-icon{transform:translateX(-2px)}.global-back:focus-visible{outline:3px solid rgba(170,181,20,.35);outline-offset:2px}.page .card>.global-back,.booking-inner>.global-back{margin-bottom:20px}.admin-sidebar nav>.global-back{width:100%;height:auto;border:0;border-radius:10px;background:#222420;color:#d8d9d5;justify-content:flex-start;padding:9px 11px;margin-bottom:5px}.admin-sidebar nav>.global-back:hover{background:#292b27;color:#fff}.admin-sidebar nav>.global-back .global-back-icon{background:#aab514;color:#171817}.customer-shop nav .global-back,.history-page nav .global-back{height:38px;padding-right:10px}.customer-shop nav .global-back-icon,.history-page nav .global-back-icon{width:25px;height:25px}@media(max-width:580px){.customer-shop nav .global-back-label,.history-page nav .global-back-label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}.customer-shop nav .global-back,.history-page nav .global-back{width:36px;padding:5px}.booking-inner>.global-back{margin-bottom:14px}}
/* Kermit's responsive interface */
:root{--ink:#171817;--ink-2:#292b27;--paper:#f4f3ec;--surface:#fffefa;--line:#d8d9cf;--muted:#6c7068;--accent:#aebb19;--accent-soft:#edf0cf;--danger:#b42318;--radius:18px;--shadow:0 16px 44px rgba(24,25,22,.08)}
body{background:radial-gradient(circle at 85% 0,#eef0d8 0,transparent 30%),#e6e8e2;color:var(--ink);overflow-x:hidden}
button,a,input,select,textarea{transition:border-color .18s,background .18s,color .18s,transform .18s,box-shadow .18s}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid rgba(174,187,25,.32);outline-offset:2px}
.button,.checkout-button,.booking-button,.book-button{min-height:44px;border:0;border-radius:12px;background:var(--ink)!important;color:#fff!important;font-weight:800;letter-spacing:.01em;box-shadow:0 8px 20px rgba(23,24,23,.14);cursor:pointer}.button:hover,.checkout-button:hover,.booking-button:hover,.book-button:hover{background:#30322e!important;transform:translateY(-1px)}.button:disabled,.checkout-button:disabled{transform:none;box-shadow:none;cursor:not-allowed}.logout{min-height:42px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none!important;border:1px solid var(--line);border-radius:11px;background:var(--surface);color:var(--ink)!important;font-weight:750;padding:9px 14px}.logout:hover{background:var(--accent-soft);border-color:#c6cc8d}
.control,input:not([type=radio]):not([type=checkbox]):not([type=hidden]),select,textarea{max-width:100%;border:1px solid #cfd2c8;border-radius:11px;background:#fff;padding:11px 13px;color:var(--ink)}.control:focus,input:focus,select:focus,textarea:focus{border-color:#98a20f;box-shadow:0 0 0 3px rgba(174,187,25,.16)}
.notice{border:1px solid #b8dbc4;background:#edf8f0}.error{border-color:#efc8c5!important}.welcome,.panel,.hero-metrics,.stat-row>div,.product-card,.payment-card,.history-card,.empty{background:var(--surface)!important;border-color:var(--line)!important;box-shadow:var(--shadow)!important}
.admin-shell,.app-shell{width:min(1560px,calc(100% - 24px))!important;height:calc(100dvh - 24px)!important;min-height:620px!important;margin:12px auto!important;border:7px solid var(--ink)!important;border-radius:26px!important;grid-template-columns:clamp(210px,17vw,250px) minmax(0,1fr)!important;background:var(--paper)!important;overflow:hidden!important;box-shadow:0 24px 70px rgba(20,22,19,.17)!important}
.admin-sidebar,.sidebar{background:linear-gradient(160deg,#171817,#20221f)!important;padding:24px 16px!important;min-height:0!important}.admin-brand,.side-brand{margin-bottom:24px!important}.admin-sidebar nav a,.side-nav a{min-height:46px;border-radius:12px;padding:11px 13px!important}.admin-sidebar nav a.active,.side-nav a.active{background:#30322d!important;box-shadow:inset 4px 0 var(--accent)!important}.admin-user,.side-user{border-top:1px solid #343631;margin-top:auto!important;padding-top:16px!important}
.admin-workspace,.workspace{height:100%!important;overflow-y:auto!important;overflow-x:hidden!important;padding:clamp(22px,3vw,38px)!important}.dashboard{width:100%!important;max-width:none!important}.topbar,.dash-head{min-height:70px;align-items:center!important;margin-bottom:24px!important}.topbar h1,.dash-head h1{letter-spacing:-.035em}.topbar .muted{margin:5px 0 0}.welcome{border-radius:var(--radius)!important}.admin-workspace table{width:100%;border-collapse:separate!important;border-spacing:0}.admin-workspace th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:#f1f2eb}.admin-workspace th:first-child{border-radius:10px 0 0 10px}.admin-workspace th:last-child{border-radius:0 10px 10px 0}.admin-workspace td,.admin-workspace th{padding:12px!important}.admin-workspace td{border-bottom:1px solid #e5e6de!important}
.sell-shell{width:min(1560px,calc(100% - 24px))!important;margin:12px auto!important}.sell-layout{grid-template-columns:minmax(0,1fr) minmax(300px,350px)!important}.payment-card{top:0!important;max-height:calc(100dvh - 110px);overflow-y:auto}.payment-options{grid-template-columns:1fr 1fr}.payment-options label{margin:0!important;align-items:flex-start}.product-grid{align-items:stretch}.product-card{display:flex;flex-direction:column}.product-copy{display:flex;flex-direction:column;flex:1}.product-copy .add-cart{margin-top:auto}.pos-category{margin-top:32px!important;color:#3e4410;border-color:#aeb95b!important}
.customer-shop,.history-page{background:linear-gradient(180deg,#f7f6f0,#eeefe7)!important}.customer-shop nav,.history-page nav{position:sticky;top:0;z-index:30;width:100%!important;height:74px!important;padding:0 max(18px,calc((100% - 1180px)/2));background:rgba(247,246,240,.94);border-bottom:1px solid var(--line);backdrop-filter:blur(14px)}.customer-shop header,.history-page>header{padding:44px 0 26px!important}.customer-shop header h1,.history-page>header h1{letter-spacing:-.04em}.shop-grid article{box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s}.shop-grid article:hover{transform:translateY(-3px);box-shadow:0 20px 46px rgba(24,25,22,.12)}.order-bar{bottom:18px!important;border:1px solid #3d403a;box-shadow:0 16px 46px rgba(0,0,0,.28)!important}.order-bar button{min-height:46px;cursor:pointer}.history-grid{align-items:start}.history-card{transition:transform .18s}.history-card:hover{transform:translateY(-2px)}
.booking-page{background:radial-gradient(circle at 20% 10%,#eef0d2,transparent 34%),#dfe2dc!important}.booking-shell{width:min(1320px,calc(100% - 24px))!important;height:calc(100dvh - 24px)!important;border-width:7px!important}.booking-brand{background:linear-gradient(155deg,#171817,#292c25)!important}.booking-form{background:#f8f7f1}.booking-inner{width:min(760px,100%)!important}.booking-button{position:sticky;bottom:0;z-index:5;margin-top:16px;box-shadow:0 -10px 25px #f8f7f1,0 10px 22px rgba(0,0,0,.15)!important}
@media(max-width:1100px){.admin-shell,.app-shell{grid-template-columns:205px minmax(0,1fr)!important}.admin-workspace,.workspace{padding:22px!important}.sell-layout{grid-template-columns:1fr!important}.payment-card{position:relative!important;max-height:none;order:-1}.product-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
@media(max-width:780px){body{background:var(--paper)}.admin-shell,.app-shell{width:100%!important;height:auto!important;min-height:100dvh!important;margin:0!important;border:0!important;border-radius:0!important;grid-template-columns:1fr!important;overflow:visible!important}.admin-sidebar,.sidebar{position:sticky!important;top:0;z-index:50;padding:10px 12px!important;box-shadow:0 7px 24px rgba(0,0,0,.2)}.admin-brand,.side-brand{margin:0 0 8px!important}.admin-sidebar nav,.side-nav{display:flex!important;gap:6px;overflow-x:auto;padding-bottom:2px}.admin-sidebar nav a,.side-nav a{min-height:40px;white-space:nowrap;padding:8px 11px!important;font-size:13px}.admin-sidebar nav a span,.side-nav a span{display:none}.admin-user,.side-user{display:none!important}.admin-workspace,.workspace{height:auto!important;overflow:visible!important;padding:18px 14px 90px!important}.topbar,.dash-head{align-items:flex-start!important;min-height:auto}.topbar>a,.topbar>.logout{flex:0 0 auto}.product-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.payment-card{order:-1}.customer-shop nav,.history-page nav{padding:0 12px}.customer-shop header,.customer-shop>form,.shop-error,.history-page>header,.history-grid{width:calc(100% - 24px)!important}.history-grid{grid-template-columns:1fr!important}.booking-page{padding:0!important}.booking-shell{width:100%!important;height:auto!important;min-height:100dvh!important;border:0!important;border-radius:0!important}.booking-form{overflow:visible!important}.booking-button{bottom:10px}.admin-workspace [style*="grid-template-columns"]{grid-template-columns:1fr!important}.admin-workspace [style*="display:flex"]{align-items:stretch}.admin-workspace .button,.admin-workspace .logout{width:100%;justify-content:center}}
@media(max-width:520px){.admin-workspace,.workspace{padding:14px 10px 86px!important}.topbar{display:grid!important;grid-template-columns:1fr auto;gap:12px}.topbar .logout{width:auto!important}.welcome{padding:17px!important;border-radius:14px!important}.product-grid,.shop-grid{grid-template-columns:1fr!important}.product-photo,.product-placeholder{height:190px!important}.payment-options{grid-template-columns:1fr}.sell-head h1{font-size:27px!important}.customer-actions{gap:5px!important}.customer-actions a,.customer-actions button{padding:8px!important;font-size:12px}.customer-shop header h1,.history-page>header h1{font-size:34px!important}.order-bar{bottom:8px!important;width:calc(100% - 16px)!important;padding:10px!important}.booking-form{padding:22px 14px!important}.booking-types{grid-template-columns:1fr!important}.reservation-menu-item{grid-template-columns:52px minmax(0,1fr) 58px!important}.admin-workspace table{min-width:680px}.global-back{flex:0 0 auto}}
/* Uniform Admin and Super Admin sidebar */
@media(min-width:781px){.admin-shell{grid-template-columns:250px minmax(0,1fr)!important}.admin-sidebar{box-sizing:border-box!important;width:250px!important;min-width:250px!important;max-width:250px!important;height:100dvh!important;padding:30px 20px!important;background:linear-gradient(160deg,#151615,#21231f)!important}.admin-sidebar>.admin-brand{box-sizing:border-box!important;width:100%;min-height:87px;display:flex!important;align-items:center!important;gap:10px!important;margin:0 0 28px!important;padding:4px 8px 28px!important;border-bottom:1px solid #343630!important;color:#fff!important;text-decoration:none!important}.admin-sidebar>.admin-brand img{box-sizing:border-box!important;width:58px!important;height:58px!important;min-width:58px!important;max-width:58px!important;padding:0!important;border-radius:50%!important;object-fit:contain!important;background:#fff!important}.admin-sidebar>.admin-brand strong{color:#fff!important;letter-spacing:.1em!important;white-space:nowrap}.admin-sidebar>nav{display:grid!important;align-content:start!important;gap:8px!important;min-height:0;overflow-y:auto!important;flex:1}.admin-sidebar>nav>a{box-sizing:border-box!important;width:100%;height:46px!important;min-height:46px!important;padding:12px 14px!important;border-radius:11px!important;background:#292b27!important;color:#eee!important;gap:12px!important}.admin-sidebar>nav>a:hover{background:#363933!important}.admin-sidebar>nav>a.active{background:#34372f!important;box-shadow:inset 4px 0 #b5c019!important;color:#fff!important}.admin-sidebar>nav>a span{width:22px!important}.admin-sidebar>.admin-user{box-sizing:border-box!important;min-height:73px;margin:14px 0 0!important;padding:15px 0 0 12px!important;border-top:1px solid #343630!important;display:grid!important;grid-template-columns:minmax(0,1fr) 42px;align-items:center;gap:8px}.admin-sidebar>.admin-user>div{min-width:0;display:grid}.admin-sidebar>.admin-user strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}.admin-sidebar>.admin-user small{color:#969991;font-size:11px;margin-top:3px}.admin-sidebar>.admin-user form{width:42px;margin:0!important}.admin-sidebar>.admin-user .logout-icon{width:42px!important;height:42px!important;min-width:42px!important;min-height:42px!important}}
@media(max-width:780px){.admin-sidebar{box-sizing:border-box!important;width:100%!important;min-height:68px!important;padding:8px 14px!important}.admin-sidebar>.admin-brand{display:flex!important;align-items:center;gap:8px;margin:0 0 7px!important;color:#fff;text-decoration:none}.admin-sidebar>.admin-brand img{box-sizing:border-box!important;width:42px!important;height:42px!important;padding:0!important}.admin-sidebar>.admin-brand strong{font-size:13px}.admin-sidebar>nav>a{height:42px!important;min-height:42px!important}.admin-sidebar>.admin-user{display:none!important}}
input[type="number"]{appearance:textfield;-moz-appearance:textfield}
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
/* Kermit's mark on every menu image without hiding the food photo. */
.menu-card,.shop-grid article,.product-card,.reservation-menu-item,.inventory-item{position:relative}
.menu-card:after,.shop-grid article:after,.product-card:after,.reservation-menu-item:after,.inventory-item:after{
    content:"";
    position:absolute;
    z-index:2;
    top:9px;
    left:9px;
    width:42px;
    height:42px;
    border-radius:50%;
    background:rgba(255,255,255,.92) url('{{ asset('kermits-logo.jpg') }}') center/85% no-repeat;
    border:1px solid rgba(23,24,23,.12);
    box-shadow:0 5px 14px rgba(0,0,0,.13);
    pointer-events:none;
}
.reservation-menu-item:after,.inventory-item:after{width:28px;height:28px;top:5px;left:5px}
.nav-icon{width:21px!important;height:21px!important;min-width:21px;flex:0 0 21px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;color:inherit}
.admin-sidebar nav a .nav-icon,.side-nav a .nav-icon{display:block}
@media(max-width:780px){.admin-sidebar nav a .nav-icon,.side-nav a .nav-icon{display:none}}
/* Full-page role workspaces and correctly placed navigation */
.admin-shell,.app-shell,.sell-shell{width:100%!important;height:100dvh!important;min-height:100dvh!important;margin:0!important;border:0!important;border-radius:0!important;box-shadow:none!important}.admin-sidebar,.sidebar{border-radius:0!important}.admin-workspace,.workspace{position:relative}.admin-workspace>.global-back,.workspace>.global-back{margin:0 0 18px}.customer-shop>header>.global-back,.history-page>header>.global-back{margin-bottom:22px}.booking-shell{width:100%!important;height:100dvh!important;min-height:100dvh!important;border:0!important;border-radius:0!important}.booking-page{padding:0!important}.global-back{flex:0 0 auto;box-shadow:0 7px 18px rgba(24,25,22,.07)}
@media(min-width:781px){.admin-shell{grid-template-columns:250px minmax(0,1fr)!important}.app-shell{grid-template-columns:clamp(220px,17vw,260px) minmax(0,1fr)!important}.admin-sidebar,.sidebar{height:100dvh!important}.admin-workspace,.workspace{height:100dvh!important}.booking-shell{grid-template-columns:250px minmax(0,1fr)!important}}
@media(max-width:780px){.admin-shell,.app-shell,.sell-shell,.booking-shell{height:auto!important;min-height:100dvh!important}.admin-workspace>.global-back,.workspace>.global-back{margin-bottom:14px}.customer-shop>header>.global-back,.history-page>header>.global-back{margin-bottom:16px}}
/* Full-page detail, confirmation, and customer-history screens */
.page{position:relative;min-height:100dvh;padding:0;display:grid;grid-template-columns:minmax(300px,36%) minmax(0,64%);place-items:stretch;background:radial-gradient(circle at 12% 15%,#33372e 0,transparent 25%),linear-gradient(145deg,#131413,#20221e)}.page:before{content:"KERMIT'S\A Time-honored recipes\A since 2000";white-space:pre;align-self:end;grid-column:1;grid-row:1;padding:clamp(36px,6vw,86px);color:#f6f6f0;font:800 clamp(24px,3vw,44px)/1.25 Georgia,serif;letter-spacing:-.02em}.page>.card{grid-column:2;grid-row:1;width:100%;height:100dvh;max-width:none!important;border:0;border-radius:0;padding:clamp(32px,7vw,100px);box-shadow:none;overflow-y:auto;background:radial-gradient(circle at 100% 0,#f0f1d8 0,transparent 32%),#f8f7f1;display:flex;flex-direction:column;justify-content:center}.page>.card>.global-back{width:max-content;align-self:flex-start;margin-bottom:clamp(24px,5vh,48px)}.page>.card .logo{flex:0 0 auto}.reservation-result{padding-inline:clamp(32px,10vw,150px)!important}.reservation-result .button{min-height:48px;display:flex!important;align-items:center;justify-content:center}
@media(min-width:901px){.history-page{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100dvh;padding:0!important}.history-page>nav{position:fixed!important;inset:0 auto 0 0;width:250px!important;height:100dvh!important;padding:30px 20px!important;background:linear-gradient(160deg,#151615,#21231f)!important;border:0!important;display:flex!important;flex-direction:column;align-items:stretch!important;justify-content:flex-start!important;color:#fff;backdrop-filter:none!important}.history-page>nav>a{padding:4px 8px 28px;border-bottom:1px solid #343630}.history-page>nav img{width:58px;height:58px}.history-page>nav>div{display:grid!important;gap:8px!important;margin-top:28px}.history-page>nav>div>a{min-height:46px;display:flex;align-items:center;padding:12px 14px;border-radius:11px;background:#292b27;color:#eee}.history-page>nav>div form{margin-top:14px}.history-page>nav>div button{width:100%;min-height:44px;background:transparent;color:#fff;border-color:#464941}.history-page>header,.history-page>.history-grid{grid-column:2;width:auto!important;margin:0!important}.history-page>header{padding:38px clamp(26px,4vw,58px) 26px!important}.history-page>.history-grid{padding:0 clamp(26px,4vw,58px) 50px}}
@media(max-width:760px){.page{grid-template-columns:1fr}.page:before{display:none}.page>.card{grid-column:1;height:auto;min-height:100dvh;padding:26px 18px;justify-content:flex-start}.reservation-result{padding:26px 18px!important}.page>.card>.global-back{margin-bottom:24px}}
/* Stable interaction states: controls never shift position when clicked */
button,a,[role="button"],.button,.logout,.checkout-button,.booking-button,.book-button,.global-back,.global-back-icon,.product-card,.menu-card,.history-card{transform:none!important;transition:background-color .14s ease,border-color .14s ease,color .14s ease,box-shadow .14s ease,opacity .14s ease!important}button:hover,a:hover,[role="button"]:hover,.button:hover,.logout:hover,.checkout-button:hover,.booking-button:hover,.book-button:hover,.global-back:hover,.global-back:hover .global-back-icon,.product-card:hover,.menu-card:hover,.history-card:hover{transform:none!important}button:active,a:active,[role="button"]:active{transform:none!important;filter:none!important}.is-submitting{pointer-events:none;opacity:.68!important}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
.logout-icon{width:42px!important;height:42px!important;min-width:42px!important;min-height:42px!important;padding:0!important;border:1px solid rgba(255,255,255,.18)!important;border-radius:11px!important;background:rgba(255,255,255,.06)!important;color:#fff!important;display:inline-grid!important;place-items:center!important;cursor:pointer}.logout-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.logout-icon:hover{background:rgba(174,187,25,.18)!important;border-color:rgba(174,187,25,.5)!important}.customer-shop .logout-icon,.history-page .logout-icon{color:#fff!important}.customer-actions form,.history-page nav form{display:flex;justify-content:flex-end}
@media(max-width:900px){.customer-shop .logout-icon,.history-page .logout-icon{color:#171817!important;background:#fff!important;border-color:#ccd0c5!important}}
/* Uniform customer sidebar typography and active state */
@media(min-width:901px){
    .customer-shop>nav .customer-actions,
    .history-page>nav>.history-actions{
        font-family:Arial, Helvetica, sans-serif!important;
    }
    .customer-shop>nav .customer-actions>a,
    .history-page>nav>.history-actions>a{
        box-sizing:border-box!important;
        width:100%!important;
        height:46px!important;
        min-height:46px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:flex-start!important;
        padding:12px 14px!important;
        border-radius:11px!important;
        background:#292b27!important;
        color:#f2f2ee!important;
        font-family:Arial, Helvetica, sans-serif!important;
        font-size:16px!important;
        font-weight:700!important;
        line-height:1!important;
        letter-spacing:0!important;
        text-decoration:none!important;
        box-shadow:none!important;
    }
    .customer-shop>nav .customer-actions>a.active,
    .history-page>nav>.history-actions>a.active,
    .customer-shop>nav .customer-actions>a[aria-current="page"],
    .history-page>nav>.history-actions>a[aria-current="page"]{
        background:#34372f!important;
        color:#fff!important;
        font-family:Arial, Helvetica, sans-serif!important;
        font-size:16px!important;
        font-weight:700!important;
        line-height:1!important;
        letter-spacing:0!important;
        box-shadow:inset 4px 0 #b5c019!important;
    }
    .customer-shop>nav .customer-actions>a:hover,
    .history-page>nav>.history-actions>a:hover{
        background:#34372f!important;
        color:#fff!important;
        font-size:16px!important;
        font-weight:700!important;
    }
    .customer-shop>nav>a strong,
    .history-page>nav>a strong{
        font-family:Arial, Helvetica, sans-serif!important;
        font-size:20px!important;
        font-weight:800!important;
        line-height:1!important;
        letter-spacing:.1em!important;
    }
    .customer-shop>nav .customer-actions>span,
    .history-page>nav>.history-actions>span{
        font-family:Arial, Helvetica, sans-serif!important;
        font-size:14px!important;
        font-weight:400!important;
        line-height:1.2!important;
        letter-spacing:0!important;
    }
}
@media(max-width:900px){
    .customer-shop .customer-actions>a,
    .history-page .history-actions>a{
        font-family:Arial, Helvetica, sans-serif!important;
        font-size:13px!important;
        font-weight:700!important;
        line-height:1!important;
        letter-spacing:0!important;
    }
}
/* Stop sidebar link flicker while clicking between customer pages */
.customer-shop>nav .customer-actions>a,
.history-page>nav>.history-actions>a,
.customer-shop>nav .customer-actions>a:link,
.history-page>nav>.history-actions>a:link,
.customer-shop>nav .customer-actions>a:visited,
.history-page>nav>.history-actions>a:visited,
.customer-shop>nav .customer-actions>a:hover,
.history-page>nav>.history-actions>a:hover,
.customer-shop>nav .customer-actions>a:focus,
.history-page>nav>.history-actions>a:focus,
.customer-shop>nav .customer-actions>a:active,
.history-page>nav>.history-actions>a:active{
    font-family:Arial, Helvetica, sans-serif!important;
    font-size:16px!important;
    font-weight:700!important;
    line-height:1!important;
    letter-spacing:0!important;
    text-decoration:none!important;
    transform:none!important;
    filter:none!important;
    outline:0!important;
    transition:none!important;
}
@media(min-width:901px){
    .customer-shop>nav .customer-actions>a,
    .history-page>nav>.history-actions>a{
        height:46px!important;
        min-height:46px!important;
        max-height:46px!important;
        background:#292b27!important;
        color:#f2f2ee!important;
        border-left:4px solid transparent!important;
        box-shadow:none!important;
    }
    .customer-shop>nav .customer-actions>a:hover,
    .history-page>nav>.history-actions>a:hover,
    .customer-shop>nav .customer-actions>a:focus,
    .history-page>nav>.history-actions>a:focus,
    .customer-shop>nav .customer-actions>a:active,
    .history-page>nav>.history-actions>a:active{
        background:#292b27!important;
        color:#f2f2ee!important;
        border-left-color:transparent!important;
        box-shadow:none!important;
    }
    .customer-shop>nav .customer-actions>a.active,
    .history-page>nav>.history-actions>a.active,
    .customer-shop>nav .customer-actions>a[aria-current="page"],
    .history-page>nav>.history-actions>a[aria-current="page"],
    .customer-shop>nav .customer-actions>a.active:hover,
    .history-page>nav>.history-actions>a.active:hover,
    .customer-shop>nav .customer-actions>a[aria-current="page"]:hover,
    .history-page>nav>.history-actions>a[aria-current="page"]:hover,
    .customer-shop>nav .customer-actions>a.active:focus,
    .history-page>nav>.history-actions>a.active:focus,
    .customer-shop>nav .customer-actions>a[aria-current="page"]:focus,
    .history-page>nav>.history-actions>a[aria-current="page"]:focus,
    .customer-shop>nav .customer-actions>a.active:active,
    .history-page>nav>.history-actions>a.active:active,
    .customer-shop>nav .customer-actions>a[aria-current="page"]:active,
    .history-page>nav>.history-actions>a[aria-current="page"]:active{
        background:#34372f!important;
        color:#fff!important;
        border-left-color:#b5c019!important;
        box-shadow:none!important;
    }
}
/* Keep Super Admin pages scrollable without displaying browser scrollbars. */
.admin-shell,.admin-workspace,.admin-sidebar>nav{
    scrollbar-width:none!important;
    -ms-overflow-style:none!important;
}
.admin-shell::-webkit-scrollbar,
.admin-workspace::-webkit-scrollbar,
.admin-sidebar>nav::-webkit-scrollbar{
    display:none!important;
    width:0!important;
    height:0!important;
}
</style>





</head>
<body>
@yield('content')







<script>
document.addEventListener('submit', event => {
    queueMicrotask(() => {
        if (event.defaultPrevented) return;
        const submitter = event.submitter;
        if (!submitter) return;
        submitter.classList.add('is-submitting');
        submitter.setAttribute('aria-disabled', 'true');
    });
});
</script>
<script>(()=>{const phoneInputs=document.querySelectorAll('input[type="tel"][maxlength="11"]');phoneInputs.forEach(input=>{input.setAttribute('inputmode','numeric');input.setAttribute('minlength','11');input.setAttribute('maxlength','11');input.setAttribute('pattern','09[0-9]{9}');input.addEventListener('input',()=>{input.value=input.value.replace(/\D/g,'').slice(0,11)})})})();</script>
</body>
</html>
