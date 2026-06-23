<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCard extends Model
{
    protected $fillable = [
        'shop_id',
        'shopify_product_id',
        'shopify_product_variant_id',
        'name',
        'amount',
        'code_prefix',
        'validity_days',
        'quantity',
        'quantity_issued',
        'active',
        'template_id',
        'image_url',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(GiftCardTemplate::class, 'template_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(GiftCardVoucher::class);
    }
}
