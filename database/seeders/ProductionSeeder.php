<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Suku cadang habis pakai',
                'slug' => 'consumable',
                'sort_order' => 1,
            ],
            [
                'name' => 'Suku cadang servis rutin',
                'slug' => 'routine',
                'sort_order' => 2,
            ],
            [
                'name' => 'Suku cadang pengganti utama',
                'slug' => 'major',
                'sort_order' => 3,
            ],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'sort_order' => $category['sort_order'],
                ],
            );
        }

        Setting::query()->firstOrCreate(
            ['key' => 'expiry_warning_days'],
            ['value' => '30'],
        );
    }
}
