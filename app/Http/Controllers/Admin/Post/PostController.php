<?php

namespace App\Http\Controllers\Admin\Post;

use App\Models\Post;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Actions\Post\DeletePostAction;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::active()->with(['user'])->withCount('media')->latest()->paginate(10);

        return view('admin::posts.index.index', [
            'posts' => $posts
        ]);
    }

    public function show(int $postId)
    {
        $postData = Post::active()->with(['user', 'media'])->withCount('media')->findOrFail($postId);

        return view('admin::posts.show.index', [
            'postData' => $postData
        ]);
    }

    public function destroy(int $postId)
    {
        $postData = Post::active()->findOrFail($postId);

        (new DeletePostAction($postData))->execute();
        
        $postData->user->decrementValue('publications_count', 1);

        return redirect()->route('admin.posts.index')->with('flashMessage', (new Flash(content: __('admin/flash.post.delete_success')))->get());
    }
}
