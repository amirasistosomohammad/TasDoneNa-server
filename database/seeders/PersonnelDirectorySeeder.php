<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds officers for the Personnel Directory tab component.
 * Provides Active, Deactivated, and Rejected personnel to test the structure.
 */
class PersonnelDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $personnel = $this->personnelDirectoryData();

        foreach ($personnel as $data) {
            $email = $data['email'];
            
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => 'officer',
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                    'email_verified_at' => now(),
                    'employee_id' => $data['employee_id'] ?? null,
                    'position' => $data['position'] ?? null,
                    'division' => $data['division'] ?? null,
                    'school_name' => $data['school_name'] ?? null,
                    'avatar_url' => $data['avatar_url'] ?? null,
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                    'deactivation_reason' => $data['deactivation_reason'] ?? null,
                    'created_at' => $data['created_at'] ?? now(),
                ]
            );

            // Set status dates based on status (only if not already set)
            if ($data['status'] === 'approved') {
                if (!$user->approved_at) {
                    $user->update([
                        'approved_at' => now()->subDays(rand(5, 90)),
                        'approval_remarks' => 'Account approved during initial setup.',
                    ]);
                }
                if (!$data['is_active'] && !$user->deactivated_at) {
                    $user->update([
                        'deactivated_at' => now()->subDays(rand(1, 30)),
                    ]);
                }
            } elseif ($data['status'] === 'rejected') {
                if (!$user->rejected_at) {
                    $user->update([
                        'rejected_at' => now()->subDays(rand(3, 60)),
                    ]);
                }
            }
        }
    }

    /**
     * @return array<int, array{name: string, email: string, status: string, is_active?: bool, employee_id?: string, position?: string, division?: string, school_name?: string, avatar_url?: string, rejection_reason?: string, deactivation_reason?: string, created_at?: \Carbon\Carbon}>
     */
    private function personnelDirectoryData(): array
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

        return [
            // Active personnel (status=approved, is_active=true)
            [
                'name' => 'ALICIA BERNARDO',
                'email' => 'personnel.active.1@tasdonena.test',
                'status' => 'approved',
                'is_active' => true,
                'employee_id' => '2001',
                'position' => 'Teacher I',
                'division' => 'Division of Manila',
                'school_name' => 'Manila Elementary School',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2001',
            ],
            [
                'name' => 'BENJAMIN CRUZ',
                'email' => 'personnel.active.2@tasdonena.test',
                'status' => 'approved',
                'is_active' => true,
                'employee_id' => '2002',
                'position' => 'Teacher II',
                'division' => 'Division of Quezon City',
                'school_name' => 'Quezon City High School',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2002',
            ],
            [
                'name' => 'CLARISSA DOMINGO',
                'email' => 'personnel.active.3@tasdonena.test',
                'status' => 'approved',
                'is_active' => true,
                'employee_id' => '2003',
                'position' => 'Head Teacher I',
                'division' => 'Division of Cebu City',
                'school_name' => 'Cebu National High School',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2003',
            ],
            [
                'name' => 'DANIEL ESPINO',
                'email' => 'personnel.active.4@tasdonena.test',
                'status' => 'approved',
                'is_active' => true,
                'employee_id' => '2004',
                'position' => 'School Principal I',
                'division' => 'Division of Davao City',
                'school_name' => 'Davao Central Elementary',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2004',
            ],
            [
                'name' => 'ELENA FLORES',
                'email' => 'personnel.active.5@tasdonena.test',
                'status' => 'approved',
                'is_active' => true,
                'employee_id' => '2005',
                'position' => 'Education Program Specialist',
                'division' => 'Division of Iloilo',
                'school_name' => 'Iloilo Science High School',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2005',
            ],
            [
                'name' => 'FERNANDO GARCIA',
                'email' => 'personnel.active.6@tasdonena.test',
                'status' => 'approved',
                'is_active' => true,
                'employee_id' => '2006',
                'position' => 'Teacher III',
                'division' => 'Division of Baguio',
                'school_name' => 'Baguio City School',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2006',
            ],
            // Deactivated personnel (status=approved, is_active=false)
            [
                'name' => 'GABRIELA HERRERA',
                'email' => 'personnel.deactivated.1@tasdonena.test',
                'status' => 'approved',
                'is_active' => false,
                'employee_id' => '2011',
                'position' => 'Teacher II',
                'division' => 'Division of Zamboanga',
                'school_name' => 'Zamboanga Integrated School',
                'deactivation_reason' => 'Maternity leave – extended absence.',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2011',
            ],
            [
                'name' => 'HECTOR IBANEZ',
                'email' => 'personnel.deactivated.2@tasdonena.test',
                'status' => 'approved',
                'is_active' => false,
                'employee_id' => '2012',
                'position' => 'Head Teacher II',
                'division' => 'Division of Manila',
                'school_name' => 'Manila Elementary School',
                'deactivation_reason' => 'Transfer to another division.',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2012',
            ],
            [
                'name' => 'ISABEL JIMENEZ',
                'email' => 'personnel.deactivated.3@tasdonena.test',
                'status' => 'approved',
                'is_active' => false,
                'employee_id' => '2013',
                'position' => 'Teacher I',
                'division' => 'Division of Quezon City',
                'school_name' => 'Quezon City High School',
                'deactivation_reason' => 'Resigned – left DepEd for private sector.',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2013',
            ],
            // Rejected personnel (status=rejected)
            [
                'name' => 'JORGE KALAW',
                'email' => 'personnel.rejected.1@tasdonena.test',
                'status' => 'rejected',
                'is_active' => false,
                'employee_id' => '2021',
                'position' => 'Teacher I',
                'division' => 'Division of Cebu City',
                'school_name' => 'Cebu National High School',
                'rejection_reason' => 'Invalid or duplicate employee ID.',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2021',
            ],
            [
                'name' => 'KARINA LOPEZ',
                'email' => 'personnel.rejected.2@tasdonena.test',
                'status' => 'rejected',
                'is_active' => false,
                'employee_id' => '2022',
                'position' => 'Teacher II',
                'division' => 'Division of Davao City',
                'school_name' => 'Davao Central Elementary',
                'rejection_reason' => 'Incomplete supporting documents.',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2022',
            ],
            [
                'name' => 'LUIS MARTINEZ',
                'email' => 'personnel.rejected.3@tasdonena.test',
                'status' => 'rejected',
                'is_active' => false,
                'employee_id' => '2023',
                'position' => 'Education Program Specialist',
                'division' => 'Division of Iloilo',
                'school_name' => 'Iloilo Science High School',
                'rejection_reason' => 'Application submitted under wrong division.',
                'avatar_url' => 'https://i.pravatar.cc/400?u=2023',
            ],
        ];
    }
}
