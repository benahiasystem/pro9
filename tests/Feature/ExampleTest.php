<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    // ########### INICIO PRUEBA ACCESO RAÍZ PRO9
    public function testBasicTest()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
    // ########### FIN PRUEBA ACCESO RAÍZ PRO9
}
