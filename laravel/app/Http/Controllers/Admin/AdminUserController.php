<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $admins = User::whereIn('role', ['super_admin', 'support_admin'])->orderBy('created_at')->get();
        return view('admin.admins.index', ['admins' => $admins]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $role = $request->input('role') === 'support_admin' ? 'support_admin' : 'super_admin';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return $this->redirectWithFlash('/admin/admins', 'error', t('admin.admin_users.valid_fields_required'));
        }
        if (User::where('email', $email)->exists()) {
            return $this->redirectWithFlash('/admin/admins', 'error', t('admin.admin_users.email_exists'));
        }

        User::create([
            'company_id' => null,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'status' => 'active',
        ]);
        AuditLog::record($request->user(), 'admin_user_create', 'user', null, "{$name} ({$email}) as {$role}");

        return $this->redirectWithFlash('/admin/admins', 'success', t('admin.admin_users.created'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $admin = User::find($id);
        if (!$admin || !in_array($admin->role, ['super_admin', 'support_admin'], true)) {
            abort(404, 'Admin user not found.');
        }
        if ($admin->id === $request->user()->id) {
            return $this->redirectWithFlash('/admin/admins', 'error', t('admin.admin_users.cannot_remove_self'));
        }
        if ($admin->role === 'super_admin' && User::where('role', 'super_admin')->count() <= 1) {
            return $this->redirectWithFlash('/admin/admins', 'error', t('admin.admin_users.last_super_admin'));
        }
        $admin->delete();
        AuditLog::record($request->user(), 'admin_user_delete', 'user', $id, "{$admin->name} ({$admin->email})");

        return $this->redirectWithFlash('/admin/admins', 'success', t('admin.admin_users.removed'));
    }
}
