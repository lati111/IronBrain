<?php

namespace Tests\Feature\Api\DnD\Timeline\CategoryApi;

use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class DeleteCategoryTest extends AbstractApiFeatureTester
{
    use TimelineTestHelper;

    private Timeline $timeline;
    private Category $category;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
        $this->category = $this->createTestCategory($this->timeline, 'Wars');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return $this->getDeleteRoute($this->timeline, $this->category);
    }

    protected function getModel(): Category
    {
        return new Category();
    }

    private function getDeleteRoute(Timeline $timeline, Category $category): string
    {
        return route('api.dnd.timeline.category.delete', ['timeline_uuid' => $timeline->uuid, 'category_uuid' => $category->uuid]);
    }

    public function test_category_is_deleted_and_its_items_are_kept(): void
    {
        $other = $this->createTestCategory($this->timeline, 'Plagues');
        $event = $this->createTestEvent($this->timeline, $this->category, 5);
        $timespan = $this->createTestTimespan($this->timeline, $this->category, 1, 9);
        $period = $this->createTestPeriod($this->timeline, $this->category, 1, 99);
        $kept = $this->createTestEvent($this->timeline, $other, 6, 'Other category');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertApiMessage('Category deleted', $response);
        $this->assertFalse(Category::where('uuid', $this->category->uuid)->exists());
        $this->assertEquals(['Plagues'], array_column($response->json('data.timeline.categories'), 'name'));

        // The items are left without a category
        $this->assertNull($event->fresh()->category_uuid);
        $this->assertNull($timespan->fresh()->category_uuid);
        $this->assertNull($period->fresh()->category_uuid);
        $this->assertEquals($other->uuid, $kept->fresh()->category_uuid);
        // Both events are still on the timeline
        $this->assertCount(2, $response->json('data.timeline.events'));
    }

    public function test_category_of_other_user_is_not_deleted(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');
        $category = $this->createTestCategory($timeline, 'Theirs');

        $this->getHttpClient($this->getOperationUser())->post($this->getDeleteRoute($timeline, $category))->assertNotFound();
        $this->assertTrue(Category::where('uuid', $category->uuid)->exists());
    }

    public function test_category_of_other_timeline_is_not_deleted(): void
    {
        $other = $this->createTestTimeline($this->getAdminUser(), 'Other');
        $category = $this->createTestCategory($other, 'Theirs');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getDeleteRoute($this->timeline, $category));

        $response->assertNotFound();
        $this->assertApiMessage('Category not found', $response);
        $this->assertTrue(Category::where('uuid', $category->uuid)->exists());
    }
}
