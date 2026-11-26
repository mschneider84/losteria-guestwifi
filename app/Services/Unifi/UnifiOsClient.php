<?php

namespace App\Services\Unifi;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Anbindung an die UniFi Network-Anwendung auf einer UniFi-OS-Konsole (UDM SE, UDM Pro, UCG).
 *
 * Anmeldung: POST /api/auth/login → Cookie "TOKEN" + Header "X-CSRF-Token".
 * Freischalten: POST /proxy/network/api/s/{site}/cmd/stamgr mit cmd=authorize-guest.
 * Die Sitzung wird 20 Minuten zwischengespeichert und bei 401 einmal erneuert.
 */
class UnifiOsClient implements UnifiClient
{
    private const SESSION_CACHE = 'unifi.session';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly bool $verifyTls = false,
        private readonly int $timeout = 8,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            rtrim((string) Settings::get('unifi.url'), '/'),
            (string) Settings::get('unifi.username'),
            (string) Settings::get('unifi.password'),
            (bool) Settings::get('unifi.verify_tls'),
            (int) Settings::get('unifi.timeout'),
        );
    }

    public function authorizeGuest(string $site, string $mac, int $minutes, ?int $upKbps = null, ?int $downKbps = null, ?int $quotaMb = null, ?string $apMac = null): void
    {
        $payload = array_filter([
            'cmd' => 'authorize-guest',
            'mac' => strtolower($mac),
            'minutes' => $minutes,
            'up' => $upKbps,
            'down' => $downKbps,
            'bytes' => $quotaMb,
            'ap_mac' => $apMac ? strtolower($apMac) : null,
        ], fn ($v) => $v !== null);

        $this->post("/proxy/network/api/s/{$site}/cmd/stamgr", $payload);
    }

    public function unauthorizeGuest(string $site, string $mac): void
    {
        $this->post("/proxy/network/api/s/{$site}/cmd/stamgr", ['cmd' => 'unauthorize-guest', 'mac' => strtolower($mac)]);
    }

    public function network(string $site): array
    {
        $devices = collect($this->request('get', "/proxy/network/api/s/{$site}/stat/device")->json('data', []))->map(function ($d) {
            $radios = collect($d['radio_table_stats'] ?? []);
            $packets = $radios->sum('tx_packets');

            return [
                'id' => (string) ($d['_id'] ?? $d['mac'] ?? ''), 'mac' => $d['mac'] ?? null, 'name' => $d['name'] ?? null, 'model' => $d['model'] ?? null,
                'online' => (int) ($d['state'] ?? 0) === 1, 'is_ap' => ($d['type'] ?? '') === 'uap',
                'retries_pct' => $packets ? round($radios->sum('tx_retries') / $packets * 100, 1) : null,
                'cpu_pct' => isset($d['system-stats']['cpu']) ? (float) $d['system-stats']['cpu'] : null,
                'uptime_sec' => isset($d['uptime']) ? (int) $d['uptime'] : null,
            ];
        })->values();
        $byMac = $devices->pluck('id', 'mac');

        $clients = collect($this->request('get', "/proxy/network/api/s/{$site}/stat/sta")->json('data', []))->filter(fn ($c) => ! empty($c['mac']))->map(fn ($c) => [
            'mac' => strtolower($c['mac']), 'ap_id' => isset($c['ap_mac']) ? ($byMac[$c['ap_mac']] ?? null) : null,
            'wireless' => empty($c['is_wired']), 'guest' => ! empty($c['is_guest']),
            'rx_bytes' => isset($c['rx_bytes']) ? (int) $c['rx_bytes'] : null, 'tx_bytes' => isset($c['tx_bytes']) ? (int) $c['tx_bytes'] : null,
        ])->values();

        return ['devices' => $devices->all(), 'clients' => $clients->all()];
    }

    public function sites(): array
    {
        $data = $this->request('get', '/proxy/network/api/self/sites')->json('data', []);

        return collect($data)->mapWithKeys(fn ($s) => [$s['name'] => $s['desc'] ?? $s['name']])->all();
    }

    private function post(string $path, array $payload): array
    {
        $json = $this->request('post', $path, $payload)->json();
        if (($json['meta']['rc'] ?? 'ok') !== 'ok') {
            throw new UnifiException('UniFi meldet: '.($json['meta']['msg'] ?? 'unbekannter Fehler'));
        }

        return $json['data'] ?? [];
    }

    private function request(string $method, string $path, array $payload = [], bool $retry = true): Response
    {
        $session = $this->session();
        $response = $this->http()
            ->withHeaders(['X-CSRF-Token' => $session['csrf']])
            ->withCookies(['TOKEN' => $session['token']], parse_url($this->baseUrl, PHP_URL_HOST))
            ->{$method}($this->baseUrl.$path, $payload);

        if ($response->status() === 401 && $retry) {
            Cache::forget(self::SESSION_CACHE);

            return $this->request($method, $path, $payload, false);
        }
        if ($response->failed()) {
            throw new UnifiException("UniFi antwortet mit HTTP {$response->status()} auf {$path}");
        }

        return $response;
    }

    /** @return array{token:string,csrf:string} */
    private function session(): array
    {
        return Cache::remember(self::SESSION_CACHE, 1200, function () {
            if ($this->username === '' || $this->password === '') {
                throw new UnifiException('UniFi-Zugang ist in den Einstellungen nicht hinterlegt.');
            }
            $response = $this->http()->post($this->baseUrl.'/api/auth/login', [
                'username' => $this->username,
                'password' => $this->password,
                'rememberMe' => false,
            ]);
            if ($response->failed()) {
                throw new UnifiException("Anmeldung an der UDM fehlgeschlagen (HTTP {$response->status()}).");
            }
            $token = $response->cookies()->getCookieByName('TOKEN')?->getValue();
            $csrf = $response->header('X-CSRF-Token') ?: $response->header('x-updated-csrf-token');
            if (! $token) {
                throw new UnifiException('Die UDM hat kein Anmelde-Token geliefert.');
            }

            return ['token' => $token, 'csrf' => (string) $csrf];
        });
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout($this->timeout)->withOptions(['verify' => $this->verifyTls]);
    }
}
