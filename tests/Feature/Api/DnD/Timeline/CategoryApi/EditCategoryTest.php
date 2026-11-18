<?php

namespace Tests\Feature\Api\DnD\Timeline\CategoryApi;

use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class EditCategoryTest extends AbstractApiFeatureTester
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
        return $this->getEditRoute($this->timeline, $this->category);
    }

    protected function getModel(): Category
    {
        return new Category();
    }

    private function getEditRoute(Timeline $timeline, Category $category): string
    {
        return route('api.dnd.timeline.category.edit', ['timeline_uuid' => $timeline->uuid, 'category_uuid' => $category->uuid]);
    }

    private function post(array $overwrites = [], ?string $route = null)
    {
        return $this->getHttpClient($this->getOperationUser())->post($route ?? $this->getRoute(), array_merge([
            'name' => 'Conflicts', 'description' => 'Everything violent', 'color' => '#AA3311',
        ], $overwrites));
    }

    public function test_category_is_updated(): void
    {
        $response = $this->post();

        $response->assertOk();
        $this->assertApiMessage('Category updated', $response);
        $category = $this->category->fresh();
        $this->assertEquals(['Conflicts', 'Everything violent', '#aa3311'], [$category->name, $category->description, $category->color]);
        $this->assertEquals(['Conflicts'], array_column($response->json('data.timeline.categories'), 'name'));
    }

    public function test_category_default_visibility_is_saved(): void
    {
        $this->assertFalse($this->category->fresh()->hidden_by_default);

        $response = $this->post(['hidden_by_default' => '1']);
        $response->assertOk();
        $this->assertTrue($this->category->fresh()->hidden_by_default);
        $this->assertTrue($response->json('data.timeline.categories.0.hidden_by_default'));

        // An unticked checkbox isn't sent
        $this->post()->assertOk();
        $this->assertFalse($this->category->fresh()->hidden_by_default);
    }

    public function test_category_may_keep_its_name(): void
    {
        $this->post(['name' => 'Wars'])->assertOk();
    }

    public function test_validation(): void
    {
        $this->createTestCategory($this->timeline, 'Plagues');

        foreach ([['name' => ''], ['name' => 'Plagues'], ['color' => 'red'], ['color' => '']] as $invalid) {
            $this->post($invalid)->assertBadRequest();
        }
        $this->assertEquals('Wars', $this->category->fresh()->name);
    }

    public function test_category_of_other_user_is_not_found(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');
        $category = $this->createTestCategory($timeline, 'Theirs');

        $this->post([], $this->getEditRoute($timeline, $category))->assertNotFound();
        $this->assertEquals('Theirs', $category->fresh()->name);
    }
}
