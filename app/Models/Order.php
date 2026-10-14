<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'order_id', 'subtotal', 'discount', 'tax', 'shipping_charge', 'total', 'payment_method', 'cancel_reason', 'status','payment_status'];

    protected static function booted()
    {
        static::addGlobalScope('user_orders', function ($query) {
            if (auth()->check()) { // if user logged in
                $query->where('user_id', auth()->id());
            }
        });
    }
    protected function casts(): array
{
    return [
        'expires_at' => 'datetime',
    ];
}
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
