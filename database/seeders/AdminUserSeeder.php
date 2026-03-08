<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@urskool.test'],
            [
                'name' => 'Administrador UrSkool',
                'password' => Hash::make('Admin@12345'),
                'is_admin' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'student1@urskool.test'],
            [
                'name' => 'Maya Cossa',
                'password' => Hash::make('Student@123'),
                'is_admin' => false,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'student2@urskool.test'],
            [
                'name' => 'Noah Matola',
                'password' => Hash::make('Student@123'),
                'is_admin' => false,
            ],
        );
    }
}
