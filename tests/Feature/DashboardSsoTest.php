<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DashboardSso;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardSsoTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        User::create(['name' => 'Manfred', 'email' => 'boss@test.example', 'password' => 'passwort-lang-genug', 'role' => 'superadmin']);
        $this->secret = DashboardSso::generateSecret();
    }

    private function token(array $over = [], ?string $secret = null): string
    {
        return DashboardSso::makeToken($over + [
            'iss' => 'losteria-dashboard', 'aud' => 'losteria-guestwifi', 'sub' => 'dash-42', 'email' => 'Lisa@Test.example',
            'name' => 'Lisa Leitung', 'role' => 'manager', 'iat' => time(), 'exp' => time() + 60, 'jti' => Str::random(24),
        ], $secret ?? $this->secret);
    }

    public function test_dashboard_token_logs_in_and_creates_user_with_role(): void
    {
        $this->get('/sso?token='.$this->token(['redirect' => '/admin/netzwerk']))->assertRedirect('/admin/netzwerk');
        $this->assertAuthenticated();
        $u = User::where('sso_sub', 'dash-42')->first();
        $this->assertSame(['lisa@test.example', 'Lisa Leitung', 'manager'], [$u->email, $u->name, $u->role]);

        // Rolle ändert sich im Dashboard → wird beim nächsten Login übernommen
        auth()->logout();
        $this->post('/sso', ['token' => $this->token(['role' => 'admin', 'redirect' => 'https://evil.example'])])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('admin', $u->fresh()->role);
        $this->assertSame(2, User::count());
    }

    public function test_bad_tokens_are_rejected(): void
    {
        $jti = Str::random(24);
        $cases = [
            $this->token([], 'falscher-schluessel'),
            $this->token(['exp' => time() - 120, 'iat' => time() - 180]),
            $this->token(['exp' => time() + 3600]),
            $this->token(['aud' => 'anderes-system']),
            $this->token(['role' => 'gott']),
            $this->token(['jti' => 'kurz']),
            'kein.token',
        ];
        foreach ($cases as $t) {
            $this->get('/sso?token='.$t)->assertRedirect(route('login'));
            $this->assertGuest();
        }
        // gleicher Token nur einmal
        $once = $this->token(['jti' => $jti]);
        $this->get('/sso?token='.$once)->assertRedirect();
        auth()->logout();
        $this->get('/sso?token='.$once)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_login_only_as_emergency_for_superadmin(): void
    {
        User::create(['name' => 'Alt', 'email' => 'alt@test.example', 'password' => 'passwort-lang-genug', 'role' => 'admin']);
        $this->post('/anmelden', ['email' => 'alt@test.example', 'password' => 'passwort-lang-genug'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/anmelden', ['email' => 'boss@test.example', 'password' => 'passwort-lang-genug'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->get(route('login'));
    }

    public function test_blocked_user_cannot_come_back_via_dashboard(): void
    {
        User::create(['name' => 'X', 'email' => 'x@test.example', 'sso_sub' => 'dash-7', 'password' => 'x', 'role' => 'viewer', 'active' => false]);
        $this->get('/sso?token='.$this->token(['sub' => 'dash-7', 'email' => 'x@test.example']))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_without_secret_sso_is_off_and_password_login_works(): void
    {
        Settings::set('sso.secret', null);
        $this->get('/sso?token='.$this->token())->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('login'))->assertDontSee('Notfall-Zugang');
    }
}
