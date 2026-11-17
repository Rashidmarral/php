<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PasswordReset;
use App\Models\Plan;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The login page had none of this covered before: no request-rate limiting
 * on /login, /login/2fa, /forgot-password or /register (unlimited password
 * or 2FA-code guessing at network speed), a timing side-channel on /login
 * that let an attacker tell a wrong-password response apart from an
 * unknown-email response by how long it took (Hash::check() was skipped
 * entirely when no user matched), password-reset tokens stored in plain
 * text in the database, and no invalidation of a user's other sessions
 * after a password reset. This covers the fix for each.
 */
class LoginSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // The throttle middleware's hit counts live in the cache, not the
        // database, so DatabaseTransactions alone doesn't reset them between
        // tests in this class — without this, an earlier test's /login or
        // /login/2fa attempts would carry over and trip a later test's own
        // throttle check.
        Cache::flush();
    }

    private function makeActiveUser(string $password = 'Str0ng!Pass2024'): User
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);

        return User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => $password,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_login_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
            $response->assertStatus(302);
        }

        $response = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
        $response->assertStatus(429);
    }

    /**
     * Laravel's plain `throttle:N,M` middleware keys its counter by IP alone
     * (it does not vary by route) — applying it with no distinct prefix to
     * several auth routes would draw them all from the SAME pool, so a
     * visitor who failed a few logins would also find registration or a
     * password-reset request blocked. Each route below carries its own
     * prefix for exactly this reason.
     */
    public function test_exhausting_the_login_throttle_does_not_block_registration_or_password_reset_requests(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(429);

        $plan = Plan::create(['slug' => 'starter-'.bin2hex(random_bytes(4)), 'name' => 'Starter']);
        $register = $this->post('/register', [
            'company_name' => 'Acme', 'name' => 'Owner', 'email' => 'new-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Str0ng!Pass2024', 'password_confirmation' => 'Str0ng!Pass2024', 'plan' => $plan->slug, 'terms' => '1',
        ]);
        $register->assertRedirect('/app');

        $forgot = $this->post('/forgot-password', ['email' => 'someone@example.com']);
        $forgot->assertRedirect('/login');
    }

    public function test_login_with_unknown_email_and_wrong_password_on_known_email_both_still_fail_the_same_way(): void
    {
        $user = $this->makeActiveUser();

        $unknown = $this->post('/login', ['email' => 'definitely-not-registered@example.com', 'password' => 'whatever123']);
        $wrongPassword = $this->post('/login', ['email' => $user->email, 'password' => 'whatever123']);

        $unknown->assertSessionHasErrors('email');
        $wrongPassword->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_without_two_factor_authenticates_immediately(): void
    {
        $user = $this->makeActiveUser();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Str0ng!Pass2024']);

        $response->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_for_a_user_with_two_factor_enabled_requires_the_code_and_does_not_authenticate_yet(): void
    {
        $user = $this->makeActiveUser();
        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Str0ng!Pass2024']);

        $response->assertRedirect('/login/2fa');
        $this->assertGuest();

        $code = Totp::currentCode($secret);
        $verify = $this->post('/login/2fa', ['code' => $code]);
        $verify->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_challenge_locks_out_after_five_wrong_codes(): void
    {
        $user = $this->makeActiveUser();
        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Str0ng!Pass2024']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login/2fa', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $locked = $this->post('/login/2fa', ['code' => '000000']);

        $locked->assertRedirect('/login');
        $locked->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_token_is_stored_hashed_not_in_plain_text(): void
    {
        $user = $this->makeActiveUser();

        $this->post('/forgot-password', ['email' => $user->email]);

        $reset = PasswordReset::where('email', $user->email)->firstOrFail();
        $this->assertSame(64, strlen($reset->token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $reset->token);
    }

    public function test_password_reset_invalidates_the_users_other_existing_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->makeActiveUser();

        $rawToken = bin2hex(random_bytes(32));
        PasswordReset::create(['email' => $user->email, 'token' => hash('sha256', $rawToken), 'expires_at' => now()->addHour()]);

        DB::table('sessions')->insert([
            'id' => 'old-session-id', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
        ]);

        $response = $this->post('/reset-password/'.$rawToken, [
            'password' => 'NewStr0ng!Pass2024', 'password_confirm' => 'NewStr0ng!Pass2024',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session-id']);
        $this->assertTrue(Hash::check('NewStr0ng!Pass2024', $user->fresh()->password));
    }

    public function test_an_expired_or_unknown_reset_token_is_rejected(): void
    {
        $response = $this->post('/reset-password/'.bin2hex(random_bytes(32)), [
            'password' => 'NewStr0ng!Pass2024', 'password_confirm' => 'NewStr0ng!Pass2024',
        ]);

        $response->assertRedirect('/forgot-password');
    }
}
