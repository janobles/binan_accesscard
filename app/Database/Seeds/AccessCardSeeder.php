<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AccessCardSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('barangays')->ignore(true)->insertBatch([
            ['id' => 1, 'name' => 'Canlalay', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'San Antonio', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Poblacion', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->db->table('sectors')->ignore(true)->insertBatch([
            ['id' => 1, 'name' => 'PWD', 'description' => 'Persons with disability', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Senior', 'description' => 'Senior citizens', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Solo Parent', 'description' => 'Registered solo parents', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'General', 'description' => 'General social services beneficiaries', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->db->table('services')->ignore(true)->insertBatch([
            ['id' => 1, 'name' => 'Financial Assistance', 'description' => 'General financial assistance request', 'sector_id' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'PWD Medical Assistance', 'description' => 'Medical support for PWD beneficiaries', 'sector_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Birthday Cash Gift', 'description' => 'Birthday cash gift for senior citizens', 'sector_id' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Solo Parent Assistance', 'description' => 'Assistance for registered solo parents', 'sector_id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->db->table('users')->ignore(true)->insertBatch([
            [
                'id' => 1,
                'username' => 'admin',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'full_name' => 'System Administrator',
                'role' => 'Admin',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'username' => 'employee',
                'password_hash' => password_hash('employee123', PASSWORD_DEFAULT),
                'full_name' => 'CSWD Employee',
                'role' => 'Employee',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
