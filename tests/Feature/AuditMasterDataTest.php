<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AuditMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_create_update_and_delete_are_audited(): void
    {
        $item = Item::factory()->create(['name' => 'Oli Mesin']);

        $created = $this->logFor($item, 'item.created');
        $this->assertNull($created->before);
        $this->assertSame('Oli Mesin', $created->after['name']);

        $item->update(['name' => 'Oli Mesin Baru']);

        $updated = $this->logFor($item, 'item.updated');
        $this->assertSame('Oli Mesin', $updated->before['name']);
        $this->assertSame('Oli Mesin Baru', $updated->after['name']);

        $item->delete();

        $deleted = $this->logFor($item, 'item.deleted');
        $this->assertSame('Oli Mesin Baru', $deleted->before['name']);
        $this->assertNull($deleted->after);
    }

    public function test_supplier_create_update_and_delete_are_audited(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Toko Oli']);

        $created = $this->logFor($supplier, 'supplier.created');
        $this->assertNull($created->before);
        $this->assertSame('Toko Oli', $created->after['name']);

        $supplier->update(['name' => 'Toko Oli Jaya']);

        $updated = $this->logFor($supplier, 'supplier.updated');
        $this->assertSame('Toko Oli', $updated->before['name']);
        $this->assertSame('Toko Oli Jaya', $updated->after['name']);

        $supplier->delete();

        $deleted = $this->logFor($supplier, 'supplier.deleted');
        $this->assertSame('Toko Oli Jaya', $deleted->before['name']);
        $this->assertNull($deleted->after);
    }

    public function test_customer_create_update_and_delete_are_audited(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi']);

        $created = $this->logFor($customer, 'customer.created');
        $this->assertNull($created->before);
        $this->assertSame('Budi', $created->after['name']);

        $customer->update(['name' => 'Budi Santoso']);

        $updated = $this->logFor($customer, 'customer.updated');
        $this->assertSame('Budi', $updated->before['name']);
        $this->assertSame('Budi Santoso', $updated->after['name']);

        $customer->delete();

        $deleted = $this->logFor($customer, 'customer.deleted');
        $this->assertSame('Budi Santoso', $deleted->before['name']);
        $this->assertNull($deleted->after);
    }

    public function test_update_without_real_changes_does_not_create_log(): void
    {
        $item = Item::factory()->create(['name' => 'Oli Mesin']);

        $countBefore = $this->logsFor($item)->count();

        $item->update(['name' => 'Oli Mesin']);
        $item->save();

        $this->assertSame($countBefore, $this->logsFor($item)->count());
    }

    public function test_audit_is_rolled_back_when_transaction_fails(): void
    {
        try {
            DB::transaction(function (): void {
                Item::factory()->create(['name' => 'Barang Gagal']);

                throw new RuntimeException('Transaksi gagal.');
            });
        } catch (RuntimeException) {
            // Transaction is expected to fail.
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function logFor(Item|Supplier|Customer $model, string $event): AuditLog
    {
        return $this->logsFor($model)->where('event', $event)->firstOrFail();
    }

    /**
     * @return Builder<AuditLog>
     */
    private function logsFor(Item|Supplier|Customer $model): Builder
    {
        return AuditLog::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey());
    }
}
