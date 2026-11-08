<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Payment\PaymentType;
use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'status' => PaymentStatus::class,
        'payment_type' => PaymentType::class,
        'created_at' => ModelTimestampCast::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getProviderNameAttribute(): string
    {
        $providerKey = $this->payment_method === 'yookassa' ? 'yoo_kassa' : $this->payment_method;
        $providerName = config("payment.providers.{$providerKey}.name");

        return is_string($providerName) && $providerName !== '' ? $providerName : 'Unknown';
    }

    public function getFormattedAmountAttribute(): string
    {
        return Num::currency($this->amount, $this->currency);
    }
}
