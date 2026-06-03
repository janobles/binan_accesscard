<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Generates ~50,000 dummy member records in family units.
 *
 * Run:  php spark db:seed DummyDataSeeder
 *
 * Set TRUNCATE_BEFORE_SEED = true to wipe member + member_services first.
 * Sector, services, and users rows are never touched.
 */
class DummyDataSeeder extends Seeder
{
    private const TARGET_COUNT        = 50000;
    private const BATCH_SIZE          = 500;
    private const TRUNCATE_BEFORE_SEED = false;

    // -------------------------------------------------------------------------
    // Data pools
    // -------------------------------------------------------------------------

    private array $lastNames = [
        'Dela Cruz', 'Reyes', 'Santos', 'Garcia', 'Torres', 'Flores', 'Lopez',
        'Gonzales', 'Ramos', 'Mendoza', 'Castro', 'Bautista', 'Aquino', 'Rivera',
        'Villanueva', 'Soriano', 'Mercado', 'Dizon', 'Lim', 'Cruz', 'Navarro',
        'Espiritu', 'Aguilar', 'Santiago', 'Domingo', 'Reyes', 'Pascual', 'Guevara',
        'Ocampo', 'Estrada', 'Manalo', 'Vergara', 'Tolentino', 'Magno', 'Mariano',
        'Enriquez', 'Peralta', 'Abad', 'Rojas', 'Padilla', 'Francisco', 'Salazar',
        'Miranda', 'Hernandez', 'Medina', 'Catalan', 'Baltazar', 'Andres', 'Luna',
        'Dela Torre', 'Macaraeg', 'Tagalog', 'Buenaventura', 'Delos Santos',
        'Macapagal', 'Catacutan', 'Paras', 'Yap', 'Tan', 'Ong',
    ];

    private array $firstNamesMale = [
        'Juan', 'Jose', 'Ramon', 'Eduardo', 'Roberto', 'Carlos', 'Manuel', 'Pedro',
        'Antonio', 'Ricardo', 'Francisco', 'Alfredo', 'Fernando', 'Miguel', 'Rodrigo',
        'Renato', 'Dante', 'Ernesto', 'Rolando', 'Arnold', 'Rodel', 'Danilo',
        'Romulo', 'Alejandro', 'Bernardo', 'Cesar', 'Dario', 'Edgardo', 'Felix',
        'Gilbert', 'Herminio', 'Isidro', 'Jonathan', 'Kevin', 'Lester', 'Mark',
        'Nathan', 'Oscar', 'Paolo', 'Quirino', 'Rey', 'Samuel', 'Tomas', 'Ulysses',
        'Victor', 'Wilfredo', 'Xavier', 'Yohan', 'Zaldy', 'Arnel', 'Benito',
    ];

    private array $firstNamesFemale = [
        'Maria', 'Ana', 'Lourdes', 'Grace', 'Elena', 'Sofia', 'Gloria', 'Rosario',
        'Consuelo', 'Remedios', 'Teresita', 'Natividad', 'Maricel', 'Cristina',
        'Angelica', 'Maribel', 'Luzviminda', 'Rosalinda', 'Erlinda', 'Florencia',
        'Carmelita', 'Divina', 'Esperanza', 'Fe', 'Gina', 'Helen', 'Imelda',
        'Jenny', 'Karen', 'Lilian', 'Mylene', 'Nenita', 'Olivia', 'Perla',
        'Queenie', 'Rica', 'Susan', 'Tess', 'Ursula', 'Virginia', 'Wilma',
        'Xyza', 'Yolanda', 'Zenaida', 'Alicia', 'Belinda', 'Celia', 'Delia',
        'Elvira', 'Filomena',
    ];

    private array $middleNames = [
        'Ramos', 'Santos', 'Garcia', 'Torres', 'Flores', 'Lopez', 'Cruz',
        'Mendoza', 'Castro', 'Rivera', 'Reyes', 'Bautista', 'Lim', 'Dizon',
        'Dela Cruz', 'Navarro', 'Espiritu', 'Aguilar', 'Ocampo', 'Estrada',
        'Manalo', 'Vergara', 'Mariano', 'Enriquez', 'Abad', 'Salazar',
        'Miranda', 'Hernandez', 'Medina', 'Andres',
    ];

