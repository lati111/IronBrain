<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\AuthController;
use App\Models\Auth\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Faker\Generator as Faker;

abstract class AbstractUnitTester extends Testcase
{
    protected ?User $user = null;

    use WithFaker;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
        $this->seed(AuthSeeder::class);
    }

    protected function getAdminUser(): User {
        return User::where('name', 'Admin')->first();
    }
}
