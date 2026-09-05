<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            [
                'name'           => 'Ahmed Khan',
                'phone'          => '0300-1234567',
                'role'           => 'Master Tailor',
                'salary_type'    => 'Both',
                'monthly_salary' => 25000,
                'per_suit_rate'  => 350,
                'joining_date'   => '2024-03-15',
                'is_active'      => true,
                'notes'          => 'Senior master tailor, specializes in sherwanis and formal suits.',
            ],
            [
                'name'           => 'Bilal Hussain',
                'phone'          => '0312-9876543',
                'role'           => 'Tailor',
                'salary_type'    => 'Per Suit',
                'monthly_salary' => 0,
                'per_suit_rate'  => 250,
                'joining_date'   => '2024-08-01',
                'is_active'      => true,
                'notes'          => 'Good with casual and pant-shirt orders.',
            ],
            [
                'name'           => 'Farooq Ali',
                'phone'          => '0321-5551234',
                'role'           => 'Cutter',
                'salary_type'    => 'Monthly',
                'monthly_salary' => 20000,
                'per_suit_rate'  => 0,
                'joining_date'   => '2025-01-10',
                'is_active'      => true,
                'notes'          => 'Handles all fabric cutting, very precise.',
            ],
            [
                'name'           => 'Usman Raza',
                'phone'          => '0333-4445566',
                'role'           => 'Helper',
                'salary_type'    => 'Monthly',
                'monthly_salary' => 12000,
                'per_suit_rate'  => 0,
                'joining_date'   => '2025-06-20',
                'is_active'      => true,
                'notes'          => 'Assists with ironing and finishing.',
            ],
            [
                'name'           => 'Kashif Mehmood',
                'phone'          => '0345-7778899',
                'role'           => 'Finisher',
                'salary_type'    => 'Per Suit',
                'monthly_salary' => 0,
                'per_suit_rate'  => 150,
                'joining_date'   => '2025-04-05',
                'is_active'      => true,
                'notes'          => 'Buttonholes, hemming, and final touches.',
            ],
        ];

        foreach ($records as $data) {
            Staff::create($data);
        }

        $this->command->info('Created 5 staff records.');
    }
}
