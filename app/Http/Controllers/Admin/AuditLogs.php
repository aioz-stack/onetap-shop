<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogs extends Controller
{
    public function index(Request $request): Response
    {
        $action = trim((string) $request->query('action'));
        $search = trim((string) $request->query('search'));
        $from = $request->date('from');
        $to = $request->date('to');

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => AuditLog::query()
                ->with('user:id,name,username')
                ->when($action !== '', fn ($query) => $query->where('action', 'like', "{$action}%"))
                ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->only(['id', 'name', 'username']),
                    'subject' => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : null,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'filters' => ['action' => $action ?: null, 'search' => $search, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'actionGroups' => AuditLog::query()
                ->selectRaw("distinct substr(action, 1, instr(action, '.') - 1) as action_group")
                ->pluck('action_group')
                ->filter()
                ->sort()
                ->values(),
        ]);
    }
}