    private array $streets = [
        'Rizal St', 'Mabini St', 'Bonifacio St', 'Luna St', 'Del Pilar St',
        'Aguinaldo St', 'Quezon Ave', 'Marcos Blvd', 'Acacia St', 'Sampaguita St',
        'Malvar St', 'Recto Ave', 'Osmena St', 'Laurel St', 'Magsaysay Ave',
        'Taft Ave', 'Quirino Ave', 'Burgos St', 'Gomez St', 'Zamora St',
        'A. Mabini St', 'J. Rizal Ave', 'P. Burgos St', 'Gen. Luna Rd',
    ];

    private array $barangays = [
        'Brgy. San Vicente', 'Brgy. Malaban', 'Brgy. Canlalay', 'Brgy. Poblacion',
        'Brgy. Santo Niño', 'Brgy. San Antonio', 'Brgy. Tubigan', 'Brgy. Zapote',
        'Brgy. Platero', 'Brgy. De La Paz', 'Brgy. Loma', 'Brgy. Casile',
        'Brgy. Langkiwa', 'Brgy. Soro-soro', 'Brgy. Timbao', 'Brgy. Halang',
        'Brgy. Behia', 'Brgy. Malamig', 'Brgy. Mamplasan', 'Brgy. Sto. Tomas',
        'Brgy. San Jose', 'Brgy. Niyugan', 'Brgy. Biñan', 'Brgy. Puting Kahoy',
    ];

    private array $religions = [
        'Roman Catholic', 'Roman Catholic', 'Roman Catholic', 'Roman Catholic',
        'Roman Catholic', 'Iglesia ni Cristo', 'Born Again Christian',
        'Seventh Day Adventist', 'Islam', 'Protestant',
    ];

    private array $educationLevels = [
        'No Formal Education', 'Elementary', 'High School Graduate',
        'Vocational', 'College Graduate', 'Post Graduate',
    ];

    private array $jobs = [
        'Jeepney Driver', 'Tricycle Driver', 'Vendor', 'Sari-Sari Store Owner',
        'Housewife', 'Unemployed', 'Laborer', 'Farmer', 'Security Guard',
        'Construction Worker', 'Seamstress', 'Laundrywoman', 'Teacher', 'Nurse',
        'Call Center Agent', 'Factory Worker', 'Carpenter', 'Electrician',
        'Plumber', 'Mechanic', 'Fisherman', 'Barangay Tanod', 'Government Employee',
        'Utility Worker', 'Janitor', 'Cook', 'Baker', 'Dressmaker', 'Welder',
        'Painter', 'Mason', 'Caregiver', 'Sales Agent', 'Cashier', 'Waiter',
    ];

    // sector IDs grouped by type (must match seeded sector table)
    private array $pwdSectors    = [1, 2, 3, 4, 5];
    private array $spSectors     = [6, 7];
    private array $scSectors     = [8, 9, 10, 11, 12, 13, 14, 15, 16];

    // serviceIDs grouped by relevant sector type
    private array $pwdServices   = [4, 28, 29, 30, 31, 32];
    private array $spServices    = [19, 20, 21, 22, 23];
    private array $scServices    = [0, 1, 2, 3, 4, 5, 6, 7];
    private array $generalServices = [11, 12, 20, 23, 28, 32];

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------

