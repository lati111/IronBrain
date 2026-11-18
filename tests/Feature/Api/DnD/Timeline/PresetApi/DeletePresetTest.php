<?php

namespace Tests\Feature\Api\DnD\Timeline\PresetApi;

use App\Models\DnD\Timeline\FilterPreset;
use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class DeletePresetTest extends AbstractApiFeatureTester
{
    use TimelineTestHelper;

    private Timeline $timeline;
    private FilterPreset $preset;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
        $this->preset = $this->createPreset($this->timeline, 'Wars only');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return $this->getDeleteRoute($this->timeline, $this->preset);
    }

    protected function getModel(): FilterPreset
    {
        return new FilterPreset();
    }

    private function createPreset(Timeline $timeline, string $name): FilterPreset
    {
        $preset = new FilterPreset();
        $preset->timeline_uuid = $timeline->uuid;
        $preset->name = $name;
        $preset->setList('whitelist', ['war']);
        $preset->save();
        return $preset;
    }

    private function getDeleteRoute(Timeline $timeline, FilterPreset $preset): string
    {
        return route('api.dnd.timeline.preset.delete', ['timeline_uuid' => $timeline->uuid, 'preset_uuid' => $preset->uuid]);
    }

    public function test_preset_is_deleted(): void
    {
        $this->createPreset($this->timeline, 'Kept');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertApiMessage('Preset deleted', $response);
        $this->assertFalse(FilterPreset::where('uuid', $this->preset->uuid)->exists());
        $this->assertEquals(['Kept'], array_column($response->json('data.timeline.presets'), 'name'));
    }

    public function test_deleting_the_timeline_deletes_its_presets(): void
    {
        $this->timeline->delete();

        $this->assertFalse(FilterPreset::where('uuid', $this->preset->uuid)->exists());
    }

    public function test_preset_of_other_user_is_not_deleted(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');
        $preset = $this->createPreset($timeline, 'Theirs');

        $this->getHttpClient($this->getOperationUser())->post($this->getDeleteRoute($timeline, $preset))->assertNotFound();
        $this->assertTrue(FilterPreset::where('uuid', $preset->uuid)->exists());
    }

    public function test_preset_of_other_timeline_is_not_deleted(): void
    {
        $other = $this->createTestTimeline($this->getAdminUser(), 'Other');
        $preset = $this->createPreset($other, 'Theirs');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getDeleteRoute($this->timeline, $preset));

        $response->assertNotFound();
        $this->assertApiMessage('Preset not found', $response);
        $this->assertTrue(FilterPreset::where('uuid', $preset->uuid)->exists());
    }
}
