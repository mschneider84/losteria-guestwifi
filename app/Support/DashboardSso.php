<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Anmeldung über das L'Osteria Dashboard (Single Sign-on).
 * Das Dashboard schickt einen kurzlebigen, mit dem gemeinsamen Schlüssel signierten Token (JWT, HS256).
 * Das Portal prüft ihn, legt den Benutzer an bzw. aktualisiert Name und Rolle und meldet ihn an.
 */
class DashboardSso
{
    public const ISSUER = 'losteria-dashboard';

    public const AUDIENCE = 'losteria-guestwifi';

    public const MAX_LIFETIME = 300; // Sekunden zwischen iat und exp

    public const LEEWAY = 30; // Uhrabweichung

    public static function secret(): ?string
    {
        return Settings::get('sso.secret') ?: null;
    }

    public static function dashboardUrl(): ?string
    {
        return Settings::get('sso.dashboard_url') ?: null;
    }

    /** Aktiv, sobald ein Schlüssel erzeugt wurde. Dann gilt der Passwort-Login nur noch als Notfall-Zugang für Superadmins. */
    public static function active(): bool
    {
        return self::secret() !== null;
    }

    public static function generateSecret(): string
    {
        $secret = 'sso_'.Str::random(48);
        Settings::set('sso.secret', $secret);

        return $secret;
    }

    /** Token für Tests und als Referenz für die Dashboard-Seite. */
    public static function makeToken(array $claims, string $secret): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $b64(json_encode($claims, JSON_UNESCAPED_UNICODE));

        return $head.'.'.$body.'.'.$b64(hash_hmac('sha256', $head.'.'.$body, $secret, true));
    }

    /**
     * @return array{sub:string, email:string, name:string, role:string, redirect:?string}
     *
     * @throws RuntimeException mit kurzem Grund (wird protokolliert, nicht dem Nutzer gezeigt)
     */
    public static function verify(string $token): array
    {
        $secret = self::secret() ?? throw new RuntimeException('sso_off');
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('format');
        }
        [$h, $b, $s] = $parts;
        $dec = fn (string $x) => base64_decode(strtr($x, '-_', '+/'), true);
        $head = json_decode((string) $dec($h), true);
        if (($head['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('alg');
        }
        $expected = hash_hmac('sha256', $h.'.'.$b, $secret, true);
        if (! hash_equals($expected, (string) $dec($s))) {
            throw new RuntimeException('signature');
        }
        $c = json_decode((string) $dec($b), true);
        if (! is_array($c)) {
            throw new RuntimeException('payload');
        }
        $now = time();
        $iat = (int) ($c['iat'] ?? 0);
        $exp = (int) ($c['exp'] ?? 0);
        if (($c['iss'] ?? null) !== self::ISSUER || ($c['aud'] ?? null) !== self::AUDIENCE) {
            throw new RuntimeException('issuer');
        }
        if ($exp < $now - self::LEEWAY || $iat > $now + self::LEEWAY || $exp - $iat > self::MAX_LIFETIME || $exp <= $iat) {
            throw new RuntimeException('expired');
        }
        $jti = (string) ($c['jti'] ?? '');
        if (strlen($jti) < 16 || ! Cache::add('sso.jti.'.hash('sha256', $jti), 1, self::MAX_LIFETIME + 2 * self::LEEWAY)) {
            throw new RuntimeException('replay');
        }
        $role = (string) ($c['role'] ?? '');
        if (! isset(Permissions::ROLES[$role])) {
            throw new RuntimeException('role');
        }
        $email = strtolower(trim((string) ($c['email'] ?? '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || blank($c['sub'] ?? null)) {
            throw new RuntimeException('identity');
        }
        $redirect = (string) ($c['redirect'] ?? '');

        return [
            'sub' => mb_substr((string) $c['sub'], 0, 120),
            'email' => $email,
            'name' => mb_substr(trim((string) ($c['name'] ?? '')) ?: $email, 0, 100),
            'role' => $role,
            // nur Pfade innerhalb der Verwaltung, keine fremden Adressen
            'redirect' => preg_match('#^/admin(/[A-Za-z0-9/_\-]*)?(\?[A-Za-z0-9=&_\-]*)?$#', $redirect) ? $redirect : null,
        ];
    }
}
