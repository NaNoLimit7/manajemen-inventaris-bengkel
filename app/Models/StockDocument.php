<?php

namespace App\Models;

use Database\Factories\StockDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockDocument extends Model
{
    /** @use HasFactory<StockDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'purpose',
        'occurred_on',
        'reference_number',
        'supplier_id',
        'customer_id',
        'purchase_order_id',
        'reverses_document_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_document_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_document_id');
    }
}
