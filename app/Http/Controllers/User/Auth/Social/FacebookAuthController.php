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

namespace App\Http\Controllers\User\Auth\Social;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Services\Auth\Social\SocialAuthService;
use Laravel\Socialite\Two\FacebookProvider;

class FacebookAuthController extends Controller
{
    protected $defaultScopes = ['email', 'public_profile'];

    protected array $driverCredentials;

    protected string $driverName = 'facebook';

    protected $socialAuthService;

    public function __construct(SocialAuthService $socialAuthService)
    {
        $this->socialAuthService = $socialAuthService;
        $this->driverCredentials = $this->socialAuthService->getCredentials($this->driverName);
    }

    public function index()
    {
        $socialite = Socialite::buildProvider(FacebookProvider::class, $this->driverCredentials);

        return $socialite->scopes($this->defaultScopes)->redirect();
    }

    public function callbackHandler()
    {
        $socialiteUser = $this->fetchUserData();
    }

    private function fetchUserData()
    {
        return Socialite::buildProvider(FacebookProvider::class, $this->driverCredentials)->user();
    }
}
