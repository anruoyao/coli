<?php

namespace App\Observers;

use App\Models\Post;
use App\Actions\Post\IndexPostAction;
use App\Actions\Post\DeindexPostAction;

class PostObserver
{
    public function created(Post $post): void
    {
        (new IndexPostAction(post: $post))->execute();
    }

    public function updated(Post $post): void
    {
        (new IndexPostAction(post: $post))->execute();
    }

    public function deleted(Post $post): void
    {
        (new DeindexPostAction(post: $post))->execute();
    }

    public function restored(Post $post): void
    {
        (new IndexPostAction(post: $post))->execute();
    }

    public function forceDeleted(Post $post): void
    {
        (new DeindexPostAction(post: $post))->execute();
    }
}
