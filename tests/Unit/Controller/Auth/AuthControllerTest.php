<?php

namespace Tests\Unit\Controller\Auth;

use App\Enum\Auth\UserEnum;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class AuthControllerTest extends AbstractControllerUnitTester
{
    //| show login tests

    public function testLoginShow(): void
    {
        $response = $this->get(route('auth.login'));
        $this->assertView($response, 'authentication.login');
    }

    public function testLoginAlreadySignedIn(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('auth.login'));
        $this->assertRedirect($response, 'home');
    }

    //| show signup tests

    public function testSignupShow(): void
    {
        $response = $this->get(route('auth.signup'));
        $this->assertView($response, 'authentication.signup');
    }

    public function testSignupAlreadySignedIn(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('auth.signup'));
        $this->assertRedirect($response, 'home');
    }

    //| logout tests

    public function testLogout(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('auth.logout'));

        $this->assertRedirect($response, 'auth.login', [
            'message' => UserEnum::LOGOUT_MESSAGE,
        ]);
    }

    public function testLogoutNotSignedIn(): void
    {
        $response = $this->get(route('auth.logout'));
        $this->assertRedirect($response, 'home');
    }
}
