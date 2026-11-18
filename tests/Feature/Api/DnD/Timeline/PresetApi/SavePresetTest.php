<?php

namespace Tests\Feature\Api\DnD\Timeline\PresetApi;

use App\Models\DnD\Timeline\FilterPreset;
use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class SavePresetTest extends AbstractApiFeatureTester
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
        return $this->getSaveRoute($this->timeline);
    }

    protected function getModel(): FilterPreset
    {
        return new FilterPreset();
    }

    private function getSaveRoute(Timeline $timeline): string
    {
        return route('api.dnd.timeline.preset.save', ['timeline_uuid' => $timeline->uuid]);
    }

    private function post(array $overwrites = [], ?string $route = null)
    {
        return $this->getHttpClient($this->getOperationUser())->post($route ?? $this->getRoute(), array_merge([
            'name' => 'Wars only', 'blacklist' => 'magic', 'whitelist' => 'war', 'highlight' => 'dragons',
        ], $overwrites));
    }

    public function test_preset_is_saved_and_returned_with_the_timeline(): void
    {
        $response = $this->post();

        $response->assertCreated();
        $this->assertApiMessage('Preset saved', $response);

        $preset = FilterPreset::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($this->timeline->uuid, $preset->timeline_uuid);
        $this->assertEquals('Wars only', $preset->name);
        $this->assertEquals(['magic'], $preset->getList('blacklist'));
        $this->assertEquals(['war'], $preset->getList('whitelist'));
        $this->assertEquals(['dragons'], $preset->getList('highlight'));
        $this->assertEquals([['uuid' => $preset->uuid, 'name' => 'Wars only', 'blacklist' => ['magic'], 'whitelist' => ['war'], 'highlight' => ['dragons']]],
            $response->json('data.timeline.presets'));
    }

    public function test_lists_are_tidied_and_may_be_empty(): void
    {
        $response = $this->post(['blacklist' => ' a, ,b,a ', 'whitelist' => '', 'highlight' => null]);

        $response->assertCreated();
        $preset = FilterPreset::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals(['a', 'b'], $preset->getList('blacklist'));
        $this->assertEquals([], $preset->getList('whitelist'));
        $this->assertNull($preset->highlight);
    }

    public function test_preset_of_the_same_name_is_replaced(): void
    {
        $first = $this->post()->json('data.uuid');

        $response = $this->post(['name' => ' Wars only ', 'blacklist' => 'plague']);

        $response->assertOk();
        $this->assertApiMessage('Preset updated', $response);
        $this->assertEquals($first, $response->json('data.uuid'));
        $this->assertEquals(1, FilterPreset::where('timeline_uuid', $this->timeline->uuid)->count());
        $this->assertEquals(['plague'], FilterPreset::where('uuid', $first)->first()->getList('blacklist'));
    }

    public function test_name_may_be_shared_with_other_timelines(): void
    {
        $this->post();
        $other = $this->createTestTimeline($this->getAdminUser(), 'Other');

        $this->post([], $this->getSaveRoute($other))->assertCreated();
        $this->assertEquals(2, FilterPreset::where('name', 'Wars only')->count());
    }

    public function test_validation(): void
    {
        foreach ([['name' => ''], ['name' => '   '], ['name' => str_repeat('a', 65)], ['blacklist' => str_repeat('a', 1025)]] as $invalid) {
            $this->post($invalid)->assertBadRequest();
        }
        $this->assertEquals(0, FilterPreset::count());
    }

    public function test_timeline_of_other_user_is_not_found(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');

        $this->post([], $this->getSaveRoute($timeline))->assertNotFound();
        $this->assertEquals(0, FilterPreset::where('timeline_uuid', $timeline->uuid)->count());
    }
}
