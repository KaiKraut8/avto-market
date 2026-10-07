<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarInquiry extends Model
{
    protected $fillable = ['car_id', 'visitor_id', 'name', 'email', 'phone', 'message'];
}
