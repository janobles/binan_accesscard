<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * components/table_controls takes its hidden filter fields as name => value
 * pairs and escapes them itself. It used to take a string of markup each caller
 * assembled, which spread the escaping across seven views and left the
 * component printing caller HTML verbatim.
 */
final class TableControlsHiddenFieldsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The renderer is a singleton and CI4 keeps view data on it between
        // render() calls, so one case's hidden fields would seed the next's.
        \Config\Services::resetSingle('renderer');
    }

    private function render(array $data): string
    {
        return view('components/table_controls', $data + [
            'searchAction' => 'https://example.test/list',
            'sizeAction'   => 'https://example.test/list',
        ]);
    }

    public function testHiddenValuesAreEscaped(): void
    {
        $html = $this->render([
            'searchHidden' => ['q' => '"><script>alert(1)</script>'],
            'sizeHidden'   => ['status' => '"><img src=x onerror=alert(1)>'],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringContainsString('<input type="hidden" name="q"', $html);
        $this->assertStringContainsString('<input type="hidden" name="status"', $html);
    }

    /** A blank filter means the same as no filter, and only lengthens the URL. */
    public function testEmptyValuesAreDropped(): void
    {
        $html = $this->render([
            'sizeHidden' => ['q' => '', 'status' => 'archived'],
        ]);

        // name="q" on its own is the search input, which always renders.
        $this->assertStringNotContainsString('<input type="hidden" name="q"', $html);
        $this->assertStringContainsString('<input type="hidden" name="status" value="archived">', $html);
    }

    /** No caller may go back to handing this component a string of markup. */
    public function testNoCallerPassesRawHtml(): void
    {
        $hits = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH . 'Views'));

        foreach ($files as $file) {
            if ($file->isFile() && str_contains((string) file_get_contents($file->getPathname()), 'HiddenHtml')) {
                $hits[] = $file->getPathname();
            }
        }

        $this->assertSame([], $hits);
    }
}
