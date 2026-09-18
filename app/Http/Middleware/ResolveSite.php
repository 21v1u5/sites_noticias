<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Site (tenant) for the current request based on the request
 * host, and binds it into the container so it is available application-wide
 * via the current_site() helper. Each niche site shares this single Laravel
 * codebase/deploy but is fully isolated at the data layer by site_id.
 */
class ResolveSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $site = Cache::remember(
            "site:domain:{$host}",
            now()->addMinutes(10),
            fn () => Site::query()->where('domain', $host)->where('is_active', true)->first(),
        );

        abort_unless($site, 404, "Nenhum site ativo configurado para o domínio [{$host}].");

        app()->instance(Site::class, $site);

        return $next($request);
    }
}
