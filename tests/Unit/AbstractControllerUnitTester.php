<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;

abstract class AbstractControllerUnitTester extends AbstractUnitTester
{
    protected function assertView(TestResponse $response, string $view, array $vars = []) {
        $vars = array_merge($vars, $this->getBaseRouteVars());

        $response->assertViewIs($view);
        $response->assertViewHasAll($vars);
    }

    protected function assertRedirect(TestResponse $response, string $route, array $vars = []) {
        $response->assertRedirectToRoute($route);
        foreach ($vars as $key => $value) {
            $this->assertEquals($value, session($key));
        }
    }

    protected function assertValidationRequired(string $fieldName) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field is required.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationTooLong(string $fieldName, int $maxLength) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must not be greater than %d characters.',
            $fieldString,
            $maxLength
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationTooShort(string $fieldName, int $maxLength) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be at least %d characters.',
            $fieldString,
            $maxLength
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationString(string $fieldName) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be a string.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationEmail(string $fieldName) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must be a valid email address.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationTaken(string $fieldName) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s has already been taken.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationMixedCase(string $fieldName) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must contain at least one uppercase and one lowercase letter.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message);
    }

    protected function assertValidationHasNumbers(string $fieldName) {
        $fieldString = $this->formatValidationFieldName($fieldName);
        $message = sprintf(
            'The %s field must contain at least one number.',
            $fieldString
        );

        $this->assertValidation($fieldName, $message);
    }

    private function formatValidationFieldName(string $fieldName) {
        $fieldName = str_replace("_", " ", $fieldName);
        return $fieldName;
    }

    protected function assertValidationValid(string $fieldName) {
        $this->assertEmpty($this->getValidationErrors($fieldName));
    }

    private function assertValidation(string $fieldName, string $message) {
        $this->assertContains($message, $this->getValidationErrors($fieldName));
    }

    protected function getValidationErrors(string $fieldName) {
        $errors = session('errors');
        if ($errors === null) {
            return [];
        }

        return $errors->get($fieldName);
    }

    protected function getBaseRouteVars() {
        $controller = new BaseController();
        return $controller->getbaseRouteVars();
    }
}
