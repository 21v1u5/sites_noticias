# Rede de sites de noticias automatizados

Stack Laravel multi-site: um unico codebase/deploy alimenta varios sites de
nicho (economia, tecnologia, saude, ...), cada um com seu proprio dominio,
identidade visual minima e configuracao de AdSense. Cada site gera artigos
originais automaticamente a partir de **dados/fatos publicos** (indicadores
oficiais, APIs governamentais, comunicados com licenca aberta) - o pipeline
nunca raspa nem reescreve reportagens de terceiros disfarcando como
conteudo proprio.

## Por que multi-site em um so codebase?

Criar um site novo e criar uma linha na tabela `sites` (via
`php artisan site:create`), nao um novo deploy/container. O middleware
`App\Http\Middleware\ResolveSite` identifica o site pelo dominio da
requisicao (`Host` header) e isola todo o conteudo por `site_id`. Isso
significa que a mesma stack Docker (1 App, 1 Nginx, 1 Worker, 1 Scheduler,
1 Redis, 1 Postgres) atende quantos sites de nicho voce quiser, sem
multiplicar infraestrutura por site.

## Arquitetura de contêineres

| Servico     | Papel                                                                 |
|-------------|------------------------------------------------------------------------|
| `nginx`     | Recebe o trafego, serve estaticos, repassa PHP para o `app`           |
| `app`       | PHP-FPM - so serve o site/paineis, nunca roda a automacao pesada      |
| `worker`    | `php artisan queue:work redis` - ingestao, IA, imagens, publicacao    |
| `scheduler` | `php artisan schedule:work` - dispara a ingestao horaria              |
| `redis`     | Fila de jobs + cache de pagina                                        |
| `postgres`  | Armazena sites, fontes, templates e artigos                           |

```bash
npm ci && npm run build   # compila os assets antes do build da imagem nginx
docker compose up -d --build
```

(a primeira vez sobe um servico `migrate` que roda as migrations e sai;
os demais esperam ele terminar com sucesso antes de subir)

## Pipeline de automacao

1. **Ingestao (`FetchNewsSourceJob`, agendado a cada hora)** - le fatos
   brutos de uma `NewsSource` (IBGE, BCB, RSS oficial, API generica) e
   gera um `SourceLead` por fato novo (deduplicado por `external_lead_id`).
2. **Geracao (`GenerateArticleJob`)** - para cada site inscrito na fonte,
   envia o(s) fato(s) para a API do DeepSeek com um `PromptTemplate`
   escolhido aleatoriamente (varia o tom/estrutura editorial entre
   artigos). O artigo e sempre escrito a partir dos dados fornecidos,
   nunca reescrevendo texto de terceiros.
3. **Midia (`FetchArticleImageJob`)** - busca uma imagem de banco livre de
   direitos (Unsplash/Pexels) a partir de palavras-chave extraidas do
   titulo, e agenda a publicacao respeitando o espacamento minimo do site
   (`sites.publish_spacing_minutes`, com jitter aleatorio) - evita
   publicar um lote de artigos de uma vez so.
4. **Publicacao (`PublishArticleJob`)** - dispara com `delay()` no horario
   agendado, marca o artigo como `published` e limpa o cache Redis da
   home/artigo.

## Criando um novo site de nicho

```bash
php artisan site:create "Economia Hoje" economia economiahoje.com.br \
    --adsense-client=ca-pub-XXXXXXXXXXXXXXXX \
    --adsense-header=1111111111 \
    --adsense-in-article=2222222222 \
    --adsense-sidebar=3333333333 \
    --adsense-footer=4444444444
```

Isso cria o site, vincula automaticamente todas as `news_sources` ativas
cujo `niches` inclua `economia`, e ja fica pronto para o proximo ciclo do
scheduler. Depois so falta apontar o DNS/reverse proxy do dominio para
esta stack.

## Estrutura dos blocos do AdSense

- `resources/views/components/adsense-slot.blade.php` - componente que
  renderiza um slot (`<x-adsense-slot position="header" />`). Se o site
  nao tiver `adsense_client_id` ou nao houver slot configurado para a
  posicao, **nao renderiza nada** - evita "ad code sem anuncio real", que
  o AdSense penaliza.
