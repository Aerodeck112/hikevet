<?php

namespace App\Support;

/**
 * Episoadele video (reels) ale clinicii. Lista si imaginile de previzualizare vin cu codul, in
 * public/media/episoade; videoclipurile se urca separat, o singura data, in acelasi director.
 * Pe site apar doar episoadele al caror videoclip exista pe server.
 */
class Episodes
{
    public const DIR = 'media/episoade';

    /** @return list<array{slug:string,title:string,seconds:int,order:int,video:string,poster:?string,length:string}> */
    public static function available(): array
    {
        $manifest = public_path(self::DIR.'/episoade.json');
        $items = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : [];
        $out = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $slug = (string) ($item['slug'] ?? '');
            if (! preg_match('/^[a-z0-9-]+$/', $slug) || ! is_file(public_path(self::DIR.'/'.$slug.'.mp4'))) {
                continue;
            }
            $seconds = (int) ($item['seconds'] ?? 0);
            $out[] = [
                'slug' => $slug, 'title' => (string) ($item['title'] ?? $slug), 'seconds' => $seconds, 'order' => (int) ($item['order'] ?? 0),
                'video' => asset(self::DIR.'/'.$slug.'.mp4'),
                'poster' => is_file(public_path(self::DIR.'/'.$slug.'.webp')) ? asset(self::DIR.'/'.$slug.'.webp') : null,
                'length' => intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT),
            ];
        }
        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $out;
    }
}
