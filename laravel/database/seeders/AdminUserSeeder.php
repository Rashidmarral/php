<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@buildxact-saudi.local'],
            [
                'company_id' => null,
                'name' => 'Platform Admin',
                'password' => 'Admin@12345',
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        $this->command?->info('Super admin seeded: admin@buildxact-saudi.local / Admin@12345');
    }
}
