<?php

namespace Tests\Feature;

use Tests\TestCase;

final class PwaCorsTest extends TestCase
{
    public function test_allowed_origin_receives_cors_header(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:8081']);

        $this->get('/api/v1/health', ['Origin' => 'http://localhost:8081'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8081');
    }

    public function test_unknown_origin_is_not_allowed(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:8081']);

        $response = $this->get('/api/v1/health', ['Origin' => 'https://attacker.invalid']);
        $response->assertOk();

        // Quando apenas UMA origem está liberada, o middleware CORS pode
        // retornar essa origem fixa também nas respostas para outros hosts.
        // O navegador NÃO libera a resposta se a origem requisitante não
        // corresponde ao Access-Control-Allow-Origin.
        $allowed = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotSame('https://attacker.invalid', $allowed);
        $this->assertNotSame('*', $allowed);
    }

    public function test_login_preflight_allows_authorization_and_content_type(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:8081']);

        $response = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:8081',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $this->assertSame('http://localhost:8081', $response->headers->get('Access-Control-Allow-Origin'));
        $headers = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $headers);
        $this->assertStringContainsString('content-type', $headers);
    }

    public function test_unknown_origin_preflight_does_not_authorize_it(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:8081']);

        $response = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://attacker.invalid',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $allowed = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotSame('https://attacker.invalid', $allowed);
        $this->assertNotSame('*', $allowed);
    }
}
