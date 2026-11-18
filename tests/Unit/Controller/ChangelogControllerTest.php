<?php

namespace Tests\Unit\Controller;

use App\Models\Auth\User;
use Illuminate\Support\Facades\Storage;

class ChangelogControllerTest extends AbstractControllerUnitTester
{
    public function testReleasesAreSortedNewestFirst(): void
    {
        Storage::fake();
        Storage::put('config/Core/changelog.json', json_encode([
            ['version' => '1.0.0', 'date' => '2026-01-01', 'modules' => ['Core' => ['Old']]],
            ['version' => '1.1.0', 'date' => '2026-02-01', 'modules' => ['Core' => ['New'], 'DnD' => ['Fantasy Timeline' => ['Newer']]]],
        ]));

        $response = $this->get(route('changelog.show'));

        $response->assertOk();
        $response->assertViewIs('changelog');
        $this->assertEquals(['1.1.0', '1.0.0'], array_column($response->viewData('releases'), 'version'));
        $response->assertSee('DnD');
        $response->assertSee('Fantasy Timeline');
        $response->assertSee('Newer');
        $response->assertSee('Old');
    }

    private function putRestrictedChangelog(): void
    {
        Storage::fake();
        Storage::put('config/Core/changelog.json', json_encode([
            ['version' => '1.0.0', 'date' => '2026-01-01', 'modules' => [
                'Core' => ['General' => ['Public change', ['text' => 'Internal change', 'permission' => 'admin']]],
                'DnD' => ['Fantasy Timeline' => ['permission' => 'dnd_timeline.access', 'changes' => ['Timeline change']]],
            ]],
            ['version' => '2.0.0', 'date' => '2026-02-01', 'modules' => [
                'Core' => ['General' => [['text' => 'Only for admins', 'permission' => 'admin']]],
            ]],
        ]));
    }

    public function testGuestsOnlySeeUnrestrictedChanges(): void
    {
        $this->putRestrictedChangelog();

        $response = $this->get(route('changelog.show'));

        $response->assertSee('Public change');
        $response->assertDontSee('Internal change');
        $response->assertDontSee('Timeline change');
        $this->assertEquals(['1.0.0'], array_column($response->viewData('releases'), 'version'));
    }

    public function testUsersOnlySeeChangesTheyHavePermissionFor(): void
    {
        $this->putRestrictedChangelog();

        $response = $this->actingAs(User::where('email', 'test@test.nl')->first())->get(route('changelog.show'));

        $response->assertSee('Public change');
        $response->assertDontSee('Internal change');
        $response->assertDontSee('Timeline change');
    }

    public function testAdminsSeeEverything(): void
    {
        $this->putRestrictedChangelog();

        $response = $this->actingAs($this->getAdminUser())->get(route('changelog.show'));

        $response->assertSee('Public change');
        $response->assertSee('Internal change');
        $response->assertSee('Timeline change');
        $response->assertSee('Only for admins');
    }

    public function testFilteredPageOnlyShowsThatSubmoduleAndMarksItSeen(): void
    {
        Storage::fake();
        Storage::put('config/Core/modules.json', json_encode([[
            'code' => 'cl_test', 'name' => 'ClTest', 'description' => 'A module', 'thumbnail' => null, 'route' => null,
            'permission' => null, 'order' => 91, 'in_overview' => true, 'in_nav' => true,
            'submodules' => [['code' => 'sub', 'name' => 'ClSub', 'order' => 1, 'route' => 'home', 'permission' => null, 'in_overview' => true]],
        ]]));
        $this->artisan('import:modules')->assertExitCode(0);
        Storage::put('config/Core/changelog.json', json_encode([
            ['version' => '1.0.0', 'date' => '2026-01-01', 'modules' => [
                'ClTest' => ['ClSub' => ['Sub change'], 'ClOther' => ['Other change']],
                'Elsewhere' => ['Elsewhere change'],
            ]],
        ]));

        $response = $this->get(route('changelog.show', ['module' => 'cl_test', 'submodule' => 'sub']));

        $response->assertSee('Sub change');
        $response->assertDontSee('Other change');
        $response->assertDontSee('Elsewhere change');
        $response->assertCookie('changelog_seen');
    }

    public function testFilteringOnAnUnknownModuleShowsNothing(): void
    {
        $this->putRestrictedChangelog();

        $response = $this->get(route('changelog.show', ['module' => 'does_not_exist']));

        $response->assertDontSee('Public change');
        $response->assertCookieMissing('changelog_seen');
    }

    public function testMissingChangelogShowsEmptyPage(): void
    {
        Storage::fake();

        $response = $this->get(route('changelog.show'));

        $response->assertOk();
        $response->assertSee('No changes have been logged yet.');
    }
}
