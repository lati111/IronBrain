<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

trait ValidationTests
{
    //| Message constants
    protected const string ASSERT_STATUS_CODE_FAIL = 'Http status code was %s while expecting %s while validating %s';

    //| Assert presence
    public function assertNullableValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
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

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'required');

        $this->assertContains(
            Lang::get('validation.required', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertUniqueValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $item = $this->getModel()::inRandomOrder()->first() ?? $this->getModel()->factory()->make();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $item[$column]
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'unique');

        $this->assertContains(
            Lang::get('validation.unique', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }


    public function assertExistsValidation(string $column, string $fakeValue, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $fakeValue
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'unique');

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

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $fakeValue
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'in_array');

        $this->assertContains(
            Lang::get('validation.in', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    //| Assert data type
    public function assertStringValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->numberBetween(1, 6)
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'string');

        $this->assertContains(
            Lang::get('validation.string', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertIntegerValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->text(6)
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'integer');

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

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->text(6)
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'numeric');

        $this->assertContains(
            Lang::get('validation.numeric', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertEmailValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->numberBetween(1, 6)
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'email');

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

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $this->faker->regexify('[A-Za-z0-9]{10}')
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'phone_number');
        $this->assertContains(
            Lang::get('validation.phone', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertDateValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->text(6)
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'date');

        $this->assertContains(
            Lang::get('validation.date', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertBooleanValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->text(20)
            ], $this->getDefaultHeaders());
        $response->assertBadRequest();

        $this->assertBadRequest($response, 'bool');

        $this->assertContains(
            Lang::get('validation.boolean', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    public function assertCheckboxValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => 'aaa'
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'checkbox');

        $this->assertContains(
            Lang::get('validation.checkbox', ['attribute' => $alias]),
            $response->json()['errors'][$column],
            'Failed asserting "checkbox" validation'
        );
    }

    public function assertPasswordValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        // Validate requires lower case
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->regexify('[A-Z]{6}[0-9]{3}')
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'password_lower_case');

        $this->assertContains(
            Lang::get('validation.password.mixed', ['attribute' => $alias]),
            $response->json()['errors'][$column],
            'Failed asserting "checkbox" validation'
        );

        // Validate requires upper case
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->regexify('[a-z]{6}[0-9]{3}')
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'password_upper_case');

        $this->assertContains(
            Lang::get('validation.password.mixed', ['attribute' => $alias]),
            $response->json()['errors'][$column],
            'Failed asserting "checkbox" validation'
        );

        // Validate requires letters
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->regexify('[0-9]{8}')
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'password_letters');

        $this->assertContains(
            Lang::get('validation.password.letters', ['attribute' => $alias]),
            $response->json()['errors'][$column],
            'Failed asserting "checkbox" validation'
        );

        // Validate requires numbers
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->regexify('[A-Z][a-z]{6}')
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'password_numbers');

        $this->assertContains(
            Lang::get('validation.password.numbers', ['attribute' => $alias]),
            $response->json()['errors'][$column],
            'Failed asserting "checkbox" validation'
        );
    }

    //| Assert length
    public function assertMaxValidation(string $type, string $column, int $max, string $alias = null): void
    {
        $types = ['string', 'numeric', 'array', 'file'];
        if (in_array($type, $types) === false) {
            throw new \Exception('Invalid type "%s" given in max validation. Only the following types exist: %s', $type, implode(', ', $types));
        }

        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => Str::random($max + 1)
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'max');

        $this->assertContains(
            Lang::get('validation.max.'.$type, ['attribute' => $alias, 'max' => $max]),
            $this->getError($response, $column) ?? []
        );
    }

    public function assertMinValidation(string $type, string $column, int $min, string $alias = null): void
    {
        $types = ['string', 'numeric', 'array', 'file'];
        if (in_array($type, $types) === false) {
            throw new \Exception('Invalid type "%s" given in max validation. Only the following types exist: %s', $type, implode(', ', $types));
        }

        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $min - 1
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'min');

        $this->assertContains(
            Lang::get('validation.min.'.$type, ['attribute' => $alias, 'min' => $min]),
            $response->json()['errors'][$column]
        );
    }

    public function assertBetweenValidation(string $type, string $column, int $min, int $max, string $alias = null): void
    {
        $types = ['string', 'numeric', 'array', 'file'];
        if (in_array($type, $types) === false) {
            throw new \Exception('Invalid type "%s" given in max validation. Only the following types exist: %s', $type, implode(', ', $types));
        }

        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $min - 1
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'between_min');

        $this->assertContains(
            Lang::get('validation.between.'.$type, ['attribute' => $alias, 'min' => $min, 'max' => $max]),
            $response->json()['errors'][$column]
        );

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $max + 1
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'between_max');

        $this->assertContains(
            Lang::get('validation.between.'.$type, ['attribute' => $alias, 'min' => $min, 'max' => $max]),
            $response->json()['errors'][$column]
        );
    }

    //| Misc options
    public function assertDateFormatValidation(string $column, string $format, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => fake()->date(($format === 'Y-m-d' ? 'H:i:s' : 'Y-m-d'))
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'date_format');

        $this->assertContains(
            Lang::get('validation.date_format', ['attribute' => $alias, 'format' => $format]),
            $response->json()['errors'][$column]
        );
    }

    public function assertConfirmedValidation(string $column, string $alias = null): void
    {
        if ($alias === null) {
            $alias = $column;
        }

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                $column => $this->getPostParameters()[$column],
                $column.'_confirmation' => $this->getPostParameters()[$column].'aaaaa'
            ], $this->getDefaultHeaders());

        $this->assertBadRequest($response, 'confirmed');

        $this->assertContains(
            Lang::get('validation.confirmed', ['attribute' => $alias]),
            $response->json()['errors'][$column]
        );
    }

    //| Status code asserts

    public function assertBadRequest(TestResponse $response, string $validation_type): void
    {
        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->status(),
            sprintf(
                self::ASSERT_STATUS_CODE_FAIL,
                $response->status(),
                Response::HTTP_BAD_REQUEST,
                $validation_type,
            ),
        );
    }

    //| Utils
    public function getError(TestResponse $response, ?string $column = null) {
        $errors = $response->json()['errors'];
        if ($column === null) {
            return $errors;
        } else if ($errors === null) {
            return null;
        }

        return $errors[$column] ?? null;
    }
}
