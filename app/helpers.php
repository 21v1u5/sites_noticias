<?php

use App\Models\Site;

if (! function_exists('current_site')) {
    /**
     * The Site resolved for the current request by App\Http\Middleware\ResolveSite.
     * Only safe to call within a web request lifecycle (never in a queued Job,
     * where the site must be passed explicitly since there is no HTTP host).
     */
    function current_site(): Site
    {
        return app(Site::class);
    }
}

if (! function_exists('render_adsense_slot')) {
    /**
     * Renders the adsense-slot component to a raw HTML string, for the cases
     * (like mid-article injection via ArticleAdInjector) where it needs to
     * be spliced into other HTML rather than placed in the Blade template.
     */
    function render_adsense_slot(string $position): string
    {
        return (string) view('components.adsense-slot', ['position' => $position])->render();
    }
}
