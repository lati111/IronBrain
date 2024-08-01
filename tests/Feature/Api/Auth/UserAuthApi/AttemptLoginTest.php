<?php

namespace Api\Auth\UserAuthApi;

use App\Enum\Auth\UserEnum;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\API\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class AttemptLoginTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests;

    /** @var string $username The username for the user trying to login */
    protected string $username;

    /** @var string $username The password for the user trying to login */
    protected string $password;

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();

        $this->username = fake()->userName();
        $this->password = fake()->password();

        $this->createRandomEntity(User::class, [
            'username' => $this->username,
            'password' => Hash::make($this->password),
        ]);
    }

    /** @inheritDoc */
    protected function getRoute(): string
    {
        return route('api.auth.login');
    }

    /** @inheritDoc */
    protected function getOperationUser(): User|null {
        return null;
    }


    /** @inheritDoc */
    protected function getModel(): User
    {
        return new User();
    }

    /** @inheritDoc */
    function getPostParameters(array $overwrites = []): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'remember_me' => fake()->boolean() ? 'on' : null
        ];
    }

    /**
     * Test validation for 'username' attribute
     */
    public function test_username_validation(): void {
        $this->assertRequiredValidation('username');
        $this->assertStringValidation('username');
    }

    /**
     * Test validation for 'password' attribute
     */
    public function test_password_validation(): void  {
        $this->assertRequiredValidation('password');
        $this->assertStringValidation('password');
    }

    /**
     * Test validation for 'remember_me' attribute
     */
    public function test_remember_me_validation(): void  {
        $this->assertCheckboxValidation('remember_me', 'remember me');
    }

    /**
     * Tests that invalid logins are properly caught
     */
    public function test_invalid_login() {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'username' => fake()->userName(),
                'password' => fake()->password()
            ]);

        $response->assertUnauthorized();
        $this->assertApiMessage(UserEnum::INVALID_LOGIN, $response);
    }
}
