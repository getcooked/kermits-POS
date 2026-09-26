@once
@push('styles')
<style>
.account-head{margin-bottom:20px}.account-head p,.account-eyebrow{margin:0;color:#7a8300;font-size:11px;letter-spacing:.16em}.account-head h1{margin:5px 0;font-size:30px}.account-head span{color:#687286}
.account-message{padding:12px;margin-bottom:14px;border-radius:10px}
.account-summary-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}.account-summary-cards>.welcome{padding:20px}.account-summary-cards span{color:#687286}.account-summary-cards h2{margin:6px 0 0;font-size:27px;overflow-wrap:anywhere}
.account-grid{display:grid;grid-template-columns:minmax(320px,.8fr) minmax(0,1.2fr);gap:20px;align-items:start}
.account-card{padding:24px;border-radius:15px!important}.account-card h2{margin:5px 0 18px;font-size:20px}
.account-card-head{display:flex;justify-content:space-between;align-items:start;gap:14px}.account-card-head>strong{width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;border-radius:11px;background:#e9ecd4;color:#667000}
.account-record{padding:14px 0;border-top:1px solid #e7eaf0}
.account-record-summary{display:grid;grid-template-columns:44px minmax(0,1fr) auto;gap:12px;align-items:center}.account-record-summary>span{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:#e9ecd4;color:#667000;font-weight:800}.account-record-summary>div{display:grid;min-width:0}.account-record small{color:#687286;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.account-badge{font-size:11px;font-weight:800;padding:5px 8px;border-radius:999px;background:#f0f1ed;white-space:nowrap}
.account-record-link{display:inline-block;margin:11px 0 0 56px;color:#596100;font-weight:800;font-size:13px}
.account-empty{padding:18px 0;text-align:center;color:#687286}
@media(max-width:950px){.account-grid{grid-template-columns:1fr}}
@media(max-width:700px){.account-summary-cards{grid-template-columns:1fr}}
@media(max-width:520px){.account-record-summary{grid-template-columns:40px minmax(0,1fr)}.account-record-summary>.account-badge,.account-record-summary>.account-stats{grid-column:2;width:max-content}.account-record-link{margin-left:52px}}
</style>
@endpush
@endonce
