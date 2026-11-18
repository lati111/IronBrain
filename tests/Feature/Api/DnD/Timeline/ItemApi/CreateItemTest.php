<?php

namespace Tests\Feature\Api\DnD\Timeline\ItemApi;

use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Event;
use App\Models\DnD\Timeline\Period;
use App\Models\DnD\Timeline\Timeline;
use App\Models\DnD\Timeline\Timespan;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\TimelineTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateItemTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, TimelineTestHelper;

    private Timeline $timeline;
    private Category $category;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
        $this->category = $this->createTestCategory($this->timeline);
    }

    protected function getRoute(): string
    {
        return route('api.dnd.timeline.item.create', ['timeline_uuid' => $this->timeline->uuid]);
    }

    protected function getModel(): Event
    {
        return new Event();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'type'          => 'event',
            'name'          => 'The Sundering',
            'year'          => -120,
            'category'      => $this->category->name,
        ], $overwrites);
    }

    private function post(array $overwrites = [])
    {
        return $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters($overwrites));
    }

    //| validation tests

    public function test_type_validation(): void
    {
        $this->assertRequiredValidation('type');

        $response = $this->post(['type' => 'century']);
        $response->assertBadRequest();
        $this->assertArrayHasKey('type', $response->json('errors'));
    }

    public function test_required_fields(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), ['type' => 'timespan']);

        $response->assertBadRequest();
        foreach (['name', 'category', 'start_year', 'end_year'] as $field) {
            $this->assertArrayHasKey($field, $response->json('errors'));
        }
    }

    public function test_years_must_be_whole_numbers(): void
    {
        $response = $this->post(['year' => 'long ago']);
        $response->assertBadRequest();
        $this->assertArrayHasKey('year', $response->json('errors'));

        $response = $this->post(['year' => 12.5]);
        $response->assertBadRequest();
        $this->assertArrayHasKey('year', $response->json('errors'));
    }

    public function test_range_may_not_end_before_it_starts(): void
    {
        $response = $this->post(['type' => 'timespan', 'start_year' => 10, 'end_year' => 9]);

        $response->assertBadRequest();
        $this->assertArrayHasKey('end_year', $response->json('errors'));
    }

    public function test_new_category_needs_a_valid_color(): void
    {
        foreach ([null, 'red', '#abc'] as $color) {
            $response = $this->post(['category' => 'Plagues', 'category_color' => $color]);
            $response->assertBadRequest();
            $this->assertArrayHasKey('category_color', $response->json('errors'));
        }
        $this->assertFalse(Category::where('name', 'Plagues')->exists());
    }

    //| save tests

    public function test_event_is_saved(): void
    {
        $response = $this->post(['description' => 'The continent splits', 'tags' => ' war, , elves,war ']);

        $response->assertCreated();
        $this->assertApiMessage('Event created', $response);

        $event = Event::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($this->timeline->uuid, $event->timeline_uuid);
        $this->assertEquals(-120, $event->year);
        $this->assertEquals('The continent splits', $event->description);
        $this->assertEquals(['war', 'elves'], $event->getTags());

        $state = $response->json('data.timeline.events.0');
        $this->assertEquals(['war', 'elves'], $state['tags']);
        $this->assertArrayNotHasKey('source', $state);
    }

    public function test_timespan_and_period_are_saved_apart(): void
    {
        $response = $this->post(['type' => 'timespan', 'name' => 'The Long Siege', 'start_year' => 5, 'end_year' => 12]);
        $response->assertCreated();
        $this->assertApiMessage('Timespan created', $response);
        $timespan = Timespan::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals([5, 12], [$timespan->start_year, $timespan->end_year]);

        $response = $this->post(['type' => 'period', 'name' => 'The Age of Dragons', 'start_year' => -500, 'end_year' => 300]);
        $response->assertCreated();
        $this->assertApiMessage('Period created', $response);
        $this->assertTrue(Period::where('uuid', $response->json('data.uuid'))->exists());

        $this->assertEquals(['The Long Siege'], array_column($response->json('data.timeline.timespans'), 'name'));
        $this->assertEquals(['The Age of Dragons'], array_column($response->json('data.timeline.periods'), 'name'));
    }

    public function test_existing_category_is_reused_by_name(): void
    {
        $response = $this->post(['category' => $this->category->name]);

        $response->assertCreated();
        $this->assertEquals($this->category->uuid, Event::where('uuid', $response->json('data.uuid'))->value('category_uuid'));
        $this->assertEquals(1, Category::where('timeline_uuid', $this->timeline->uuid)->count());
    }

    public function test_new_category_is_created_with_the_item(): void
    {
        $other = $this->createTestTimeline($this->getAdminUser(), 'Other');
        $this->createTestCategory($other, 'Plagues', '#000000');

        $response = $this->post(['category' => ' Plagues ', 'category_color' => '#00FF00']);

        $response->assertCreated();
        $category = Category::where('timeline_uuid', $this->timeline->uuid)->where('name', 'Plagues')->first();
        $this->assertNotNull($category);
        $this->assertEquals('#00ff00', $category->color);
        $this->assertEquals($category->uuid, Event::where('uuid', $response->json('data.uuid'))->value('category_uuid'));
        $this->assertContains('Plagues', array_column($response->json('data.timeline.categories'), 'name'));
    }

    public function test_timeline_of_other_user_is_not_found(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.timeline.item.create', ['timeline_uuid' => $timeline->uuid]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Timeline not found', $response);
    }

    public function test_forbidden_without_permission(): void
    {
        $count = Event::count();

        $response = $this->withHeaders($this->getDefaultHeaders())
            ->actingAs($this->getRandomUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertViewIs('errors.auth.forbidden');
        $this->assertEquals($count, Event::count());
    }
}
