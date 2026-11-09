<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function withBasicAuthCredentials(string $user = 'owner', string $password = 'secret'): static
    {
        config([
            'app.basic_auth.user' => $user,
            'app.basic_auth.password' => $password,
        ]);

        return $this->withHeader('Authorization', 'Basic '.base64_encode($user.':'.$password));
    }
}
