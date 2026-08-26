<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->limit(120)->toString();
        $roles = [
            User::ROLE_SUPER_ADMIN => 'Super Admin',
            User::ROLE_ADMIN => 'Admin',
            User::ROLE_CASHIER => 'Cashier',
        ];
        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $role = array_key_exists($request->string('role')->toString(), $roles)
            ? $request->string('role')->toString()
            : '';
        $method = in_array(strtoupper($request->string('method')->toString()), $methods, true)
            ? strtoupper($request->string('method')->toString())
            : '';
        $action = $request->string('action')->trim()->limit(255, '')->toString();

        $logs = ActivityLog::query()
            ->with('actor')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $like = "%{$search}%";

                    $query
                        ->where('actor_name', 'like', $like)
                        ->orWhere('actor_role', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('route_name', 'like', $like)
                        ->orWhere('path', 'like', $like)
                        ->orWhere('ip_address', 'like', $like)
                        ->orWhere('user_agent', 'like', $like);
                });
            })
            ->when($role !== '', fn (Builder $query) => $query->where('actor_role', $role))
            ->when($method !== '', fn (Builder $query) => $query->where('method', $method))
            ->when($action !== '', fn (Builder $query) => $query->where('action', $action))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'search' => $search,
            'role' => $role,
            'method' => $method,
            'action' => $action,
            'roles' => $roles,
            'methods' => $methods,
            'actions' => ActivityLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'todayCount' => ActivityLog::query()->whereDate('created_at', today())->count(),
            'failedCount' => ActivityLog::query()->where('status_code', '>=', 400)->count(),
        ]);
    }
}
