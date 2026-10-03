<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenMessage extends Model
{
    // 只记录隐藏时间（created_at），供媒体回收的宽限期判定使用，无 updated_at
    public $timestamps = true;

    const UPDATED_AT = null;

    public $guarded = [];
}
