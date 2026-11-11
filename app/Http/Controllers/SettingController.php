<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(AlertService $alertService): Response
    {
        return Inertia::render('Settings', [
            'expiryWarningDays' => $alertService->expiryWarningDays(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::query()->updateOrCreate(
            ['key' => 'expiry_warning_days'],
            ['value' => (string) $request->integer('expiry_warning_days')],
        );

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Pengaturan berhasil disimpan.');
    }
}
