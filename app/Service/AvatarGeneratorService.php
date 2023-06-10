<?php

namespace App\Service;

use App\Models\Auth\User;
use splitbrain\RingIcon\RingIconSVG;
use Illuminate\Support\Facades\File;

class AvatarGeneratorService {
    public static function generateProfilePicture(User $user): void {
        $path = sprintf(__DIR__.'/../../public/img/profile/%s', $user->uuid);
        if (is_dir($path) === false) {
            File::makeDirectory($path, 755, true);
        }

        $profilePicture = new RingIconSVG(128, 3);
        $profilePicture->createImage($user->name . $user->email, sprintf(__DIR__.'/../../public/img/profile/%s/pfp.svg', $user->uuid));
        $user->profile_picture = sprintf('%s/pfp.svg', $user->uuid);
        $user->save();
    }
}
