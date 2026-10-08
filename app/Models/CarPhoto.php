<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A car photo. The image itself is stored in the database (column "data"); it is served by
// PhotoController at /photos/{id}. Lists never load the image bytes: see the "without-data" scope.
#[Fillable(['path', 'mime', 'size', 'data', 'position'])]
class CarPhoto extends Model
{
    public const LIST_COLUMNS = ['id', 'car_id', 'path', 'mime', 'size', 'position', 'created_at', 'updated_at'];

    protected $hidden = ['data'];

    protected static function booted(): void
    {
        // a car list would otherwise pull every photo's bytes from the database
        static::addGlobalScope('without-data', function (Builder $query) {
            if ($query->getQuery()->columns === null) {
                $query->select(array_map(fn ($c) => 'car_photos.'.$c, self::LIST_COLUMNS));
            }
        });
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function url(): string
    {
        return route('photos.show', $this);
    }
}
