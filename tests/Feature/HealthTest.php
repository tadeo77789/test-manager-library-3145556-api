<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_returns_ok_without_token(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_unknown_route_uses_single_error_body(): void
    {
        $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonStructure(['code', 'message', 'details']);
    }
}
