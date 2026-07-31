<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'slug');

        User::firstOrCreate(['email' => 'learner@example.com'], [
            'name' => 'Test Learner',
            'password' => Hash::make('password123'),
            'role_id' => $roles['learner'] ?? null,
            'email_verified_at' => now(),
        ]);

        User::firstOrCreate(['email' => 'teacher@example.com'], [
            'name' => 'Test Teacher',
            'password' => Hash::make('password123'),
            'role_id' => $roles['teacher'] ?? null,
            'email_verified_at' => now(),
        ]);

        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Test Admin',
            'password' => Hash::make('password123'),
            'role_id' => $roles['admin'] ?? null,
            'email_verified_at' => now(),
            'google_id' => 'admin-google-id',
        ]);

        User::firstOrCreate(['email' => 'superadmin@example.com'], [
            'name' => 'Test Super Admin',
            'password' => Hash::make('password123'),
            'role_id' => $roles['super_admin'] ?? null,
            'email_verified_at' => now(),
            'google_id' => 'superadmin-google-id',
        ]);

        $this->command->info('✅ TestUserSeeder: Đã tạo thành công 4 tài khoản mẫu (password: password123).');
    }
}
