<?php
// Optimizeaza imaginile preluate din site-ul existent: WebP, maxim 1600 px, plus varianta de 640 px.
// Originalele nu se modifica si nu intra in Git. Rulare: php tools/content/build_media.php <dir-sursa>
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src)) { fwrite(STDERR, "director sursa lipsa\n"); exit(1); }
$root = dirname(__DIR__, 2);
$out = $root . '/public/media';
$manifest = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $rel = substr($file->getPathname(), strlen($src) + 1);
    $info = @getimagesize($file->getPathname());
    if (!$info) continue;
    [$w, $h, $type] = $info;
    $img = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($file->getPathname()),
        IMAGETYPE_PNG => imagecreatefrompng($file->getPathname()),
        IMAGETYPE_WEBP => imagecreatefromwebp($file->getPathname()),
        default => null,
    };
    if (!$img) continue;
    $base = preg_replace('/\.(jpe?g|png|webp)$/i', '', $rel);
    $base = preg_replace('/-scaled$/', '', $base);
    $slug = strtolower(preg_replace('/[^A-Za-z0-9\/]+/', '-', $base));
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');
    if (isset($manifest[$slug])) continue; // varianta -scaled si originalul sunt aceeasi imagine
    $entry = ['source' => $rel, 'width' => $w, 'height' => $h, 'variants' => []];
    foreach ([1600, 640] as $max) {
        if ($max === 640 && $w <= 640) continue;
        $nw = min($w, $max); $nh = (int) round($h * $nw / $w);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $name = $slug . ($max === 640 ? '-640' : '') . '.webp';
        @mkdir(dirname("$out/$name"), 0775, true);
        imagewebp($dst, "$out/$name", $type === IMAGETYPE_PNG && $w <= 600 ? 92 : 80);
        $entry['variants'][$max === 640 ? 'small' : 'large'] = ['path' => "media/$name", 'width' => $nw, 'height' => $nh, 'bytes' => filesize("$out/$name")];
        imagedestroy($dst);
    }
    imagedestroy($img);
    $entry['sha256_source'] = hash_file('sha256', $file->getPathname());
    $manifest[$slug] = $entry;
}
ksort($manifest);
file_put_contents($root . '/database/seeders/data/media-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$bytes = array_sum(array_map(fn ($e) => array_sum(array_column($e['variants'], 'bytes')), $manifest));
printf("%d imagini, %.1f MB in public/media\n", count($manifest), $bytes / 1048576);
