<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccessCardSchema extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('barangays', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('sectors', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 80],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'role' => ['type' => 'ENUM', 'constraint' => ['Admin', 'Employee'], 'default' => 'Employee'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'sector_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sector_id');
        $this->forge->addForeignKey('sector_id', 'sectors', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('services', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'head_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sector_id' => ['type' => 'INT', 'unsigned' => true],
            'barangay_id' => ['type' => 'INT', 'unsigned' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'middle_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'birthdate' => ['type' => 'DATE'],
            'gender' => ['type' => 'ENUM', 'constraint' => ['Male', 'Female', 'Other']],
            'relationship_to_head' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Head'],
            'address' => ['type' => 'VARCHAR', 'constraint' => 255],
            'contact_no' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('head_id');
        $this->forge->addKey('sector_id');
        $this->forge->addKey('barangay_id');
        $this->forge->addForeignKey('head_id', 'members', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('sector_id', 'sectors', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('barangay_id', 'barangays', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('members', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'member_id' => ['type' => 'INT', 'unsigned' => true],
            'service_id' => ['type' => 'INT', 'unsigned' => true],
            'assigned_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['Pending', 'Released', 'Cancelled'], 'default' => 'Pending'],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'assigned_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('member_id');
        $this->forge->addKey('service_id');
        $this->forge->addKey('assigned_by');
        $this->forge->addForeignKey('member_id', 'members', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'services', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('assigned_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('member_services', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('audit_trails', true);
    }

    public function down()
    {
        foreach (['audit_trails', 'member_services', 'members', 'services', 'users', 'sectors', 'barangays'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
