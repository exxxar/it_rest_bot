<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    //

    protected $fillable = [
        'user_id',
        'order_type',
        'phone',
    ];
}
