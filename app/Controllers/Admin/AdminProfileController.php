<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

class AdminProfileController extends Controller
{
    public function index(): void
    {
        $this->view('admin/profile/index', ['pageTitle' => 'My Profile', 'admin' => Auth::user()], 'layouts/admin');
    }

    public function update(): void
    {
        $this->verifyCsrf();
        $admin = Auth::user();

        $name = trim((string) $this->input('name'));
        $email = strtolower(trim((string) $this->input('email')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'A valid name and email are required.');
            self::redirect('/admin/profile');
        }
        $existing = User::first('email', $email);
        if ($existing && (int) $existing['id'] !== (int) $admin['id']) {
            $this->flash('error', 'Another user already uses this email.');
            self::redirect('/admin/profile');
        }

        $data = ['name' => $name, 'email' => $email];

        $newPassword = (string) $this->input('new_password', '');
        if ($newPassword !== '') {
            $currentPassword = (string) $this->input('current_password', '');
            if (!password_verify($currentPassword, $admin['password_hash'])) {
                $this->flash('error', 'Current password is incorrect — password was not changed.');
                self::redirect('/admin/profile');
            }
            if (strlen($newPassword) < 8) {
                $this->flash('error', 'New password must be at least 8 characters — password was not changed.');
                self::redirect('/admin/profile');
            }
            if ($newPassword !== (string) $this->input('new_password_confirm', '')) {
                $this->flash('error', 'New password confirmation does not match — password was not changed.');
                self::redirect('/admin/profile');
            }
            $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        User::update((int) $admin['id'], $data);

        $this->flash('success', 'Profile updated.');
        self::redirect('/admin/profile');
    }
}
