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
            return $this->redirectWithFlash('/admin/profile', 'error', 'A valid name and email are required.');
        }
        $existing = User::where('email', $email)->first();
        if ($existing && $existing->id !== $admin->id) {
            return $this->redirectWithFlash('/admin/profile', 'error', 'Another user already uses this email.');
        }

        $data = ['name' => $name, 'email' => $email];

        $newPassword = (string) $request->input('new_password', '');
        if ($newPassword !== '') {
            $currentPassword = (string) $request->input('current_password', '');
            if (!Hash::check($currentPassword, $admin->password)) {
                return $this->redirectWithFlash('/admin/profile', 'error', 'Current password is incorrect — password was not changed.');
            }
            if (strlen($newPassword) < 8) {
                return $this->redirectWithFlash('/admin/profile', 'error', 'New password must be at least 8 characters — password was not changed.');
            }
            if ($newPassword !== (string) $request->input('new_password_confirm', '')) {
                return $this->redirectWithFlash('/admin/profile', 'error', 'New password confirmation does not match — password was not changed.');
            }
            $data['password'] = $newPassword;
        }

        $admin->update($data);

        return $this->redirectWithFlash('/admin/profile', 'success', 'Profile updated.');
    }
}
