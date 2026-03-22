<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds tasks for the My Tasks component for a specific user (ID 36).
 * Run with: php artisan db:seed --class=MyTasksUser36Seeder
 */
class MyTasksUser36Seeder extends Seeder
{
    private const USER_ID = 36;

    public function run(): void
    {
        $user = User::find(self::USER_ID);

        if (!$user) {
            $this->command->warn('MyTasksUser36Seeder: User ID ' . self::USER_ID . ' not found.');
            return;
        }

        $existingCount = Task::where('created_by', self::USER_ID)->count();
        if ($existingCount >= 10) {
            $this->command->info('MyTasksUser36Seeder: User ' . self::USER_ID . ' already has ' . $existingCount . ' tasks. Skipping to avoid duplicates.');
            return;
        }

        foreach ($this->myTasksData() as $data) {
            Task::create($data);
        }

        $this->command->info('MyTasksUser36Seeder: Seeded tasks for user ID ' . self::USER_ID . '.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function myTasksData(): array
    {
        $now = now();
        $base = [
            'created_by' => self::USER_ID,
            'assigned_to' => self::USER_ID,
        ];

        return [
            array_merge($base, [
                'title' => 'Prepare quarterly lesson plans for Grade 7 Mathematics',
                'description' => 'Develop comprehensive lesson plans covering algebra and geometry for Q2. Include formative assessments and differentiation strategies.',
                'mfo' => 'MFO 1: Teaching-Learning Process',
                'kra' => 'KRA 1: Content Knowledge and Pedagogy',
                'kra_weight' => 25.00,
                'objective' => 'Ensure alignment with DepEd curriculum and learning competencies.',
                'movs' => ['Lesson plan documents', 'Sample worksheets', 'Assessment rubrics'],
                'due_date' => $now->copy()->addDays(14)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(10)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high',
                'timeline_start' => $now->copy()->subDays(7)->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(14)->format('Y-m-d'),
                'performance_criteria' => ['Submitted on time', 'Reviewed by head teacher'],
            ]),
            array_merge($base, [
                'title' => 'Conduct parent-teacher conference for Section A',
                'description' => 'Schedule and facilitate PTC for all 45 students. Document concerns and follow-up actions.',
                'mfo' => 'MFO 2: Learner Development',
                'kra' => 'KRA 2: Learning Environment',
                'kra_weight' => 20.00,
                'objective' => 'Strengthen home-school partnership and address learner needs.',
                'movs' => ['Attendance sheet', 'Minutes of meeting', 'Action plan'],
                'due_date' => $now->copy()->addDays(7)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(5)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high',
                'timeline_start' => $now->copy()->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(7)->format('Y-m-d'),
                'performance_criteria' => ['At least 80% attendance', 'Documented minutes'],
            ]),
            array_merge($base, [
                'title' => 'Submit IPCRF mid-year review documents',
                'description' => 'Complete self-assessment and gather MOVs for IPCRF review. Coordinate with rater for schedule.',
                'mfo' => 'MFO 5: Plus Factor',
                'kra' => 'KRA 5: Professional Growth',
                'kra_weight' => 15.00,
                'objective' => 'Demonstrate progress on professional development targets.',
                'movs' => ['Self-assessment form', 'MOV portfolio', 'Reflection journal'],
                'due_date' => $now->copy()->addDays(21)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(18)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'medium',
                'timeline_start' => $now->copy()->subDays(30)->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(21)->format('Y-m-d'),
                'performance_criteria' => ['Rater approval', 'Complete documentation'],
            ]),
            array_merge($base, [
                'title' => 'Attend LAC session on differentiated instruction',
                'description' => 'Participate in school LAC and submit reflection paper. Apply strategies in classroom.',
                'mfo' => 'MFO 4: Professional Development',
                'kra' => 'KRA 4: Curriculum and Planning',
                'kra_weight' => 20.00,
                'objective' => 'Enhance teaching strategies through collaborative learning.',
                'movs' => ['Attendance certificate', 'Reflection paper', 'Lesson implementation log'],
                'due_date' => $now->copy()->subDays(3)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->subDays(5)->format('Y-m-d'),
                'status' => 'completed',
                'priority' => 'medium',
                'timeline_start' => $now->copy()->subDays(14)->format('Y-m-d'),
                'timeline_end' => $now->copy()->subDays(3)->format('Y-m-d'),
                'performance_criteria' => ['100% attendance', 'Reflection submitted'],
            ]),
            array_merge($base, [
                'title' => 'Organize classroom reading corner',
                'description' => 'Set up and maintain a reading corner with age-appropriate materials. Track student engagement.',
                'mfo' => 'MFO 2: Learner Development',
                'kra' => 'KRA 2: Learning Environment',
                'kra_weight' => 20.00,
                'objective' => 'Promote love of reading and independent learning.',
                'movs' => ['Photos of reading corner', 'Borrowing log', 'Student feedback'],
                'due_date' => $now->copy()->addDays(5)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(3)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'low',
                'timeline_start' => $now->copy()->subDays(2)->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(5)->format('Y-m-d'),
                'performance_criteria' => ['Functional reading area', 'At least 20 books'],
            ]),
            array_merge($base, [
                'title' => 'Prepare and administer periodical test for Mathematics',
                'description' => 'Create test items aligned with MELCs. Administer, check, and encode results in E-Class Record.',
                'mfo' => 'MFO 1: Teaching-Learning Process',
                'kra' => 'KRA 1: Content Knowledge and Pedagogy',
                'kra_weight' => 25.00,
                'objective' => 'Assess learner mastery of Q1 competencies.',
                'movs' => ['Test questionnaire', 'Answer key', 'E-Class Record screenshot'],
                'due_date' => $now->copy()->addDays(10)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(8)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high',
                'timeline_start' => $now->copy()->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(10)->format('Y-m-d'),
                'performance_criteria' => ['Table of specifications', 'Item analysis'],
            ]),
            array_merge($base, [
                'title' => 'Coordinate with barangay for Brigada Eskwela',
                'description' => 'Liaise with barangay officials for volunteer support and materials. Document participation.',
                'mfo' => 'MFO 3: School and Community',
                'kra' => 'KRA 3: Diversity of Learners',
                'kra_weight' => 15.00,
                'objective' => 'Strengthen community involvement in school activities.',
                'movs' => ['Memorandum of agreement', 'Attendance list', 'Photos'],
                'due_date' => $now->copy()->subDays(15)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->subDays(18)->format('Y-m-d'),
                'status' => 'completed',
                'priority' => 'medium',
                'timeline_start' => $now->copy()->subDays(30)->format('Y-m-d'),
                'timeline_end' => $now->copy()->subDays(15)->format('Y-m-d'),
                'performance_criteria' => ['MOA signed', 'Activity report'],
            ]),
            array_merge($base, [
                'title' => 'Update DLL for Week 8–10',
                'description' => 'Complete daily lesson logs with annotations and reflections. Submit to head teacher for checking.',
                'mfo' => 'MFO 1: Teaching-Learning Process',
                'kra' => 'KRA 1: Content Knowledge and Pedagogy',
                'kra_weight' => 25.00,
                'objective' => 'Maintain accurate teaching records.',
                'movs' => ['DLL soft copy', 'Printed DLL with signature'],
                'due_date' => $now->copy()->addDays(3)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(2)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high',
                'timeline_start' => $now->copy()->subDays(7)->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(3)->format('Y-m-d'),
                'performance_criteria' => ['All days covered', 'Head teacher approved'],
            ]),
            array_merge($base, [
                'title' => 'Facilitate remedial classes for struggling learners',
                'description' => 'Conduct after-class remediation for 12 identified learners. Document progress.',
                'mfo' => 'MFO 2: Learner Development',
                'kra' => 'KRA 2: Learning Environment',
                'kra_weight' => 20.00,
                'objective' => 'Improve numeracy and literacy of at-risk learners.',
                'movs' => ['Remediation plan', 'Attendance records', 'Pre/post assessment'],
                'due_date' => $now->copy()->addDays(30)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(28)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'medium',
                'timeline_start' => $now->copy()->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(30)->format('Y-m-d'),
                'performance_criteria' => ['At least 8 sessions', 'Progress documented'],
            ]),
            array_merge($base, [
                'title' => 'Submit accomplishment report for October',
                'description' => 'Compile monthly accomplishments with MOVs. Submit to school head.',
                'mfo' => 'MFO 5: Plus Factor',
                'kra' => 'KRA 5: Professional Growth',
                'kra_weight' => 15.00,
                'objective' => 'Document professional activities and outputs.',
                'movs' => ['Accomplishment form', 'Supporting documents'],
                'due_date' => $now->copy()->subDays(8)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->subDays(10)->format('Y-m-d'),
                'status' => 'completed',
                'priority' => 'medium',
                'timeline_start' => $now->copy()->subDays(31)->format('Y-m-d'),
                'timeline_end' => $now->copy()->subDays(8)->format('Y-m-d'),
                'performance_criteria' => ['Signed by school head', 'Complete MOVs'],
            ]),
            array_merge($base, [
                'title' => 'Prepare science experiment materials for Grade 6',
                'description' => 'Gather and organize materials for the "Properties of Matter" experiment. Conduct safety briefing.',
                'mfo' => 'MFO 1: Teaching-Learning Process',
                'kra' => 'KRA 1: Content Knowledge and Pedagogy',
                'kra_weight' => 25.00,
                'objective' => 'Ensure hands-on learning experience for students.',
                'movs' => ['Materials checklist', 'Safety guidelines', 'Experiment worksheet'],
                'due_date' => $now->copy()->addDays(1)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high',
                'timeline_start' => $now->copy()->subDays(2)->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(1)->format('Y-m-d'),
                'performance_criteria' => ['All materials ready', 'Safety briefing done'],
            ]),
            array_merge($base, [
                'title' => 'Attend division training on ICT integration',
                'description' => 'Two-day training on using digital tools in instruction. Submit certificate and implementation plan.',
                'mfo' => 'MFO 4: Professional Development',
                'kra' => 'KRA 4: Curriculum and Planning',
                'kra_weight' => 20.00,
                'objective' => 'Integrate technology in teaching and learning.',
                'movs' => ['Certificate of attendance', 'Implementation plan', 'Sample digital lesson'],
                'due_date' => $now->copy()->addDays(28)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(25)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'medium',
                'timeline_start' => $now->copy()->addDays(14)->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(28)->format('Y-m-d'),
                'performance_criteria' => ['100% attendance', 'Plan approved'],
            ]),
            array_merge($base, [
                'title' => 'Short task with minimal details',
                'description' => null,
                'mfo' => null,
                'kra' => 'KRA 5: Professional Growth',
                'kra_weight' => 10.00,
                'objective' => null,
                'movs' => null,
                'due_date' => $now->copy()->addDays(60)->format('Y-m-d'),
                'cutoff_date' => null,
                'status' => 'pending',
                'priority' => 'low',
                'timeline_start' => null,
                'timeline_end' => null,
                'performance_criteria' => null,
            ]),
            array_merge($base, [
                'title' => 'Very long task title to test responsive layout and text truncation in the table cells across different screen sizes',
                'description' => 'This task has an intentionally long title to verify that the TaskList component handles overflow correctly on mobile, tablet, and desktop views.',
                'mfo' => 'MFO 1: Teaching-Learning Process',
                'kra' => 'KRA 1: Content Knowledge and Pedagogy',
                'kra_weight' => 25.00,
                'objective' => 'Test UI responsiveness with edge-case content.',
                'movs' => ['Document 1', 'Document 2'],
                'due_date' => $now->copy()->addDays(7)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(5)->format('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high',
                'timeline_start' => $now->copy()->format('Y-m-d'),
                'timeline_end' => $now->copy()->addDays(7)->format('Y-m-d'),
                'performance_criteria' => ['Layout verified', 'No overflow'],
            ]),
            array_merge($base, [
                'title' => 'Campus journalism workshop coordination',
                'description' => 'Coordinate with division office for campus journalism workshop. Prepare list of student participants.',
                'mfo' => 'MFO 3: School and Community',
                'kra' => 'KRA 3: Diversity of Learners',
                'kra_weight' => 15.00,
                'objective' => 'Develop student writing and journalism skills.',
                'movs' => ['Participant list', 'Parent consent forms', 'Workshop photos'],
                'due_date' => $now->copy()->addDays(14)->format('Y-m-d'),
                'cutoff_date' => $now->copy()->addDays(12)->format('Y-m-d'),
                'status' => 'completed',
                'priority' => 'low',
                'timeline_start' => $now->copy()->subDays(21)->format('Y-m-d'),
                'timeline_end' => $now->copy()->subDays(7)->format('Y-m-d'),
                'performance_criteria' => ['Workshop conducted', 'Outputs submitted'],
            ]),
        ];
    }
}
