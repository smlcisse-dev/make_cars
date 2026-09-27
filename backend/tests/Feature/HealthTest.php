<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
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
            ->assertExactJson(['status' => 'ok', 'db' => 'ok']);

        $this->assertCount(1, DB::getQueryLog());
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