    public function run(): void
    {
        if (self::TRUNCATE_BEFORE_SEED) {
            CLI::write('Truncating member_services and member tables...', 'yellow');
            $this->db->query('SET FOREIGN_KEY_CHECKS=0');
            $this->db->table('member_services')->truncate();
            $this->db->table('member')->truncate();
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
            CLI::write('Done truncating.', 'yellow');
        }

        $nextID = $this->getNextAutoIncrement();
        CLI::write("Starting memberID will be: {$nextID}", 'cyan');

        $memberBatch   = [];
        $servicesBatch = [];
        $totalMembers  = 0;
        $id            = $nextID;
        $now           = date('Y-m-d H:i:s');

        $this->db->transStart();

        while ($totalMembers < self::TARGET_COUNT) {
            $familySize = rand(2, 5);
            if ($totalMembers + $familySize > self::TARGET_COUNT) {
                $familySize = self::TARGET_COUNT - $totalMembers;
            }

            $headID     = $id;
            $lastName   = $this->pick($this->lastNames);
            $headAge    = rand(28, 72);
            $headSex    = (rand(0, 9) < 7) ? 'Male' : 'Female';
            $headBday   = $this->birthdayFromAge($headAge);
            $headSectors = $this->assignHeadSectors($headAge, $headSex);
            $address    = $this->randomAddress();

            // --- Head row ---
            $memberBatch[] = $this->buildMember(
                id:           $id,
                headID:       $headID,
                lastName:     $lastName,
                firstName:    $this->randomFirstName($headSex),
                sex:          $headSex,
                birthday:     $headBday,
                age:          $headAge,
                relationship: 'Head of Family',
                civilStatus:  $this->civilStatus($headAge),
                address:      $address,
                sectors:      $headSectors,
                now:          $now,
            );
            $this->addServices($servicesBatch, $id, $headSectors, $now);
            $id++;
            $totalMembers++;

            // --- Family members ---
            $spouseAdded  = false;
            $remaining    = $familySize - 1;

            for ($m = 0; $m < $remaining; $m++) {
                [$relLabel, $memSex, $memAge] = $this->pickFamilyRole(
                    $headAge, $headSex, $spouseAdded, $m
                );
                if ($relLabel === 'Asawa') {
                    $spouseAdded = true;
                }

                $memSectors = $this->assignMemberSectors($memAge, $memSex, $relLabel);
                $memberBatch[] = $this->buildMember(
                    id:           $id,
                    headID:       $headID,
                    lastName:     $lastName,
                    firstName:    $this->randomFirstName($memSex),
                    sex:          $memSex,
                    birthday:     $this->birthdayFromAge($memAge),
                    age:          $memAge,
                    relationship: $relLabel,
                    civilStatus:  $this->civilStatus($memAge),
                    address:      $address,
                    sectors:      $memSectors,
                    now:          $now,
                );
                $this->addServices($servicesBatch, $id, $memSectors, $now);
                $id++;
                $totalMembers++;

                // Flush member batch
                if (count($memberBatch) >= self::BATCH_SIZE) {
                    $this->db->table('member')->insertBatch($memberBatch);
                    $memberBatch = [];
                }
            }

            if ($totalMembers % 5000 < $familySize) {
                CLI::write("  Generated {$totalMembers} / " . self::TARGET_COUNT . " members...", 'green');
            }
        }

        // Flush remaining members
        if (!empty($memberBatch)) {
            $this->db->table('member')->insertBatch($memberBatch);
        }

        // Flush all member_services
        foreach (array_chunk($servicesBatch, self::BATCH_SIZE) as $chunk) {
            $this->db->table('member_services')->insertBatch($chunk);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            CLI::write('Transaction failed. Check DB logs.', 'red');
        } else {
            CLI::write("Done! Inserted {$totalMembers} members and " . count($servicesBatch) . ' service records.', 'green');
        }
    }

    // -------------------------------------------------------------------------
    // Builders
    // -------------------------------------------------------------------------

    private function buildMember(
        int    $id,
        int    $headID,
        string $lastName,
        string $firstName,
        string $sex,
        string $birthday,
        int    $age,
        string $relationship,
        string $civilStatus,
        string $address,
        string $sectors,
        string $now,
    ): array {
        $hasJob    = $age >= 18 && rand(0, 9) < 7;
        $job       = $hasJob ? $this->pick($this->jobs) : null;
        $salary    = $hasJob ? rand(4, 40) * 1000 : null;
        $education = $this->pick($this->educationLevels);

        if ($age < 7) {
            $education = 'No Formal Education';
        } elseif ($age < 13) {
            $education = 'Elementary';
        }

        return [
            'memberID'      => $id,
            'headID'        => $headID,
            'lastname'      => $lastName,
            'firstname'     => $firstName,
            'middlename'    => $this->pick($this->middleNames),
            'suffix'        => null,
            'birthday'      => $birthday,
            'civilstatus'   => $civilStatus,
            'sex'           => $sex,
            'education'     => $education,
            'job'           => $job,
            'Salary'        => $salary,
            'contactnumber' => rand(9000000000, 9999999999),
            'relationship'  => $relationship,
            'address'       => $address,
            'religion'      => $this->pick($this->religions),
            'sectorID'      => $sectors,
            'dt_created'    => $now,
            'dt_updated'    => $now,
            'dt_deleted'    => null,
        ];
    }

