<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@deped.gov.ph'],
            [
                'name' => 'TasDoneNa Admin',
                'password' => Hash::make('123456'),
                'role' => 'admin',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            PendingOfficersSeeder::class,
            PersonnelDirectorySeeder::class,
        ]);
    }
}
