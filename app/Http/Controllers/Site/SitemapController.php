<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Services\Seo\Seo;
use App\Support\Settings;

class SitemapController extends Controller
{
    public function sitemap()
    {
        $urls = [];
        $add = function (string $path, $updated) use (&$urls) {
            $urls[] = ['loc' => Seo::canonicalUrl($path), 'lastmod' => $updated?->toDateString()];
        };

        foreach (Page::published()->where('noindex', false)->get() as $page) {
            $add($page->path(), $page->updated_at);
        }
        foreach (Service::published()->get() as $service) {
            $add($service->path(), $service->updated_at);
        }
        foreach (Post::published()->get() as $post) {
            $add($post->path(), $post->updated_at);
        }

        return response()->view('site.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /panou-cont', 'Disallow: /acces-cont', 'Disallow: /consimtamant-tableta',
            'Disallow: /programare/confirmare', 'Disallow: /install'];

        if (! app()->isProduction()) {
            $lines = ['User-agent: *', 'Disallow: /'];
        }

        $extra = trim((string) Settings::get('seo.robots_extra'));
        if ($extra !== '') {
            $lines[] = $extra;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
