<?php

use App\Support\FamilyAgeEligibility;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FamilyAgeEligibilityTest extends CIUnitTestCase
{
    private const SECTORS = [
        ['sectorID' => 1, 'shortcode' => 'SC'],
        ['sectorID' => 4, 'shortcode' => 'B'],
        ['sectorID' => 9, 'shortcode' => 'PWD'],
    ];

    private const SERVICES = [
        ['serviceID' => 28, 'category' => 'Senior Citizen'],
        ['serviceID' => 44, 'category' => 'Bata (Children)'],
        ['serviceID' => 50, 'category' => 'Financial Assistance Programs'],
    ];

    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = new DateTimeImmutable('2026-07-22');
    }

    public function testChildSectorAndServicesAreAllowedOnlyBelowEighteen(): void
    {
        $this->assertNull($this->error('2008-07-23', [4], [44]));
        $this->assertStringContainsString('below 18', (string) $this->error('2008-07-22', [4], []));
        $this->assertStringContainsString('below 18', (string) $this->error('2000-01-01', [], [44]));
    }

    public function testSeniorSectorAndProgramsAreAllowedOnlyFromSixty(): void
    {
        $this->assertStringContainsString('60 years old and above', (string) $this->error('1966-07-23', [1], []));
        $this->assertStringContainsString('60 years old and above', (string) $this->error('2000-01-01', [], [28]));
        $this->assertNull($this->error('1966-07-22', [1], [28]));
    }

    public function testOtherSectorsAndServicesAreNotAgeRestricted(): void
    {
        $this->assertNull($this->error('2000-01-01', [9], [50]));
        $this->assertNull($this->error('not-a-date', [1, 4], [28, 44]));
    }

    private function error(string $birthday, array $sectorIds, array $serviceIds): ?string
    {
        return FamilyAgeEligibility::selectionError(
            $birthday,
            $sectorIds,
            $serviceIds,
            self::SECTORS,
            self::SERVICES,
            $this->today,
        );
    }
}
