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
}
