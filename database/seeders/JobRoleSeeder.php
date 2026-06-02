<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobRole;

class JobRoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['title' => 'Hair Stylist', 'description' => 'Specializes in haircuts and styling.'],
            ['title' => 'Nail Technician', 'description' => 'Provides manicure and pedicure services.'],
            ['title' => 'Front Desk', 'description' => 'Handles appointments and customer inquiries.'],
            ['title' => 'Manager', 'description' => 'Oversees salon operations.'],
        ];

        foreach ($roles as $role) {
            JobRole::create($role);
        }
    }
}
