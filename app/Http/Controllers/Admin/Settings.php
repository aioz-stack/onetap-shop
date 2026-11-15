<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsSchema;
use App\Services\Administration\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class Settings extends Controller
{
    public function __construct(private SettingsService $settings, private AuditLogger $auditLogger) {}

    public function edit(string $group = 'general'): Response
    {
        $definition = SettingsSchema::group($group);
        abort_unless($definition, 404);

        $values = [];

        foreach ($definition['fields'] as $key => $field) {
            $values[$key] = $this->settings->get($group, $key, $field['default']);
        }

        return Inertia::render('Admin/Settings/Edit', [
            'group' => $group,
            'groups' => collect(SettingsSchema::groups())->map(fn ($item, $key) => ['key' => $key, 'label' => $item['label']])->values(),
            'definition' => [
                'label' => $definition['label'],
                'description' => $definition['description'],
                'fields' => collect($definition['fields'])->map(fn ($field, $key) => ['key' => $key] + collect($field)->except(['rules', 'public'])->all())->values(),
            ],
            'values' => $values,
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $definition = SettingsSchema::group($group);
        abort_unless($definition, 404);

        $validated = $request->validate(
            collect($definition['fields'])->mapWithKeys(fn ($field, $key) => [$key => $field['rules']])->all(),
            [],
            collect($definition['fields'])->mapWithKeys(fn ($field, $key) => [$key => strtolower($field['label'])])->all(),
        );

        $old = [];

        DB::transaction(function () use ($definition, $group, $validated, &$old) {
            foreach ($definition['fields'] as $key => $field) {
                $value = $validated[$key] ?? null;
                $value = match ($field['type']) {
                    'boolean' => $value ? '1' : '0',
                    'money' => number_format((float) $value, 2, '.', ''),
                    default => $value === null ? null : (string) $value,
                };

                $old[$key] = $this->settings->get($group, $key);
                $this->settings->set($group, $key, $value);
            }
        });

        $this->auditLogger->log('settings.updated', null, [$group => $old], [$group => $validated]);

        return back()->with('success', "{$definition['label']} settings saved.");
    }
}
