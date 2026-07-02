<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Common User 1',
            'email' => 'common+1@example.com',
            'password' => '12345678',
            'type' => UserType::Common,
        ]);

        User::factory()->create([
            'name' => 'Common User 2',
            'email' => 'common+2@example.com',
            'password' => '12345678',
            'type' => UserType::Common,
        ]);

        User::factory()->create([
            'name' => 'Merchant User 1',
            'email' => 'merchant+1@example.com',
            'password' => '12345678',
            'type' => UserType::Merchant,
        ]);

        User::factory()->create([
            'name' => 'Merchant User 2',
            'email' => 'merchant+2@example.com',
            'password' => '12345678',
            'type' => UserType::Merchant,
        ]);
    }
}
