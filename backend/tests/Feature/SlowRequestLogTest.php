<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Journal des requêtes lentes (middleware LogSlowRequests, config
 * logging.slow_requests) : route, durée et nombre de requêtes SQL.
 */
class SlowRequestLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/slow', function () {
            User::query()->count();
            User::query()->exists();
            usleep(30_000);

            return response()->json(['ok' => true]);
        })->name('test.slow');
    }

    public function test_a_request_over_the_threshold_is_logged_with_its_route_duration_and_sql_query_count(): void
    {
        config(['logging.slow_requests' => ['enabled' => true, 'threshold_ms' => 20]]);
        Log::spy();

        $this->getJson('/_test/slow')->assertOk();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            return $message === 'Requête lente'
                && $context['method'] === 'GET'
                && $context['route'] === '/_test/slow'
                && $context['route_name'] === 'test.slow'
                && $context['status'] === 200
                && $context['duration_ms'] >= 30
                && $context['sql_queries'] === 2
                && is_int($context['sql_ms']);
        });
    }

    public function test_a_request_under_the_threshold_is_not_logged(): void
    {
        config(['logging.slow_requests' => ['enabled' => true, 'threshold_ms' => 5000]]);
        Log::spy();

        $this->getJson('/_test/slow')->assertOk();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_nothing_is_logged_when_disabled(): void
    {
        config(['logging.slow_requests' => ['enabled' => false, 'threshold_ms' => 0]]);
        Log::spy();

        $this->getJson('/_test/slow')->assertOk();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_it_is_enabled_by_default_in_production_only_with_a_one_second_threshold(): void
    {
        $load = function (array $variables): array {
            foreach ($variables as $name => $value) {
                $_SERVER[$name] = $value;
            }

            try {
                return (require config_path('logging.php'))['slow_requests'];
            } finally {
                foreach (array_keys($variables) as $name) {
                    unset($_SERVER[$name]);
                }
            }
        };

        $this->assertSame(['enabled' => true, 'threshold_ms' => 1000], $load(['APP_ENV' => 'production']));
        $this->assertFalse($load(['APP_ENV' => 'local'])['enabled']);
        $this->assertSame(['enabled' => true, 'threshold_ms' => 1500], $load(['APP_ENV' => 'local', 'LOG_SLOW_REQUESTS' => 'true', 'SLOW_REQUEST_THRESHOLD_MS' => '1500']));
    }
}
