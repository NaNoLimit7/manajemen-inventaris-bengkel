<?php

namespace Tests\Feature;

use Tests\TestCase;

class BasicAuthTest extends TestCase
{
    public function test_returns_unauthorized_without_credentials(): void
    {
        $this->configureCredentials();

        $response = $this->get('/');

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Basic realm="Inventaris Bengkel"');
    }

    public function test_returns_unauthorized_with_wrong_password(): void
    {
        $this->configureCredentials();

        $response = $this->withHeader('Authorization', 'Basic '.base64_encode('owner:wrong'))->get('/');

        $response->assertStatus(401);
    }

    public function test_allows_request_with_correct_credentials(): void
    {
        $this->configureCredentials();

        $response = $this->withHeader('Authorization', 'Basic '.base64_encode('owner:secret'))->get('/');

        $response->assertOk();
    }

    public function test_fails_closed_when_credentials_are_missing_outside_local(): void
    {
        config([
            'app.basic_auth.user' => null,
            'app.basic_auth.password' => null,
        ]);

        $response = $this->get('/');

        $response->assertStatus(503);
    }

    public function test_bypasses_gate_in_local_environment_when_credentials_are_missing(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        config([
            'app.basic_auth.user' => null,
            'app.basic_auth.password' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
    }

    private function configureCredentials(): void
    {
        config([
            'app.basic_auth.user' => 'owner',
            'app.basic_auth.password' => 'secret',
        ]);
    }
}
