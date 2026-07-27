<?php

use App\Support\MemberFieldNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MemberFieldNormalizerTest extends CIUnitTestCase
{
    public function testIsNoDataMatchesPlaceholdersRegardlessOfCaseAndSpacing(): void
    {
        foreach (['none', 'None', 'NONE', '  none  ', 'n/a', 'N/A', 'N / A', 'na',
            'nil', 'null', 'blank', 'empty', 'no data', 'No Data', 'not applicable',
            'not available', 'unknown', 'UNK'] as $placeholder) {
            $this->assertTrue(
                MemberFieldNormalizer::isNoData($placeholder),
                "expected '{$placeholder}' to be treated as no-data"
            );
        }
    }

    public function testIsNoDataLeavesRealValuesAlone(): void
    {
        foreach (['Juan', 'Dela Cruz', '5000', '0', 'Male', 'Married', 'SR1',
            '-', '--', '', '   '] as $real) {
            $this->assertFalse(
                MemberFieldNormalizer::isNoData($real),
                "expected '{$real}' to be kept as real data"
            );
        }
    }

    public function testBlankIfNoDataBlanksPlaceholdersAndTrimsRealValues(): void
    {
        $this->assertSame('', MemberFieldNormalizer::blankIfNoData('none'));
        $this->assertSame('', MemberFieldNormalizer::blankIfNoData('  N/A '));
        $this->assertSame('', MemberFieldNormalizer::blankIfNoData('Blank'));

        $this->assertSame('Juan', MemberFieldNormalizer::blankIfNoData('  Juan '));
        $this->assertSame('5000', MemberFieldNormalizer::blankIfNoData('5000'));
        // Dashes are NOT treated as blank (standard-word set only).
        $this->assertSame('-', MemberFieldNormalizer::blankIfNoData('-'));
    }

    public function testCleanNameUppercasesAndKeepsNamePunctuation(): void
    {
        $this->assertSame('DELA CRUZ', MemberFieldNormalizer::cleanName('dela cruz'));
        $this->assertSame('DELA CRUZ', MemberFieldNormalizer::cleanName('  Dela   Cruz  '));
        $this->assertSame("O'BRIEN-SANTOS JR.", MemberFieldNormalizer::cleanName("o'brien-santos jr."));
        // Digits and symbols are stripped, as before.
        $this->assertSame('JUAN', MemberFieldNormalizer::cleanName('Juan123'));
        // Enye must survive uppercasing.
        $this->assertSame('PEÑA', MemberFieldNormalizer::cleanName('peña'));
    }

    public function testCleanAddressUppercasesAndKeepsAddressPunctuation(): void
    {
        $this->assertSame('123 RIZAL ST.', MemberFieldNormalizer::cleanAddress('123 rizal st.'));
        $this->assertSame('BLK 4 LOT 12 (PHASE 1)', MemberFieldNormalizer::cleanAddress('blk 4 lot 12 (phase 1)'));
        $this->assertSame('#5 MABINI ST., PUROK 2', MemberFieldNormalizer::cleanAddress('#5 mabini st., purok 2'));
        // Odd symbols are stripped, as before.
        $this->assertSame('123 MAIN', MemberFieldNormalizer::cleanAddress('123 <Main>'));
    }
}
