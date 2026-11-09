<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemCodeService
{
    /**
     * Create an item with a manual code or a generated BRG-XXXXX code.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Item
    {
        return DB::transaction(function () use ($data): Item {
            $providedCode = trim((string) ($data['code'] ?? ''));

            $item = new Item($data);
            $item->code = $providedCode !== '' ? $providedCode : 'TMP-'.Str::uuid();
            $item->save();

            if ($providedCode === '') {
                $item->code = sprintf('BRG-%05d', $item->id);
                $item->save();
            }

            return $item->refresh();
        });
    }
}
