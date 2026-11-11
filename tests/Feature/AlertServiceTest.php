<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Setting;
use App\Models\StockBatch;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertService $alerts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alerts = app(AlertService::class);
    }

    public function test_items_below_minimum_stock_are_listed(): void
    {
        $lowItem = Item::factory()->create(['min_stock' => 5]);
        StockBatch::factory()->for($lowItem)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $this->assertTrue($this->alerts->lowStockItems()->contains($lowItem));
    }

    public function test_item_without_batches_counts_as_empty_stock(): void
    {
        $item = Item::factory()->create(['min_stock' => 1]);

        $this->assertTrue($this->alerts->lowStockItems()->contains($item));
    }

    public function test_item_with_zero_minimum_stock_does_not_trigger_alert(): void
    {
        $item = Item::factory()->create(['min_stock' => 0]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 0,
        ]);

        $this->assertFalse($this->alerts->lowStockItems()->contains($item));
    }

    public function test_inactive_item_does_not_trigger_alert(): void
    {
        $item = Item::factory()->create([
            'min_stock' => 5,
            'is_active' => false,
        ]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 1,
        ]);

        $this->assertFalse($this->alerts->lowStockItems()->contains($item));
    }

    public function test_stock_at_minimum_is_not_low(): void
    {
        $item = Item::factory()->create(['min_stock' => 5]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $this->assertFalse($this->alerts->lowStockItems()->contains($item));
    }

    public function test_expiring_batches_follow_warning_days_setting(): void
    {
        Setting::query()->create(['key' => 'expiry_warning_days', 'value' => '10']);

        $item = Item::factory()->create(['has_expiry' => true]);
        $withinWarning = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addDays(8)->toDateString(),
            'quantity_remaining' => 3,
        ]);
        $outsideWarning = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addDays(15)->toDateString(),
            'quantity_remaining' => 3,
        ]);

        $this->assertTrue($this->alerts->expiringBatches()->contains($withinWarning));
        $this->assertFalse($this->alerts->expiringBatches()->contains($outsideWarning));

        Setting::query()->where('key', 'expiry_warning_days')->update(['value' => '20']);

        $this->assertTrue($this->alerts->expiringBatches()->contains($outsideWarning));
    }

    public function test_batch_today_is_expiring_not_expired(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);
        $todayBatch = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->toDateString(),
            'quantity_remaining' => 1,
        ]);

        $this->assertTrue($this->alerts->expiringBatches()->contains($todayBatch));
        $this->assertFalse($this->alerts->expiredBatches()->contains($todayBatch));
    }

    public function test_empty_batches_do_not_trigger_expiry_alerts(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);
        $expiring = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addDays(5)->toDateString(),
            'quantity_remaining' => 0,
        ]);
        $expired = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->subDays(5)->toDateString(),
            'quantity_remaining' => 0,
        ]);

        $this->assertFalse($this->alerts->expiringBatches()->contains($expiring));
        $this->assertFalse($this->alerts->expiredBatches()->contains($expired));
    }

    public function test_batch_without_expiry_is_never_expired(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $this->assertFalse($this->alerts->expiringBatches()->contains($batch));
        $this->assertFalse($this->alerts->expiredBatches()->contains($batch));
    }

    public function test_counts_returns_matching_numbers(): void
    {
        Setting::query()->create(['key' => 'expiry_warning_days', 'value' => '30']);

        $lowItem = Item::factory()->create(['min_stock' => 10]);
        StockBatch::factory()->for($lowItem)->withoutExpiry()->create([
            'quantity_remaining' => 1,
        ]);

        $expiringItem = Item::factory()->create(['has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($expiringItem)->create([
            'expiry_date' => now()->addDays(7)->toDateString(),
            'quantity_remaining' => 2,
        ]);

        $expiredItem = Item::factory()->create(['has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($expiredItem)->create([
            'expiry_date' => now()->subDays(2)->toDateString(),
            'quantity_remaining' => 4,
        ]);

        $this->assertSame([
            'low_stock' => 1,
            'expiring' => 1,
            'expired' => 1,
            'total' => 3,
        ], $this->alerts->counts());
    }
}