    private function addServices(array &$batch, int $memberID, string $sectorString, string $now): void
    {
        $sectors    = json_decode($sectorString, true) ?? [];
        $servicePool = [];

        $hasPWD = !empty(array_intersect($sectors, $this->pwdSectors));
        $hasSP  = !empty(array_intersect($sectors, $this->spSectors));
        $hasSC  = !empty(array_intersect($sectors, $this->scSectors));

        if ($hasSC)  $servicePool = array_merge($servicePool, $this->scServices);
        if ($hasPWD) $servicePool = array_merge($servicePool, $this->pwdServices);
        if ($hasSP)  $servicePool = array_merge($servicePool, $this->spServices);

        if (empty($servicePool) && rand(0, 9) < 3) {
            $servicePool = $this->generalServices;
        }

        if (empty($servicePool)) return;

        $servicePool = array_unique($servicePool);
        shuffle($servicePool);
        $count = min(rand(1, 3), count($servicePool));

        for ($i = 0; $i < $count; $i++) {
            $batch[] = [
                'memberID'   => $memberID,
                'serviceID'  => $servicePool[$i],
                'dt_created' => $now,
                'dt_updated' => $now,
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Sector assignment
    // -------------------------------------------------------------------------

    private function assignHeadSectors(int $age, string $sex): string
    {
        $sectors = [];

        if ($age >= 60) {
            $scCount = rand(1, 3);
            $pool    = $this->scSectors;
            shuffle($pool);
            $sectors = array_slice($pool, 0, $scCount);
            return '[' . implode(',', $sectors) . ']';
        }

        $roll = rand(1, 100);
        if ($roll <= 12) {
            // PWD
            $count   = rand(1, 3);
            $pool    = $this->pwdSectors;
            shuffle($pool);
            $sectors = array_slice($pool, 0, $count);
        } elseif ($roll <= 22) {
            // Solo Parent
            $sectors = rand(0, 1) ? $this->spSectors : [6];
        }

        return empty($sectors) ? '[]' : '[' . implode(',', $sectors) . ']';
    }

    private function assignMemberSectors(int $age, string $sex, string $relationship): string
    {
        if ($age < 18) return '[]';

        if ($age >= 60) {
            $pool  = $this->scSectors;
            shuffle($pool);
            return '[' . implode(',', array_slice($pool, 0, rand(1, 2))) . ']';
        }

        // PWD chance: 8%
        if (rand(1, 100) <= 8) {
            $pool = $this->pwdSectors;
            shuffle($pool);
            return '[' . implode(',', array_slice($pool, 0, rand(1, 2))) . ']';
        }

        return '[]';
    }

    // -------------------------------------------------------------------------
    // Family role logic
    // -------------------------------------------------------------------------

    private function pickFamilyRole(int $headAge, string $headSex, bool $spouseAdded, int $index): array
    {
        $oppositeSex = ($headSex === 'Male') ? 'Female' : 'Male';

        // First member: try to add a spouse if head is old enough
        if (!$spouseAdded && $headAge >= 22 && rand(0, 9) < 8) {
            $spouseAge = max(18, $headAge + rand(-6, 6));
            return ['Asawa', $oppositeSex, $spouseAge];
        }

        // Grandparent (5% chance after spouse is placed)
        if ($spouseAdded && $index >= 2 && rand(0, 99) < 5) {
            $gpSex = $this->pick(['Male', 'Female']);
            $gpAge = rand(65, 88);
            return ['Magulang', $gpSex, $gpAge];
        }

        // Child
        $maxChildAge = max(1, $headAge - 20);
        $childAge    = rand(1, min($maxChildAge, 28));
        $childSex    = $this->pick(['Male', 'Female']);
        return ['Anak', $childSex, $childAge];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getNextAutoIncrement(): int
    {
        $row = $this->db->query(
            "SELECT AUTO_INCREMENT FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'member'"
        )->getRow();

        return $row ? (int) $row->AUTO_INCREMENT : 1;
    }

    private function birthdayFromAge(int $age): string
    {
        $year  = (int) date('Y') - $age;
        $month = rand(1, 12);
        $day   = rand(1, 28);
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function civilStatus(int $age): string
    {
        if ($age < 18) return 'Single';
        if ($age < 25) return rand(0, 9) < 8 ? 'Single' : 'Married';
        $roll = rand(0, 9);
        if ($roll < 6) return 'Married';
        if ($roll < 8) return 'Single';
        if ($roll < 9) return 'Widowed';
        return 'Others';
    }

    private function randomFirstName(string $sex): string
    {
        return $sex === 'Male'
            ? $this->pick($this->firstNamesMale)
            : $this->pick($this->firstNamesFemale);
    }

    private function randomAddress(): string
    {
        $num    = rand(1, 250);
        $street = $this->pick($this->streets);
        $brgy   = $this->pick($this->barangays);
        return "{$num} {$street}, {$brgy}, Biñan, Laguna";
    }

    private function pick(array $array): mixed
    {
        return $array[array_rand($array)];
    }
}
