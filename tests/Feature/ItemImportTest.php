<?php

namespace Tests\Feature;

use App\Models\Item;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductionSeeder::class);
    }

    public function test_template_can_be_downloaded(): void
    {
        $response = $this->withBasicAuthCredentials()->get(route('items.import.template'));

        $response->assertOk();
        $response->assertDownload('template-import-barang.csv');
    }

    public function test_preview_returns_summary_without_saving(): void
    {
        $content = $this->csv([
            ['', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'ya', '10', '2027-01-01'],
            ['BRG-BUSI', 'Busi', 'Suku cadang habis pakai', 'NGK', 'pcs', '35000', '50000', '4', 'RAK-02', 'tidak', '5', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.preview'),
            ['file' => $this->upload($content)],
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Import')
            ->has('preview.rows', 2)
            ->where('preview.summary.total_items', 2)
            ->where('preview.summary.initial_stock_items', 2)
            ->where('preview.summary.total_initial_stock', 15)
            ->where('preview.rows.0.category_name', 'Suku cadang habis pakai'));

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_import_creates_items_and_one_initial_stock_document(): void
    {
        $content = $this->csv([
            ['', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'ya', '10', '2027-01-01'],
            ['BRG-BUSI', 'Busi', 'Suku cadang habis pakai', 'NGK', 'pcs', '35000', '50000', '4', 'RAK-02', 'tidak', '5', ''],
            ['BRG-FILTER', 'Filter Udara', 'routine', 'Yamaha', 'pcs', '40000', '60000', '3', 'RAK-03', 'tidak', '0', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.store'),
            ['file' => $this->upload($content)],
        );

        $response->assertRedirect(route('items.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('items', 3);
        $this->assertDatabaseCount('stock_documents', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'in',
            'notes' => 'Stok awal (import)',
            'supplier_id' => null,
        ]);

        $oli = Item::query()->where('name', 'Oli Mesin')->firstOrFail();
        $busi = Item::query()->where('code', 'BRG-BUSI')->firstOrFail();
        $filter = Item::query()->where('code', 'BRG-FILTER')->firstOrFail();

        $this->assertMatchesRegularExpression('/^BRG-\d{5}$/', $oli->code);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $oli->id,
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 10,
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $busi->id,
            'expiry_date' => null,
            'quantity_remaining' => 5,
        ]);
        $this->assertDatabaseMissing('stock_batches', [
            'item_id' => $filter->id,
        ]);
        $this->assertEqualsWithDelta(45_000, (float) $oli->purchase_price, 0.001);
    }

    public function test_import_does_not_save_anything_when_any_row_is_invalid(): void
    {
        $content = $this->csv([
            ['BRG-OK', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'tidak', '10', ''],
            ['BRG-BAD', 'Barang Salah', 'kategori-tidak-ada', 'X', 'pcs', '1000', '2000', '1', '', 'tidak', '0', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.store'),
            ['file' => $this->upload($content)],
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Import')
            ->where('preview', null)
            ->has('rowErrors', 1));

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_duplicate_code_in_file_is_rejected(): void
    {
        $content = $this->csv([
            ['BRG-DUP', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'tidak', '10', ''],
            ['BRG-DUP', 'Busi', 'consumable', 'NGK', 'pcs', '35000', '50000', '4', 'RAK-02', 'tidak', '5', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.preview'),
            ['file' => $this->upload($content)],
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Import')
            ->where('preview', null)
            ->has('rowErrors', 1));

        $this->assertDatabaseCount('items', 0);
    }

    public function test_existing_code_is_rejected(): void
    {
        Item::factory()->create(['code' => 'BRG-EXIST']);

        $content = $this->csv([
            ['BRG-EXIST', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'tidak', '10', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.preview'),
            ['file' => $this->upload($content)],
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Import')
            ->where('preview', null)
            ->has('rowErrors', 1));

        $this->assertDatabaseCount('items', 1);
    }

    public function test_expiry_is_required_for_initial_stock_with_expiry(): void
    {
        $content = $this->csv([
            ['BRG-OLI', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'ya', '10', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.store'),
            ['file' => $this->upload($content)],
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Import')
            ->where('preview', null)
            ->has('rowErrors', 1));

        $this->assertDatabaseCount('items', 0);
    }

    public function test_initial_stock_zero_does_not_create_stock_document(): void
    {
        $content = $this->csv([
            ['BRG-NOSTOCK', 'Barang Tanpa Stok', 'routine', 'Yamaha', 'pcs', '40000', '60000', '3', 'RAK-04', 'tidak', '0', ''],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.store'),
            ['file' => $this->upload($content)],
        );

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_semicolon_delimiter_and_bom_are_supported(): void
    {
        $content = "\xEF\xBB\xBF".$this->csv([
            ['BRG-SEMI', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '6', 'RAK-01', 'tidak', '4', ''],
        ], ';');

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.preview'),
            ['file' => $this->upload($content)],
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Import')
            ->where('preview.summary.total_items', 1)
            ->where('preview.rows.0.code', 'BRG-SEMI'));
    }

    public function test_file_is_required(): void
    {
        $response = $this->withBasicAuthCredentials()->post(route('items.import.preview'), []);

        $response->assertSessionHasErrors('file');
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function csv(array $rows, string $delimiter = ','): string
    {
        $header = [
            'code',
            'name',
            'category',
            'brand',
            'unit',
            'purchase_price',
            'sale_price',
            'min_stock',
            'shelf_location',
            'has_expiry',
            'initial_stock',
            'expiry_date',
        ];

        $lines = [implode($delimiter, $header)];

        foreach ($rows as $row) {
            $lines[] = implode($delimiter, $row);
        }

        return implode("\n", $lines);
    }

    private function upload(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('items.csv', $content);
    }
}
