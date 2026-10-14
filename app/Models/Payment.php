<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'gateway_payment_id',
        'gateway_order_id',
        'amount',
        'payment_method',
        'status',
        'initiation_status',
        'paid_at'
    ];
}
