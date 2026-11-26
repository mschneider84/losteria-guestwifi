<?php

namespace App\Services\Unifi;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Freischaltung über die UniFi-Cloud – ohne VPN, Portfreigabe oder feste IP.
 *
 * Weg: Portal → api.ui.com (Site Manager, Connector) → UDM SE (Network Integration API).
 * Basis: https://api.ui.com/v1/connector/consoles/{consoleId}/proxy/network/integration/v1
 * Schlüssel: Site Manager API-Key aus unifi.ui.com (Header X-API-KEY).
 *
 * Ablauf laut Ubiquiti „External Hotspot API for Authorization Clients“:
 *   GET  /sites                                          → Site-ID
 *   GET  /sites/{siteId}/clients?filter=macAddress.eq('…') → Client-ID
 *   POST /sites/{siteId}/clients/{clientId}/actions      → AUTHORIZE_GUEST_ACCESS
 * Voraussetzung: UniFi Network ≥ 9.1.105, UniFi OS ≥ 5.0.3, Konsole mit dem UI-Konto verbunden.
 */
class UnifiCloudClient implements UnifiClient
{
    public const BASE = 'https://api.ui.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $consoleId,
        private readonly int $timeout = 10,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            (string) Settings::get('unifi.cloud_api_key'),
            (string) Settings::get('unifi.console_id'),
            max(10, (int) Settings::get('unifi.timeout')),
        );
    }

    public function authorizeGuest(string $site, string $mac, int $minutes, ?int $upKbps = null, ?int $downKbps = null, ?int $quotaMb = null, ?string $apMac = null): void
    {
        $siteId = $this->siteId($site);
        $clientId = $this->clientId($siteId, $mac);

        $payload = array_filter([
            'action' => 'AUTHORIZE_GUEST_ACCESS',
            'timeLimitMinutes' => $minutes,
            'dataUsageLimitMBytes' => $quotaMb,
            'rxRateLimitKbps' => $downKbps,
            'txRateLimitKbps' => $upKbps,
        ], fn ($v) => $v !== null);

        $this->request('post', "/sites/{$siteId}/clients/{$clientId}/actions", $payload);
    }

    public function unauthorizeGuest(string $site, string $mac): void
    {
        $siteId = $this->siteId($site);
        $this->request('post', "/sites/{$siteId}/clients/{$this->clientId($siteId, $mac)}/actions", ['action' => 'UNAUTHORIZE_GUEST_ACCESS']);
    }

    public function sites(): array
    {
        return collect($this->allSites())->mapWithKeys(fn ($s) => [($s['internalReference'] ?? $s['id']) => $s['name'] ?? $s['id']])->all();
    }

    /** Konsolen (UDM SE usw.) des UI-Kontos – für die Auswahl in den Einstellungen. */
    public function consoles(): array
    {
        $response = $this->http()->get(self::BASE.'/v1/hosts');
        $this->guard($response, '/v1/hosts');

        return collect($response->json('data', []))->mapWithKeys(fn ($h) => [
            $h['id'] => trim(($h['reportedState']['name'] ?? $h['reportedState']['hostname'] ?? 'Konsole').' · '.($h['ipAddress'] ?? '')),
        ])->all();
    }

    /** Site-ID zum Kurznamen („default“) bzw. Namen; bei einer einzigen Site immer diese. */
    private function siteId(string $site): string
    {
        return Cache::remember("unifi.cloud.site.{$this->consoleId}.{$site}", 86400, function () use ($site) {
            $sites = $this->allSites();
            foreach ($sites as $s) {
                if (in_array($site, [$s['id'] ?? null, $s['internalReference'] ?? null, $s['name'] ?? null], true)) {
                    return $s['id'];
                }
            }
            if (count($sites) === 1) {
                return $sites[0]['id'];
            }
            throw new UnifiException("Site „{$site}“ wurde auf der Konsole nicht gefunden.");
        });
    }

    private function allSites(): array
    {
        return $this->request('get', '/sites', ['limit' => 200])->json('data', []);
    }

    private function clientId(string $siteId, string $mac): string
    {
        $mac = strtolower($mac);
        $data = $this->request('get', "/sites/{$siteId}/clients", ['filter' => "macAddress.eq('{$mac}')"])->json('data', []);
        $id = $data[0]['id'] ?? null;
        if (! $id) {
            throw new UnifiException('Das Gerät ist (noch) nicht im WLAN angemeldet.');
        }

        return $id;
    }

    private function request(string $method, string $path, array $data = []): Response
    {
        if ($this->apiKey === '' || $this->consoleId === '') {
            throw new UnifiException('UniFi-Cloud: API-Schlüssel oder Konsole fehlt in den Einstellungen.');
        }
        $url = self::BASE."/v1/connector/consoles/{$this->consoleId}/proxy/network/integration/v1{$path}";
        $response = $this->http()->{$method}($url, $data);
        $this->guard($response, $path);

        return $response;
    }

    private function guard(Response $response, string $path): void
    {
        if ($response->successful()) {
            return;
        }
        $hint = match ($response->status()) {
            401 => 'API-Schlüssel ungültig',
            403 => 'keine Berechtigung für diese Konsole',
            404 => 'Konsole oder Eintrag nicht gefunden',
            408, 504 => 'Konsole antwortet nicht (offline?)',
            429 => 'zu viele Anfragen an die UniFi-Cloud',
            default => 'Fehler',
        };
        $msg = $response->json('message') ?? $response->json('error') ?? '';
        throw new UnifiException(trim("UniFi-Cloud: {$hint} (HTTP {$response->status()} bei {$path}) ".(is_string($msg) ? $msg : '')));
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout($this->timeout)->withHeaders(['X-API-KEY' => $this->apiKey]);
    }
}
