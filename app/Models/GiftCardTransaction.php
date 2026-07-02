<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTransaction extends Model
{
    protected $fillable = [
        'voucher_id',
        'shopify_order_id',
        'shopify_customer_id',
        'customer_name',
        'customer_email',
        'amount_used',
        'balance_before',
        'balance_after',
    ];

    protected $casts = [
        'amount_used' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(GiftCardVoucher::class, 'voucher_id');
    }
}
