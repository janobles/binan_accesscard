<?php

namespace App\Models;

use CodeIgniter\Model;

class BarangayModel extends Model
{
    protected $table = 'barangays';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name'];
    protected $useTimestamps = true;
}
