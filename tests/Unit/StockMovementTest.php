<?php

namespace Tests\Unit;

use App\Models\StockMovement;
use LogicException;
use PHPUnit\Framework\TestCase;

class StockMovementTest extends TestCase
{
    public function test_stock_movement_cannot_be_updated(): void
    {
        $this->expectException(LogicException::class);

        (new StockMovement)->update(['quantity' => 5]);
    }

    public function test_stock_movement_cannot_be_deleted(): void
    {
        $this->expectException(LogicException::class);

        (new StockMovement)->delete();
    }
}
