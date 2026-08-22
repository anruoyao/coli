<?php

namespace App\Models\Traits\Bookmark;

use App\Models\Bookmark;

trait Bookmarkable
{
	public function bookmarks()
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable', 'bookmarkable_type', 'bookmarkable_id', 'id');
    }

	public function addBookmark(int $userId)
    {
        return $this->bookmarks()->create(['user_id' => $userId]);
    }

	public function removeBookmark(int $userId)
    {
        return $this->bookmarks()->where('user_id', $userId)->delete();
    }

	public function isBookmarkedBy(int $userId)
    {
        return $this->bookmarks()->where('user_id', $userId)->exists();
    }
}
