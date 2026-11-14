<?php

namespace Tests\Feature\Api\DnD\Timeline\ItemApi;

use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Timeline;
use App\Models\DnD\Timeline\Timespan;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class EditItemTest extends AbstractApiFeatureTester
{
    use TimelineTestHelper;

    private Timeline $timeline;
    private Category $category;
    private Timespan $timespan;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
        $this->category = $this->createTestCategory($this->timeline);
        $this->timespan = $this->createTestTimespan($this->timeline, $this->category, 1, 10, 'The Long Siege');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return $this->getEditRoute('timespan', $this->timespan->uuid);
    }

    protected function getModel(): Timespan
    {
        return new Timespan();
    }

    private function getEditRoute(string $type, string $uuid): string
    {
        return route('api.dnd.timeline.item.edit', ['timeline_uuid' => $this->timeline->uuid, 'type' => $type, 'item_uuid' => $uuid]);
    }

    private function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'          => 'The Short Siege',
            'start_year'    => 2,
            'end_year'      => 3,
            'category'      => $this->category->name,
            'tags'          => 'war',
        ], $overwrites);
    }

    public function test_item_is_updated_and_keeps_its_source(): void
    {
        $this->timespan->source = 'Session 4 notes';
        $this->timespan->save();

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters());

        $response->assertOk();
        $this->assertApiMessage('Timespan updated', $response);

        $timespan = $this->timespan->fresh();
        $this->assertEquals('The Short Siege', $timespan->name);
        $this->assertEquals([2, 3], [$timespan->start_year, $timespan->end_year]);
        $this->assertEquals(['war'], $timespan->getTags());
        $this->assertEquals('Session 4 notes', $timespan->source);
    }

    public function test_item_is_validated(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters(['end_year' => 1]));

        $response->assertBadRequest();
        $this->assertArrayHasKey('end_year', $response->json('errors'));
        $this->assertEquals(10, $this->timespan->fresh()->end_year);
    }

    public function test_item_under_another_type_is_not_found(): void
    {
        foreach (['period', 'century'] as $type) {
            $response = $this->getHttpClient($this->getOperationUser())
                ->post($this->getEditRoute($type, $this->timespan->uuid), $this->getPostParameters());

            $response->assertNotFound();
            $this->assertApiMessage('Item not found', $response);
        }
    }

    public function test_item_of_other_timeline_is_not_found(): void
    {
        $other = $this->createTestTimeline($this->getAdminUser(), 'Other');
        $timespan = $this->createTestTimespan($other, null, 1, 2);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getEditRoute('timespan', $timespan->uuid), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertEquals(2, $timespan->fresh()->end_year);
    }
}
