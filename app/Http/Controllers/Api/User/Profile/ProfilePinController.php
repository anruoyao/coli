<?php

namespace App\Http\Controllers\Api\User\Profile;

use App\Actions\Post\SetProfilePinAction;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilePinController extends Controller
{
    use SupportsApiResponses;

    public function update(Request $request, int $post, SetProfilePinAction $action): JsonResponse
    {
        $request->validate(['pinned' => ['required', 'boolean']]);

        $post = $action->execute($request->user(), $post, $request->boolean('pinned'));

        return $this->responseSuccess([
            'data' => [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'profile_pinned' => $post->profile_pinned,
                'profile_pinned_at' => $post->profile_pinned_at?->toIso8601String(),
            ],
        ]);
    }
}
