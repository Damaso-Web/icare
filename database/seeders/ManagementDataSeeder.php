<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Program;
use App\Models\Department;
use App\Models\ReferralFormOption;
use Illuminate\Database\Seeder;

class ManagementDataSeeder extends Seeder
{
    public function run(): void
    {
        $colleges = [
            'College of Agriculture (CA)'                              => 'CA',
            'College of Arts and Humanities (CAH)'                     => 'CAH',
            'College of Engineering (CE)'                              => 'CE',
            'College of Forestry (CF)'                                 => 'CF',
            'College of Human Ecology (CHE)'                           => 'CHE',
            'College of Human Kinetics (CHK)'                          => 'CHK',
            'College of Information Sciences (CIS)'                    => 'CIS',
            'College of Medicine (CM)'                                 => 'CM',
            'College of Natural Sciences (CNS)'                        => 'CNS',
            'College of Numeracy and Applied Sciences (CNAS)'          => 'CNAS',
            'College of Nursing (CN)'                                  => 'CN',
            'College of Public Administration and Governance (CPAG)'   => 'CPAG',
            'College of Social Sciences (CSS)'                         => 'CSS',
            'College of Teacher Education (CTE)'                       => 'CTE',
            'College of Veterinary Medicine (CVM)'                     => 'CVM',
        ];

        $collegeIds = [];
        foreach ($colleges as $name => $abbrev) {
            $collegeIds[$name] = College::firstOrCreate(['name' => $name], ['abbrev' => $abbrev])->id;
        }

        $programs = [
            'College of Agriculture (CA)' => [
                'Bachelor of Science in Agriculture',
                'Bachelor of Science in Agribusiness',
            ],
            'College of Arts and Humanities (CAH)' => [
                'Bachelor of Arts in English Language',
                'Bachelor of Arts in Communication',
                'Bachelor of Arts in Filipino Language',
            ],
            'College of Engineering (CE)' => [
                'Bachelor of Science in Civil Engineering',
                'Bachelor of Science in Electrical Engineering',
                'Bachelor of Science in Agricultural and Biosystems Engineering',
                'Bachelor of Science in Industrial Engineering',
            ],
            'College of Forestry (CF)' => [
                'Bachelor of Science in Forestry',
            ],
            'College of Human Ecology (CHE)' => [
                'Bachelor of Science in Hospitality Management',
                'Bachelor of Science in Food Technology',
                'Bachelor of Science in Nutrition and Dietetics',
                'Bachelor of Science in Entrepreneurship',
                'Bachelor of Science in Tourism Management',
            ],
            'College of Human Kinetics (CHK)' => [
                'Bachelor of Physical Education',
                'Bachelor of Science in Exercise and Sports Sciences',
            ],
            'College of Information Sciences (CIS)' => [
                'Bachelor of Science in Information Technology',
                'Bachelor of Library and Information Science',
                'Bachelor of Science in Development Communication',
            ],
            'College of Medicine (CM)' => [
                'Doctor of Medicine',
            ],
            'College of Natural Sciences (CNS)' => [
                'Bachelor of Science in Biology',
                'Bachelor of Science in Chemistry',
                'Bachelor of Science in Environmental Science',
            ],
            'College of Numeracy and Applied Sciences (CNAS)' => [
                'Bachelor of Science in Mathematics',
                'Bachelor of Science in Statistics',
            ],
            'College of Nursing (CN)' => [
                'Bachelor of Science in Nursing',
            ],
            'College of Public Administration and Governance (CPAG)' => [
                'Bachelor of Public Administration',
            ],
            'College of Social Sciences (CSS)' => [
                'Bachelor of Arts in Psychology',
                'Bachelor of Arts in History',
            ],
            'College of Teacher Education (CTE)' => [
                'Bachelor of Early Childhood Education',
                'Bachelor of Elementary Education',
                'Bachelor of Secondary Education',
                'Bachelor of Technology and Livelihood Education',
            ],
            'College of Veterinary Medicine (CVM)' => [
                'Doctor of Veterinary Medicine',
            ],
        ];

        foreach ($programs as $collegeName => $names) {
            foreach ($names as $name) {
                Program::firstOrCreate(['college_id' => $collegeIds[$collegeName], 'name' => $name]);
            }
        }

        // NOTE: source departments.js keyed Nursing as "College of Nursing (CON)",
        // which never matched colleges.js's "College of Nursing (CN)" — the Nursing
        // department dropdown has been silently empty. Fixed here.
        $departments = [
            'College of Agriculture (CA)' => ['Agronomy', 'Animal Science', 'Agricultural Engineering'],
            'College of Arts and Humanities (CAH)' => ['English', 'Filipino', 'Communication', 'History'],
            'College of Engineering (CE)' => ['Civil Engineering', 'Electrical Engineering', 'Mechanical Engineering'],
            'College of Forestry (CF)' => ['Forest Science', 'Environmental Science'],
            'College of Human Ecology (CHE)' => ['Hospitality Management', 'Food Technology'],
            'College of Human Kinetics (CHK)' => ['Physical Education', 'Sports Science'],
            'College of Information Sciences (CIS)' => ['Information Technology', 'Computer Science'],
            'College of Medicine (CM)' => ['Doctor of Medicine'],
            'College of Natural Sciences (CNS)' => ['Biology', 'Chemistry', 'Physics'],
            'College of Numeracy and Applied Sciences (CNAS)' => ['Mathematics', 'Statistics'],
            'College of Nursing (CN)' => ['Nursing'],
            'College of Public Administration and Governance (CPAG)' => ['Public Administration', 'Political Science'],
            'College of Social Sciences (CSS)' => ['Psychology', 'Sociology'],
            'College of Teacher Education (CTE)' => ['Elementary Education', 'Secondary Education'],
            'College of Veterinary Medicine (CVM)' => ['Veterinary Medicine'],
        ];

        foreach ($departments as $collegeName => $names) {
            foreach ($names as $name) {
                Department::firstOrCreate(['college_id' => $collegeIds[$collegeName], 'name' => $name]);
            }
        }

        $referralTypes = [
            'class_attendance'      => 'Class Attendance (Absences/Tardiness)',
            'counseling'            => 'Counseling',
            'academic_deficiency'   => 'Academic Deficiency',
            'leave_of_absence'      => 'Leave of Absence',
            'withdrawal'            => 'Withdrawal',
            'readmission'           => 'Readmission',
            'shifting'              => 'Shifting',
            'psychological_testing' => 'Psychological Testing',
            'disciplinary'          => 'Acts of Misconduct',
        ];
        $i = 0;
        foreach ($referralTypes as $value => $label) {
            ReferralFormOption::firstOrCreate(
                ['category' => 'referral_type', 'value' => $value],
                ['label' => $label, 'sort_order' => $i++]
            );
        }

        $referralSources = [
            'faculty' => 'Faculty Referral',
            'sdu'     => 'SDU Referral',
            'self'    => 'Self-Referral',
            'dean'    => "Dean's Office",
            'parent'  => 'Parent / Guardian',
        ];
        $i = 0;
        foreach ($referralSources as $value => $label) {
            ReferralFormOption::firstOrCreate(
                ['category' => 'referral_source', 'value' => $value],
                ['label' => $label, 'sort_order' => $i++]
            );
        }

        $misconductTypes = [
            'Intellectual Dishonesty', 'Fraud', 'Harm to Persons', 'Damage to Property',
            'Unauthorized Possession/Use of Dangerous Objects', 'Unauthorized Possession/Use of Prohibited Drugs',
            'Undermining or Obstructing Investigations', 'Violation of IT Resources Policies',
            'Stealing within University Premises', 'Preparing or Disseminating Libelous/Subversive Materials',
            'Committing Sexual Acts within University Premises', 'Instigating or Leading Boycotts/Disruption of Classes',
            'Drinking Alcoholic Beverages or Drunken Behavior', 'Smoking', 'Gambling within University Premises',
            'Violation of Municipal/Provincial Ordinance', 'Non-wearing of Valid School I.D.',
            'Unauthorized Use of Borrowed or Stolen I.D.', 'Loitering During Curfew Hours',
            'Failure to Obtain Permit for Facility Use', 'Unauthorized Use of University Name',
            'Unauthorized Posting/Distributing of Notices',
            'Possessing/Distributing Immoral, Indecent, or Subversive Literature',
            'Littering', 'Spitting', 'Violating Legally Posted Instructions or Signage',
            'Disobeying Lawful Written Orders', 'Appropriating Property of Another (Student Organization)',
            'Other Form of Misconduct',
        ];
        $i = 0;
        foreach ($misconductTypes as $label) {
            // value === label here since the current dropdown has no separate codes
            ReferralFormOption::firstOrCreate(
                ['category' => 'act_of_misconduct', 'value' => $label],
                ['label' => $label, 'sort_order' => $i++]
            );
        }
    }
}