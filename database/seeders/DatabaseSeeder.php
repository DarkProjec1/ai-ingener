<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'operator@example.com'],
            [
                'name' => 'Оператор',
                'password' => Hash::make('password'),
                'is_operator' => true,
            ]
        );
    }
}
