<?php

namespace Tests\Unit;

use App\Support\MemberFieldNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

final class MemberFieldNormalizerSplitTest extends CIUnitTestCase
{
    public function testSplitRecognizesTrailingBarangay(): void
    {
        $parts = MemberFieldNormalizer::splitAddressBarangay('123 Sampaguita St, Canlalay');

        $this->assertSame('123 Sampaguita St', $parts['address']);
        $this->assertSame('Canlalay', $parts['barangay']);
    }

    public function testSplitBarangayOnlyValue(): void
    {
        $parts = MemberFieldNormalizer::splitAddressBarangay('Canlalay');

        $this->assertSame('', $parts['address']);
        $this->assertSame('Canlalay', $parts['barangay']);
    }

    public function testSplitUnknownBarangayFallsBackToAddress(): void
    {
        $parts = MemberFieldNormalizer::splitAddressBarangay('Somewhere Else, Not A Barangay');

        $this->assertSame('Somewhere Else, Not A Barangay', $parts['address']);
        $this->assertSame('', $parts['barangay']);
    }

    public function testSplitIsInverseOfCombine(): void
    {
        $combined = MemberFieldNormalizer::combineAddressBarangay('123 Sampaguita St', 'Canlalay');
        $parts    = MemberFieldNormalizer::splitAddressBarangay($combined);

        $this->assertSame('123 Sampaguita St', $parts['address']);
        $this->assertSame('Canlalay', $parts['barangay']);
    }
}
