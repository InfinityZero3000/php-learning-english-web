<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Level;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Role::upsert([
            ['name' => 'Admin',   'slug' => 'admin'],
            ['name' => 'Learner', 'slug' => 'learner'],
        ], ['slug'], ['name']);

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && ! User::where('email', 'admin@example.com')->exists()) {
            User::create([
                'role_id' => $adminRole->id,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]);
        }

        $learnerRole = Role::where('slug', 'learner')->first();
        if ($learnerRole && ! User::where('email', 'user@example.com')->exists()) {
            User::create([
                'role_id' => $learnerRole->id,
                'name' => 'Standard Learner',
                'email' => 'user@example.com',
                'password' => Hash::make('user123'),
                'email_verified_at' => now(),
            ]);
        }
        Level::upsert([
            ['name' => 'Beginner',     'slug' => 'beginner',     'sort_order' => 1],
            ['name' => 'Intermediate', 'slug' => 'intermediate',  'sort_order' => 2],
            ['name' => 'Advanced',     'slug' => 'advanced',      'sort_order' => 3],
            ['name' => 'A1',           'slug' => 'a1',            'sort_order' => 4],
            ['name' => 'A2',           'slug' => 'a2',            'sort_order' => 5],
            ['name' => 'B1',           'slug' => 'b1',            'sort_order' => 6],
            ['name' => 'B2',           'slug' => 'b2',            'sort_order' => 7],
            ['name' => 'C1',           'slug' => 'c1',            'sort_order' => 8],
            ['name' => 'C2',           'slug' => 'c2',            'sort_order' => 9],
        ], ['slug'], ['name', 'sort_order']);

        Topic::upsert([
            ['name' => 'General',  'slug' => 'general'],
            ['name' => 'Animals',  'slug' => 'animals'],
            ['name' => 'Food',     'slug' => 'food'],
            ['name' => 'Travel',   'slug' => 'travel'],
            ['name' => 'Business', 'slug' => 'business'],
        ], ['slug'], ['name']);

        // Tạo khóa học mẫu nếu chưa có
        $beginner = Level::where('slug', 'beginner')->first();

        if (! Course::where('slug', 'tieng-anh-co-ban')->exists()) {
            Course::create([
                'level_id' => $beginner?->id,
                'title' => 'Tiếng Anh Cơ Bản',
                'slug' => 'tieng-anh-co-ban',
                'description' => 'Khóa học tiếng Anh dành cho người mới bắt đầu. Học từ vựng, ngữ pháp và hội thoại căn bản.',
                'status' => 'published',
            ]);
        }

        if (! Course::where('slug', 'tieng-anh-giao-tiep')->exists()) {
            Course::create([
                'level_id' => Level::where('slug', 'intermediate')->value('id'),
                'title' => 'Tiếng Anh Giao Tiếp',
                'slug' => 'tieng-anh-giao-tiep',
                'description' => 'Khóa học tập trung vào kỹ năng giao tiếp hằng ngày và môi trường làm việc.',
                'status' => 'published',
            ]);
        }

        $this->command->info('✅ CatalogSeeder: Roles, Levels, Topics và Courses đã được seed.');
    }
}
