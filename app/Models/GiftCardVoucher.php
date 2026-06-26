<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardVoucher extends Model
{
    protected $fillable = [
        'gift_card_id',
        'gift_card_order_id',
        'shopify_order_id',
        'shopify_order_line_item_id',
        'shopify_customer_id',
        'code',
        'original_amount',
        'remaining_balance',
        'currency',
        'sender_name',
        'recipient_name',
        'recipient_email',
        'personal_message',
        'scheduled_send_date',
        'sent_at',
        'expires_at',
        'status',
        'used_in_order_number',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_send_date' => 'date',
        'sent_at' => 'datetime',
        'expires_at' => 'date',
        'original_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function giftCardOrder(): BelongsTo
    {
        return $this->belongsTo(GiftCardOrder::class, 'gift_card_order_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class, 'voucher_id');
    }
}
