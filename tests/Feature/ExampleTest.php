<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seluruh halaman aplikasi berada di balik autentikasi, sehingga tamu
     * yang membuka dashboard diarahkan ke halaman login.
     */
    public function test_a_guest_is_redirected_to_the_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
