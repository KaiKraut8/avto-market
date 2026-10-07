<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarView extends Model
{
    public $timestamps = false;

    protected $fillable = ['car_id', 'visitor_id', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
