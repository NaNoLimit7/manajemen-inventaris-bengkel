<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Setting;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Collection;

class AlertService
{
    public const DEFAULT_EXPIRY_WARNING_DAYS = 30;

    /**
     * @return Collection<int, Item>
     */
    public function lowStockItems(): Collection
    {
        return Item::query()
            ->active()
            ->withCurrentStock()
            ->where('min_stock', '>', 0)
            ->whereRaw($this->lowStockCondition())
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, StockBatch>
     */
    public function expiringBatches(): Collection
    {
        $today = now()->startOfDay()->toDateString();
        $limit = now()->startOfDay()->addDays($this->expiryWarningDays())->toDateString();

        return StockBatch::query()
            ->with('item')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $limit)
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, StockBatch>
     */
    public function expiredBatches(): Collection
    {
        return StockBatch::query()
            ->with('item')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{low_stock: int, expiring: int, expired: int, total: int}
     */
    public function counts(): array
    {
        $today = now()->startOfDay()->toDateString();
        $limit = now()->startOfDay()->addDays($this->expiryWarningDays())->toDateString();

        $lowStock = Item::query()
            ->active()
            ->where('min_stock', '>', 0)
            ->whereRaw($this->lowStockCondition())
            ->count();

        $expiring = StockBatch::query()
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $limit)
            ->count();

        $expired = StockBatch::query()
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $today)
            ->count();

        return [
            'low_stock' => $lowStock,
            'expiring' => $expiring,
            'expired' => $expired,
            'total' => $lowStock + $expiring + $expired,
        ];
    }

    public function expiryWarningDays(): int
    {
        $value = Setting::query()
            ->where('key', 'expiry_warning_days')
            ->value('value');

        if (! is_numeric($value)) {
            return self::DEFAULT_EXPIRY_WARNING_DAYS;
        }

        $days = (int) $value;

        return $days > 0 ? $days : self::DEFAULT_EXPIRY_WARNING_DAYS;
    }

    private function lowStockCondition(): string
    {
        return 'COALESCE((SELECT SUM(stock_batches.quantity_remaining) FROM stock_batches WHERE stock_batches.item_id = items.id), 0) < items.min_stock';
    }
}
