@extends('layouts.app')

@section('title', 'Activity Logs · Kermit\'s')

@section('content')
<div class="admin-shell activity-log-shell">
    @include('partials.admin-sidebar')

    <main class="admin-workspace activity-logs-workspace">
        <div class="activity-log-page">
            <header class="activity-log-header">
                <div>
                    <p>SUPER ADMIN</p>
                    <h1>Activity logs</h1>
                    <span>Review account and system activity, request details, and device information.</span>
                </div>
                <div class="activity-log-count" aria-label="{{ number_format($logs->total()) }} total activity records">
                    <strong>{{ number_format($logs->total()) }}</strong>
                    <small>Total records</small>
                </div>
            </header>

            <section class="activity-log-results" aria-labelledby="activity-log-results-title">
                <header class="activity-log-results-header">
                    <div>
                        <h2 id="activity-log-results-title">Latest activity</h2>
                        <p>Newest records are shown first.</p>
                    </div>
                    @if($logs->total() > 0)
                        <span>
                            Showing {{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }}
                            of {{ number_format($logs->total()) }}
                        </span>
                    @endif
                </header>

                @if($logs->isEmpty())
                    <div class="activity-log-empty">
                        <span aria-hidden="true">@include('partials.nav-icon', ['name' => 'activity'])</span>
                        <strong>No activity logs found</strong>
                        <p>{{ $search !== '' || $role !== '' || $method !== '' || $action !== '' ? 'Try changing or resetting the current filters.' : 'Recorded system activity will appear here.' }}</p>
                    </div>
                @else
                    <div class="activity-log-table-wrap">
                        <table class="activity-log-table">
                            <thead>
                                <tr>
                                    <th scope="col">Date and time</th>
                                    <th scope="col">Actor</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">IP address</th>
                                    <th scope="col">Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    @php
                                        $userAgent = trim((string) $log->user_agent);
                                    @endphp
                                    <tr>
                                        <td data-label="Date and time">
                                            <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M d, Y h:i:s A') }}">
                                                <strong>{{ $log->created_at->format('M d, Y') }}</strong>
                                                <span>{{ $log->created_at->format('h:i:s A') }}</span>
                                                <small>{{ $log->created_at->diffForHumans() }}</small>
                                            </time>
                                        </td>
                                        <td data-label="Actor">
                                            <div class="activity-log-actor">
                                                <span aria-hidden="true">{{ strtoupper(substr($log->actor_name ?: 'S', 0, 1)) }}</span>
                                                <div>
                                                    <strong>{{ $log->actor_name ?: 'System' }}</strong>
                                                    <small>{{ $log->actor_role ? str($log->actor_role)->replace('_', ' ')->title() : 'No account role' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Activity">
                                            <div class="activity-log-description">
                                                <strong>{{ str($log->action)->replace(['.', '_'], ' ')->title() }}</strong>
                                                <p>{{ $log->description ?: 'No additional details were recorded.' }}</p>
                                            </div>
                                        </td>
                                        <td data-label="IP address">
                                            <strong class="activity-log-ip">{{ $log->ip_address ?: 'Unknown IP' }}</strong>
                                        </td>
                                        <td data-label="Device">
                                            <span class="activity-log-device" title="{{ $userAgent !== '' ? $userAgent : 'Device unavailable' }}">
                                                {{ $userAgent !== '' ? \Illuminate\Support\Str::limit($userAgent, 74) : 'Device unavailable' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @php
                        $logs->appends(array_filter([
                            'search' => $search,
                            'role' => $role,
                            'method' => $method,
                            'action' => $action,
                        ], fn ($value) => $value !== ''));
                    @endphp
                    @if($logs->hasPages())
                        <nav class="activity-log-pagination" aria-label="Activity log pages">
                            <span>Page {{ number_format($logs->currentPage()) }} of {{ number_format($logs->lastPage()) }}</span>
                            <div>
                                @if($logs->onFirstPage())
                                    <span class="is-disabled" aria-disabled="true">Previous</span>
                                @else
                                    <a href="{{ $logs->previousPageUrl() }}" rel="prev">Previous</a>
                                @endif

                                @if($logs->hasMorePages())
                                    <a href="{{ $logs->nextPageUrl() }}" rel="next">Next</a>
                                @else
                                    <span class="is-disabled" aria-disabled="true">Next</span>
                                @endif
                            </div>
                        </nav>
                    @endif
                @endif
            </section>
        </div>
    </main>
</div>

@push('styles')
<style>
.activity-log-shell{background:#f5f6ef}.activity-logs-workspace{background:#f5f6ef}.activity-log-page{width:min(1280px,100%);margin:0 auto}.activity-log-header{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:20px}.activity-log-header p{margin:0;color:#7a8300;font-size:11px;font-weight:800;letter-spacing:.16em}.activity-log-header h1{margin:6px 0 4px;font-size:32px;letter-spacing:-.025em}.activity-log-header>div:first-child>span{color:#687286}.activity-log-count{min-width:128px;padding:13px 16px;border:1px solid #daddd1;border-radius:12px;background:#fff;text-align:right}.activity-log-count strong{display:block;font-size:24px;line-height:1}.activity-log-count small{display:block;margin-top:5px;color:#74786f}.activity-log-results{border:1px solid #daddd1;border-radius:16px;background:#fff}.activity-log-results-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.activity-log-results-header h2{margin:0;font-size:18px}.activity-log-results-header p{margin:4px 0 0;color:#74786f;font-size:12px}.activity-log-results{overflow:hidden}.activity-log-results-header{align-items:center;padding:19px 20px;border-bottom:1px solid #e6e8e0}.activity-log-results-header>span{color:#687286;font-size:12px}.activity-log-table-wrap{overflow-x:auto}.activity-log-table{width:100%;min-width:1050px;border-collapse:collapse}.activity-log-table th{padding:12px 14px;background:#f7f8f3;color:#62675e;font-size:11px;letter-spacing:.06em;text-align:left;text-transform:uppercase}.activity-log-table td{padding:15px 14px;border-top:1px solid #eceee7;vertical-align:top}.activity-log-table tbody tr:first-child td{border-top:0}.activity-log-table tbody tr:hover{background:#fafbf7}.activity-log-table time{display:grid;min-width:120px}.activity-log-table time strong{font-size:13px}.activity-log-table time span{margin-top:2px;font-size:12px}.activity-log-table time small{margin-top:5px;color:#858a80;font-size:10px}.activity-log-actor{display:flex;align-items:center;gap:10px;min-width:150px}.activity-log-actor>span{width:36px;height:36px;flex:0 0 36px;display:grid;place-items:center;border-radius:50%;background:#e9ecd4;color:#626b00;font-size:12px;font-weight:850}.activity-log-actor>div{min-width:0;display:grid}.activity-log-actor strong{max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}.activity-log-actor small{margin-top:3px;color:#767b72;font-size:11px}.activity-log-description{min-width:210px;max-width:330px}.activity-log-description strong{font-size:13px}.activity-log-description p{margin:5px 0 0;color:#687286;font-size:12px;line-height:1.45;overflow-wrap:anywhere}.activity-log-ip{display:block;min-width:150px;font:700 12px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}.activity-log-device{display:-webkit-box;min-width:190px;max-width:270px;overflow:hidden;color:#767b72;font-size:11px;line-height:1.4;-webkit-box-orient:vertical;-webkit-line-clamp:2}.activity-log-empty{min-height:280px;padding:44px 20px;display:grid;place-items:center;align-content:center;text-align:center}.activity-log-empty>span{width:54px;height:54px;display:grid;place-items:center;margin-bottom:13px;border-radius:14px;background:#e9ecd4;color:#626b00}.activity-log-empty svg{width:27px;height:27px}.activity-log-empty strong{font-size:17px}.activity-log-empty p{margin:5px 0 0;color:#74786f}.activity-log-pagination{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 20px;border-top:1px solid #e6e8e0;background:#fafbf7}.activity-log-pagination>span{color:#687286;font-size:12px}.activity-log-pagination>div{display:flex;gap:8px}.activity-log-pagination a,.activity-log-pagination .is-disabled{min-width:82px;padding:8px 12px;border:1px solid #d4d8cc;border-radius:8px;text-align:center;font-size:12px;font-weight:750}.activity-log-pagination a{background:#fff;color:#171817;text-decoration:none}.activity-log-pagination a:hover{border-color:#aab514;background:#f7f9e9}.activity-log-pagination .is-disabled{background:#f1f2ee;color:#a1a59d;cursor:not-allowed}
@media(max-width:760px){.activity-log-page{width:100%}.activity-log-header{align-items:flex-start}.activity-log-header h1{font-size:27px}.activity-log-count{min-width:105px;padding:12px}.activity-log-results-header{padding:16px}.activity-log-results-header>span{display:none}.activity-log-table-wrap{overflow:visible}.activity-log-table{display:block;min-width:0}.activity-log-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}.activity-log-table tbody{display:grid;gap:12px;padding:12px}.activity-log-table tr{display:grid;padding:15px;border:1px solid #dfe2d8;border-radius:12px;background:#fff}.activity-log-table td{display:grid;grid-template-columns:108px minmax(0,1fr);gap:12px;padding:11px 0;border:0;border-top:1px solid #eceee7}.activity-log-table td:first-child{padding-top:0;border-top:0}.activity-log-table td:last-child{padding-bottom:0}.activity-log-table td::before{content:attr(data-label);padding-top:2px;color:#797e75;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.activity-log-table time,.activity-log-actor,.activity-log-description,.activity-log-ip,.activity-log-device{min-width:0;max-width:none}.activity-log-table time{grid-template-columns:auto 1fr;column-gap:8px}.activity-log-table time small{grid-column:1/-1}.activity-log-description p{margin-top:3px}.activity-log-pagination{align-items:stretch;flex-direction:column;text-align:center}.activity-log-pagination>div{display:grid;grid-template-columns:1fr 1fr}.activity-log-pagination a,.activity-log-pagination .is-disabled{min-height:40px;display:grid;place-items:center}}
@media(max-width:480px){.activity-log-header{display:grid}.activity-log-count{width:100%;text-align:left}.activity-log-count strong,.activity-log-count small{display:inline}.activity-log-count small{margin-left:6px}.activity-log-table td{grid-template-columns:1fr;gap:6px}.activity-log-table td::before{padding:0}.activity-log-actor>span{width:32px;height:32px;flex-basis:32px}}
</style>
@endpush
@endsection
