<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ruleaza inaintea rutarii: /en/servicii/ devine /servicii/ cu limba engleza, ca sa nu dublam rutele.
 * Adresele generate (route(), site_url()) primesc automat prefixul limbii; resursele (CSS, imagini) nu.
 */
class LocalizeRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        [$locale, $rest] = Locales::split($request->getPathInfo());
        // Fiecare cerere porneste de la radacina (in teste aplicatia serveste mai multe cereri la rand).
        URL::forceRootUrl(null);
        URL::useAssetOrigin(null);

        if ($locale !== null) {
            $query = $request->getQueryString();
            $request->server->set('REQUEST_URI', $request->getBaseUrl().$rest.($query ? '?'.$query : ''));
            $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(),
                $request->files->all(), $request->server->all(), $request->getContent());
            URL::forceRootUrl($request->root().'/'.$locale);
            URL::useAssetOrigin($request->root());
        }

        $locale ??= Locales::DEFAULT;
        app()->setLocale($locale);
        Carbon::setLocale($locale);
        $request->attributes->set('hv.locale', $locale);

        return $next($request);
    }
}
