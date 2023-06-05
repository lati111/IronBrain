<?php

namespace Tests\Unit\Controller\Auth;

use App\Enum\Auth\UserEnum;
use App\Models\Auth\User;
use App\Service\AvatarGeneratorService;
use Illuminate\Support\Facades\Auth;
use Tests\Unit\Controller\AbstractControllerUnitTester;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class AuthControllerTest extends AbstractControllerUnitTester
{

    //| show signup tests
    public function testShowSignupSuccess(): void
    {
        $response = $this->get(route('auth.signup.show'));
        $this->assertView($response, 'authentication.signup');
    }

    public function testShowSignupAlreadySignedIn(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('auth.signup.show'));
        $this->assertRedirect($response, 'home.show');
    }

    //| show login tests
    public function testShowLoginSuccess(): void
    {
        $response = $this->get(route('auth.login.show'));
        $this->assertView($response, 'authentication.login');
    }

    public function testShowLoginAlreadySignedIn(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('auth.login.show'));
        $this->assertRedirect($response, 'home.show');
    }

    //| attempt signup tests
    public function testAttemptSignupValid(): void
    {
        $username = $this->faker->regexify('[A-Za-z0-9]{16}');
        $email = $this->faker->regexify('[A-Z][A-Za-z0-9]{4}[0-9]@ironbrain[.]io');
        $password = $this->faker->regexify('[A-Z]{2}[a-z]{6}[0-9]{3}');

        $response = $this->post(route('auth.signup.save'), [
            'name' => $username,
            'email' => $email,
            'password' => $password,
            'repeat_password' => $password,
        ]);

        $this->assertRedirect($response, 'auth.login.show', [
            "message" => UserEnum::SIGNUP_SUCCESS_MESSAGE,
        ]);

        $user = User::where('name', $username)->where('email', $email)->first();
        $this->assertNotNull($user);

        $this->assertFileExists('public/img/profile/' . $user->profile_picture);
        $this->assertTrue(File::deleteDirectory('public/img/profile/' . $user->uuid));

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals($username, $user->name);
        $this->assertEquals($email, $user->email);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotNull($user->profile_picture);
    }

    public function testAttemptSignupAlreadySignedIn(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->post(route('auth.signup.save'));
        $this->assertRedirect($response, 'home.show');
    }

    public function testAttemptSignupUsernameValidation(): void
    {
        $route = route('auth.signup.save');

        //valid
        $this->post($route, [
            'name' => $this->faker->regexify('[A-Za-z0-9]{16}'),
        ]);
        $this->assertValidationValid('name');

        //is required
        $this->post($route);
        $this->assertValidationRequired('name');

        //too long
        $this->post($route, [
            'name' => $this->faker->regexify('[A-Za-z0-9]{29}'),
        ]);
        $this->assertValidationTooLong('name', 28);

        //is string
        $this->post($route, [
            'name' => 44,
        ]);
        $this->assertValidationString('name');
    }

    public function testAttemptSignupEmailValidation(): void
    {
        $route = route('auth.signup.save');
        //valid
        $this->post($route, [
            'email' => $this->faker->regexify('[A-Z][A-Za-z0-9]{4}[0-9]@ironbrain[.]io'),
        ]);
        $this->assertValidationValid('email');

        //is required
        $this->post($route);
        $this->assertValidationRequired('email');

        //too long
        $this->post($route, [
            'email' => $this->faker->regexify('[A-Za-z0-9]{28}@ironbrain.io'),
        ]);
        $this->assertValidationTooLong('email', 40);

        //is email
        $this->post($route, [
            'email' => $this->faker->regexify('[A-Za-z0-9]{20}'),
        ]);
        $this->assertValidationEmail('email');

        //already taken
        $this->post($route, [
            'email' => $this->getRandomEntity(User::class)->email,
        ]);
        $this->assertValidationTaken('email');
    }

    public function testAttemptSignupPasswordValidation(): void
    {
        //valid
        $this->post(route('auth.signup.save'), [
            'password' => $this->faker->regexify('[A-Z]{2}[a-z]{6}[0-9]{3}'),
        ]);
        $this->assertValidationValid('password');

        //is required
        $this->post(route('auth.signup.save'));
        $this->assertValidationRequired('password');

        //too short
        $this->post(route('auth.signup.save'), [
            'password' => $this->faker->regexify('[A-Za-z0-9]{4}'),
        ]);
        $this->assertValidationTooShort('password', 8);

        //is string
        $this->post(route('auth.signup.save'), [
            'password' => 44,
        ]);
        $this->assertValidationString('password');

        //missing upper case character
        $this->post(route('auth.signup.save'), [
            'password' => $this->faker->regexify('[a-z]{8}[0-9]{3}'),
        ]);
        $this->assertValidationMixedCase('password');

        //missing lower case character
        $this->post(route('auth.signup.save'), [
            'password' => $this->faker->regexify('[A-Z]{8}[0-9]{3}'),
        ]);
        $this->assertValidationMixedCase('password');

        //missing numbers
        $this->post(route('auth.signup.save'), [
            'password' => $this->faker->regexify('[A-Z]{1}[a-z]{8}'),
        ]);
        $this->assertValidationHasNumbers('password');
    }

    public function testAttemptSignupRepeatPasswordValidation(): void
    {
        $this->mock(AvatarGeneratorService::class);

        //valid
        $password = $this->faker->regexify('[A-Z]{2}[a-z]{6}[0-9]{3}');
        $this->post(route('auth.signup.save'), [
            'name' => $this->faker->regexify('[A-Za-z0-9]{16}'),
            'email' => $this->faker->regexify('[A-Z][A-Za-z0-9]{4}[0-9]@ironbrain[.]io'),
            'password' => $password,
            'repeat_password' => $password,
        ]);
        $this->assertValidationValid('repeat_password');

        //is required
        $this->post(route('auth.signup.save'), []);
        $this->assertValidationRequired('repeat_password');

        //is string
        $this->post(route('auth.signup.save'), [
            'repeat_password' => 44,
        ]);
        $this->assertValidationString('repeat_password');

        //passwords match
        $this->post(route('auth.signup.save'), [
            'name' => $this->faker->regexify('[A-Za-z0-9]{16}'),
            'email' => $this->faker->regexify('[A-Z][A-Za-z0-9]{4}[0-9]@ironbrain[.]io'),
            'password' => $this->faker->regexify('[A-Z]{2}[a-z]{6}[0-9]{2}'),
            'repeat_password' => $password,
        ]);
        $this->assertEquals(UserEnum::PASSWORDS_NOT_MATCHING_MESSAGE, session('error'));
    }

    //| attempt login tests
    public function testAttemptLoginValid(): void
    {
        $email = 'test@test.nl';

        $response = $this->post(route('auth.login.attempt'), [
            'email' => $email,
            'password' => 'Password123',
        ]);

        $this->assertRedirect($response, 'home.show', [
            "message" => UserEnum::LOGIN_SUCCESS_MESSAGE,
        ]);

        $user = User::where('email', $email)->first();
        $this->assertEquals($user, Auth::user());
    }

    public function testAttemptLoginAlreadySignedIn(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->post(route('auth.login.attempt'));
        $this->assertRedirect($response, 'home.show');
    }

    public function testAttemptLoginEmailValidation(): void
    {
        $route = route('auth.login.attempt');

        //valid
        $this->post($route, [
            'email' => $this->faker->regexify('[A-Z][A-Za-z0-9]{4}[0-9]@ironbrain[.]io'),
        ]);
        $this->assertValidationValid('email');

        //is required
        $this->post($route, []);
        $this->assertValidationRequired('email');
    }

    public function testAttemptLoginPasswordValidation(): void
    {
        $route = route('auth.login.attempt');

        //valid
        $this->post($route, [
            'password' => $this->faker->regexify('[A-Za-z0-9]{16}'),
        ]);
        $this->assertValidationValid('password');

        //is required
        $this->post($route, []);
        $this->assertValidationRequired('password');
    }

    public function testAttemptLoginRememberMeValidation(): void
    {
        $route = route('auth.login.attempt');

        //valid
        $this->post($route, [
            'remember_me' => 'on',
        ]);
        $this->assertValidationValid('remember_me');

        //is nullable
        $this->post($route);
        $this->assertValidationValid('remember_me');
    }

    //| logout tests
    public function testAttemptLogoutValid(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('auth.logout'));

        $this->assertRedirect($response, 'auth.login.show', [
            "message" => UserEnum::LOGOUT_MESSAGE,
        ]);

        $this->assertNull(Auth::user());
    }

    public function testAttemptLogoutNotSignedIn(): void
    {
        $response = $this->get(route('auth.logout'));
        $this->assertRedirect($response, 'home.show');
    }
}
