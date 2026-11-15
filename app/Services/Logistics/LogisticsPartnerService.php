<?php

namespace App\Services\Logistics;

use App\Enums\RiderStatus;
use App\Enums\RoleName;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Identity\UserAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsPartnerService
{
    public function __construct(private UserAccountService $accounts, private AuditLogger $auditLogger) {}

    /**
     * Create a partner and the operator account that manages it.
     *
     * @param  array<string, mixed>  $partnerData
     * @param  array{name: string, username: string, email: string, password: string}  $ownerData
     */
    public function create(array $partnerData, array $ownerData, User $admin): LogisticsPartner
    {
        return DB::transaction(function () use ($partnerData, $ownerData, $admin) {
            $owner = $this->accounts->create($ownerData, [RoleName::Logistics], $admin);
            $partner = LogisticsPartner::query()->create($partnerData + ['user_id' => $owner->id]);
            $this->auditLogger->log('logistics_partner.created', $partner, newValues: $partnerData, actor: $admin);

            return $partner;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LogisticsPartner $partner, array $data, User $admin): LogisticsPartner
    {
        $old = $partner->only(array_keys($data));
        $partner->update($data);
        $this->auditLogger->log('logistics_partner.updated', $partner, $old, $data, $admin);

        return $partner;
    }

    public function setRiderStatus(RiderProfile $rider, RiderStatus $status, User $actor): RiderProfile
    {
        if ($rider->status === $status) {
            throw ValidationException::withMessages(['status' => "This rider is already {$status->label()}."]);
        }

        $old = $rider->status->value;
        $rider->forceFill(['status' => $status] + ($status === RiderStatus::Suspended ? ['availability' => 'offline'] : []))->save();
        $this->auditLogger->log('rider.'.$status->value, $rider, ['status' => $old], ['status' => $status->value], $actor);

        return $rider;
    }
}
