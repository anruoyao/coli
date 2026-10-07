<?php

namespace App\Models\Traits\Timestamp;

use Illuminate\Database\Eloquent\Model;

trait SyncCreatedAtOnSave
{
    protected static function bootSyncCreatedAtOnSave()
    {
        static::saving(function (Model $model) {
            // 仅更新场景处理：把 created_at 还原为数据库原始值，避免水合回传
            // （如 Livewire 组件）把它标记为 dirty 后被重写（时区漂移 / 1292 格式错误）。
            // 新建场景（insert）的 created_at 由框架在 performInsert 的
            // updateTimestamps() 中填充，此时 attributes 尚无该键，不能访问，
            // 否则会抛 Undefined array key "created_at" 导致创建页 500。
            if (! $model->exists) {
                return;
            }

            $createdAtColumn = $model->getCreatedAtColumn();
            $rawOriginal = $model->getRawOriginal($createdAtColumn);

            if (! is_null($rawOriginal)) {
                $model->setAttribute($createdAtColumn, $rawOriginal);
            }
        });
    }
}
