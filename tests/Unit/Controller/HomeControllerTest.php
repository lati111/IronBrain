<?php

namespace Tests\Unit\Controller;

use App\Models\Auth\User;
use Illuminate\Support\Facades\Storage;

class HomeControllerTest extends AbstractControllerUnitTester
{
    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        Storage::put('config/Core/modules.json', json_encode([
            [
                'code' => 'home_test_standalone',
                'name' => 'Standalone',
                'description' => 'A module shown on its own',
                'thumbnail' => null,
                'route' => 'home',
                'permission' => null,
                'order' => 1,
                'in_overview' => true,
                'in_nav' => true,
                'submodules' => [],
            ],
            [
                'code' => 'home_test_group',
                'name' => 'Group',
                'description' => 'A module shown as a group of its submodules',
                'thumbnail' => null,
                'route' => null,
                'permission' => null,
                'order' => 2,
                'in_overview' => true,
                'in_nav' => true,
                'submodules' => [
                    ['code' => 'open', 'name' => 'Open', 'order' => 1, 'route' => 'home', 'permission' => null, 'in_overview' => true],
                    ['code' => 'locked', 'name' => 'Locked', 'order' => 2, 'route' => 'home', 'permission' => 'dnd_timeline.access', 'in_overview' => true],
                    ['code' => 'nav_only', 'name' => 'Nav only', 'order' => 3, 'route' => 'home', 'permission' => null],
                ],
            ],
        ]));
        $this->artisan('import:modules')->assertExitCode(0);
    }

    private function getOverview(User $user): array
    {
        $response = $this->actingAs($user)->get(route('home'));
        $this->assertView($response, 'home');

        $groups = [];
        foreach ($response->viewData('overviewGroups') as $group) {
            $groups[$group['module']->code] = $group['submodules']->pluck('code')->values()->all();
        }

        return [
            'modules' => collect($response->viewData('overviewModules'))->pluck('code')->all(),
            'groups' => $groups,
        ];
    }

    public function testSubmodulesAreGroupedUnderTheirModule(): void
    {
        $overview = $this->getOverview($this->getAdminUser());

        $this->assertContains('home_test_standalone', $overview['modules']);
        $this->assertNotContains('home_test_group', $overview['modules']);
        $this->assertEquals(['open', 'locked'], $overview['groups']['home_test_group']);
    }

    public function testInaccessibleSubmodulesAreHidden(): void
    {
        $overview = $this->getOverview(User::where('email', 'test@test.nl')->first());

        $this->assertEquals(['open'], $overview['groups']['home_test_group']);
    }
}
