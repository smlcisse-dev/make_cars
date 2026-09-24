<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Réglages réseau lus depuis l'environnement, préparés pour la production
 * (PASSATION.md, « Bloquant avant la mise en production ») : origines CORS
 * autorisées et proxies de confiance.
 */
class ProductionNetworkConfigTest extends TestCase
{
    /**
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function loadConfigFileWith(string $file, array $variables): array
    {
        foreach ($variables as $name => $value) {
            $_SERVER[$name] = $value;
        }

        try {
            return require config_path($file);
        } finally {
            foreach (array_keys($variables) as $name) {
                unset($_SERVER[$name]);
            }
        }
    }

    public function test_cors_origins_are_read_from_the_environment(): void
    {
        $config = $this->loadConfigFileWith('cors.php', [
            'CORS_ALLOWED_ORIGINS' => 'https://app.makecars.bj, https://admin.makecars.bj',
        ]);

        $this->assertSame(['https://app.makecars.bj', 'https://admin.makecars.bj'], $config['allowed_origins']);
    }

    public function test_cors_defaults_to_the_local_frontend(): void
    {
        $config = require config_path('cors.php');

        $this->assertSame(['http://localhost:5173'], $config['allowed_origins']);
    }

    public function test_cors_answers_only_the_configured_origin(): void
    {
        config(['cors.allowed_origins' => ['https://app.makecars.bj']]);

        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/health', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        // Une autre origine ne reçoit jamais son propre nom en retour : le
        // navigateur refuse alors la réponse (avec une seule origine
        // configurée, la bibliothèque CORS renvoie toujours celle-ci).
        $this->assertSame('https://app.makecars.bj', $preflight('https://app.makecars.bj')->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('http://localhost:5173', $preflight('http://localhost:5173')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_trusted_proxies_are_read_from_the_environment(): void
    {
        $this->assertNull($this->loadConfigFileWith('trustedproxy.php', ['TRUSTED_PROXIES' => ''])['proxies']);
        $this->assertSame('10.0.0.0/8', $this->loadConfigFileWith('trustedproxy.php', ['TRUSTED_PROXIES' => '10.0.0.0/8'])['proxies']);
    }

    public function test_forwarded_ip_is_ignored_without_trusted_proxy(): void
    {
        Route::get('/_test/ip', fn (Request $request) => $request->ip());

        $this->get('/_test/ip', ['X-Forwarded-For' => '41.85.1.2'])->assertSeeText('127.0.0.1');
    }

    public function test_forwarded_ip_is_used_behind_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);
        Route::get('/_test/ip', fn (Request $request) => $request->ip());

        $this->get('/_test/ip', ['X-Forwarded-For' => '41.85.1.2'])->assertSeeText('41.85.1.2');
    }
}
