<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The stepper is the app's own primitive: Bootstrap ships no stepper, and the
 * two callers (bulk import, family data entry) need the same markup in two
 * orientations. These assertions are the contract both callers read.
 */
final class StepperComponentTest extends CIUnitTestCase
{
    private function render(array $data): string
    {
        return view('components/stepper', $data, ['saveData' => false]);
    }

    public function testHorizontalStepsAreAnOrderedListInsideALabelledNav(): void
    {
        $html = $this->render([
            'orientation' => 'horizontal',
            'label'       => 'Import progress',
            'steps'       => [
                ['label' => 'Upload', 'state' => 'done'],
                ['label' => 'Review and Fix', 'state' => 'current'],
            ],
        ]);

        $this->assertStringContainsString('<nav class="stepper stepper-horizontal" aria-label="Import progress">', $html);
        $this->assertStringContainsString('<ol class="stepper-steps">', $html);
        $this->assertSame(2, substr_count($html, '<li class="stepper-step"'));
    }

    public function testAStepWithoutAnHrefIsNotALink(): void
    {
        $html = $this->render([
            'label' => 'Import progress',
            'steps' => [['label' => 'Upload', 'state' => 'current']],
        ]);

        $this->assertStringContainsString('<span class="stepper-step-link"', $html);
        $this->assertStringNotContainsString('<a class="stepper-step-link"', $html);
    }

    public function testAStepWithAnHrefIsALink(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [['label' => 'Head of Family', 'href' => '#section-head']],
        ]);

        $this->assertStringContainsString('<a class="stepper-step-link" href="#section-head"', $html);
    }

    public function testExactlyTheCurrentStepCarriesAriaCurrent(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [
                ['label' => 'One', 'state' => 'done'],
                ['label' => 'Two', 'state' => 'current'],
                ['label' => 'Three'],
            ],
        ]);

        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
    }

    public function testStepsAreNumberedFromTheLoopIndexNotTheCaller(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [
                ['label' => 'One', 'number' => 99],
                ['label' => 'Two'],
            ],
        ]);

        $this->assertStringContainsString('<span class="stepper-step-indicator" aria-hidden="true">1</span>', $html);
        $this->assertStringContainsString('<span class="stepper-step-indicator" aria-hidden="true">2</span>', $html);
        $this->assertStringNotContainsString('99', $html);
    }

    public function testStateDefaultsToUpcomingAndIsPassedThrough(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [
                ['label' => 'One', 'state' => 'error'],
                ['label' => 'Two'],
            ],
        ]);

        $this->assertStringContainsString('data-state="error"', $html);
        $this->assertStringContainsString('data-state="upcoming"', $html);
    }

    public function testAnUnknownStateFallsBackToUpcoming(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [['label' => 'One', 'state' => 'exploded']],
        ]);

        $this->assertStringNotContainsString('exploded', $html);
        $this->assertStringContainsString('data-state="upcoming"', $html);
    }

    public function testDoneAndErrorStatesAreAnnouncedNotOnlyColoured(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [
                ['label' => 'One', 'state' => 'done'],
                ['label' => 'Two', 'state' => 'error'],
            ],
        ]);

        $this->assertStringContainsString('<span class="visually-hidden">Completed, </span>', $html);
        $this->assertStringContainsString('<span class="visually-hidden">Needs attention, </span>', $html);
    }

    public function testAnUnknownOrientationFallsBackToHorizontal(): void
    {
        $html = $this->render([
            'orientation' => 'diagonal',
            'label'       => 'Import progress',
            'steps'       => [['label' => 'One']],
        ]);

        $this->assertStringContainsString('stepper stepper-horizontal', $html);
        $this->assertStringNotContainsString('diagonal', $html);
    }

    public function testLabelsAndHrefsAreEscaped(): void
    {
        $html = $this->render([
            'label' => 'Record sections',
            'steps' => [['label' => 'Sectors & <b>Programs</b>', 'href' => '#a"b']],
        ]);

        $this->assertStringNotContainsString('<b>Programs</b>', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringNotContainsString('href="#a"b"', $html);
        // Absence of the raw form is only half the claim: the escaped form has
        // to be what landed in the attribute, or a dropped href would pass too.
        $this->assertStringContainsString('href="#a&quot;b"', $html);
    }

    public function testNoStepsRendersNothing(): void
    {
        $this->assertSame('', trim($this->render(['label' => 'Record sections', 'steps' => []])));
    }

    public function testTheMissingLabelFallsBackToProgress(): void
    {
        $html = $this->render(['steps' => [['label' => 'One']]]);

        $this->assertStringContainsString('aria-label="Progress"', $html);
    }
}
