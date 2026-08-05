<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guards the components/card bodyView/bodyData leak: CI4 shares view() data
 * across calls in the same request, so a card that sets $bodyView must clear
 * the pair on its way into the nested render or the next card rendered with no
 * $bodyView of its own inherits it and re-renders the first card's body
 * (the memory-exhaustion bug layout.php was patched for at its one call site).
 * No database - these are static component views.
 */
final class CardBodyViewLeakTest extends CIUnitTestCase
{
    public function testACardWithNoBodyViewDoesNotInheritAPriorCardsBodyView(): void
    {
        // Card A sets bodyView/bodyData, the way Lookups/sectors.php does.
        view('components/card', [
            'title'    => 'Card A',
            'bodyView' => 'components/table_footer',
            'bodyData' => ['leftContent' => 'Leaked Inner Body'],
        ]);

        // Card B sets no bodyView at all, the way most cards on a page after it do.
        $second = view('components/card', ['title' => 'Card B']);

        $this->assertStringNotContainsString('Leaked Inner Body', $second);
        $this->assertStringContainsString('Card B', $second);
    }
}
