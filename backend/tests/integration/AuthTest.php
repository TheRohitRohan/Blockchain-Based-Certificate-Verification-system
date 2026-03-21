<?php

namespace Tests\Integration;

use App\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * Verify register then login works against the database.
     */
    public function test_register_then_login_roundTrip(): void
    {
        $auth = new Auth();
        $auth->register([
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => 'mypassword',
            'role' => 'university',
            'full_name' => 'Bob Smith',
            'university_id' => 1,
        ]);

        $user = $auth->login('bob@example.com', 'mypassword');

        $this->assertNotNull($user);
        $this->assertSame('bob@example.com', $user['email']);
    }
}
