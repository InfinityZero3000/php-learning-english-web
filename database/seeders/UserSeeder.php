<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $learnerRole = Role::where('slug', 'learner')->first();

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'learner@example.com'],
            [
                'name' => 'Learner User',
                'password' => Hash::make('password'),
                'role_id' => $learnerRole?->id,
                'email_verified_at' => now(),
            ]
        );

        // Tạo thêm 5 learner dùng factory nếu có
        if (method_exists(User::class, 'factory')) {
            User::factory()->count(5)->create([
                'role_id' => $learnerRole?->id,
            ]);
        }
    }
}