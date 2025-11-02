<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'item_type',
        'metadata'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
        'metadata' => 'json'
    ];

    /**
     * Get the invoice that owns the item
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Calculate total price from quantity and unit price
     */
    public function calculateTotal(): void
    {
        $this->total_price = $this->quantity * $this->unit_price;
        $this->save();
    }

    /**
     * Get formatted unit price
     */
    public function getFormattedUnitPrice(): string
    {
        return number_format($this->unit_price, 4);
    }

    /**
     * Get formatted total price
     */
    public function getFormattedTotalPrice(): string
    {
        return number_format($this->total_price, 2);
    }
}
