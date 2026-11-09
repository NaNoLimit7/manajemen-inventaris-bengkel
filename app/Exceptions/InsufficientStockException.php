<?php

namespace App\Exceptions;

use App\Models\Item;
use Exception;

class InsufficientStockException extends Exception
{
    public static function forItem(Item $item, string $remaining, string $unit): self
    {
        return new self(sprintf(
            'Stok %s tidak cukup. Tersisa %s %s.',
            $item->name,
            $remaining,
            $unit,
        ));
    }
}
