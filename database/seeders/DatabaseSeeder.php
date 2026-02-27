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
        if (! User::where('email', 'admin@deped.gov.ph')->exists()) {
            User::create([
                'name' => 'TasDoneNa Admin',
                'email' => 'admin@deped.gov.ph',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]);
        }

        $this->call([
            PendingOfficersSeeder::class,
        ]);
    }
}
