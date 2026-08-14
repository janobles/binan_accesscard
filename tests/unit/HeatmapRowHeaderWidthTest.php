<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Task 13's Finding 3: .heatmap { width: 100% } with auto table layout and no
 * width on the row-header cell let that column absorb every leftover pixel
 * (764px at 1440px wide, per the verification report), stranding a day's
 * label two-thirds of the card away from its first cell. This pins that the
 * row-header column carries an explicit width, and that the printed PDF grid
 * (a separate stylesheet, Views/Scanner/pdf/report-hours.php) is untouched.
 */
final class HeatmapRowHeaderWidthTest extends CIUnitTestCase
{
    private function source(): string
    {
        return (string) file_get_contents(FCPATH . 'css/scanner-reports.css');
    }

    public function testRowHeaderColumnCarriesAWidth(): void
    {
        $css = $this->source();

        $this->assertMatchesRegularExpression(
            '/\.heatmap\s+tbody\s+th\s*\{[^}]*width\s*:\s*[\d.]+rem/s',
            $css,
            'The row-header column must be given an explicit width, or auto table layout hands it every leftover pixel again.'
        );
    }

    public function testThePdfGridsOwnStylesheetIsUntouched(): void
    {
        $pdfView = (string) file_get_contents(APPPATH . 'Views/Scanner/pdf/report-hours.php');

        // The PDF grid builds its own inline styles rather than pulling in
        // scanner-reports.css; asserting that boundary still holds is what
        // makes "desktop-only fix" true rather than assumed.
        $this->assertStringNotContainsString('scanner-reports.css', $pdfView);
    }
}
