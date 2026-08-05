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
 */

$byBarangay = $byBarangay ?? [];

$svg = @file_get_contents(FCPATH . 'assets/image/binan_brgy.svg');
$names = json_decode((string) @file_get_contents(FCPATH . 'assets/image/binan_brgy_paths.json'), true);
$names = is_array($names) ? $names : [];

// Tag each path with its barangay so the JS can colour and label it. The SVG
// is a repo asset, not user input, and the names come from a checked-in list,
// but they still go through esc() because they end up inside an attribute.
$index = 0;
$svg = preg_replace_callback(
    '/<path /',
    static function () use (&$index, $names): string {
        $name = $names[$index] ?? '';
        $index++;

        return '<path data-brgy="' . esc($name, 'attr') . '" ';
    },
    (string) $svg
);
?>
<div class="barangay-map" data-barangay-map
     data-coverage="<?= esc(json_encode(array_map(static fn (array $r): array => [
         'barangay' => $r['barangay'],
         'total'    => (int) $r['total'],
         'received' => (int) $r['received'],
         'coverage' => (int) $r['coverage'],
     ], $byBarangay)), 'attr') ?>">
  <?= $svg ?>
</div>
