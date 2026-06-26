<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardOrder extends Model
{
    protected $table = 'gift_card_orders';

    protected $fillable = [
        'shopify_order_id',
        'shopify_order_number',
        'shopify_customer_id',
        'customer_name',
        'customer_email',
        'shopify_product_id',
        'shopify_variant_id',
        'gift_card_product_name',
        'amount',
        'template_name',
        'recipient_name',
        'recipient_email',
        'sender_name',
        'personal_message',
        'delivery_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'delivery_date' => 'date',
    ];

    /**
     * Vouchers generated for this order.
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(GiftCardVoucher::class, 'gift_card_order_id');
    }
}
