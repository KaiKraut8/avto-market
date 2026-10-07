<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'description'])]
class CarPart extends Model
{
    protected static function booted(): void
    {
        // a part named like a category (Volan, Bremza, ...) is linked to it
        static::creating(function (CarPart $part) {
            $part->part_category_id ??= PartCategory::where('name', $part->name)->value('id');
        });
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'part_category_id');
    }
}
