<?php

namespace App\Http\Controllers\Api\User\Bootstrap;

use App\Info\ColibriPlus;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;

class BootstrapController extends Controller
{
    use SupportsApiResponses;

    public function bootstrap()
    {
        return $this->responseSuccess([
            'data' => [
                'version' => ColibriPlus::VERSION,
                'name' => config('app.name'),
                'author' => [
                    'name' => 'Mansur Terla. Full-Stack Web Developer.',
                    'email' => 'mansurtl.contact@gmail.com'
                ],
                'auth' => [
                    'status' => auth_check(),
                    'user' => $this->getUserData()
                ],
                'guest' => [
                    'enabled' => true,
                    'allowed_routes' => [
                        'home_index',
                        'explore_index',
                        'explore_posts',
                        'explore_people',
                        'publication_index',
                        'profile_index',
                        'profile_posts',
                        'profile_media',
                        'profile_info',
                        'marketplace_index',
                        'marketplace_show',
                        'jobs_index',
                        'jobs_show',
                        'mtl_index',
                    ]
                ]
            ]
        ]);
    }

    private function getUserData()
    {
        if(auth_check()) {
            $me = me();

            $userData = [
                'id' => $me->id,
                'name' => $me->name,
                'avatar_url' => $me->avatar_url,
                'cover_url' => $me->cover_url,
                'first_name' => $me->first_name,
                'last_name' => $me->last_name,
                'caption' => $me->getCaption(),
                'username' => $me->username,
                'has_tips' => $me->has_tips,
                'tips' => $me->tips,
                'is_master_account' => $me->isMasterAccount(),
                'is_author' => $me->isAuthor(),
                'verification' => [
                    'status' => $me->verified,
                    'date' => $me->verified_at ? $me->verified_at->getIso() : null
                ],
                'meta' => [
                    'is_admin' => $me->isAdmin()
                ]
            ];

            if($me->isAdmin()) {
                $userData['meta']['admin'] = [
                    'url' => route('admin.dash.index'),
                ];
            }

            return $userData;
        }

        return null;
    }
}
