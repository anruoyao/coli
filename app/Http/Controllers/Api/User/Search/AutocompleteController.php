<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Search;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;

class AutocompleteController extends Controller
{
    use SupportsApiResponses;

    public function searchMentions(Request $request)
    {
        $searchResults = [];
        $validated = validator([
            'query' => $request->input('query')
        ], [
            'query' => ['required', 'string', 'min:2', 'max:255']
        ])->validate();

        if($validated['query']) {
            $mentionedUsers = User::active()->whereLike('username', "%{$validated['query']}%")->with(['permitSettings'])->limit(50)->get();

            if($mentionedUsers->isNotEmpty()) {
                $searchResults = $mentionedUsers
                    // 提及隐私：过滤掉「谁能@我」设置不允许我提及的用户
                    ->filter(function($user) {
                        return $user->permitSettings?->mentions->allows(me(), $user) ?? true;
                    })
                    ->map(function($user) {
                        return [
                            'id' => $user->id,
                            'username' => $user->username,
                            'name' => $user->name,
                            'avatar_url' => $user->avatar_url,
                            'caption' => $user->caption,
                        ];
                    });
            }
        }

        return $this->responseSuccess([
            'data' => $searchResults
        ]);
    }
}
