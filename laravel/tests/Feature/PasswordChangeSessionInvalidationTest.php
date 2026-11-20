<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Changing a password from inside the app (Settings → Security for a
 * company user, or the admin profile page for platform staff) used to leave
 * every other open session on the OLD password valid until it naturally
 * expired — a shared/public computer, or a session an attacker had already
 * stolen, kept working right through the change. Both now invalidate every
 * OTHER session for that account the moment the password changes, while
 * deliberately keeping the session that made the change itself logged in.
 */
class PasswordChangeSessionInvalidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_user_password_change_invalidates_other_sessions_but_keeps_the_current_one(): void
    {
        config(['session.driver' => 'database']);
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Owner', 'email' => 'owner-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'OldStr0ng!Pass2024', 'role' => 'owner', 'status' => 'active',
        ]);

        DB::table('sessions')->insert([
            'id' => 'other-device-session', 'user_id' => $user->id, 'ip_address' => '10.0.0.5',
            'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
        ]);

        $response = $this->actingAs($user)->post('/app/settings/security', [
            'current_password' => 'OldStr0ng!Pass2024',
            'new_password' => 'NewStr0ng!Pass2024',
            'new_password_confirm' => 'NewStr0ng!Pass2024',
        ]);

        $response->assertRedirect('/app/settings/security');
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
        $this->assertTrue(Hash::check('NewStr0ng!Pass2024', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_company_user_password_change_is_rejected_with_the_wrong_current_password_and_invalidates_nothing(): void
    {
        config(['session.driver' => 'database']);
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme2-'.bin2hex(random_bytes(4)).'@example.com']);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Owner', 'email' => 'owner2-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'OldStr0ng!Pass2024', 'role' => 'owner', 'status' => 'active',
        ]);
        DB::table('sessions')->insert([
            'id' => 'other-device-session-2', 'user_id' => $user->id, 'ip_address' => '10.0.0.5',
            'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
        ]);

        $response = $this->actingAs($user)->post('/app/settings/security', [
            'current_password' => 'WrongPassword',
            'new_password' => 'NewStr0ng!Pass2024',
            'new_password_confirm' => 'NewStr0ng!Pass2024',
        ]);

        $response->assertRedirect('/app/settings/security');
        $this->assertDatabaseHas('sessions', ['id' => 'other-device-session-2']);
        $this->assertTrue(Hash::check('OldStr0ng!Pass2024', $user->fresh()->password));
    }

    public function test_admin_profile_password_change_invalidates_other_sessions_but_keeps_the_current_one(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::create([
            'company_id' => null, 'name' => 'Admin', 'email' => 'admin-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'OldStr0ng!Pass2024', 'role' => 'super_admin', 'status' => 'active',
        ]);

        DB::table('sessions')->insert([
            'id' => 'admin-other-session', 'user_id' => $admin->id, 'ip_address' => '10.0.0.6',
            'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
        ]);

        $response = $this->actingAs($admin)->post('/admin/profile', [
            'name' => $admin->name,
            'email' => $admin->email,
            'current_password' => 'OldStr0ng!Pass2024',
            'new_password' => 'NewStr0ng!Pass2024',
            'new_password_confirm' => 'NewStr0ng!Pass2024',
        ]);

        $response->assertRedirect('/admin/profile');
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-other-session']);
        $this->assertTrue(Hash::check('NewStr0ng!Pass2024', $admin->fresh()->password));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_profile_update_without_changing_password_does_not_touch_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::create([
            'company_id' => null, 'name' => 'Admin', 'email' => 'admin2-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'OldStr0ng!Pass2024', 'role' => 'super_admin', 'status' => 'active',
        ]);
        DB::table('sessions')->insert([
            'id' => 'admin-other-session-2', 'user_id' => $admin->id, 'ip_address' => '10.0.0.6',
            'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
        ]);

        $response = $this->actingAs($admin)->post('/admin/profile', [
            'name' => 'Admin Renamed',
            'email' => $admin->email,
        ]);

        $response->assertRedirect('/admin/profile');
        $this->assertDatabaseHas('sessions', ['id' => 'admin-other-session-2']);
    }
}
