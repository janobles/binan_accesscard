<?php

namespace App\Models;

use CodeIgniter\Model;

class MemberModel extends Model
{
    protected $table = 'members';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'head_id',
        'sector_id',
        'barangay_id',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'gender',
        'relationship_to_head',
        'address',
        'contact_no',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'sector_id' => 'required|is_natural_no_zero',
        'barangay_id' => 'required|is_natural_no_zero',
        'first_name' => 'required|max_length[80]',
        'last_name' => 'required|max_length[80]',
        'birthdate' => 'required|valid_date[Y-m-d]',
        'gender' => 'required|in_list[Male,Female,Other]',
        'address' => 'required|max_length[255]',
    ];

    public function searchFamilies(?string $keyword = null): array
    {
        $builder = $this->select('members.*, sectors.name AS sector_name, barangays.name AS barangay_name')
            ->join('sectors', 'sectors.id = members.sector_id')
            ->join('barangays', 'barangays.id = members.barangay_id')
            ->where('members.head_id', null)
            ->orderBy('members.last_name', 'ASC')
            ->orderBy('members.first_name', 'ASC');

        if ($keyword !== null && trim($keyword) !== '') {
            $builder->groupStart()
                ->like('members.first_name', $keyword)
                ->orLike('members.last_name', $keyword)
                ->orLike('barangays.name', $keyword)
                ->orLike('sectors.name', $keyword)
                ->groupEnd();
        }

        return $builder->findAll();
    }
}
