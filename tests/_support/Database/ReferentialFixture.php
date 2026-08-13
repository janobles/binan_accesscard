<?php

namespace Tests\Support\Database;

use CodeIgniter\Database\BaseConnection;

/**
 * Parent rows the FK-carrying tables need before a test can insert into them.
 *
 * V22 gave `qr_control`, `subsidy_distribution` and `batch_eligibility` real
 * foreign keys. The SQLite translation in DumpSchema drops constraint lines, so
 * a fixture that invents a headID or a control_no still runs there; the MariaDB
 * job rejects it. Rather than each test hand-rolling the same head and card
 * rows, they come from here, which keeps the two jobs asserting on the same
 * data instead of the SQLite one asserting on a state the schema forbids.
 */
final class ReferentialFixture
{
    /**
     * Head-of-family rows, each its own head, named so a keyword search can
     * tell them apart. Insert before anything that references a memberID.
     *
     * @param list<int> $ids
     */
    public static function heads(BaseConnection $db, array $ids): void
    {
        foreach ($ids as $id) {
            $db->table('member')->insert([
                'memberID'   => $id,
                'headID'     => $id,
                'firstname'  => 'HEAD' . $id,
                'middlename' => '',
                'lastname'   => 'FIXTURE',
            ]);
        }
    }

    /**
     * A QR control row per head, numbered $base + headID, matching how the
     * subsidy fixtures spell a claim's control_no.
     *
     * @param list<int> $headIds
     */
    public static function cards(BaseConnection $db, array $headIds, int $base = 100): void
    {
        foreach ($headIds as $headId) {
            $db->table('qr_control')->insert([
                'control_no' => $base + $headId,
                'headID'     => $headId,
            ]);
        }
    }

    /** The subsidy type a batch and its claims point at. */
    public static function subsidyType(BaseConnection $db, int $id = 1, string $name = 'Rice'): void
    {
        $db->table('subsidy')->insert(['subsidy_type_id' => $id, 'name' => $name]);
    }

    /**
     * Everything a batch's claims need in one call: the subsidy type, the head
     * rows and their cards. Callers still insert the batch and its roster,
     * which is the part each test varies.
     *
     * @param list<int> $headIds
     */
    public static function claimParents(BaseConnection $db, array $headIds, int $base = 100): void
    {
        self::subsidyType($db);
        self::heads($db, $headIds);
        self::cards($db, $headIds, $base);
    }
}
