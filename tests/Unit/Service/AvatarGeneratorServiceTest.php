<?php

namespace Tests\Unit\Service;

use App\Models\Auth\User;
use App\Service\AvatarGeneratorService;
use Exception;
use Tests\Unit\Service\AbstractServiceTester as ServiceAbstractServiceTester;
use Illuminate\Support\Facades\File;

class AvatarGeneratorServiceTest extends ServiceAbstractServiceTester
{
    public function testGenerateProfilePicture(): void
    {
        $user = $this->createRandomEntity(User::class);
        if (!($user instanceof User)) {
            throw new Exception('Incorrect model returned. User expected');
        }

        AvatarGeneratorService::generateProfilePicture($user);
        $this->assertFileExists('public/img/profile/' . $user->profile_picture);
        $this->assertTrue(File::deleteDirectory('public/img/profile/' . $user->uuid));
    }
}
