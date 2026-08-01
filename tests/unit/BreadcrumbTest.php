<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Navigation;

/**
 * Breadcrumbs come from the manifest, not from the views, so a new unlisted page
 * gets them by declaring a parent. Only pages with a parent show a trail: the
 * sidebar already marks a listed page active, and "Dashboard, Dashboard" is noise.
 */
final class BreadcrumbTest extends CIUnitTestCase
{
    public function testUnlistedPagesDeclareTheirParent(): void
    {
        $this->assertSame('records', Navigation::parentFor('records-entry'));
        $this->assertSame('records', Navigation::parentFor('records-import'));
        $this->assertSame('records', Navigation::parentFor('records-profile'));
        $this->assertSame('records', Navigation::parentFor('records-edit'));
        $this->assertSame('records', Navigation::parentFor('records-update'));
    }

    public function testListedPagesHaveNoParent(): void
    {
        $this->assertNull(Navigation::parentFor('records'));
        $this->assertNull(Navigation::parentFor('dashboard'));
    }

    public function testAnUnknownKeyHasNoParent(): void
    {
        $this->assertNull(Navigation::parentFor('nope'));
    }

    public function testRouteForResolvesAListedPage(): void
    {
        $this->assertSame('records', Navigation::routeFor('records'));
        $this->assertSame('', Navigation::routeFor('records-entry'));
    }

    public function testTheTrailReadsParentThenLeaf(): void
    {
        $parent = Navigation::parentFor('records-entry');

        $this->assertSame('Family Records', Navigation::titleFor($parent));
        $this->assertSame('New Family Record', Navigation::titleFor('records-entry'));
    }
}
