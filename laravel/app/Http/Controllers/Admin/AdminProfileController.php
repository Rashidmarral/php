<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.profile.index', ['admin' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user();

        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWithFlash('/admin/profile', 'error', t('admin.profile.valid_name_email_required'));
        }
        $existing = User::where('email', $email)->first();
        if ($existing && $existing->id !== $admin->id) {
            return $this->redirectWithFlash('/admin/profile', 'error', t('admin.profile.email_taken'));
        }

        $data = ['name' => $name, 'email' => $email];

        $newPassword = (string) $request->input('new_password', '');
        if ($newPassword !== '') {
            $currentPassword = (string) $request->input('current_password', '');
            if (!Hash::check($currentPassword, $admin->password)) {
                return $this->redirectWithFlash('/admin/profile', 'error', t('admin.profile.current_password_incorrect'));
            }
            if (strlen($newPassword) < 8) {
                return $this->redirectWithFlash('/admin/profile', 'error', t('admin.profile.new_password_min_length'));
            }
            if ($newPassword !== (string) $request->input('new_password_confirm', '')) {
                return $this->redirectWithFlash('/admin/profile', 'error', t('admin.profile.new_password_mismatch'));
            }
            $data['password'] = $newPassword;
        }

        $admin->update($data);

        return $this->redirectWithFlash('/admin/profile', 'success', t('admin.profile.updated'));
    }
}
