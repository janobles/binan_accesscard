<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AccessCardSeeder extends Seeder
{
    public function run()
    {
        $developer = [
            'username' => 'developer',
            'password' => password_hash('developer123', PASSWORD_ARGON2ID),
            'role'     => 'Developer',
            'isactive' => 'Enable',
            'memberID' => null,
        ];

        $existingDeveloper = $this->db->table('users')
            ->where('role', 'Developer')
            ->get()
            ->getRowArray();

        if ($existingDeveloper === null) {
            $this->db->table('users')->insert($developer);

            return;
        }

        $this->db->table('users')
            ->where('userID', $existingDeveloper['userID'])
            ->update($developer);
    }
}
