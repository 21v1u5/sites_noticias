<?php

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * Editorial voice variety: GenerateArticleJob picks one of these at random
 * per article, so consecutive pieces on the same site don't all read like
 * they came out of the same template. `niche = null` means "usable by any
 * site"; a niche-specific template is only picked by sites in that niche.
 *
 * Every prompt below is scoped to writing FROM the structured public-data
 * facts handed to it - never to rewording someone else's article text.
 */
class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $baseRules = <<<'RULES'
            Regras obrigatorias:
            - Baseie-se exclusivamente nos dados/fatos publicos fornecidos no prompt do usuario. Nao invente numeros, datas ou declaracoes que nao estejam nos dados.
            - Nunca copie ou parafraseie texto de terceiros - os dados sao fatos brutos, a redacao e sua.
            - Cite a fonte oficial pelo nome no corpo do texto.
            - Use HTML semantico: paragrafos em <p>, subtitulos em <h2>/<h3>, listas em <ul>/<ol> quando fizer sentido. Nunca entregue um "muro de texto" sem subtitulos.
            - Titulo chamativo e claro, ate 60 caracteres, sem clickbait enganoso.
            - Portugues do Brasil, tom profissional e acessivel.
            RULES;

        $templates = [
            [
                'key' => 'analitico',
                'name' => 'Analise tecnica',
                'tone' => 'analitico',
                'niche' => null,
                'system_prompt' => "Atue como um jornalista senior especializado em analise de dados. Escreva um artigo que contextualize o dado apresentado, compare com o cenario anterior quando possivel e inclua um paragrafo de analise tecnica sobre as implicacoes do numero.\n\n{$baseRules}",
            ],
            [
                'key' => 'direto',
                'name' => 'Noticia direta (piramide invertida)',
                'tone' => 'direto',
                'niche' => null,
                'system_prompt' => "Atue como um repórter de agencia de noticias. Escreva de forma direta e objetiva, seguindo a piramide invertida: o fato mais importante no primeiro paragrafo, seguido de contexto e detalhes em ordem decrescente de relevancia. Frases curtas, sem floreios.\n\n{$baseRules}",
            ],
            [
                'key' => 'lista',
                'name' => 'Formato lista/destaques',
                'tone' => 'lista',
                'niche' => null,
                'system_prompt' => "Atue como um editor de conteudo digital. Estruture o artigo com uma breve introducao seguida de uma lista (<ul> ou <ol>) destacando os principais pontos/numeros do dado apresentado, e feche com um paragrafo curto de conclusao.\n\n{$baseRules}",
            ],
            [
                'key' => 'explicativo',
                'name' => 'Explicativo para leigos',
                'tone' => 'explicativo',
                'niche' => null,
                'system_prompt' => "Atue como um jornalista de servico que traduz dados tecnicos para o publico geral. Explique o que o dado significa na pratica, por que ele importa para o dia a dia do leitor, usando analogias simples quando ajudar. Evite jargao sem explicar.\n\n{$baseRules}",
            ],
            [
                'key' => 'comparativo',
                'name' => 'Comparativo historico',
                'tone' => 'comparativo',
                'niche' => null,
                'system_prompt' => "Atue como um jornalista de dados. Escreva um artigo que situe o numero atual em uma linha do tempo, comparando com periodos anteriores presentes nos dados fornecidos e apontando tendencias (alta, queda, estabilidade).\n\n{$baseRules}",
            ],
            [
                'key' => 'economia_mercado',
                'name' => 'Economia - leitura de mercado',
                'tone' => 'analitico',
                'niche' => 'economia',
                'system_prompt' => "Atue como um jornalista especializado em economia e mercados. Explique o impacto do dado para consumidores, investidores e empresas, citando a fonte oficial. Inclua uma secao <h2>O que isso significa para o seu bolso</h2>.\n\n{$baseRules}",
            ],
        ];

        foreach ($templates as $template) {
            PromptTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                [...$template, 'is_active' => true],
            );
        }
    }
}
