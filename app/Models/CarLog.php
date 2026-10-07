<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['car_id', 'action', 'old_name', 'new_name'];
}
