<?php

namespace Tests\Feature\Security;

use App\Enums\User\UserRole;
use App\Livewire\Admin\Auth\Login as AdminLogin;
use App\Livewire\User\Auth\Login as UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class LoginSessionRotationTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function test_user_login_regenerates_the_session_identifier(): void
    {
        config(['features.login.enabled' => true]);
        $user = $this->makeUser(['password' => 'secret-password']);
        $session = $this->bootTestSession();
        $sessionId = $session->getId();
        $login = new UserLogin();
        $login->loginStep = 2;
        $login->authCreds = [
            'login' => $user->email,
            'password' => 'secret-password',
            'remember' => false,
        ];
        $login->userData = $user;

        $login->submitForm();

        $this->assertNotSame($sessionId, $session->getId());
    }

    public function test_admin_login_regenerates_the_session_identifier(): void
    {
        $admin = $this->makeUser([
            'role' => UserRole::ADMIN->value,
            'password' => 'secret-password',
        ]);
        $session = $this->bootTestSession();
        $sessionId = $session->getId();
        $login = new AdminLogin();
        $login->authCreds = [
            'login' => $admin->email,
            'password' => 'secret-password',
        ];

        $login->submitForm();

        $this->assertNotSame($sessionId, $session->getId());
    }

    private function bootTestSession(): Store
    {
        $session = app('session')->driver();
        $session->start();

        $request = Request::create('/');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $session;
    }
}
