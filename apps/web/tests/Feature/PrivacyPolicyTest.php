<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_describes_cookie_and_ip_analytics(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Политика конфиденциальности')
            ->assertSee('tokamaki_visitor')
            ->assertSee('IP-адрес')
            ->assertSee('User-Agent')
            ->assertSee('Яндекс Метрика')
            ->assertSee('5 лет');
    }

    public function test_public_pages_link_to_the_privacy_policy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('privacy'), false);

        $this->get('/kartina-po-nomeram-po-foto')
            ->assertOk()
            ->assertSee(route('privacy'), false);
    }
}
