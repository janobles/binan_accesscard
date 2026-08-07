<?php
/**
 * Biñan's 24 barangays as a choropleth beside the coverage leaderboard.
 *
 * The SVG carries no per-path identity, so the name list comes from
 * binan_brgy_paths.json, which scripts/verify-barangay-paths.php generates
 * after checking path order against the source GeoJSON's centroids. Coverage
 * rows come from SubsidyStatsModel::byBarangay() via the batch snapshot.
 *
 * Colouring, popovers and the link to the rollout chart live in
 * assets/js/dashboard/barangay-map.js.
 *
 * Renders nothing at all when the SVG is unreadable or its path count does not
 * match the name list: a map of unlabelled shapes reads as a bug, while the
 * leaderboard beside it carries the same figures on its own.
 */

$byBarangay = $byBarangay ?? [];

$svg = @file_get_contents(FCPATH . 'assets/image/binan_brgy.svg');
$names = json_decode((string) @file_get_contents(FCPATH . 'assets/image/binan_brgy_paths.json'), true);
$names = is_array($names) ? $names : [];

// \s* after the tag name so a path written as "<path\n" or "<path\t" still
// counts; the old pattern needed exactly one space and would have silently
// tagged nothing if the asset were ever reformatted.
$pathCount = preg_match_all('/<path[\s>]/', (string) $svg);

// Every path has to get a name. A short list or a re-exported SVG with a
// different path count would otherwise produce a map of blank shapes that
// looks like a rendering bug rather than a stale asset, so the caller is told
// to render the leaderboard alone.
$mapUsable = $svg !== false && $svg !== '' && $names !== [] && $pathCount === count($names);

if (! $mapUsable) {
    log_message('error', 'Barangay map not rendered: {paths} SVG paths against {names} names.', [
        'paths' => (string) $pathCount,
        'names' => (string) count($names),
    ]);
} else {
    // Tag each path with its barangay so the JS can colour and label it. The SVG
    // is a repo asset, not user input, and the names come from a checked-in list,
    // but they still go through esc() because they end up inside an attribute.
    $index = 0;
    $svg = preg_replace_callback(
        '/<path(?=[\s>])/',
        static function () use (&$index, $names): string {
            $name = $names[$index] ?? '';
            $index++;

            return '<path data-brgy="' . esc($name, 'attr') . '"';
        },
        (string) $svg
    );
}
?>
<?php if ($mapUsable): ?>
<div class="barangay-map" data-barangay-map
     data-coverage="<?= esc(json_encode(array_map(static fn (array $r): array => [
         'barangay' => $r['barangay'],
         'total'    => (int) $r['total'],
         'received' => (int) $r['received'],
         'coverage' => (int) $r['coverage'],
     ], $byBarangay)), 'attr') ?>">
  <?= $svg ?>
</div>
<?php endif; ?>
