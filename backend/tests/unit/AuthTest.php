<?php

namespace Tests\Unit;

use App\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * Ensure login returns user data when credentials are valid.
     */
    public function test_login_withValidCredentials_returnsUser(): void
    {
        $user = $this->seedUser();
        $auth = new Auth();

        $result = $auth->login($user['email'], 'secret');

        $this->assertIsArray($result);
        $this->assertSame($user['email'], $result['email']);
        $this->assertArrayNotHasKey('password_hash', $result);
    }

    /**
     * Ensure login returns null for invalid password.
     */
    public function test_login_withInvalidPassword_returnsNull(): void
    {
        $user = $this->seedUser();
        $auth = new Auth();

        $result = $auth->login($user['email'], 'wrong');

        $this->assertNull($result);
    }

    /**
     * Ensure generateToken and verifyToken produce a valid round-trip payload.
     */
    public function test_generateToken_andVerifyToken_roundTrip(): void
    {
        $user = $this->seedUser();
        $auth = new Auth();

        $token = $auth->generateToken($user);
        $payload = $auth->verifyToken($token);

        $this->assertNotNull($payload);
        $this->assertSame($user['email'], $payload['email']);
        $this->assertSame($user['role'], $payload['role']);
    }

    /**
     * Ensure verifyToken rejects malformed tokens.
     */
    public function test_verifyToken_withMalformedToken_returnsNull(): void
    {
        $auth = new Auth();
        $this->assertNull($auth->verifyToken('bad.token.parts'));
    }
}
