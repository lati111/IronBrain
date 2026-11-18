<?php

namespace Tests\Feature\Api\DnD\Timeline\TimelineApi;

use App\Models\DnD\Timeline\Timeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateTimelineTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
    }

    protected function getRoute(): string
    {
        return route('api.dnd.timeline.create');
    }

    protected function getModel(): Timeline
    {
        return new Timeline();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name' => 'The Age of Dragons',
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertStringValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_description_validation(): void
    {
        $this->assertNullableValidation('description');
        $this->assertMaxValidation('string', 'description', 2048);
    }

    public function test_cover_image_must_be_an_image(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'cover_image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            ]));

        $response->assertBadRequest();
        $this->assertArrayHasKey('cover_image', $response->json('errors'));
    }

    //| timeline is saved tests

    public function test_timeline_is_saved_for_user(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['description' => 'Before the fall of the empire']));

        $response->assertCreated();
        $this->assertApiMessage('Timeline created', $response);

        $timeline = Timeline::where('uuid', $response->json('data'))->first();
        $this->assertNotNull($timeline);
        $this->assertEquals('The Age of Dragons', $timeline->name);
        $this->assertEquals('Before the fall of the empire', $timeline->description);
        $this->assertEquals($this->getAdminUser()->uuid, $timeline->owner_uuid);
        $this->assertNull($timeline->cover_image);
    }

    public function test_timeline_is_saved_with_cover_image(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'cover_image' => UploadedFile::fake()->image('cover.png'),
            ]));

        $response->assertCreated();
        $timeline = Timeline::where('uuid', $response->json('data'))->first();
        $this->assertStringStartsWith(Timeline::COVER_IMAGE_PATH . '/', $timeline->cover_image);
        FileStorage::assertExists($timeline->cover_image);
    }

    //| permission test

    public function test_forbidden_without_permission(): void
    {
        $count = Timeline::count();

        $response = $this->withHeaders($this->getDefaultHeaders())
            ->actingAs($this->getRandomUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertViewIs('errors.auth.forbidden');
        $this->assertEquals($count, Timeline::count());
    }
}
