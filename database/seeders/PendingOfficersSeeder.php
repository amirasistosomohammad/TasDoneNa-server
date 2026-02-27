<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds pending personnel for testing the Account Approvals component
 * (pagination, view details, approve/reject UX).
 */
class PendingOfficersSeeder extends Seeder
{
    public function run(): void
    {
        $pending = $this->pendingOfficers();

        foreach ($pending as $i => $data) {
            $email = $data['email'];
            if (User::where('email', $email)->exists()) {
                continue;
            }

            User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => 'officer',
                'status' => 'pending',
                'email_verified_at' => now(),
                'employee_id' => $data['employee_id'] ?? null,
                'position' => $data['position'] ?? null,
                'division' => $data['division'] ?? null,
                'school_name' => $data['school_name'] ?? null,
                'created_at' => $data['created_at'] ?? now()->subDays($i),
            ]);
        }
    }

    /**
     * @return array<int, array{name: string, email: string, employee_id?: string, position?: string, division?: string, school_name?: string, created_at?: \Carbon\Carbon}>
     */
    private function pendingOfficers(): array
    {
        $schools = [
            'Manila Elementary School',
            'Quezon City High School',
            'Cebu National High School',
            'Davao Central Elementary',
            'Iloilo Science High School',
            'Baguio City School',
            'Zamboanga Integrated School',
        ];

        $positions = [
            'Teacher I',
            'Teacher II',
            'Teacher III',
            'Head Teacher I',
            'Head Teacher II',
            'School Principal I',
            'Education Program Specialist',
        ];

        $divisions = [
            'Division of Manila',
            'Division of Quezon City',
            'Division of Cebu City',
            'Division of Davao City',
            'Division of Iloilo',
            'Division of Baguio',
            'Division of Zamboanga',
        ];

        $names = [
            'Maria Santos',
            'Juan Dela Cruz',
            'Ana Reyes',
            'Pedro Garcia',
            'Carmen Lopez',
            'Roberto Mendoza',
            'Elena Torres',
            'Miguel Fernandez',
            'Rosa Bautista',
            'Antonio Ramos',
            'Lourdes Cruz',
            'Jose Villanueva',
            'Teresa Gonzales',
            'Francisco Reyes',
            'Sofia Aquino',
            'Carlos Santiago',
            'Amira Mohammad',
            'Ramon Castillo',
            'Patricia Morales',
            'Fernando Ortiz',
            'Lucia Herrera',
            'Ricardo Jimenez',
        ];

        $officers = [];
        foreach ($names as $i => $name) {
            $idx = $i + 1;
            $officers[] = [
                'name' => strtoupper($name),
                'email' => "pending.personnel.{$idx}@tasdonena.test",
                'employee_id' => (string) (1000 + $idx),
                'position' => $positions[$i % count($positions)],
                'division' => $divisions[$i % count($divisions)],
                'school_name' => $schools[$i % count($schools)],
                'created_at' => now()->subDays(count($names) - $idx),
            ];
        }

        return $officers;
    }
}
