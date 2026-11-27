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

    /** Konsole und ggf. eigener Schlüssel des Standorts, sonst die Werte aus den Einstellungen. */
    public static function forLocation(\App\Models\Location $location): self
    {
        return new self(
            (string) ($location->unifi_api_key ?: Settings::get('unifi.cloud_api_key')),
            (string) ($location->unifi_console_id ?: Settings::get('unifi.console_id')),
            max(10, (int) Settings::get('unifi.timeout')),
        );
    }

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

    /**
     * Access Points und verbundene Geräte über die Network Integration API.
     * Felder werden vorsichtig gelesen: Was die UDM nicht liefert, bleibt leer (null).
     */
    public function network(string $site): array
    {
        $siteId = $this->siteId($site);

        $devices = collect($this->paged("/sites/{$siteId}/devices"))->map(function ($d) {
            $features = array_map('strtolower', array_map('strval', (array) ($d['features'] ?? [])));
            $isAp = in_array('accesspoint', $features, true) || ! empty($d['interfaces']['radios'] ?? null) || in_array('radios', array_map('strtolower', array_map('strval', (array) ($d['interfaces'] ?? []))), true);

            return [
                'id' => (string) ($d['id'] ?? ''), 'mac' => isset($d['macAddress']) ? strtolower($d['macAddress']) : null,
                'name' => $d['name'] ?? null, 'model' => $d['model'] ?? null,
                'online' => strtoupper((string) ($d['state'] ?? '')) === 'ONLINE', 'is_ap' => $isAp,
                'retries_pct' => null, 'cpu_pct' => null, 'uptime_sec' => null,
            ];
        })->filter(fn ($d) => $d['id'] !== '')->values()->all();

        // Kennzahlen je Access Point (höchstens 30 Aufrufe; Limit der Cloud: 100 je Minute und Konsole)
        foreach ($devices as $i => $d) {
            if (! $d['is_ap'] || ! $d['online'] || $i >= 30) {
                continue;
            }
            try {
                $st = $this->request('get', "/sites/{$siteId}/devices/{$d['id']}/statistics/latest")->json() ?? [];
            } catch (UnifiException) {
                continue;
            }
            $retries = collect($st['interfaces']['radios'] ?? [])->pluck('txRetriesPct')->filter(fn ($v) => is_numeric($v));
            $devices[$i]['retries_pct'] = $retries->isNotEmpty() ? round((float) $retries->max(), 1) : null;
            $devices[$i]['cpu_pct'] = is_numeric($st['cpuUtilizationPct'] ?? null) ? (float) $st['cpuUtilizationPct'] : null;
            $devices[$i]['uptime_sec'] = is_numeric($st['uptimeSec'] ?? null) ? (int) $st['uptimeSec'] : null;
        }

        $clients = collect($this->paged("/sites/{$siteId}/clients"))->filter(fn ($c) => ! empty($c['macAddress']))->map(fn ($c) => [
            'mac' => strtolower($c['macAddress']),
            'ap_id' => $c['uplinkDeviceId'] ?? null,
            'wireless' => strtoupper((string) ($c['type'] ?? 'WIRELESS')) === 'WIRELESS',
            'guest' => strtoupper((string) ($c['access']['type'] ?? '')) === 'GUEST',
            'rx_bytes' => self::bytes($c, ['rxBytes', 'statistics.rxBytes', 'traffic.rxBytes']),
            'tx_bytes' => self::bytes($c, ['txBytes', 'statistics.txBytes', 'traffic.txBytes']),
        ])->values()->all();

        return ['devices' => $devices, 'clients' => $clients];
    }

    private static function bytes(array $c, array $paths): ?int
    {
        foreach ($paths as $p) {
            $v = data_get($c, $p);
            if (is_numeric($v)) {
                return (int) $v;
            }
        }

        return null;
    }

    /** Liste mit offset/limit vollständig abholen (höchstens 10 Seiten à 200). */
    private function paged(string $path): array
    {
        $all = [];
        for ($page = 0, $offset = 0; $page < 10; $page++) {
            $json = $this->request('get', $path, ['offset' => $offset, 'limit' => 200])->json() ?? [];
            $data = $json['data'] ?? [];
            $all = array_merge($all, $data);
            $offset += count($data);
            if (! $data || $offset >= (int) ($json['totalCount'] ?? 0)) {
                break;
            }
        }

        return $all;
    }

    public function sites(): array
    {
        return collect($this->allSites())->mapWithKeys(fn ($s) => [($s['internalReference'] ?? $s['id']) => $s['name'] ?? $s['id']])->all();
    }

    /** Konsolen (UDM SE usw.) des UI-Kontos – für die Auswahl in Einstellungen und Standorten. */
    public function consoles(): array
    {
        return collect($this->hosts())->map(fn ($h) => trim($h['name'].($h['ip'] ? ' · '.$h['ip'] : '').($h['owner'] ? '' : ' · nur Admin-Zugriff')))->all();
    }

    /** @return array<string, array{name:string, ip:?string}> */
    public function hosts(): array
    {
        if ($this->apiKey === '') {
            throw new UnifiException('UniFi-Cloud: Der API-Schlüssel fehlt in den Einstellungen.');
        }
        $response = $this->http()->get(self::BASE.'/v1/hosts');
        $this->guard($response, '/v1/hosts');

        return collect($response->json('data', []))
            ->filter(fn ($h) => ! empty($h['id']) && (($h['type'] ?? 'console') === 'console'))
            ->mapWithKeys(fn ($h) => [$h['id'] => [
                'name' => (string) ($h['reportedState']['name'] ?? $h['reportedState']['hostname'] ?? 'Konsole'),
                'ip' => $h['ipAddress'] ?? ($h['reportedState']['ip'] ?? null),
                // Nur Konsolen im Eigentum des Schlüssel-Kontos lassen sich über die Cloud steuern
                'owner' => ($h['owner'] ?? true) !== false,
            ]])->all();
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
        if ($this->apiKey === '') {
            throw new UnifiException('UniFi-Cloud: Der API-Schlüssel fehlt in den Einstellungen.');
        }
        if ($this->consoleId === '') {
            throw new UnifiException('UniFi-Cloud: Für diesen Standort ist noch keine Konsole gewählt (Standorte → Bearbeiten → UniFi-Konsole).');
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
            403 => 'keine Berechtigung für diese Konsole – über die Cloud klappt es nur mit einem API-Schlüssel des UI-Kontos, dem die Konsole gehört (Owner), nicht eines Admins',
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
