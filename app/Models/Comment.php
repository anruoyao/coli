<?php

namespace App\Models;

use App\Models\Traits\Base\BaseModel;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;
use App\Models\Traits\Text\InteractsWithText;

class Comment extends Model
{
    use BaseModel,
        InteractsWithText;
    
    public $fillable = [
        'content',
        'user_id',
        'post_id',
        'parent_id',
        'root_id',
    ];

    protected $casts = [
        'created_at' => ModelTimestampCast::class
    ];

    protected static function booted(): void
    {
        // 创建回复时自动归一 root_id：回复「回复」也挂到顶层主评论的线程下。
        static::creating(function (Comment $comment) {
            if (! empty($comment->parent_id) && empty($comment->root_id)) {
                $parent = static::query()->where('id', $comment->parent_id)->first(['id', 'parent_id', 'root_id']);
                if ($parent) {
                    $comment->root_id = $parent->root_id ?: $parent->id;
                }
            }

            if (empty($comment->parent_id)) {
                $comment->root_id = null;
            }
        });
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id', 'id');
    }

    public function root()
    {
        return $this->belongsTo(Comment::class, 'root_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

    /**
     * 同一线程下的全部回复（含任意层级，按 root_id 归并）。
     */
    public function threadReplies()
    {
        return $this->hasMany(Comment::class, 'root_id', 'id');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable', 'reactable_type', 'reactable_id', 'id');
    }

    public function getPostHashIdAttribute(): string
	{
		return encode_id($this->post_id);
	}
}
