<?php

namespace App\Models;

use App\Database\Configs\Table;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    public $table = Table::USER_BLOCKS;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blocked_at' => ModelTimestampCast::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function blockedUser()
    {
        return $this->belongsTo(User::class, 'blocked_user_id', 'id');
    }
}
