<?php

use App\Libraries\ViewFormatter;
use PHPUnit\Framework\TestCase;

final class ViewFormatterTest extends TestCase
{
    public function testSearchFilterDetectionIgnoresBlankValues(): void
    {
        $this->assertFalse(ViewFormatter::hasSearchFilters('', ['', '  ']));
        $this->assertTrue(ViewFormatter::hasSearchFilters('Maria', []));
        $this->assertTrue(ViewFormatter::hasSearchFilters('', ['', 'Admin']));
    }

    public function testAccountStatusFormattingRecognizesSupportedValues(): void
    {
        $this->assertTrue(ViewFormatter::isActiveStatus(true));
        $this->assertTrue(ViewFormatter::isActiveStatus('enabled'));
        $this->assertTrue(ViewFormatter::isActiveStatus(1));
        $this->assertFalse(ViewFormatter::isActiveStatus('Disabled'));
        $this->assertSame('Enable', ViewFormatter::formatStatus('active'));
        $this->assertSame('Disabled', ViewFormatter::formatStatus('off'));
    }

    public function testListHelperNormalizesCommaSeparatedValues(): void
    {
        $this->assertSame(['SC', 'PWD'], ViewFormatter::splitList('SC, PWD'));
    }

    public function testSectorAndServiceHelpersNormalizeViewData(): void
    {
        $catalog = [
            'SC' => [['sectorID' => 1]],
            'PWD' => [['sectorID' => 2]],
            'EMPTY' => [],
        ];

        $this->assertSame(['PWD'], ViewFormatter::selectedSectorCategories($catalog, [2]));
        $this->assertSame(['SC', 'PWD'], ViewFormatter::sectorCategoryKeys($catalog));
        $this->assertSame([1, 2], ViewFormatter::integerList(['1', 'invalid', '2'], true));
        $this->assertSame(['Default', 'New'], ViewFormatter::serviceCategoryOptions([
            ['category' => 'New'],
            ['category' => 'Default'],
        ], ['Default']));
    }

    public function testDebugArgumentProducesCompactValues(): void
    {
        $this->assertSame('null', ViewFormatter::debugArgument(null));
        $this->assertSame('[]', ViewFormatter::debugArgument([]));
        $this->assertSame('[...]', ViewFormatter::debugArgument(['value']));
        $this->assertSame("'text'", ViewFormatter::debugArgument('text'));
    }
}
