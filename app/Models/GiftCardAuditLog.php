<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardAuditLog extends Model
{
    protected $fillable = [
        'voucher_id',
        'admin_user_id',
        'action',
        'old_value',
        'new_value',
        'reason',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(GiftCardVoucher::class, 'voucher_id');
    }
}
