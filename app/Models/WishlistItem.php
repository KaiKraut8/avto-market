<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['visitor_id', 'car_id'];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
