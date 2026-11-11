<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_badges_are_shared_with_matching_alert_counts(): void
    {
        $lowItem = Item::factory()->create(['min_stock' => 10]);
        StockBatch::factory()->for($lowItem)->withoutExpiry()->create([
            'quantity_remaining' => 1,
        ]);

        $item = Item::factory()->create(['has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addDays(5)->toDateString(),
            'quantity_remaining' => 2,
        ]);
        StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->subDays(5)->toDateString(),
            'quantity_remaining' => 3,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('badges.low_stock', 1)
            ->where('badges.expiring', 1)
            ->where('badges.expired', 1)
            ->where('badges.total', 3));
    }

    public function test_badges_are_available_on_every_inertia_page(): void
    {
        $response = $this->withBasicAuthCredentials()->get(route('items.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('badges.low_stock', 0)
            ->where('badges.expiring', 0)
            ->where('badges.expired', 0)
            ->where('badges.total', 0));
    }
}
