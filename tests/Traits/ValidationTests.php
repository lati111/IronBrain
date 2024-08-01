<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait ValidationTests
{
    public function getError(TestResponse $response, ?string $column = null) {
        $errors = $response->json()['errors'];
        if ($column === null) {
            return $errors;
        } else if ($errors === null) {
            return null;
        }

        return $errors[$column] ?? null;
    }

    public function assertNullableValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [], $this->getDefaultHeaders());

        $this->assertNotContains(
            Lang::get('validation.required', ['attribute' => $alias]),
            $this->getError($response) ?? []
        );
    }

    public function assertRequiredValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.required', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertExistsValidation(string $column, string $fakeValue, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => $fakeValue
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.exists', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertInArrayValidation(string $column, string $fakeValue, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => $fakeValue
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.in', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertStringValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->numberBetween(1, 6)
            ], $this->getDefaultHeaders());
        $response->assertBadRequest();

        $this->assertContains(
            Lang::get('validation.string', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertEmailValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->numberBetween(1, 6)
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.email', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertPhoneNumberValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => $this->faker->regexify('[A-Za-z0-9]{10}')
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.phone', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }


    public function assertTooLongValidation(string $column, int $max, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => Str::random($max + 1)
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.max.string', ['attribute' => $alias, 'max' => $max]),
            $this->getError($response, $column) ?? []
        );
    }

    public function assertIntegerValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->text(6)
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.integer', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertNumericValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->text(6)
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.numeric', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertNumericMinValidation(string $column, int $min, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => $min - 1
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.min.numeric', ['attribute' => $alias, 'min' => $min]),
            $response->json()['errors'][$column]
        );
    }

    public function assertNumericBetweenValidation(string $column, int $min, int $max, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => $min - 1
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.between.numeric', ['attribute' => $alias, 'min' => $min, 'max' => $max]),
            $response->json()['errors'][$column]
        );

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => $max + 1
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.between.numeric', ['attribute' => $alias, 'min' => $min, 'max' => $max]),
            $response->json()['errors'][$column]
        );
    }

    public function assertDateValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->text(6)
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.date', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertDateFormatValidation(string $column, string $format, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->date(($format === 'Y-m-d' ? 'H:i:s' : 'Y-m-d'))
            ], $this->getDefaultHeaders());

        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.date_format', ['attribute' => $alias, 'format' => $format]),
            $response->json()['errors'][$column]
        );
    }

    public function assertBooleanValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), [
                $column => fake()->text(20)
            ], $this->getDefaultHeaders());
        $response->assertBadRequest();

        $this->assertContains(
            Lang::get('validation.boolean', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }
}
