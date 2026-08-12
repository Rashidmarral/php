<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\Feature;
use App\Support\Mailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $companyId = Auth::user()->company_id;

        return view('app.team.index', [
            'members' => User::where('company_id', $companyId)->orderBy('created_at')->get()->toArray(),
            'userLimit' => Feature::userLimit(),
            'withinUserLimit' => Feature::withinUserLimit(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        if (!Feature::withinUserLimit()) {
            return $this->redirectWithFlash('/app/billing', 'error', "Your plan's team member limit has been reached. Upgrade to invite more.");
        }

        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $role = (string) $request->input('role', 'estimator');
        if (!array_key_exists($role, User::ASSIGNABLE_ROLES)) {
            $role = 'estimator';
        }

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWithFlash('/app/team', 'error', 'A valid name and email are required.');
        }
        if (User::where('email', $email)->exists()) {
            return $this->redirectWithFlash('/app/team', 'error', 'A user with this email already exists.');
        }

        $tempPassword = bin2hex(random_bytes(4));
        $companyId = Auth::user()->company_id;
        User::create([
            'company_id' => $companyId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($tempPassword),
            'role' => $role,
            'status' => 'active',
        ]);

        if (Mailer::isConfigured()) {
            $company = Company::find($companyId);
            $result = Mailer::send(
                $email,
                $name,
                "You've been invited to {$company->name} on BuildXact Saudi",
                "Hi {$name},\n\nYou've been added to {$company->name}'s BuildXact Saudi account.\n\nLog in at " . rtrim((string) config('app.url'), '/') . "/login\nEmail: {$email}\nTemporary password: {$tempPassword}\n\nPlease change your password after logging in."
            );
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok']
                ? "Team member invited — an email with login details was sent to {$email}."
                : "Team member invited, but the invite email failed to send ({$result['error']}). Temporary password: {$tempPassword} (share this securely).");
        } else {
            $this->flash('success', "Team member invited. Temporary password: {$tempPassword} (share this securely).");
        }
        return redirect('/app/team');
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwned($id);

        if ($member->role === 'owner') {
            return $this->redirectWithFlash('/app/team', 'error', "The account owner's role cannot be changed.");
        }

        $role = (string) $request->input('role', 'estimator');
        if (!array_key_exists($role, User::ASSIGNABLE_ROLES)) {
            return $this->redirectWithFlash('/app/team', 'error', 'Invalid role.');
        }

        $member->update(['role' => $role]);
        $this->flash('success', 'Role updated for ' . $member->name . '.');
        return redirect('/app/team');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwned($id);
        if ($member->id === Auth::id()) {
            return $this->redirectWithFlash('/app/team', 'error', 'You cannot remove yourself.');
        }
        if ($member->role === 'owner') {
            return $this->redirectWithFlash('/app/team', 'error', 'The account owner cannot be removed.');
        }
        $member->delete();
        $this->flash('success', 'Team member removed.');
        return redirect('/app/team');
    }

    private function findOwned(int $id): User
    {
        $member = User::find($id);
        abort_if(!$member || $member->company_id !== Auth::user()->company_id, 404, 'Team member not found.');
        return $member;
    }
}
