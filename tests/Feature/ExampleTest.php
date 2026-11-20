<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect('/login');

        $response = $this->get('/login');
        $response->assertOk()
            ->assertSee('Laundry-Kos')
            ->assertSee('img/logo-app.jpg')
            ->assertSee('img/favicon-32x32.png')
            ->assertSee('font-awesome')
            ->assertSee('passwordField')
            ->assertDontSee('<style>', false)
            ->assertDontSee('<script>', false);
    }
}
