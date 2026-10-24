<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('services.filament_admin.email');
        $password = config('services.filament_admin.password');

        if (blank($email) || blank($password)) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Abelikov',
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );
    }
}