- Posicoes de fabrica: `header`, `in_article`, `sidebar`, `footer`
  (guardadas em `sites.adsense_slots`, um JSON `{posicao: ad_slot_id}`).
- `App\Support\ArticleAdInjector` insere o slot `in_article` nativamente
  no HTML do artigo (depois do 2º paragrafo por padrao), em vez de
  empilhar anuncios antes/depois do conteudo - alinhado com a orientacao
  do proprio Google de intercalar anuncios no fluxo de leitura.
- O layout (`resources/views/layouts/app.blade.php`) so injeta o script
  `adsbygoogle.js` quando o site tem `adsense_client_id` configurado.
- Cada bloco de anuncio tem o rotulo "Publicidade" (`resources/css/app.css`,
  classe `.ad-slot`) para distinguir claramente conteudo editorial de
  anuncio.

Para trocar os slots de um site depois de criado: `php artisan tinker` e
`Site::find($id)->update(['adsense_slots' => [...]])`, ou crie um comando
`site:adsense` se preferir algo mais ergonomico.

## Cadencia humana de publicacao

Cada site tem `publish_spacing_minutes` (default 45). `FetchArticleImageJob`
calcula o proximo horario livre a partir do ultimo artigo agendado/publicado
do site e soma um jitter aleatorio de 0-8 minutos, evitando publicacoes em
lote e horarios "redondos demais".

## Fontes de dados (`news_sources`)

`database/seeders/NewsSourceSeeder.php` cadastra 40 fontes candidatas.
**Apenas 4 vem ativas de fabrica** (endpoints publicos, sem chave de API,
testados end-to-end nesta stack):

| Fonte | Conector | Cobre |
|---|---|---|
| IBGE (SIDRA) | `IbgeSidraConnector` | Indicadores economicos (IPCA, etc.) |
| Banco Central (SGS) | `BcbSgsConnector` | Series temporais (Selic, IPCA, cambio) |
| dados.gov.br (CKAN) | `GenericJsonConnector` | Busca no catalogo publico |
| NASA APOD | `GenericJsonConnector` | Ciencia/espaco |

As outras 36 sao um catalogo de referencia (`is_active = false`) - cada
uma tem um `license_note` explicando o que falta (endpoint especifico,
chave de API, ou um conector dedicado) antes de ativar. Ative uma fonte
com:

```php
NewsSource::where('key', 'novo_caged')->update([
    'endpoint' => '...',
    'config' => [...],
    'is_active' => true,
]);
```

### Escrevendo um conector novo

Implemente `App\Contracts\NewsSourceConnector::fetch(NewsSource $source): Collection<SourceLead>`
e aponte `news_sources.connector` para a classe. Cada `SourceLead` carrega
so fatos brutos (`facts`) - a redacao do artigo e sempre trabalho da IA em
cima desses fatos, nunca uma copia de texto de terceiros.

## Templates de prompt (`prompt_templates`)

`GenerateArticleJob` escolhe um template ativo aleatorio (do nicho do site
ou generico) a cada artigo, variando tom/estrutura editorial (analitico,
direto, lista, explicativo, comparativo). Todos os templates seedados
instruem a IA a: escrever so a partir dos fatos fornecidos, citar a fonte
oficial, usar HTML semantico (`<h2>`/`<h3>`/`<ul>`) e nunca inventar dados.

## Variaveis de ambiente relevantes

Ver `.env.example` - destaque para `DEEPSEEK_*` (chave, timeout, retry),
`IMAGE_PROVIDER` + `UNSPLASH_ACCESS_KEY`/`PEXELS_API_KEY`, e
`NEWS_INGESTION_FREQUENCY_MINUTES`.

## Desenvolvimento local sem Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
# ajuste DB_HOST/REDIS_HOST no .env para 127.0.0.1 se nao estiver usando os containers
php artisan migrate --seed
npm install && npm run build
php artisan serve
```
