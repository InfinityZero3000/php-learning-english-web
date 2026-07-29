<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyControllerApiParityTest extends TestCase
{
    #[DataProvider('businessEndpoints')]
    public function test_every_removed_controller_business_action_has_an_api_endpoint(string $method, string $uri): void
    {
        $matched = collect(Route::getRoutes()->getRoutes())->contains(
            fn (LaravelRoute $route) => $route->uri() === $uri && in_array($method, $route->methods(), true),
        );

        $this->assertTrue($matched, "Missing {$method} {$uri}");
    }

    public static function businessEndpoints(): array
    {
        return array_map(fn (array $endpoint) => $endpoint, [
            ['GET', 'api/v1/admin/users'], ['PUT', 'api/v1/admin/users/{user}/role'],
            ['POST', 'api/v1/auth/register'], ['POST', 'api/v1/auth/login'], ['POST', 'api/v1/auth/logout'],
            ['GET', 'api/v1/auth/me'], ['POST', 'api/v1/auth/email/resend'], ['GET', 'api/v1/auth/email/verify/{id}/{hash}'],
            ['POST', 'api/v1/auth/password/forgot'], ['POST', 'api/v1/auth/password/reset'],
            ['GET', 'api/v1/vocabulary'], ['GET', 'api/v1/vocabulary/{vocabulary}'], ['PUT', 'api/v1/vocabulary/{vocabulary}/bookmark'],
            ['GET', 'api/v1/admin/catalog/courses'], ['POST', 'api/v1/admin/catalog/courses'],
            ['GET', 'api/v1/admin/catalog/courses/{course}'], ['PUT', 'api/v1/admin/catalog/courses/{course}'],
            ['DELETE', 'api/v1/admin/catalog/courses/{course}'],
            ['GET', 'api/v1/admin/catalog/lessons'], ['POST', 'api/v1/admin/catalog/lessons'],
            ['GET', 'api/v1/admin/catalog/lessons/{lesson}'], ['PUT', 'api/v1/admin/catalog/lessons/{lesson}'],
            ['DELETE', 'api/v1/admin/catalog/lessons/{lesson}'],
            ['GET', 'api/v1/admin/catalog/quizzes'], ['POST', 'api/v1/admin/catalog/quizzes'],
            ['GET', 'api/v1/admin/catalog/quizzes/{quiz}'], ['PUT', 'api/v1/admin/catalog/quizzes/{quiz}'],
            ['DELETE', 'api/v1/admin/catalog/quizzes/{quiz}'],
            ['GET', 'api/v1/admin/catalog/vocabularies'], ['POST', 'api/v1/admin/catalog/vocabularies'],
            ['GET', 'api/v1/admin/catalog/vocabularies/{vocabulary}'], ['PUT', 'api/v1/admin/catalog/vocabularies/{vocabulary}'],
            ['DELETE', 'api/v1/admin/catalog/vocabularies/{vocabulary}'],
            ['PUT', 'api/v1/profile'], ['PUT', 'api/v1/profile/password'], ['DELETE', 'api/v1/profile'],
            ['GET', 'api/v1/progress/dashboard'], ['POST', 'api/v1/progress/lesson/{lesson}/complete'],
            ['GET', 'api/v1/lesson-quizzes'], ['POST', 'api/v1/lesson-quizzes/{quiz}/attempts'],
            ['PUT', 'api/v1/lesson-quiz-attempts/{attempt}/answers/{question}'],
            ['POST', 'api/v1/lesson-quiz-attempts/{attempt}/complete'], ['GET', 'api/v1/lesson-quiz-attempts/{attempt}'],
        ]);
    }
}
