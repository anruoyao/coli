<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationBatch extends Model
{
    protected $table = 'notification_batches';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'source_time' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->belongsTo(User::class, 'notifiable_id', 'id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id', 'id');
    }
}