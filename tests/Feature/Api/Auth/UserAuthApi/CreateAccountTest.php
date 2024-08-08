<?php

namespace Api\Auth\UserAuthApi;

use App\Enum\Auth\UserEnum;
use App\Models\Auth\User;
use App\Service\AvatarGeneratorService;
use Exception;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\Feature\API\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateAccountTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use ValidationTests;
    use PostSaveOperation {
        test_post_operation as protected success_operation;
    }

    /** @inheritDoc */
    protected function getRoute(): string
    {
        return route('api.auth.signup');
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
        $password = fake()->regexify('[A-Z][a-z]{6}[0-9]{3}');

        return [
            'username' => fake()->userName(),
            'email' => fake()->boolean() ? fake()->email() : null,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    /**
     * Test validation for 'username' attribute
     * @throws Exception
     */
    public function test_username_validation(): void {
        $this->assertRequiredValidation('username');
        $this->assertStringValidation('username');
        $this->assertMaxValidation('string', 'username', 28);
        $this->assertUniqueValidation('username');
    }

    /**
     * Test validation for 'email' attribute
     * @throws Exception
     */
    public function test_email_validation(): void {
        $this->assertNullableValidation('email');
        $this->assertEmailValidation('email');
        $this->assertMaxValidation('string', 'email', 40);
        $this->assertUniqueValidation('email');
    }

    /**
     * Test validation for 'password' attribute
     */
    public function test_password_validation(): void  {
        $this->assertRequiredValidation('password');
        $this->assertStringValidation('password');
        $this->assertPasswordValidation('password');
        $this->assertConfirmedValidation('password');
    }

    /** @inheritDoc */
    public function test_post_operation(): void
    {
        $this->mock(AvatarGeneratorService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateProfilePicture')->once();
        });

        $this->success_operation();
    }
}
