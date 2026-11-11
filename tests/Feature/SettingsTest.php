<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Setting;
use App\Models\StockBatch;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_shows_current_value(): void
    {
        Setting::query()->create(['key' => 'expiry_warning_days', 'value' => '45']);

        $response = $this->withBasicAuthCredentials()->get(route('settings.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->where('expiryWarningDays', 45));
    }

    public function test_expiry_warning_days_can_be_updated(): void
    {
        $response = $this->withBasicAuthCredentials()->put(route('settings.update'), [
            'expiry_warning_days' => 14,
        ]);

        $response->assertRedirect(route('settings.edit'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('settings', [
            'key' => 'expiry_warning_days',
            'value' => '14',
        ]);
    }

    public function test_expiry_warning_days_is_created_when_missing(): void
    {
        $this->withBasicAuthCredentials()->put(route('settings.update'), [
            'expiry_warning_days' => 7,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'expiry_warning_days',
            'value' => '7',
        ]);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $invalidValues = [null, 0, -5, 'abc', 366];

        foreach ($invalidValues as $value) {
            $response = $this->withBasicAuthCredentials()->put(route('settings.update'), [
                'expiry_warning_days' => $value,
            ]);

            $response->assertSessionHasErrors('expiry_warning_days');
        }

        $this->assertDatabaseMissing('settings', ['key' => 'expiry_warning_days']);
    }

    public function test_saved_value_changes_alert_results(): void
    {
        $item = Item::factory()->create(['has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addDays(20)->toDateString(),
            'quantity_remaining' => 2,
        ]);

        $alerts = app(AlertService::class);

        $this->assertSame(1, $alerts->counts()['expiring']);

        $this->withBasicAuthCredentials()->put(route('settings.update'), [
            'expiry_warning_days' => 10,
        ]);

        $this->assertSame(0, app(AlertService::class)->counts()['expiring']);
    }
}
