<?php
/**
 * Step indicator for a multi-part page, in a horizontal or vertical orientation.
 * Bootstrap ships no stepper, so this is the app's own primitive, styled by
 * theme.css alongside .segmented-tabs. Used by the bulk import pages as a
 * cross-page progress indicator and by the Data Entry page as the vertical spine
 * its sections hang off.
 *
 * Presentational only: it computes no state and loads no JavaScript. The caller
 * decides which step is current, and step numbers come from the loop, never from
 * the caller. The indicator is aria-hidden because the ordered list already
 * conveys position; `done` and `error` prefix a visually hidden word so state is
 * never colour alone.
 *
 * A step with an `href` renders as a link (the non-linear entry spine); without
 * one it renders as a span (import, whose steps are separate pages).
 *
 * Labels and hrefs use the default html-context `esc()`, not `'attr'`. Both
 * escape the quote and angle brackets, so neither is injectable, but the attr
 * context also encodes spaces and `#`, which would turn the spine's anchor
 * hrefs into `&#x23;section-head` and make the markup unreadable.
 *
 * Params: $orientation 'horizontal'|'vertical', $label the nav's aria-label,
 *         $steps list of ['label' => string, 'href' => ?string, 'state' =>
 *         'upcoming'|'current'|'done'|'error'].
 */
$steps = array_values((array) ($steps ?? []));

if ($steps === []) {
    return;
}

$orientation = ($orientation ?? '') === 'vertical' ? 'vertical' : 'horizontal';
$label       = trim((string) ($label ?? '')) !== '' ? (string) $label : 'Progress';

// A state the caller invented must not reach the markup as a live selector.
$states = ['upcoming', 'current', 'done', 'error'];

// Announced before the label so state is not carried by colour alone.
$prefixes = ['done' => 'Completed, ', 'error' => 'Needs attention, '];
?>
<nav class="stepper stepper-<?= $orientation ?>" aria-label="<?= esc($label) ?>">
    <ol class="stepper-steps">
        <?php foreach ($steps as $index => $step): ?>
            <?php
            $step  = (array) $step;
            $state = (string) ($step['state'] ?? 'upcoming');
            $state = in_array($state, $states, true) ? $state : 'upcoming';
            $href  = (string) ($step['href'] ?? '');
            $tag   = $href !== '' ? 'a' : 'span';
            ?>
        <li class="stepper-step" data-state="<?= $state ?>">
            <<?= $tag ?> class="stepper-step-link"<?= $href !== '' ? ' href="' . esc($href) . '"' : '' ?><?= $state === 'current' ? ' aria-current="step"' : '' ?>>
                <span class="stepper-step-indicator" aria-hidden="true"><?= $index + 1 ?></span>
                <span class="stepper-step-label"><?php if (isset($prefixes[$state])): ?><span class="visually-hidden"><?= $prefixes[$state] ?></span><?php endif; ?><?= esc((string) ($step['label'] ?? '')) ?></span>
            </<?= $tag ?>>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
