<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\StockDocument;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_page_lists_latest_logs_first_with_pagination(): void
    {
        $older = $this->makeLog(['event' => 'item.created'], now()->subDay());
        $newer = $this->makeLog(['event' => 'item.updated'], now());

        for ($index = 0; $index < 20; $index++) {
            $this->makeLog(['event' => 'item.updated'], now());
        }

        $response = $this->withBasicAuthCredentials()->get(route('reports.audit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Audit')
            ->has('logs.data', 20)
            ->where('logs.total', 22)
            ->where('logs.data.0.event', 'item.updated'));
    }

    public function test_audit_page_filters_by_event_type_and_date(): void
    {
        $oldItemLog = $this->makeLog([
            'event' => 'item.updated',
            'auditable_type' => Item::class,
        ], now()->subDays(10));

        $stockLog = $this->makeLog([
            'event' => 'stock.adjusted',
            'auditable_type' => StockDocument::class,
        ], now());

        $response = $this->withBasicAuthCredentials()->get(route('reports.audit', [
            'event' => 'stock.adjusted',
            'type' => StockDocument::class,
            'date_from' => now()->subDay()->toDateString(),
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $stockLog->id)
            ->where('filters.event', 'stock.adjusted')
            ->where('filters.type', StockDocument::class)
            ->where('filters.date_from', now()->subDay()->toDateString()));

        $response = $this->withBasicAuthCredentials()->get(route('reports.audit', [
            'date_to' => now()->subDays(5)->toDateString(),
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $oldItemLog->id));
    }

    public function test_audit_page_provides_filter_options(): void
    {
        $this->makeLog([
            'event' => 'supplier.updated',
            'auditable_type' => Supplier::class,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.audit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('events', ['supplier.updated'])
            ->where('types.0.value', Supplier::class)
            ->where('types.0.label', 'Supplier'));
    }

    public function test_audit_page_renders_empty_state_data(): void
    {
        $response = $this->withBasicAuthCredentials()->get(route('reports.audit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Audit')
            ->has('logs.data', 0)
            ->where('logs.total', 0));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeLog(array $attributes = [], mixed $createdAt = null): AuditLog
    {
        $log = AuditLog::factory()->create(array_merge([
            'auditable_id' => 1,
            'before' => ['name' => 'Lama'],
            'after' => ['name' => 'Baru'],
        ], $attributes));

        if ($createdAt !== null) {
            $log->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $log;
    }
}
