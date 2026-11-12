<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Biblioteca media: pastreaza originalul incarcat si genereaza variante WebP
 * (maxim 1600 px si 640 px) folosite pe site. Lucreaza doar cu extensia GD, disponibila pe cPanel.
 */
class MediaLibrary
{
    public const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** @param string $uploadedPath calea fisierului abia incarcat, relativa la discul public */
    public function register(string $uploadedPath, ?string $originalName, ?string $alt, ?string $category): Media
    {
        $disk = Storage::disk('public');
        $absolute = $disk->path($uploadedPath);
        $info = @getimagesize($absolute);

        if (! $info || ! in_array($info['mime'], self::ALLOWED_MIME, true)) {
            $disk->delete($uploadedPath);
            throw new RuntimeException('Fișierul nu este o imagine JPEG, PNG sau WebP validă.');
        }

        [$width, $height] = $info;
        $image = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($absolute),
            'image/png' => imagecreatefrompng($absolute),
            'image/webp' => imagecreatefromwebp($absolute),
        };

        $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $uploadedPath);
        $large = $this->variant($image, $width, $height, 1600, $disk->path($base.'.webp'));
        if ($width > 640) {
            $this->variant($image, $width, $height, 640, $disk->path($base.'-640.webp'));
        }
        imagedestroy($image);

        return Media::create([
            'disk' => 'public', 'path' => $base.'.webp', 'original_name' => $originalName ?: basename($uploadedPath), 'mime' => 'image/webp',
            'size' => filesize($disk->path($base.'.webp')), 'width' => $large[0], 'height' => $large[1], 'alt' => $alt, 'category' => $category,
            'sha256' => hash_file('sha256', $absolute), 'source_url' => $uploadedPath === $base.'.webp' ? null : $uploadedPath,
        ]);
    }

    public function delete(Media $media): void
    {
        if ($media->disk === 'public') {
            $disk = Storage::disk('public');
            $disk->delete(array_filter([$media->path, preg_replace('/\.webp$/', '-640.webp', $media->path), $media->source_url]));
        }
        $media->delete();
    }

    /** @return array{0:int,1:int} */
    private function variant(\GdImage $image, int $width, int $height, int $max, string $target): array
    {
        $w = min($width, $max);
        $h = (int) round($height * $w / $width);
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);
        imagewebp($canvas, $target, 82);
        imagedestroy($canvas);

        return [$w, $h];
    }
}
