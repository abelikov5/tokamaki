<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Токамаки')
            ->assertSee('Товары для творчества')
            ->assertSee(route('generator'), false)
            ->assertSee('mc.yandex.ru/metrika/tag.js?id=113757543', false)
            ->assertSee('mc.yandex.ru/watch/113757543', false)
            ->assertSee('application/ld+json', false);
    }
}
