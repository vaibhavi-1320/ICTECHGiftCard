<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardTemplate extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'tag',
        'media_url',
        'active',
        'body_html',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'template_id');
    }
}
