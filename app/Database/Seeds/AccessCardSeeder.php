<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AccessCardSeeder extends Seeder
{
    public function run()
    {
        $accounts = [
            ['username' => 'developer', 'password' => 'developer123', 'role' => 'Developer'],
            ['username' => 'admin', 'password' => 'admin12345', 'role' => 'Admin'],
            ['username' => 'employee', 'password' => 'employee123', 'role' => 'User'],
        ];

        foreach ($accounts as $account) {
            $this->upsertAccount($account['username'], $account['password'], $account['role']);
        }

        // Sectors and services come from accesscardV1.4.sql.
    }

    private function upsertAccount(string $username, string $password, string $role): void
    {
        $data = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => $role,
            'isactive' => 'Enable',
            'memberID' => 1,
        ];

        $existing = $this->db->table('users')
            ->where('username', $username)
            ->get()
            ->getRowArray();

        if ($existing === null) {
            $this->db->table('users')->insert($data);

            return;
        }

        $this->db->table('users')->where('userID', $existing['userID'])->update($data);
    }

}
