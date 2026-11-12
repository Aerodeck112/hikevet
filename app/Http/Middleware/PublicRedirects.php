<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Redirecturi 301 administrabile si URL canonic cu slash final, ca pe site-ul WordPress. */
class PublicRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->is('admin*', 'livewire*', 'install*', 'build/*', 'media/*', 'uploads/*')) {
            return $next($request);
        }

        $path = '/'.trim($request->path(), '/');
        $key = $path === '/' ? '/' : $path.'/';

        try {
            $map = Cache::remember('redirects.map', 600, fn () => Redirect::query()->get(['id', 'from_path', 'to_path', 'status_code'])
                ->mapWithKeys(fn ($r) => ['/'.trim($r->from_path, '/').'/' => [$r->id, $r->to_path, $r->status_code]])->all());
        } catch (\Throwable) {
            $map = [];
        }

        if (isset($map[$key])) {
            [$id, $to, $code] = $map[$key];
            Redirect::query()->whereKey($id)->increment('hits');

            return redirect()->away(str_starts_with($to, 'http') ? $to : site_url($to), $code);
        }

        // WordPress accepta si /?page_id=N in meniul vechi.
        $legacy = ['2903' => '/servicii/', '2386' => '/echipa/', '2892' => '/hikevet-contact/'];
        if ($path === '/' && isset($legacy[(string) $request->query('page_id')])) {
            return redirect()->away(site_url($legacy[(string) $request->query('page_id')]), 301);
        }

        $raw = $request->getPathInfo();
        // Doar paginile de continut (un singur segment) au slash final canonic; rutele functionale raman cum sunt.
        // (Clientul HTTP din testele automate elimina mereu slash-ul final, deci acolo regula ar produce o bucla.)
        if (! app()->runningUnitTests() && $raw !== '/' && ! str_ends_with($raw, '/') && ! str_contains(basename($raw), '.') && substr_count(trim($raw, '/'), '/') === 0) {
            $query = $request->getQueryString();

            return redirect()->away($request->root().$raw.'/'.($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
