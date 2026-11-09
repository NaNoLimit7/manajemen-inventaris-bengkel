<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Item::query()->exists()) {
            return;
        }

        $categories = Category::query()
            ->whereIn('slug', ['consumable', 'routine', 'major'])
            ->get()
            ->keyBy('slug');

        $items = [
            [
                'category' => 'consumable',
                'code' => 'BRG-00001',
                'name' => 'Oli Mesin 10W-40',
                'brand' => 'Yamaha',
                'unit' => 'botol',
                'purchase_price' => 45_000,
                'sale_price' => 55_000,
                'min_stock' => 6,
                'shelf_location' => 'RAK-01',
                'has_expiry' => true,
                'batches' => [
                    ['expiry_date' => now()->addDays(20)->toDateString(), 'quantity_remaining' => 8],
                    ['expiry_date' => now()->addMonths(6)->toDateString(), 'quantity_remaining' => 12],
                ],
            ],
            [
                'category' => 'consumable',
                'code' => 'BRG-00002',
                'name' => 'Busi Iridium',
                'brand' => 'NGK',
                'unit' => 'pcs',
                'purchase_price' => 35_000,
                'sale_price' => 50_000,
                'min_stock' => 4,
                'shelf_location' => 'RAK-02',
                'has_expiry' => false,
                'batches' => [
                    ['expiry_date' => null, 'quantity_remaining' => 24],
                ],
            ],
            [
                'category' => 'routine',
                'code' => 'BRG-00003',
                'name' => 'V-Belt CVT',
                'brand' => 'Honda',
                'unit' => 'pcs',
                'purchase_price' => 85_000,
                'sale_price' => 120_000,
                'min_stock' => 5,
                'shelf_location' => 'RAK-03',
                'has_expiry' => false,
                'batches' => [
                    ['expiry_date' => null, 'quantity_remaining' => 3],
                ],
            ],
            [
                'category' => 'routine',
                'code' => 'BRG-00004',
                'name' => 'Filter Udara',
                'brand' => 'Yamaha',
                'unit' => 'pcs',
                'purchase_price' => 40_000,
                'sale_price' => 60_000,
                'min_stock' => 3,
                'shelf_location' => 'RAK-04',
                'has_expiry' => false,
                'batches' => [
                    ['expiry_date' => null, 'quantity_remaining' => 10],
                ],
            ],
            [
                'category' => 'major',
                'code' => 'BRG-00005',
                'name' => 'Shock Absorber Belakang',
                'brand' => 'YSS',
                'unit' => 'set',
                'purchase_price' => 350_000,
                'sale_price' => 450_000,
                'min_stock' => 2,
                'shelf_location' => 'RAK-05',
                'has_expiry' => false,
                'batches' => [
                    ['expiry_date' => null, 'quantity_remaining' => 2],
                ],
            ],
            [
                'category' => 'consumable',
                'code' => 'BRG-00006',
                'name' => 'Minyak Rem DOT 3',
                'brand' => 'Prestone',
                'unit' => 'botol',
                'purchase_price' => 25_000,
                'sale_price' => 40_000,
                'min_stock' => 4,
                'shelf_location' => 'RAK-06',
                'has_expiry' => true,
                'batches' => [
                    ['expiry_date' => now()->addDays(5)->toDateString(), 'quantity_remaining' => 2],
                ],
            ],
        ];

        foreach ($items as $itemData) {
            $category = $categories->get($itemData['category']);

            if (! $category) {
                continue;
            }

            $item = Item::query()->create([
                'category_id' => $category->id,
                'code' => $itemData['code'],
                'name' => $itemData['name'],
                'brand' => $itemData['brand'],
                'unit' => $itemData['unit'],
                'purchase_price' => $itemData['purchase_price'],
                'sale_price' => $itemData['sale_price'],
                'min_stock' => $itemData['min_stock'],
                'shelf_location' => $itemData['shelf_location'],
                'has_expiry' => $itemData['has_expiry'],
                'is_active' => true,
            ]);

            foreach ($itemData['batches'] as $batch) {
                StockBatch::query()->create([
                    'item_id' => $item->id,
                    'expiry_date' => $batch['expiry_date'],
                    'quantity_remaining' => $batch['quantity_remaining'],
                    'received_on' => now()->subDays(7)->toDateString(),
                ]);
            }
        }
    }
}
