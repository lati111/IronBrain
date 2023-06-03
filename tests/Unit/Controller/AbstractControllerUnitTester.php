<?php

namespace Tests\Unit\Controller;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;
use Tests\Unit\AbstractUnitTester;

abstract class AbstractControllerUnitTester extends AbstractUnitTester
{
    //| routing asserts
    protected function assertView(TestResponse $response, string $view, array $vars = [])
    {
        $vars = array_merge($vars, $this->getBaseRouteVars());

        $response->assertValid();
        $response->assertViewIs($view);
        $response->assertViewHasAll($vars);
    }

    protected function assertRedirect(TestResponse $response, string $route, array $vars = [])
    {
        $response->assertValid();
        $response->assertRedirectToRoute($route);
        foreach ($vars as $key => $value) {
            $this->assertEquals($value, session($key));
        }
    }

    //| validation asserts
    protected function assertValidationRequired(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field is required.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationTooLong(string $fieldName, int $maxLength, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must not be greater than %d characters.',
            $fieldString,
            $maxLength
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationTooShort(string $fieldName, int $maxLength, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be at least %d characters.',
            $fieldString,
            $maxLength
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationString(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be a string.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationInteger(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be an integer.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationBoolean(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be true or false.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationEmail(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be a valid email address.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationTaken(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s has already been taken.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationExists(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The selected %s is invalid.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationMixedCase(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must contain at least one uppercase and one lowercase letter.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationHasNumbers(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must contain at least one number.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    protected function assertValidationImageFileType(string $fieldName, array $response = [])
    {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be a file of type: png, jpg, jpeg, svg, webp.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message, $response);
    }

    private function formatValidationFieldName(string $fieldName)
    {
        $fieldName = strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $fieldName));
        $fieldName = str_replace("_", " ", $fieldName);
        return $fieldName;
    }

    protected function assertValidationValid(string $fieldName, array $response = [])
    {
        $this->assertEmpty(array_merge($this->getValidationErrors($fieldName, $response)));
    }

    private function assertValidation(string $fieldName, string $message, array $response = [])
    {
        $this->assertContains($message, $this->getValidationErrors($fieldName, $response));
    }

    protected function getValidationErrors(string $fieldName, array $response = [])
    {
        $errors = session('errors');
        $errors = ($errors !== null) ? $errors->get($fieldName) : [];
        $errors = (isset($response[$fieldName])) ? array_merge($errors, $response[$fieldName]) : $errors;
        return $errors;
    }

    protected function getBaseRouteVars()
    {
        $controller = new BaseController();
        return $controller->getbaseRouteVars();
    }
}
