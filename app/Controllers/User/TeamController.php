<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Core\Mailer;
use App\Models\Company;
use App\Models\User;

class TeamController extends Controller
{
    public function index(): void
    {
        $members = User::where('company_id', Auth::companyId(), 'created_at ASC');
        $this->view('user/team/index', [
            'pageTitle' => 'Team',
            'members' => $members,
            'userLimit' => Feature::userLimit(),
            'withinUserLimit' => Feature::withinUserLimit(),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can invite team members.');
            self::redirect('/app/team');
        }
        if (!Feature::withinUserLimit()) {
            $this->flash('error', 'Your plan\'s team member limit has been reached. Upgrade to invite more.');
            self::redirect('/app/billing');
        }

        $name = trim((string) $this->input('name'));
        $email = strtolower(trim((string) $this->input('email')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'A valid name and email are required.');
            self::redirect('/app/team');
        }
        if (User::first('email', $email)) {
            $this->flash('error', 'A user with this email already exists.');
            self::redirect('/app/team');
        }

        $tempPassword = bin2hex(random_bytes(4));
        User::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
            'role' => $this->input('role', 'staff'),
            'status' => 'active',
        ]);

        if (Mailer::isConfigured()) {
            $company = Company::find(Auth::companyId());
            $result = Mailer::send(
                $email,
                $name,
                "You've been invited to {$company['name']} on BuildXact Saudi",
                "Hi {$name},\n\nYou've been added to {$company['name']}'s BuildXact Saudi account.\n\nLog in at " . rtrim(\App\Core\Env::get('APP_URL', ''), '/') . "/login\nEmail: {$email}\nTemporary password: {$tempPassword}\n\nPlease change your password after logging in."
            );
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok']
                ? "Team member invited — an email with login details was sent to {$email}."
                : "Team member invited, but the invite email failed to send ({$result['error']}). Temporary password: {$tempPassword} (share this securely)."
            );
        } else {
            $this->flash('success', "Team member invited. Temporary password: {$tempPassword} (share this securely).");
        }
        self::redirect('/app/team');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $member = User::find((int) $id);
        if (!$member || (int) $member['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Team member not found.');
        }
        if ((int) $member['id'] === (int) Auth::user()['id']) {
            $this->flash('error', 'You cannot remove yourself.');
            self::redirect('/app/team');
        }
        User::delete($member['id']);
        $this->flash('success', 'Team member removed.');
        self::redirect('/app/team');
    }
}
