<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\NewsSource;
use App\Models\PromptTemplate;
use App\Models\Site;
use App\Services\DeepSeekService;
use App\Support\SourceLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writing step of the pipeline: turns one SourceLead (raw public data) into
 * an original article via DeepSeek. The system prompt is picked at random
 * from the active PromptTemplate pool for the site's niche, so consecutive
 * articles vary in structure/tone instead of all reading identically.
 */
class GenerateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    // Keep above services.deepseek.timeout (default 120s) so Laravel doesn't
    // kill the job mid-request before DeepSeek itself times out.
    public int $timeout = 180;

    public function __construct(
        public readonly Site $site,
        public readonly NewsSource $newsSource,
        public readonly SourceLead $lead,
    ) {}

    public function handle(DeepSeekService $deepSeek): void
    {
        if ($this->site->articles()->where('external_lead_id', $this->lead->externalId)->exists()) {
            return;
        }

        $template = $this->pickPromptTemplate();

        $started = microtime(true);
        $raw = $deepSeek->generate($template->system_prompt, $this->buildUserPrompt());
        $latencyMs = (int) ((microtime(true) - $started) * 1000);

        [$title, $html] = $this->parseTitleAndBody($raw);
        $slug = $this->uniqueSlug($title);

        $article = Article::create([
            'site_id' => $this->site->id,
            'news_source_id' => $this->newsSource->id,
            'prompt_template_id' => $template->id,
            'external_lead_id' => $this->lead->externalId,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => Str::limit(trim(strip_tags($html)), 220),
            'content' => $html,
            'source_reference_title' => $this->lead->referenceTitle,
            'source_reference_url' => $this->lead->referenceUrl,
            'status' => Article::STATUS_PENDING_IMAGE,
            'generation_meta' => [
                'model' => config('services.deepseek.model'),
                'latency_ms' => $latencyMs,
                'prompt_template_key' => $template->key,
            ],
        ]);

        FetchArticleImageJob::dispatch($article);
    }

    private function pickPromptTemplate(): PromptTemplate
    {
        $template = PromptTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('niche', $this->site->niche)->orWhereNull('niche');
            })
            ->inRandomOrder()
            ->first();

        if (! $template) {
            throw new RuntimeException("Nenhum prompt template ativo disponivel para o nicho [{$this->site->niche}].");
        }

        return $template;
    }

    private function buildUserPrompt(): string
    {
        $factsJson = json_encode($this->lead->facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
            Site: {$this->site->name} (nicho: {$this->site->niche})
            Pauta (fato/dado publico de origem): {$this->lead->title}
            Resumo do dado: {$this->lead->summary}
            Fonte oficial: {$this->lead->referenceTitle} ({$this->lead->referenceUrl})

            Dados estruturados disponiveis:
            {$factsJson}

            Escreva um artigo jornalistico ORIGINAL em portugues do Brasil a partir
            exclusivamente destes dados/fatos publicos acima. Nao copie nem reescreva
            texto de terceiros - use os dados como base para sua propria analise e
            contextualizacao. Cite a fonte oficial pelo nome no corpo do texto.

            Responda exatamente neste formato (texto simples, sem bloco de codigo):
            TITULO: <titulo chamativo de ate 60 caracteres>
            CONTEUDO:
            <corpo do artigo em HTML semantico: paragrafos em <p>, subtitulos em
            <h2>/<h3>, listas em <ul>/<ol> quando fizer sentido>
            PROMPT;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseTitleAndBody(string $raw): array
    {
        if (preg_match('/TITULO:\s*(.+)/i', $raw, $titleMatch)
            && preg_match('/CONTEUDO:\s*(.+)/is', $raw, $bodyMatch)) {
            return [trim($titleMatch[1]), trim($bodyMatch[1])];
        }

        // The model didn't follow the format - fall back to the lead title
        // and use the whole response as the body instead of failing the job.
        return [$this->lead->title, trim($raw)];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::slug($this->lead->title) ?: 'artigo';
        $slug = $base;
        $suffix = 2;

        while ($this->site->articles()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
