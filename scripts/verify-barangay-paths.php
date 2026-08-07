<?php
/**
 * One-off check that binan_brgy.svg's path order matches the barangay order in
 * the GeoJSON the SVG was exported from, then writes the name list the map
 * view reads.
 *
 * The SVG carries 24 unlabelled paths with no id or class, so the only way to
 * know which path is which barangay is to trust the export order. This script
 * stops that being an assumption: it compares the normalised centroid of each
 * SVG path against the normalised centroid of each GeoJSON polygon and reports
 * any pair that does not line up.
 *
 * Usage: php scripts/verify-barangay-paths.php /tmp/binan_brgy.json
 */

$geojsonPath = $argv[1] ?? '/tmp/binan_brgy.json';
$svgPath     = __DIR__ . '/../public/assets/image/binan_brgy.svg';
$outPath     = __DIR__ . '/../public/assets/image/binan_brgy_paths.json';

$geo = json_decode((string) file_get_contents($geojsonPath), true);
$svg = (string) file_get_contents($svgPath);

preg_match_all('/<path d="([^"]+)"/', $svg, $matches);
$paths = $matches[1];

if (count($paths) !== count($geo['features'])) {
    fwrite(STDERR, sprintf("Count mismatch: %d paths, %d features\n", count($paths), count($geo['features'])));
    exit(1);
}

/** Mean of every coordinate pair in an SVG path's number stream. */
function svgCentroid(string $d): array
{
    preg_match_all('/-?\d+\.?\d*/', $d, $nums);
    $values = array_map('floatval', $nums[0]);
    $xs = $ys = [];
    for ($i = 0; $i + 1 < count($values); $i += 2) {
        $xs[] = $values[$i];
        $ys[] = $values[$i + 1];
    }

    return [array_sum($xs) / count($xs), array_sum($ys) / count($ys)];
}

/** Mean of every coordinate pair in a GeoJSON polygon or multipolygon. */
function geoCentroid(array $geometry): array
{
    $xs = $ys = [];
    $walk = static function ($node) use (&$walk, &$xs, &$ys): void {
        if (is_array($node) && isset($node[0]) && is_numeric($node[0]) && is_numeric($node[1] ?? null)) {
            $xs[] = (float) $node[0];
            $ys[] = (float) $node[1];

            return;
        }
        foreach ((array) $node as $child) {
            $walk($child);
        }
    };
    $walk($geometry['coordinates']);

    return [array_sum($xs) / count($xs), array_sum($ys) / count($ys)];
}

/** Rescale a set of points to the unit square so SVG and map units compare. */
function normalize(array $points): array
{
    $xs = array_column($points, 0);
    $ys = array_column($points, 1);
    $minX = min($xs); $maxX = max($xs);
    $minY = min($ys); $maxY = max($ys);

    return array_map(static function (array $p) use ($minX, $maxX, $minY, $maxY): array {
        return [
            ($p[0] - $minX) / max(1e-9, $maxX - $minX),
            ($p[1] - $minY) / max(1e-9, $maxY - $minY),
        ];
    }, $points);
}

$svgPoints = normalize(array_map('svgCentroid', $paths));
$geoPoints = normalize(array_map(static fn (array $f): array => geoCentroid($f['geometry']), $geo['features']));

// SVG y grows downward, the map's latitude grows upward, so flip before comparing.
$geoPoints = array_map(static fn (array $p): array => [$p[0], 1 - $p[1]], $geoPoints);

$names  = array_map(static fn (array $f): string => (string) $f['properties']['adm4_en'], $geo['features']);
$failed = false;

foreach ($svgPoints as $i => $point) {
    $distance = sqrt(($point[0] - $geoPoints[$i][0]) ** 2 + ($point[1] - $geoPoints[$i][1]) ** 2);
    $status   = $distance < 0.08 ? 'ok' : 'MISMATCH';
    if ($distance >= 0.08) {
        $failed = true;
    }
    printf("%-18s %s (%.4f)\n", $names[$i], $status, $distance);
}

if ($failed) {
    fwrite(STDERR, "\nOrder does not line up. Do not ship the map on this mapping.\n");
    exit(1);
}

// The database spelling wins over the GeoJSON's.
$names = array_map(static fn (string $n): string => $n === 'Mampalasan' ? 'Mamplasan' : $n, $names);

file_put_contents($outPath, json_encode($names, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
printf("\nWrote %s\n", $outPath);
