<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Permissions + roles first — users created below may be assigned roles.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Promote the seed user to admin so the management UI is reachable
        // immediately after `migrate:fresh --seed`. Remove this block if you
        // want a clean slate with no admin.
        User::firstWhere('email', 'test@example.com')?->assignRole('admin');
    }
}
