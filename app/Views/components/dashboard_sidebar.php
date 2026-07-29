<?php
/**
 * Shared dashboard sidebar (SB Admin 1 sb-sidenav), rendered from the navigation
 * manifest. Every link, its heading, and whether this role sees it comes from
 * Config\Navigation, so adding a page is one entry there rather than an edit here.
 *
 * Kiosk pages render via Scanner/kiosk-layout and never use this component. The
 * brand link lives in the topnav partial.
 *
 * $role       normalized role label ('Admin', 'Encoder', ...)
 * $activePage manifest key of the page being rendered
 */

use Config\Navigation;

$role = (string) ($role ?? '');
$activePage = (string) ($activePage ?? '');
$links = Navigation::linksFor($role);
$renderedHeading = null;
?>
    <nav class="sb-sidenav accordion sb-sidenav-dark <?= esc(strtolower($role)) ?>" id="dashboard-sidebar">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <?php foreach ($links as $link): ?>
                    <?php if ($link['heading'] !== $renderedHeading): ?>
                        <div class="sb-sidenav-menu-heading"><?= esc($link['heading']) ?></div>
                        <?php $renderedHeading = $link['heading']; ?>
                    <?php endif; ?>
                    <a class="nav-link<?= $link['key'] === $activePage ? ' active' : '' ?>" href="<?= site_url($link['route']) ?>">
                        <div class="sb-nav-link-icon"><i class="bi <?= esc($link['icon']) ?>" aria-hidden="true"></i></div><?= esc($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
