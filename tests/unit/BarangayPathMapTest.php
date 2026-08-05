<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class BarangayPathMapTest extends CIUnitTestCase
{
    private function names(): array
    {
        return json_decode((string) file_get_contents(FCPATH . 'assets/image/binan_brgy_paths.json'), true);
    }

    public function testTwentyFourBarangaysInPathOrder(): void
    {
        $this->assertCount(24, $this->names());
    }

    /** The SVG must still hold exactly one path per barangay. */
    public function testPathCountMatchesTheNameList(): void
    {
        $svg = (string) file_get_contents(FCPATH . 'assets/image/binan_brgy.svg');
        $this->assertSame(24, substr_count($svg, '<path'));
    }

    /** The seeded barangay table spells it Mamplasan; the GeoJSON did not. */
    public function testDatabaseSpellingWins(): void
    {
        $names = $this->names();
        $this->assertContains('Mamplasan', $names);
        $this->assertNotContains('Mampalasan', $names);
    }
}
