<?php

namespace Tests\Feature\Api\DnD\Timeline\SettingsApi;

use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class EditSettingsTest extends AbstractApiFeatureTester
{
    use TimelineTestHelper;

    private Timeline $timeline;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.dnd.timeline.settings.edit', ['timeline_uuid' => $this->timeline->uuid]);
    }

    protected function getModel(): Timeline
    {
        return new Timeline();
    }

    private function post(array $settings, ?string $route = null)
    {
        return $this->getHttpClient($this->getOperationUser())->post($route ?? $this->getRoute(), $settings);
    }

    public function test_settings_are_saved(): void
    {
        $response = $this->post(['current_year' => 705, 'display_start_year' => -300, 'display_end_year' => 720]);

        $response->assertOk();
        $this->assertApiMessage('Settings saved', $response);

        $timeline = $this->timeline->fresh();
        $this->assertEquals([705, -300, 720], [$timeline->current_year, $timeline->display_start_year, $timeline->display_end_year]);
        $this->assertEquals(['current_year' => 705, 'display_start_year' => -300, 'display_end_year' => 720],
            $response->json('data.timeline.settings'));
    }

    public function test_settings_may_be_left_empty(): void
    {
        $this->timeline->current_year = 1;
        $this->timeline->display_start_year = 2;
        $this->timeline->display_end_year = 3;
        $this->timeline->save();

        // Either end of the range may be left open
        $this->post(['current_year' => '', 'display_start_year' => 10])->assertOk();

        $timeline = $this->timeline->fresh();
        $this->assertEquals([null, 10, null], [$timeline->current_year, $timeline->display_start_year, $timeline->display_end_year]);
    }

    public function test_settings_are_validated(): void
    {
        foreach ([
            ['current_year' => 'now'],
            ['current_year' => 1.5],
            ['display_start_year' => 10, 'display_end_year' => 9],
            ['display_end_year' => 3000000000],
        ] as $invalid) {
            $this->post($invalid)->assertBadRequest();
        }
        $this->assertNull($this->timeline->fresh()->display_start_year);
    }

    public function test_timeline_of_other_user_is_not_found(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');

        $response = $this->post(['current_year' => 5], route('api.dnd.timeline.settings.edit', ['timeline_uuid' => $timeline->uuid]));

        $response->assertNotFound();
        $this->assertNull($timeline->fresh()->current_year);
    }
}
