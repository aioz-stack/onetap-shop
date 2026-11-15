<?php

namespace App\Services\Administration;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records security- and money-relevant actions in `audit_logs`.
 */
class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(string $action, ?Model $subject = null, ?array $oldValues = null, ?array $newValues = null, ?User $actor = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => ($actor ?? $this->request->user())?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 1000),
        ]);
    }
}
