<?php

namespace App\Services\Logistics;

use App\Models\LogisticsPartner;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Service areas (barangay or whole city) and their delivery fees. Shared by admin and logistics screens.
 */
class CoverageService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function validated(Request $request, LogisticsPartner $partner, ?ServiceArea $area = null): array
    {
        $validated = $request->validate([
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
        ]);

        $validated['barangay'] = filled($validated['barangay'] ?? null) ? trim($validated['barangay']) : null;
        $validated['city'] = trim($validated['city']);

        $duplicate = $partner->serviceAreas()
            ->whereRaw('lower(city) = ?', [mb_strtolower($validated['city'])])
            ->where(fn ($query) => $validated['barangay'] ? $query->whereRaw('lower(barangay) = ?', [mb_strtolower($validated['barangay'])]) : $query->whereNull('barangay'))
            ->when($area, fn ($query) => $query->whereKeyNot($area->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['city' => 'This area is already covered.']);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(LogisticsPartner $partner, array $data, User $actor, ?ServiceArea $area = null): ServiceArea
    {
        if ($area) {
            abort_unless($area->logistics_partner_id === $partner->id, 404);
            $old = $area->only(array_keys($data));
            $area->update($data);
            $this->auditLogger->log('service_area.updated', $area, $old, $data, $actor);

            return $area;
        }

        $area = $partner->serviceAreas()->create($data);
        $this->auditLogger->log('service_area.created', $area, newValues: $data, actor: $actor);

        return $area;
    }

    public function delete(LogisticsPartner $partner, ServiceArea $area, User $actor): void
    {
        abort_unless($area->logistics_partner_id === $partner->id, 404);
        $this->auditLogger->log('service_area.deleted', $area, $area->only(['city', 'barangay', 'delivery_fee']), actor: $actor);
        $area->delete();
    }
}
