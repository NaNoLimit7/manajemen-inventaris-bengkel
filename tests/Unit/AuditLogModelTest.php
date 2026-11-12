<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_table_has_no_updated_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('audit_logs', 'created_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
    }

    public function test_audit_logs_table_has_expected_indexes(): void
    {
        $indexedColumns = collect(Schema::getIndexes('audit_logs'))
            ->map(fn (array $index): array => $index['columns'])
            ->all();

        $this->assertContains(['auditable_type', 'auditable_id'], $indexedColumns);
        $this->assertContains(['created_at'], $indexedColumns);
    }

    public function test_factory_creates_audit_log_with_array_payloads(): void
    {
        $log = AuditLog::factory()->create([
            'before' => ['name' => 'Oli Mesin'],
            'after' => ['name' => 'Oli Mesin Baru'],
        ]);

        $fresh = $log->fresh();

        $this->assertSame(['name' => 'Oli Mesin'], $fresh->before);
        $this->assertSame(['name' => 'Oli Mesin Baru'], $fresh->after);
        $this->assertInstanceOf(Carbon::class, $fresh->created_at);
    }

    public function test_auditable_relation_returns_related_model(): void
    {
        $item = Item::factory()->create();
        $log = AuditLog::factory()->create([
            'auditable_type' => Item::class,
            'auditable_id' => $item->id,
        ]);

        $this->assertTrue($log->auditable->is($item));
    }
}
