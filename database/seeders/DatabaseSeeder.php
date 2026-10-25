<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Iwan',
            'email' => 'rachmanj@gmail.com',
            'password' => Hash::make('rahasia123'),
            'is_admin' => true,
        ]);
    }
}
