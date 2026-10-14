<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id',
        'gateway_payment_id',
        'gateway_refund_id',
        'amount',
        'status',
        'reason',
        'request_started_at',
        'request_key',
        'submitted_at',
        'submission_status',
    ];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'request_started_time' => 'datetime', 'submitted_at' => 'datetime',];
    }
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
