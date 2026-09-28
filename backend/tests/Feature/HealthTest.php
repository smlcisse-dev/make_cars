<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
        ]);
    }

    public function test_health_endpoint_runs_no_sql_query(): void
    {
        DB::enableQueryLog();

        $this->getJson('/api/health')->assertOk();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_db_health_endpoint_queries_the_database(): void
    {
        DB::enableQueryLog();

        $this->getJson('/api/health/db')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'db' => 'ok'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_db_health_endpoint_hides_the_exception_detail(): void
    {
        $secret = 'connection to server at "aws-0-eu-central-1.pooler.supabase.com", user "postgres.secretproject" failed';
        DB::partialMock()->shouldReceive('transaction')->andThrow(new RuntimeException($secret));
        Log::spy();

        $response = $this->getJson('/api/health/db')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'db' => 'unavailable'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringNotContainsString('supabase', $response->getContent());
        $this->assertStringNotContainsString('secretproject', $response->getContent());
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $context['message'] === $secret
        );
    }

    public function test_db_health_endpoint_returns_503_when_database_is_unreachable(): void
    {
        config(['database.connections.sqlite.database' => '/nonexistent/dir/makecars.sqlite']);
        DB::purge('sqlite');

        $this->getJson('/api/health/db')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'db' => 'unavailable']);
    }

    public function test_db_health_endpoint_is_rate_limited(): void
    {
        $this->getJson('/api/health/db')->assertHeader('X-RateLimit-Limit', 120);
    }
}
