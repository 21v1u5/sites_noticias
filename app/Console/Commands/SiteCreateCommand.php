<?php

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Automates spinning up a new niche site on the shared multi-tenant stack:
 * one `sites` row (no new containers/deploys needed) plus its default
 * news source subscriptions, so ingestion picks it up on the next
 * scheduler tick.
 */
class SiteCreateCommand extends Command
{
    protected $signature = 'site:create
        {name : Nome de exibicao do site, ex: "Economia Hoje"}
        {niche : Slug do nicho, ex: economia, tecnologia, saude, esportes, ciencia}
        {domain : Dominio pelo qual o site sera acessado, ex: economiahoje.com.br}
        {--slug= : Slug interno do site (gerado a partir do nome se omitido)}
        {--tagline= : Subtitulo/descricao curta do site}
        {--adsense-client= : ID de cliente do AdSense, ex: ca-pub-1234567890123456}
        {--adsense-header= : Ad slot ID para o topo da pagina}
        {--adsense-in-article= : Ad slot ID para o meio do artigo}
        {--adsense-sidebar= : Ad slot ID para a barra lateral}
        {--adsense-footer= : Ad slot ID para o rodape}
        {--locale=pt_BR : Locale do site}
        {--timezone=America/Sao_Paulo : Timezone do site}
        {--spacing=45 : Minutos minimos de espacamento entre publicacoes}
        {--no-attach-sources : Nao vincular automaticamente as fontes do nicho}';

    protected $description = 'Cria um novo site de nicho na stack multi-site e vincula as fontes de noticia correspondentes';

    public function handle(): int
    {
        $niche = Str::slug($this->argument('niche'));
        $domain = strtolower(trim($this->argument('domain')));
        $slug = $this->option('slug') ? Str::slug($this->option('slug')) : Str::slug($this->argument('name'));

        if (Site::query()->where('domain', $domain)->exists()) {
            $this->error("Ja existe um site cadastrado para o dominio [{$domain}].");

            return self::FAILURE;
        }

        if (Site::query()->where('slug', $slug)->exists()) {
            $this->error("Ja existe um site com o slug [{$slug}]. Use --slug para definir outro.");

            return self::FAILURE;
        }

        $adsenseSlots = array_filter([
            'header' => $this->option('adsense-header'),
            'in_article' => $this->option('adsense-in-article'),
            'sidebar' => $this->option('adsense-sidebar'),
            'footer' => $this->option('adsense-footer'),
        ]);

        $site = Site::query()->create([
            'name' => $this->argument('name'),
            'slug' => $slug,
            'niche' => $niche,
            'domain' => $domain,
            'tagline' => $this->option('tagline'),
            'locale' => $this->option('locale'),
            'timezone' => $this->option('timezone'),
            'adsense_client_id' => $this->option('adsense-client'),
            'adsense_slots' => $adsenseSlots ?: null,
            'publish_spacing_minutes' => (int) $this->option('spacing'),
            'is_active' => true,
        ]);

        $this->info("Site criado: {$site->name} (#{$site->id}) -> https://{$site->domain}");

        if (! $this->option('no-attach-sources')) {
            $attached = $this->attachNicheSources($site);
            $this->info("{$attached} fonte(s) de noticia vinculada(s) ao nicho [{$niche}].");
        }

        $this->newLine();
        $this->comment('Proximos passos:');
        $this->line('  1. Aponte o DNS/reverse proxy de '.$site->domain.' para esta stack.');
        $this->line('  2. Configure o Ad Manager/AdSense do site com os slots definidos (ou edite depois via seeder/tinker).');
        $this->line('  3. O scheduler ja vai incluir este site no proximo ciclo horario de ingestao.');

        return self::SUCCESS;
    }

    private function attachNicheSources(Site $site): int
    {
        $sources = NewsSource::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (NewsSource $source) => in_array($site->niche, $source->niches ?? [], true));

        foreach ($sources as $source) {
            $site->newsSources()->syncWithoutDetaching([$source->id]);
        }

        return $sources->count();
    }
}
